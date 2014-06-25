<?php
/**
 * Generator for all proxy classes
 * @package outlet
 */
class OutletProxyGenerator {
	private $config;

	/**
	 * Constructs a new instance of OutletProxyGenerator
	 * @param OutletConfig $config configuration
	 * @return OutletProxyGenerator instance
	 */
	function __construct (OutletConfig $config) {
		$this->config = $config;
	}


	/**
	 * Extracts primary key information from a configuration for a given class
	 * @param array $conf configuration array
	 * @param string $clazz entity class 
	 * @return array primary key properties
	 */
	function getPkProp($conf, $clazz) {
		foreach ($conf['classes'][$clazz]['props'] as $key=>$f) {
			if (isset($f[2]['pk']) && $f[2]['pk'] == true) {
				$pks[] = $key;
			}

			if (!count($pks)) {
				throw new Exception('You must specified at least one primary key');
			}

			if (count($pks) == 1) {
				return $pks[0];
			} else {
				return $pks;
			}
		}
	}

	/**
	 * Generates the source code for the proxy classes
	 * @return string class source
	 */
	function generate () {
		$c = '';
		foreach ($this->config->getEntities() as $entity) {
			$clazz = $entity->clazz;

			$c .= "if (!class_exists('{$clazz}_OutletProxy', false)) {\n";
			$c .= "  class {$clazz}_OutletProxy extends $clazz implements OutletProxy { \n";
			$c .= "    static \$_outlet; \n";

			foreach ($entity->getAssociations() as $assoc) {
				switch ($assoc->getType()) {
					case 'one-to-many': $c .= $this->createOneToManyFunctions($assoc); break;
					case 'many-to-one': $c .= $this->createManyToOneFunctions($assoc); break;
					case 'one-to-one':	$c .= $this->createOneToOneFunctions($assoc); break;
					case 'many-to-many': $c .= $this->createManyToManyFunctions($assoc); break;
					default: throw new Exception("invalid association type: {$assoc->getType()}");
				}
			}
			$c .= "  }\n";
			$c .= "}\n";
		}

//print_r($c);
		return $c;
	}

	/**
	 * Generates the code to support one to one associations
	 * @param OutletAssociationConfig $config configuration
	 * @return string one to one function code
	 */
	function createOneToOneFunctions (OutletAssociationConfig $config) {
		$foreign	= $config->getForeign();
		$keys 		= $config->getKeys();
		$getter 	= $config->getGetter();
		$setter		= $config->getSetter();
		if ($config->getLocalUseGettersAndSetters()) {
			$keyPrefix = 'get';
			$keySuffix = '()';
		}

		$c = '';
		$c .= "    public function $getter() { \n";
		$c .= "	     if (" . Outlet::combineArgs($keys, "is_null(\$this->$keyPrefix", "$keySuffix)") . ") return parent::$getter(); \n";
		$c .= "	     if (is_null(parent::$getter()) && " . Outlet::combineArgs($keys, "\$this->$keyPrefix", $keySuffix) . ") { \n";
		$c .= "        parent::$setter( Outlet::getInstance()->load('$foreign', array(" . Outlet::combineArgs($keys, "\$this->$keyPrefix", $keySuffix, ", ") . ") ) ); \n";
		$c .= "      } \n";
		$c .= "	     return parent::$getter(); \n";
		$c .= "    } \n";

		return $c;
	}

	/**
	 * generates the code to support one to many associations
	 * @param outletassociationconfig $config configuration
	 * @return string one to many functions code
	 */
	function createonetomanyfunctions (outletassociationconfig $config) {
		$foreign	= $config->getforeign();
		$foreignname	= $config->getforeignname();
		$keys 		= $config->getKeys();
		$pk_props	= $config->getRefKeys();
		$getter		= $config->getGetter();
		$setter		= $config->getSetter();
		if ($config->getLocalUseGettersAndSetters()) {
			$keyPrefix = 'get';
			$keySuffix = '()';
		}

		$c = '';
		$c .= "    public function {$getter}() { // one-to-many getter\n";
		$c .= "      \$args = func_get_args(); \n";
		$c .= "      if (count(\$args)) { \n";
		$c .= "        if (is_null(\$args[0])) return parent::{$getter}(); \n";
		$c .= "        \$qExtras = \$args[0]; \n";
		$c .= "      } else { \n";
		$c .= "        \$qExtras = ''; \n";
		$c .= "      } \n";
		$c .= "      if (isset(\$args[1])) \$params = \$args[1]; \n";
		$c .= "      else \$params = array(); \n";

		// if there's a where clause
		//$c .= "  	echo \$qExtras; \n";
		//$c .= "      \$q = trim(\$q); \n";
		$c .= "      if (stripos(\$qExtras, 'where') !== false) { \n";
                $c .= "        \$qExtras = ' and ' . substr(\$qExtras, 5); \n";
		$c .= "	     }\n";
		$c .= "      \$q = ''; \n";
		for ($i = 0; $i < count($keys); $i++) {
			$c .= "        \$q .= '{"."$foreign.{$keys[$i]}} = \''.\$this->{$keyPrefix}{$keys[$i]}{$keySuffix}.'\' " . ($i != count($keys) - 1 ? "and " : "") . "'; \n";
		}
		$c .= "        \$q .= \$qExtras; \n";
		//$c .= "  	echo \"<h2>$foreign, \$q</h2>\"; \n";

		$c .= "      \$query = self::\$_outlet->from('$foreign')->where(\$q, \$params); \n";
//$c .= "print_pre(\$params);";
		$c .= "	     \$cur_coll = parent::{$getter}(); \n";
		
		// only set the collection if the parent is not already an OutletCollection
		// or if the query is different from the previous query
		$c .= "	     if (!\$cur_coll instanceof OutletCollection || \$cur_coll->getQuery() != \$query) { \n";
		$c .= "	       parent::{$setter}( new OutletCollection( \$query ) ); \n";
		$c .= "	     } \n";
		$c .= "	     return parent::{$getter}(); \n";
		$c .= "    } \n";

		return $c;
	}

	/**
	 * Generates the code to support many to many associations
	 * @param OutletManyToManyConfig $config configuration
	 * @return string many to many function code
	 */
	function createManyToManyFunctions (OutletManyToManyConfig $config) {
		$foreign	= $config->getForeign();

		$tableKeysLocal 		= $config->getTableKeysLocal();
		$tableKeysForeign 	= $config->getTableKeysForeign();

		$pk_props	= $config->getKeys();
		$ref_pks	= $config->getRefKeys();
		$getter		= $config->getGetter();
		$setter		= $config->getSetter();
		$table		= $config->getLinkingTable();
		if ($config->getForeignUseGettersAndSetters())
			$ref_pk = 'get'.$ref_pk.'()';

		$c = '';// pk_prop: ' . $pk_prop . ', otherKey: ' . $otherKey . ', table: ' . $table . "\n";	
		$c .= "    public function {$getter}() { // many-to-many getter\n";
		$c .= "      if (parent::$getter() instanceof OutletCollection) return parent::$getter(); \n";
		//$c .= "      if (stripos(\$q, 'where') !== false) { \n";
		$c .= "      \$q = self::\$_outlet->from('$foreign') \n";
		$c .= "        ->innerJoin('$table ON ";
		for ($i = 0; $i < count($pk_props); $i++) {
			$c .= "{$table}.{$tableKeysForeign[$i]} = {"."$foreign.".$pk_props[$i]."}";
		}
		$c .= "') \n";
		$c .= "        ->where('" . Outlet::combineArgs($tableKeysLocal, '{'.$table.'.', '} = ?', " AND ") . "', array(";
		for ($i = 0; $i < count($ref_pks); $i++) {
			$c .= "\$this->" . $ref_pks[$i];
      if ($i < count($ref_pks) - 1) $c .= ",";
		}
		$c .= ")); \n"; // FIXME - not composite key aware yet
		$c .= "      parent::{$setter}( new OutletCollection( \$q ) ); \n";
		$c .= "      return parent::{$getter}(); \n";
		$c .= "    } \n";

		return $c;
	}

	/**
	 * Generates the code to support many to one associations
	 * @param OutletAssociationConfig $config configuration
	 * @return string many to one function code
	 */
	function createManyToOneFunctions (OutletAssociationConfig $config) {
		$local		= $config->getLocal();
		$foreign	= $config->getForeign();
		$keys 		= $config->getKeys();
		$refKeys	= $config->getRefKeys();
		$getter 	= $config->getGetter();
		$setter		= $config->getSetter();

		if ($config->getLocalUseGettersAndSetters()) {
			$keyPrefix = 'get';
                        $keySuffix = '()';
		}
		
		if ($config->getForeignUseGettersAndSetters()) {
			$foreignKeyPrefix = 'get';
                        $foreignKeySuffix = '()';
		}

		$c = '';
		$c .= "    public function $getter() { \n";
		//$c .= "      if (is_null(\$this->$keyGetter)) return parent::$getter(); \n";
		//$c .= "      if (is_null(parent::$getter())) { \n";//&& isset(\$this->$keyGetter)) { \n";
                $c .= "      if (" . Outlet::combineArgs($keys, "is_null(\$this->$keyPrefix", "$keySuffix)") . ") return parent::$getter(); \n";
                $c .= "      if (is_null(parent::$getter()) && " . Outlet::combineArgs($keys, "!is_null(\$this->$keyPrefix", "$keySuffix)") . ") { \n";
		$c .= "        parent::$setter( self::\$_outlet->load('$foreign', array(" . Outlet::combineArgs($keys, "\$this->$keyPrefix", $keySuffix, ", ") . ") ) ); \n";
		$c .= "      } \n";
		$c .= "      return parent::$getter(); \n";
		$c .= "    } \n";

		$c .= "    public function $setter($foreign \$ref".($config->isOptional() ? '=null' : '').") { \n";
		$c .= "      if (is_null(\$ref)) { \n";

		if ($config->isOptional()) {
			foreach ($keys as $key) {
				$c .= "        \$this->$key = null; \n";
			}
		} else {
			$c .= "        throw new OutletException(\"You can not set this to NULL since this relationship has not been marked as optional\"); \n";
		}

		$c .= "        return parent::$setter(null); \n";
		$c .= "      } \n";

		//$c .= "    \$mapped = new OutletMapper(\$ref); \n";
		//$c .= "    \$this->$key = \$mapped->getPK(); \n";
		if ($config->getLocalUseGettersAndSetters()) {
			for ($i = 0; $i < count($keys); $i++) {
				$c .= "      \$this->set" . $keys[$i] . "(\$ref->" . $refkeys[$i] . "; \n";
			}
		} else {
			for ($i = 0; $i < count($keys); $i++) {
				$c .= "      \$this->" . $keys[$i] . " = \$ref->" . $refKeys[$i] . "; \n";
			}
		}

		$c .= "      return parent::$setter(\$ref); \n";
		$c .= "    } \n";

		return $c;
	}

}

