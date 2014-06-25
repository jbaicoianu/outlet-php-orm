<?
class OutletClassGenerator {
	private $config;

	function __construct (OutletConfig $config) {
		$this->config = $config;
	}

	function generate() {
		$c = "";
		foreach ($this->config->getEntities() as $entity) {
			$clazz = $entity->clazz;
			$c .= "if (!class_exists('$clazz', false)) { ";
			$c .= "class $clazz {\n";
			foreach ($entity->GetProperties() as $k=>$v) {
				$c .= "  public \$$k";

				if (!empty($v["default"]))
       					$c .= ' = "' . $v["default"] . '"';

				$c .= ";\n";
			}

			foreach ($entity->getAssociations() as $assoc) {
				switch ($assoc->getType()) {
					case 'one-to-many': $c .= $this->createOneToManyFunctions($assoc); break;
					case 'many-to-one': $c .= $this->createManyToOneFunctions($assoc); break;
					case 'one-to-one':	$c .= $this->createOneToOneFunctions($assoc); break;
					case 'many-to-many':	$c .= $this->createManyToManyFunctions($assoc); break;
					//default: throw new Exception("invalid association type: {$assoc->getType()}");
				}
			}
			$c .= "} }\n";
		}

		return $c;
	}
	function createOneToManyFunctions (OutletAssociationConfig $config) {
		$foreign	= $config->getForeign();
		$foreignname	= $config->getForeignName();
		$foreignplural	= $config->getForeignPlural();
		//$keys 		= $config->getKeys();
		//$pk_props = $config->getRefKeys();
		$getter		= $config->getGetter();
		$setter		= $config->getSetter();
	
		$c = '';
		$c .= "  private \$" . strtolower($foreignplural) . ";\n";
		$c .= "  public function $getter() { \n";
		$c .= "    return \$this->" . strtolower($foreignplural) . "; \n";
		$c .= "  } \n";
		$c .= "  public function $setter(Collection \$ref".($config->isOptional() ? '=null' : '').") { \n";
		$c .= "    \$this->" . strtolower($foreignplural) . " = \$ref; \n";
		$c .= "  } \n";
		$c .= "  public function add{$foreignname}({$foreign} \$ref".($config->isOptional() ? '=null' : '').") { \n";
		$c .= "    if (empty(\$this->" . strtolower($foreignplural) . ")) { \$this->" . strtolower($foreignplural) . " = new Collection(); }\n";
		$c .= "    \$this->" . strtolower($foreignplural) . "->add(\$ref); \n";
		$c .= "  } \n";

		return $c;
	}

	function createManyToOneFunctions (OutletAssociationConfig $config) {
		$foreign	= $config->getForeign();
		$foreignplural	= $config->getForeignPlural();
		//$keys 		= $config->getKeys();
		//$pk_props = $config->getRefKeys();
		$getter		= $config->getGetter();
		$setter		= $config->getSetter();
	
		$c = '';
		$c .= "  private \$" . strtolower($foreign) . ";\n";
		$c .= "  public function $getter() { \n";
		$c .= "    return \$this->" . strtolower($foreign) . "; \n";
		$c .= "  } \n";
		$c .= "  public function $setter($foreign \$ref".($config->isOptional() ? '=null' : '').") { \n";
		$c .= "    \$this->" . strtolower($foreign) . " =& \$ref; \n";
		$c .= "  } \n";

		return $c;
	}
	function createOneToOneFunctions (OutletAssociationConfig $config) {
		$foreign	= $config->getForeign();
		//$keys 		= $config->getKeys();
		$getter 	= $config->getGetters();
		$setter		= $config->getSetter();

		 $c = '';
                 $c .= "  private \$" . strtolower($foreign) . ";\n";
                 $c .= "  public function $getter() { \n";
                 $c .= "    return \$this->" . strtolower($foreign) . "; \n";
                 $c .= "  } \n";
                 $c .= "  public function $setter($foreign \$ref".($config->isOptional() ? '=null' : '').") { \n";
                 $c .= "    \$this->" . strtolower($foreign) . " =& \$ref; \n";
                 $c .= "  } \n";
 
 		 return $c;
	}
	function createManyToManyFunctions (OutletManyToManyConfig $config) {
		$foreign        = $config->getForeign();
		$foreignplural	= $config->getForeignPlural();

		$tableKeyLocal          = $config->getTableKeyLocal();
		$tableKeyForeign        = $config->getTableKeyForeign();

		//$pk_props       = $config->getKeys();
		//$ref_pk         = $config->getRefKeys();
		$getter         = $config->getGetter();
		$setter         = $config->getSetter();
		$table          = $config->getLinkingTable();

		$c = '';
		$c .= "  private \$" . strtolower($foreignplural) . ";\n";
		$c .= "  public function $getter() { \n";
		$c .= "    return \$this->" . strtolower($foreignplural) . "; \n";
		$c .= "  } \n";
		$c .= "  public function $setter(Collection \$ref".($config->isOptional() ? '=null' : '').") { \n";
		$c .= "    \$this->" . strtolower($foreignplural) . " = \$ref; \n";
		$c .= "  } \n";

		return $c;
	}

}
