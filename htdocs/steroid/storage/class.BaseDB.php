<?php

require_once __DIR__ . '/interface.ITransactionBased.php';
require_once __DIR__ . '/class.Transaction.php';

class BaseDB implements ITransactionBased {
	
	protected $host, $user, $password, $database, $engine, $charset, $collation;

	protected $persistent;
	protected $transactionLevel = 0;

	protected $conn;

	protected $queryCounter = 0;
	protected $queryTime = 0;
	protected $lastQuery;

	const SAVEPOINT_NAME_PREFIX = 'SP';
	const ORDER_BY_ASC = 'ASC';
	const ORDER_BY_DESC = 'DESC';

	const DEFAULT_ENGINE = 'InnoDB';
	const DEFAULT_CHARSET = 'utf8';
	const DEFAULT_COLLATION = 'utf8_general_ci';
	
	public function getDataBase() {
		return $this->database;
	}

	public function getTableCharset( $charset = NULL ) {
		return empty( $charset ) ? $this->charset : $charset;
	}

	public function getTableCollation( $collation = NULL ) {
		return empty( $collation ) ? $this->collation : $collation;
	}

	public function getTableEngine( $engine = NULL ) {
		return empty( $engine ) ? $this->engine : $engine;
	}

	public function __construct( $host, $user, $password, $database, $engine = NULL, $charset = NULL, $collation = NULL, $persistent = true ) {
		$this->host = $host;
		$this->user = $user;
		$this->password = $password;
		$this->database = $database;

		$this->engine = $engine === NULL ? self::DEFAULT_ENGINE : $engine;
		$this->charset = $charset === NULL ? self::DEFAULT_CHARSET : $charset;
		$this->collation = $collation === NULL ? self::DEFAULT_COLLATION : $collation;

		$this->persistent = $persistent;
	}

	public function init() {
		if ( $this->conn ) return;

		$this->_createConnection();
	}

	public function isInit() {
		return (bool)$this->conn;
	}

	protected function _createConnection() {
		$this->conn = new mysqli( ( $this->persistent ? 'p:' : '' ) . $this->host, $this->user, $this->password, $this->database );

		if ( $this->conn->connect_errno != 0 ) {
			throw new Exception( 'Error connecting to database: ' . $this->conn->connect_errno . ' : ' . $this->conn->connect_error );
		}

		register_shutdown_function( array( $this, '_cleanup' ) );

		if ( !$this->conn->set_charset( $this->charset ) ) {
			throw new Exception( 'Error setting character set to "' . $this->charset . '": ' . $this->conn->error );
		}
	}

	public function __clone() {
		if ( $this->conn )
			$this->_createConnection();
	}

	public function __destruct() {
		$this->_cleanup();
	}

	protected function safeLog( $e ) {
		error_log( get_class( $e ) . " thrown within _cleanup. Message: " . $e->getMessage() . "  in " . $e->getFile() . " on line " . $e->getLine() );
		error_log( 'Exception trace stack: ' . print_r( $e->getTrace(), 1 ) );
	}

	// registered as shutdown function so we cleanup no matter what happens (destructors may not always get called!)
	// needs to be public to be callable on shutdown
	public function _cleanup() {
		if ( $this->conn ) {
			if ( $this->transactionLevel > 0 ) {
				error_log( 'Open transaction on _cleanup.' );

				try {
					$this->conn->rollback();
					$this->conn->autocommit( true );
					$this->transactionLevel = 0;
					$this->afterCleanup();
				} catch ( Exception $e ) {
					$this->safeLog( $e );
				}
			}


			$this->conn->close();
			$this->conn = NULL;
		}
	}

	protected function afterCleanup() {
		// stub to be overwritten in subclasses
	}

	public function query( $query ) {
		$this->queryCounter++;

		// FIXME: only time if needed
		$start = microtime( true );

		$res = $this->conn->query( $query );

		$this->lastQuery = $query;

		// FIXME: only time if needed + only allow if config param is set
		$queryTime = microtime( true ) - $start;

		if (isset($_GET['sqt']) && ($queryTime * 1000) > floatval($_GET['sqt'])) {
			error_log(($queryTime * 1000) . 'ms : ' . $query );
		}

		$this->queryTime += $queryTime;

		$this->checkErrorAndThrow( $query );

		return $res;
	}

	public function getQueryCount() {
		return $this->queryCounter;
	}

	public function getQueryTime() {
		return $this->queryTime;
	}

	public function getLastQuery() {
		return $this->lastQuery;
	}

	public function count( $table ) {
		$row = $this->fetchFirst( 'SELECT COUNT(*) as ct FROM ' . $this->escape( $table, false ) . ' LIMIT 1' );

		return $row[ 'ct' ];
	}

	public function escapeLike( $like ) {
		return addcslashes( $like, '\\_%' );
	}

	public function escape( $values, $addQuotes = true, $keepNull = false ) {
		if ( (array)$values === $values ) {
			$escapedValues = array();
			foreach ( $values as $k => $v ) {
				$escapedValues[ $k ] = $this->escape( $v, $addQuotes, $keepNull );
			}

		} else {
			if ( $keepNull && $values === NULL ) return "NULL";

			$escapedValues = $this->conn->real_escape_string( $values );

			if ( $addQuotes ) {
				$escapedValues = "'{$escapedValues}'";
			}
		}

		return $escapedValues;
	}

	/**
	 * Escapes an MySQL identifier
	 *
	 * Does not support arrays
	 *
	 * @param string    $object Identifier to escape
	 * @param null|bool $quote = true Add quotes to escaped identifier?
	 */
	public function escapeObjectName( $object, $quote = true ) {
		// This may not be 100% correct, but it's 100% secure and sufficient for our usage
		// See following link for complete spec:
		// http://dev.mysql.com/doc/refman/5.0/en/identifiers.html

		// As we're always only dealing with utf8 tables and utf8 data, we don't need to use the mysql function
		// We're still using mysql function + str_replace, as the strtr alternative benchmarks slower
		$ret = $this->conn->real_escape_string( str_replace( '`', '', $object ) );

		if ( $quote ) {
			$ret = '`' . $ret . '`';
		}

		return $ret;
	}

	public function escapeObjectNameArray( array $objects, $quote = true ) {
		foreach ( $objects as &$object ) {
			$object = $this->escapeObjectName( $object, $quote );
		}

		return $objects;
	}

	protected function checkErrorAndThrow( $query ) {
		if ( $this->conn->error ) {
			$errno = $this->conn->errno;

			$errorMessage = "MySQL error #{$errno}:\nQuery:\n{$query}\n{$this->conn->error}";

			switch ( $errno ) {
				case 1205: // Lock wait timeout exceeded; try restarting transaction
					try {
						$errorMessage .= "\n" . print_r( $this->fetchAll( 'SHOW ENGINE INNODB STATUS' ), true ); // try to get additional info - only works with enough privileges
					} catch ( Exception $e ) {
					} // swallow further exceptions, otherwise we'll lose the original one
					break;

			}

			throw new Exception( $errorMessage, $errno );
		}
	}

	public function fetchAll( $query ) {
		$res = $this->query( $query );

		if ( is_bool( $res ) ) { // support delete queries
			return $res;
		}

		$items = array();

		while ( $row = $res->fetch_assoc() ) {
			$items[ ] = $row;
		}

		$res->free();

		return $items;
	}

	/**
	 * Fetch values of first column of all rows
	 */
	public function fetchAllFirst( $query ) {
		$res = $this->query( $query );

		if ( is_bool( $res ) ) { // support delete queries
			return $res;
		}

		$items = array();

		while ( $row = $res->fetch_assoc() ) {
			$items[ ] = reset( $row );
		}

		$res->free();

		return $items;
	}

	/**
	 * Fetches first element
	 */
	public function fetchFirst( $query ) {
		$res = $this->query( $query );

		$row = $res->fetch_assoc();

		$res->free();

		return $row;
	}

	public function fetchRange( $query, $start = NULL, $count = NULL ) {
		$query .= $this->buildLimit( $start, $count );

		return $this->fetchAll( $query );
	}

	protected function buildLimit( $start = NULL, $count = NULL ) {
		if ( $start === NULL && $count === NULL ) return '';

		$limitString = ' LIMIT ';

		if ( $start === NULL ) {
			$limitString .= intval( $count );
		} else if ( $count === NULL ) {
			$limitString .= intval( $start ) . ', 18446744073709551615'; // max unsinged int 64 bit
		} else {
			$limitString .= intval( $start ) . ',' . intval( $count );
		}

		return $limitString;
	}

	public function getColumnSchemaForTable( $table ) {
		if ( empty( $table ) ) {
			throw new InvalidArgumentException( "\$table must be set" );
		}

		$query = 'SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ' . $this->escape( $this->database ) . ' AND TABLE_NAME = ' . $this->escape( $table );

		return $this->fetchAll( $query );
	}

	public function getTableSchema( $table ) {
		if ( empty( $table ) ) {
			throw new InvalidArgumentException( "\$table must be set" );
		}

		$query = 'SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ' . $this->escape( $this->database ) . ' AND TABLE_NAME = ' . $this->escape( $table ) . ' LIMIT 1';

		return $this->fetchFirst( $query );
	}


	public function dropTable( $table ) {
		if ( empty( $table ) ) {
			throw new InvalidArgumentException( "\$table must be set" );
		}

		$query = 'DROP TABLE ' . $this->escapeObjectName( $table );

		$this->query( $query );
	}

	public function getTableKeys( $table ) {
		if ( empty( $table ) ) {
			throw new InvalidArgumentException( "\$table must be set" );
		}

		$query = 'SHOW INDEXES FROM ' . $this->escapeObjectName( $table );

		return $this->fetchAll( $query );
	}

	protected function getUpdateString( array $columnValuePairs ) {
		$cv = array();

		foreach ( $columnValuePairs as $column => $value ) {
			$cv[ ] = $this->escapeObjectName( $column ) . '=' . ( ( $value === NULL ) ? 'NULL' : $this->escape( $value ) );
		}

		return implode( ',', $cv );
	}

	/**
	 * (non-PHPdoc)
	 * @see IDB::insert()
	 *
	 * returned insert_id is only meaningful if auto_increment column is present in table and it's the first column of the primary key
	 */
	public function insert( $table, array $columnValuePairs, array $orUpdate = NULL ) {
		if ( empty( $table ) ) {
			throw new InvalidArgumentException( "\$table  must be set" );
		}

		$columns = array();
		$values = array();

		foreach ( $columnValuePairs as $column => $value ) {
			$columns[ ] = $this->escapeObjectName( $column );
			$values[ ] = $this->escape( $value, true, true );
		}

		$query = 'INSERT INTO ' . $this->escapeObjectName( $table ) . ' (' . implode( ',', $columns ) . ') VALUES (' . implode( ',', $values ) . ')';

		if ( $orUpdate ) {
			$query .= ' ON DUPLICATE KEY UPDATE ' . $this->getUpdateString( $orUpdate );
		}

		$this->query( $query );

		return $this->conn->insert_id;
	}

	public function update( $table, $where, array $columnValuePairs ) {
		if ( empty( $table ) || empty( $columnValuePairs ) ) {
			throw new InvalidArgumentException( "\$table and \$columnValuePairs must be set" );
		}

		$query = 'UPDATE ' . $this->escapeObjectName( $table ) . ' SET ' . $this->getUpdateString( $columnValuePairs ) . ( empty( $where ) ? '' : ( ' WHERE ' . $where ) );

		$this->query( $query );

		return $this->conn->affected_rows;
	}

	public function clearTable( $table, $useTransactionUnsafeTruncate = false ) {
		$query = ( $useTransactionUnsafeTruncate ? 'TRUNCATE ' : 'DELETE FROM ' ) . $this->escape( $table );

		$this->query( $query );

		return $this->conn->affected_rows;
	}

	public function getInsertID() { // FIXME: __get with insertID
		return $this->conn->insert_id;
	}

	public function getAffectedRows() { // FIXME: __get with affectedRows
		return $this->conn->affected_rows;
	}

	public function startTransaction() {
		$transactionName = null;

		if ( $this->transactionLevel == 0 ) {
			$this->conn->autocommit( false );
		} else {
			$transactionName = self::SAVEPOINT_NAME_PREFIX . $this->transactionLevel;
			$this->query( 'SAVEPOINT ' . $transactionName );
		}

		$this->transactionLevel++;

		return new Transaction( $this, $this->transactionLevel, $transactionName );
	}

	public function commit( Transaction $transaction ) {
		if ( $transaction->level <= 0 || $transaction->level != $this->transactionLevel ) {
			throw new LogicException( '$transaction->level <= 0 || $transaction->level != $this->transactionLevel' );
		}

		if ( $transaction->level == 1 ) {
			$this->conn->commit();
			$this->conn->autocommit( true );
		} else {
			$this->query( 'RELEASE SAVEPOINT ' . $transaction->name );
		}

		$this->transactionLevel = $transaction->level - 1;
	}

	public function rollback( Transaction $transaction ) {
		if ( $transaction->level <= 0 || $transaction->level != $this->transactionLevel ) {
			throw new LogicException( '$transaction->level <= 0 || $transaction->level != $this->transactionLevel' );
		}

		if ( $transaction->level == 1 ) {
			$this->conn->rollback();
			$this->conn->autocommit( true );
		} else {
			$this->query( 'ROLLBACK TO ' . $transaction->name );
		}

		$this->transactionLevel = $transaction->level - 1;
	}

	public function release( Transaction $transaction ) {
		$this->rollback( $transaction );

		if ( $transaction->level <= 0 ) {
			throw new LogicException( '$transaction->level <= 0' );
		} else {
			throw new LogicException( 'Transaction left open without calling commit or rollback!' );
		}
	}

	public function getLock( $name, $timeout ) {
		$res = $this->fetchFirst( 'SELECT GET_LOCK(' . $this->escape( $name ) . ',' . intval( $timeout ) . ')' );

		return array_shift( $res );
	}


	public function releaseLock( $name ) {
		$res = $this->fetchFirst( 'SELECT RELEASE_LOCK(' . $this->escape( $name ) . ')' );

		return array_shift( $res );
	}


	public function isFreeLock( $name ) {
		$res = $this->fetchFirst( 'SELECT IS_FREE_LOCK(' . $this->escape( $name ) . ')' );

		return array_shift( $res );
	}
}
