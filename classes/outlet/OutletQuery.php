<?php
/**
 * @package outlet
 */
class OutletQuery {
	/**
	 * @var Outlet
	 */
	private $outlet;
	private $from;
	private $with = array();
	private $joins = array();
	private $query;
	private $params = array();
	private $orderby;
	private $limit;
	private $offset;
	private $select;
	private $groupBy;
	private $having;
	
	/**
	 * Constructs a new instance of OutletQuery
	 * @param Outlet $outlet 
	 * @return OutletQuery instance
	 */
	public function __construct (Outlet $outlet) {
		$this->outlet = $outlet;
	}
	
	/**
	 * Entity table to select from
	 * @param string $from
	 * @return OutletQuery for fluid interface
	 */
	function from ($from) {
		$this->from = $from;
		
		return $this;
	}
	
	/**
	 * Where clause to filter results by
	 * @param string $q where clause
	 * @param array $params parameters
	 * @return OutletQuery for fluid interface
	 */
	function where ($q, array $params=array()) {
		$this->query = $q;
		$this->params = $params;
		
		return $this;
	}
	
	/**
	 * Declare an inner join
	 * @param string $join entity table to join on
	 * @return OutletQuery for fluid interface
	 */
	function innerJoin ($join) {
		$this->joins[] = 'INNER JOIN ' . $join . "\n";
		
		return $this;
	}

	/**
	 * Declare a left join
	 * @param string $join entity table to join on
	 * @return OutletQuery for fluid interface
	 */
	function leftJoin ($join) {
		$this->joins[] = 'LEFT JOIN ' . $join . "\n";

		return $this;
	}
	
	/**
	 * Include associated entity tables
	 * @todo review code with respect to comment
	 * @param string... variadiac entity tables to include
	 * @return OutletQuery for fluid interface
	 */
	function with () {
		/*
		 * Because this overwrites the with variable this function behaves in a slightly unexpected way
		 * Take for instance this case:
		 * 
		 * $bugs = $outlet->from("Bug")
		 * 				  ->with("Project")
		 * 				  ->with("User")
		 * 				  ->where("{Bug.StatusID} = ? AND {User.ID} = ?", array(1, $currentUser));
		 * 				  ->limit(10)
		 * 				  ->offset(20)
		 * 				  ->find();
		 * 
		 * There won't be any project information because the second with() overrides the first
		 * 
		 * The code that would work is
		 * 
		 * $bugs = $outlet->from("Bug")
		 * 				  ->with("Project", "User")->....
		 * 
		 * It seems like both should work, we can replace the code below with
		 * 
		 * $this->with = array_merge($this->with, func_get_args());
		 * 
		 * And it will allow both calls to function properly
		 */
		$this->with = func_get_args();
		
		return $this;
	}
	
	/**
	 * Declare an ordering
	 * @param string $v Order clause 
	 * @return OutletQuery for fluid interface
	 */
	function orderBy ($v) {
		$this->orderby = $v;
		
		return $this;
	}
	
	/**
	 * Declare a limit to the result set
	 * @param int $num Number of results to return 
	 * @return OutletQuery for fluid interface
	 */
	function limit ($num) {
		$this->limit = $num;
		
		return $this;
	}
	
	/**
	 * Declare an extra column to select
	 * @param string $s column
	 * @return OutletQuery for fluid interface
	 */
	function select ($s) {
		$this->select = $s;
		
		return $this;
	}
	
	/**
	 * Declare an offset for the result set
	 * @param int $num Offset to begin returning result set at 
	 * @return OutletQuery for fluid interface
	 */
	function offset ($num) {
		$this->offset = $num;
		
		return $this;
	}
	
	/**
	 * Declare a grouping
	 * @param string $s column to group by
	 * @return OutletQuery for fluid interface
	 */
	function groupBy($s) {
		$this->groupBy = $s;
		
		return $this;
	}
	
	/**
	 * Declare a having clause
	 * @param string $s having condition
	 * @return OutletQuery for fluid interface
	 */
	function having($s) {
		$this->having = $s;
		
		return $this;
	}
	
	/**
	 * Execute the query
	 * @return array result set
	 */
	function find () {
		$outlet = $this->outlet;
		
		// get the 'from'
		$tmp = explode(' ', $this->from);

		$from = $tmp[0];
		$from_aliased = (count($tmp)>1 ? $tmp[1] : $tmp[0]);

		$config = $this->outlet->getConfig();
		$entity_config = $config->getEntity($from);
		$props = $entity_config->getProperties();
		
		$from_props = $props;
		
		$select_cols = array();
		foreach ($props as $key=>$p) {
			$select_cols[] = "\n{".$from_aliased.'.'.$key.'} as '.$from_aliased.'_'.$key;
		}
		
		// get the include entities
		$with = array();
		$with_aliased = array();
		
		$join_q = '';
		foreach ($this->with as $with_key=>$j) {
			$tmp = explode(' ', $j);
			
			$with[$with_key] = $tmp[0];
			$with_aliased[$with_key] = (count($tmp)>1 ? $tmp[1] : $tmp[0]);
			
			$assoc = $entity_config->getAssociation($with[$with_key]);

			if (!$assoc) throw new OutletException('No association found with entity or alias ['.$with[$with_key].']');
			
			$props = $config->getEntity($assoc->getForeign())->getProperties();
			foreach ($props as $key=>$p) {
				$select_cols[] = "\n{".$with_aliased[$with_key].'.'.$key.'} as '.$with_aliased[$with_key].'_'.$key;
			}
		
			$aliased_join = $with_aliased[$with_key];
			//$join_q .= "LEFT JOIN {".$assoc->getForeign()." ".$aliased_join."} ON {".$from_aliased.'.'.$assoc->getKey()."} = {".$with_aliased[$with_key].'.'.$assoc->getRefKey()."} \n";
			$keys = $assoc->getKeys();
                        $assoc_keys = $config->getEntity($assoc->getForeign())->getPKColumns();

			$join_q .= "LEFT JOIN {".$assoc->getForeign()." ".$aliased_join."} ON ";
			$join_clauses = array();
			for ($i = 0; $i < count($keys); $i++) {
				$join_clauses[] = "{".$from_aliased.'.'.$keys[$i]."} = {".$with_aliased[$with_key].'.'.$assoc_keys[$i]."} ";
			}
			$join_q .= implode(" AND ", $join_clauses);
		}
		
		$q = "SELECT ".implode(', ', $select_cols)." \n";
		
		if ($this->select) $q .= ", ".$this->select;
		
		$q .= " FROM {".$this->from."} \n";
		$q .= $join_q;
		
		$q .= implode("\n", $this->joins);
		
		if ($this->query) 		$q .= 'WHERE ' . $this->query."\n";
		if ($this->groupBy)		$q .= 'GROUP BY ' . $this->groupBy."\n";
		if ($this->orderby) 	$q .= 'ORDER BY ' . $this->orderby . "\n";
		if ($this->having)		$q .= 'HAVING ' . $this->having . "\n";
		
		// TODO: Make it work on MS SQL
		//	   In SQL Server 2005 http://www.singingeels.com/Articles/Pagination_In_SQL_Server_2005.aspx
		if ($this->limit){
			$q .= 'LIMIT '.$this->limit;
			if ($this->offset)
				$q .= ' OFFSET '.$this->offset;
		}
	
		//$stmt = $outlet->query($q, $this->params);
		$dbtable = $entity_config->table;
		$queryid = "db." . $dbtable . "." . md5($q);
		$rows = $outlet->query($q, $this->params, $queryid);

		
		$res = array();
		//while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
		$objs = array();
		$collections = array();

		foreach ($rows as $row) {
			$data = array();
			if (true || $outlet->getConnection()->getDialect() == 'pgsql') {
				foreach ($from_props as $key=>$p) {
					$data[$p[0]] = $row[strtolower($from_aliased).'_'.strtolower($key)];
				}
			} else {
				foreach ($from_props as $key=>$p) {
					$data[$p[0]] = $row[$from_aliased.'_'.$key];
				}
			}

			$obj = $outlet->getEntityForRow($entity_config, $data);
			$objid = implode("/", ($entity_config->getPKValues($obj)));
		
			foreach ($with as $with_key=>$w) {
				$a = $entity_config->getAssociation($w);
				
				if ($a) {
					$data = array();					
					$setter = $a->getSetter();
					$foreign = $a->getForeign();
					$with_entity = $config->getEntity($foreign);
					
					if ($a instanceof OutletOneToManyConfig)
					{
						if (true || $outlet->getConnection()->getDialect() == 'pgsql') {
							foreach ($with_entity->getProperties() as $key=>$p) {
								$data[$p[0]] = $row[strtolower($with_aliased[$with_key].'_'.$key)];
							}
						} else {
							foreach ($with_entity->getProperties() as $key=>$p) {
								$data[$p[0]] = $row[$with_aliased[$with_key].'_'.$key];
							}
						}
						$f = $with_entity->getPkColumns();
						
						// check to see if we found any data for the related entity
						// using the pk
						$data_returned = false;
						$pk_values = array();
						foreach ($f as $k) {
							if (!empty($data[$k])) {
								$data_returned = true;
								break;
							}
						}
						
						// If some data was returned, save it into in an array to be merged with this object once we've finished processing all the other rows
						if ($data_returned) {
							$childobj = $outlet->getEntityForRow($with_entity, $data);
							$childobjid = implode("/", ($with_entity->getPKValues($childobj)));
							$collections[$objid][$with_aliased[$with_key]][$childobjid] = $childobj;
						} else if (!isset($collections[$objid][$with_aliased[$with_key]])) {
							$collections[$objid][$with_aliased[$with_key]] = array();
						}
					}
					elseif ($a instanceof OutletManyToManyConfig)
					{
						// TODO: Implement...
					}
					// Many-to-one or one-to-one
					else
					{
						// Postgres returns columns as lowercase
						// TODO: Maybe everything should be converted to lower in query creation / processing to avoid this
						if ($outlet->getConnection()->getDialect() == 'pgsql')
							foreach ($with_entity->getProperties() as $key=>$p) {
								$data[$p[0]] = $row[strtolower($with_aliased[$with_key].'_'.$key)];
							}
						else
							foreach ($with_entity->getProperties() as $key=>$p) {
								$data[$p[0]] = $row[$with_aliased[$with_key].'_'.$key];
							}
							
						$f = $with_entity->getPkColumns();
						
						// check to see if we found any data for the related entity
						// using the pk
						$data_returned = false;
						$pk_values = array();
						foreach ($f as $k) {
							if (isset($data[$k])) {
								$data_returned = true;
								break;
							}
						}
						
						// only fill object if there was data returned
						if ($data_returned) $obj->$setter($outlet->getEntityForRow($with_entity, $data));
					}
				}
			}
			
			$objs[$objid] = $obj;
		}
		
		// Finally, merge any collections we've accumulated, and put it back into indexed array form

		foreach ($objs as $objid=>$obj) {
			if (isset($collections[$objid])) {
				foreach ($collections[$objid] as $with_type=>$with_objs) {
					$a = $entity_config->getAssociation($with_type);

					if ($a) {
						$setter = $a->getSetter();
						$collection = new Collection();
						foreach ($with_objs as $wobj) {
							$collection->add($wobj);
						}
						$obj->$setter($collection);
					}
				}
			}
			$res[] = $obj;
		}
		return $res;
	}
	
	/**
	 * Executes query returning the first result out of the result set
	 * @todo review code suggestion below
	 * @return object first result out of the result set
	 */
	public function findOne () {
		/*
		 * It would improve performance to replace the code below with
		 * $res = $this->limit(1)->find();
		 * 
		 * There is no need to do an unbound search if we only want one result
		 */
		$res = $this->find();
		
		if (count($res)) return $res[0];
	}
}
