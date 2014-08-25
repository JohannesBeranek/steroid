<?php

/**
 * @package steroid/storage
 */
require_once __DIR__ . '/interface.IDB.php';
require_once STROOT . '/storage/class.DBKeyDefinition.php';

require_once __DIR__ . '/class.BaseDB.php';

/**
 * base database class
 */
class DB extends BaseDB implements IDB  {

	const MATH_GREATEST = 'GREATEST'; // 2+ args
	const MATH_LEAST = 'LEAST'; // 2+ args
	const MATH_POW = 'POW'; // 2 args
	const MATH_LOG_10 = 'LOG10';
	const AGGREGATE_COUNT = 'COUNT'; // 1 arg
	const STRING_CONCAT = 'CONCAT'; // 2+ args
	const TIME_NOW = 'NOW'; // 0 args
	const CURRENT_TIME = 'CURRENT_TIME'; // 0 args
	const CURRENT_DATE = 'CURRENT_DATE'; // 0 args

	const DATE_DIFF = 'DATEDIFF'; // 2 args


	public static function isFunction( $n ) {
		return ( $n === self::MATH_GREATEST || $n === self::MATH_LEAST || $n === self::MATH_POW || 
			$n === self::AGGREGATE_COUNT || $n === self::STRING_CONCAT || $n === self::TIME_NOW || $n === self::MATH_LOG_10 || $n === self::DATE_DIFF ||
			$n === self::CURRENT_DATE || $n === self::CURRENT_TIME);
	}

	public static function isAggregateFunction( $n ) {
		return $n === self::AGGREGATE_COUNT;
	}

	public static function isMultiArgumentFunction( $n ) {
		return ( $n === self::MATH_GREATEST || $n === self::MATH_LEAST || $n === self::MATH_POW || $n === self::STRING_CONCAT || $n === self::DATE_DIFF );
	}

	public static function functionMaxArgumentCount( $n ) {
		return ($n === self::MATH_POW || $n === self::DATE_DIFF) ? 2 : ( $n === self::MATH_LOG_10 ? 1 : 0 );
	}

	public static function functionMinArgumentCount( $n ) {
		return $n === self::AGGREGATE_COUNT || $n === self::MATH_LOG_10 ? 1 : ( ($n === self::TIME_NOW || $n === self::CURRENT_DATE || $n === self::CURRENT_TIME ) ? 0 : 2 );
	}


	public function createTable( $isTemporary, $table, array $columnDefinitions, array $keyDefinitions, $engine = NULL, $charset = NULL ) {
		if ( empty( $table ) || empty( $columnDefinitions ) || empty( $keyDefinitions ) ) {
			throw new InvalidArgumentException( "\$table, \$keyDefinitions and \$columnDefinitions must be set" );
		}

		$query = 'CREATE ' . ( $isTemporary ? 'TEMPORARY ' : '' ) . 'TABLE ' . $this->escapeObjectName( $table ) . ' (';

		foreach ( $columnDefinitions as $column => $definition ) {
			$query .= $this->escapeObjectName( $column ) . ' ' . $definition->getCreate() . ',';
		}

		$keys = array();

		foreach ( $keyDefinitions as $keyName => $keyDef ) {
			if ( $keyName == 'PRIMARY' ) { 
				$key = 'PRIMARY KEY';
			} else {
				$key = ( !$keyDef->getValue( DBKeyDefinition::TABLEKEY_NON_UNIQUE ) ? 'UNIQUE KEY ' : 'KEY ' ) . $this->escapeObjectName( $keyName );
			}

			$key .= '(' . implode( ',', $this->escapeObjectNameArray( $keyDef->getValue( DBKeyDefinition::TABLEKEY_COLNAME ) ) ) . ')';

			$keys[ ] = $key;
		}

		$query .= implode( ',', $keys );

		$query .= ') ENGINE=' . $this->escape( $engine ? : $this->engine ) . ' DEFAULT CHARSET=' . $this->escape( $charset ? : $this->charset );

		return $this->query( $query );
	}


	// FIXME: phpdoc
	public function alterTable( $table, array $modifiedColumns, array $newColumns, array $dropColumns, array $modifiedKeys, array $newKeys, array $dropKeys, $engine = NULL, $charset = NULL ) {
		if ( empty( $table ) ) {
			throw new InvalidArgumentException( "\$table must be set" );
		}

		if ( empty( $modifiedColumns ) && empty( $newColumns ) && empty( $modifiedKeys ) && empty( $dropKeys ) && empty( $dropColumns ) && empty( $newKeys ) && empty( $engine ) && empty( $charset ) ) {
			throw new InvalidArgumentException( "At least one of \$modifiedColumns, \$newColumns, \$dropColumns, \$modifiedKeys, \$newKeys, \$dropKeys, \$engine or \$charset must be set" );
		}

		$queryParts = array();

		// FIXME: combine arrays, use switch
		if ( !empty( $dropColumns ) ) {
			foreach ( $dropColumns as $colName => $colDef ) {
				$queryParts[ ] = ' DROP ' . $this->escapeObjectName( $colName );
			}
		}

		if ( !empty( $modifiedColumns ) ) {
			foreach ( $modifiedColumns as $colName => $colDef ) {
				$queryParts[ ] = ' MODIFY ' . $this->escapeObjectName( $colName ) . ' ' . $colDef->getCreate();
			}
		}

		if ( !empty( $newColumns ) ) {
			foreach ( $newColumns as $colName => $colDef ) {
				$queryParts[ ] = ' ADD ' . $this->escapeObjectName( $colName ) . ' ' . $colDef->getCreate();
			}
		}

		if ( !empty( $modifiedKeys ) ) {
			foreach ( $modifiedKeys as $keyName => $keyDef ) {
				if ( strtoupper( $keyName ) == 'PRIMARY' ) {
					$queryParts[ ] = ' DROP PRIMARY KEY, ADD PRIMARY KEY (' . implode( ',', $this->escapeObjectNameArray( $keyDef->getValue( DBKeyDefinition::TABLEKEY_COLNAME ) ) ) . ')';
				} else {
					$keyUnique = $keyDef->getValue( DBKeyDefinition::TABLEKEY_NON_UNIQUE ) ? '' : 'UNIQUE ';
					$queryParts[ ] = ' DROP KEY ' . $this->escapeObjectName( $keyName ) . ', ADD ' . $keyUnique . 'KEY ' . $this->escapeObjectName( $keyName ) . '(' . implode( ',', $this->escapeObjectNameArray( $keyDef->getValue( DBKeyDefinition::TABLEKEY_COLNAME ) ) ) . ')';
				}
			}
		}

		if ( !empty( $newKeys ) ) {
			foreach ( $newKeys as $keyName => $keyDef ) {
				if ( strtoupper( $keyName ) == 'PRIMARY' ) {
					$queryParts[ ] = ' ADD PRIMARY KEY (' . implode( ',', $keyDef->getValue( DBKeyDefinition::TABLEKEY_COLNAME ) ) . ')';
				} else {
					$keyUnique = $keyDef->getValue( DBKeyDefinition::TABLEKEY_NON_UNIQUE ) ? '' : 'UNIQUE ';
					$queryParts[ ] = ' ADD ' . $keyUnique . 'KEY ' . $this->escapeObjectName( $keyName ) . '(' . implode( ',', $this->escapeObjectNameArray( $keyDef->getValue( DBKeyDefinition::TABLEKEY_COLNAME ) ) ) . ')';
				}
			}
		}

		if ( !empty( $dropKeys ) ) {
			foreach ( $dropKeys as $keyName ) {
				$queryParts[ ] = 'DROP KEY ' . $this->escapeObjectName( $keyName );
			}
		}

		if ( !empty( $engine ) ) {
			$queryParts[ ] = ' ENGINE = ' . $this->escape( $engine );
		}

		if ( !empty( $charset ) ) {
			$queryParts[ ] = ' DEFAULT CHARSET = ' . $this->escape( $charset );
		}

		// FIXME: collation ?

		$query = 'ALTER TABLE ' . $this->escapeObjectName( $table ) . implode( ',', $queryParts );

		return $this->query( $query );
	}
}

class TableDoesNotExistException extends Exception {
}

if (!class_exists('mysqli')) { // mysqli emulation layer for older versions of HHVM

class mysqli {
	protected $conn;

	public function __construct( $host, $user, $password, $database ) {
		if ( $host[0] === 'p' && $host[1] === ':' ) {
			$host = substr($host, 2);
			$this->conn = mysql_pconnect( $host, $user, $password );
		} else {
			$this->conn = mysql_connect( $host, $user, $password );
		}

		mysql_select_db( $database, $this->conn );
	}

	public function set_charset( $charset ) {
		return mysql_set_charset( $charset, $this->conn );
	}

	// rollback()
	// autocommit(bool)
	// commit()

	public function autocommit( $val ) {
		$this->query( 'SET AUTOCOMMIT ' . ( $val ? '1' : '0' ) );

		// closest we can get to success message
		return (bool)mysql_error( $this->conn );
	}

	public function rollback() {
		$this->query( 'ROLLBACK' );

		return (bool)mysql_error( $this->conn );
	}

	public function commit() {
		$this->query( 'COMMIT' );

		return (bool)mysql_error( $this->conn );
	}

	public function query( $query ) {
		$res = mysql_query( $query, $this->conn );

		return new mysqli_result( $res );
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'errno':
			case 'connect_errno':
				return mysql_errno( $this->conn );
			break;
			case 'error':
			case 'connect_error':
				return mysql_error( $this->conn );
			break;
			case 'insert_id':
				return mysql_insert_id( $this->conn );
			break;
			case 'affected_rows':
				return mysql_affected_rows( $this->conn );
			break;
			default:
				throw new InvalidArgumentException();
		}

		// never reached
		return NULL;
	}

	public function real_escape_string( $str ) {
		return mysql_real_escape_string( $str, $this->conn );
	}

	public function close() {
		if ( is_resource( $this->conn ) ) {
			mysql_close( $this->conn );
			$this->conn = null;
		}
		
	}

	public function __destruct() {
		$this->close();
	}
}

class mysqli_result {
	protected $res;

	public function __construct( $res ) {
		$this->res = $res;
	}

	public function fetch_assoc() {
		return mysql_fetch_assoc( $this->res );
	}

	public function free() {
		$ret = mysql_free_result( $this->res );
		$this->res = null;

		return $ret;
	}

	public function __destruct() {
		if (is_resource($this->res)) {
			mysql_free_result($this->res);
			$this->res = null;
		}
	}
}

} // if (!class_exists('mysqli'))