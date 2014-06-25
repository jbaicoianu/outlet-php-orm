<?php

/**
 * Outlet wrapper for a database connection.
 * @package outlet
 * @method PDOStatement prepare (string $statement)
 */
class OutletConnection {
	private $driver;
	private $dialect;
	private $pdo;

	protected $nestedTransactionLevel = 0;

	/**
	 * Assume that the driver supports transactions until proven otherwise
	 * @see OutletConnection::beginTransaction()
	 * @var bool
	 */
	protected $driverSupportsTransactions = true;

	/**
	 * Constructs a new instance of OutletConnection
	 * @param PDO $pdo
	 * @param string $dialect It can be 'sqlite', 'mysql', 'mssql', or 'pgsql'
	 * @return OutletConnection instance
	 */
	function __construct ($pdo, $dialect) {
		$this->pdo = $pdo;
		$this->dialect = $dialect;
	}

	/**
	 * The dialect, can be 'sqlite', 'mysql', 'mssql', or 'pgsql'
	 * @return string
	 */
	function getDialect () {
		return $this->dialect;
	}

	/**
	 * The PHP Database Object 
	 * @link http://us2.php.net/pdo
	 * @return PDO
	 */
	function getPDO () {
		return $this->pdo;
	}

	function generateQueryID($table, $args=NULL) {
		$queryid = "db." . $table;

		if (is_array($args) || is_object($args)) {
			foreach ($args as $k=>$v) {
				$queryid .= sprintf(".%s-%s", $k, $v);
			}
		} else {
			$queryid .= ":nocache";
		}

		return $queryid;
	}
	function prepareAndExecute($query, $args, $queryid=NULL) {
		if ($this->pdo) {
			$stmt = $this->pdo->prepare($query);
			$stmt->execute($args);

			$ret = array();
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$ret[] = $row;
			}
		} else {
			if ($queryid === NULL)
				$queryid = "db.default." . md5($query) . ":nocache";
			//print_pre("EXECUTE: " . $queryid);
			$result = DataManager::Query($queryid, $query, $args);
			$ret = array();
			if (!empty($result->rows)) {
				foreach ($result->rows as $row) {
					$ret[] = object_to_array($row);
				}
			}
		}
		return $ret;
	}
	/**
	 * Begins a database transaction
	 * @return bool true if successful, false otherwise 
	 */
	function beginTransaction ($queryid) {
		if ($this->pdo) {
			if (!$this->nestedTransactionLevel++) {

				// attempt standard pdo beginTransaction
				try {
					return $this->pdo->beginTransaction();
				} catch (PDOException $e) {
					// save the fact that this driver (probably dblib) doesn't support transactions
					if ($this->driverSupportsTransactions) $this->driverSupportsTransactions = false;	
					return $this->exec('BEGIN TRANSACTION');
				}
			}
			return true;
		} else {
			return DataManager::BeginTransaction($queryid);
		}
	}

	/**
	 * Commit a database transaction
	 * @see OutletConnection::beginTransaction
	 * @return bool true if successful, false otherwise
	 */
	function commit ($queryid) {
		if ($this->pdo) {
			if (!--$this->nestedTransactionLevel) {

				// commit using best method as determined in OutletConnection::beginTransaction
				if ($this->driverSupportsTransactions) {
					return $this->pdo->commit();
				} else {
					return $this->exec('COMMIT TRANSACTION');
				}
			}
			return true;
		} else {
			return DataManager::Commit($queryid);
		}
	}

	/**
	 * Rollback the current database transaction
	 * @see OutletConnection::beginTransaction()
	 * @return bool true if successful, false otherwise
	 */
	function rollBack ($queryid) {
		if ($this->pdo) {
			if (!--$this->nestedTransactionLevel) {

				// rollback using best method as determined in OutletConnection::beginTransaction()
				if ($this->driverSupportsTransactions) {
					return $this->pdo->rollBack();
				} else {
					$this->exec('ROLLBACK TRANSACTION');
				}
			}
			return true;
		} else {
			return DataManager::Rollback($queryid);
		}
	}

	/**
	 * Quotes a value to escape special characters, protects against sql injection attacks
	 * @param mixed $v value to escape
	 * @return string the escaped value 
	 */
	function quote ($v) {
		if ($this->pdo) {
			$quoted = $this->pdo->quote($v);
			
			// odbc doesn't support quote and returns false
			// quote it manually if that's the case	
			if ($v !== false && $quoted===false) {
				if (is_int($v)) {
					$quoted = $v;
				} else {
					$quoted = "'".str_replace("'", "''", $v)."'";
				}
			}	
			return $quoted;
		} else {
			return mysql_escape_string($v); // FIXME - bad!	This should pass through to the datamanager to pass off to source-specific quote functions
			//return DataManager::quote($v);
		}
	}
	
	/**
	 * Automagical __call method, overloaded to allow transparent callthrough to the pdo object
	 * @param object $method method to call 
	 * @param object $args arguments to pass to method
	 * @return mixed result from the pdo function call
	 */
	function __call ($method, $args) {
		return call_user_func_array(array($this->pdo, $method), $args);
	}
	
	/**
	 * Returns last generated ID
	 * If using PostgreSQL the $sequenceName needs to be specified
	 * @param string $sequenceName [optional] The sequence name, defaults to ''
	 * @return int the last insert id
	 */
	function lastInsertId ($sequenceName = '') {
		if ($this->getDialect() == 'mssql') {
			return $this->query('SELECT SCOPE_IDENTITY()')->fetchColumn(0);
		} else if ($this->pdo) {
			return $this->pdo->lastInsertId($sequenceName);
		}
	}
}

