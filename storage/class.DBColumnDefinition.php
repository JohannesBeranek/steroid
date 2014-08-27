<?php
/**
 * @package steroid\db
 */

require_once STROOT . '/storage/interface.IDBColumnDefinition.php';
require_once STROOT . '/util/class.ClassFinder.php';

// FIXME: require all needed classes

/**
 * class that stores, fetches and generates column definitions for a single column
 *
 * @package steroid\db
 */
class DBColumnDefinition implements IDBColumnDefinition {

	/**
	 * constants used for the definition keys as returned by mysql
	 */
	const COLUMN_DEFINITION_NAME = 'COLUMN_NAME';
	const COLUMN_DEFINITION_DEFAULT = 'COLUMN_DEFAULT';
	const COLUMN_DEFINITION_IS_NULLABLE = 'IS_NULLABLE';
	const COLUMN_DEFINITION_TYPE = 'DATA_TYPE';
	const COLUMN_DEFINITION_DATATYPE = 'internalDT';
	const COLUMN_DEFINITION_CHAR_MAX_LEN = 'CHARACTER_MAXIMUM_LENGTH';
	const COLUMN_DEFINITION_CHARSET = 'CHARACTER_SET_NAME';
	const COLUMN_DEFINITION_COLLATION = 'COLLATION_NAME';
	const COLUMN_DEFINITION_KEY = 'COLUMN_KEY';
	const COLUMN_DEFINITION_EXTRA = 'EXTRA';
	const COLUMN_DEFINITION_POSITION = 'ORDINAL_POSITION';
	const COLUMN_DEFINITION_PRECISION = 'NUMERIC_PRECISION';
	const COLUMN_DEFINITION_SCALE = 'NUMERIC_SCALE';
	const COLUMN_DEFINITION_VALUES = 'VALUES';

	/**
	 * constants for dataTypes as returned by mysql
	 */
	const COLUMN_TYPE_CHAR = 'char';
	const COLUMN_TYPE_VARCHAR = 'varchar';
	const COLUMN_TYPE_TEXT = 'text';
	const COLUMN_TYPE_MEDIUMTEXT = 'mediumtext';
	const COLUMN_TYPE_LONGTEXT = 'longtext';
	const COLUMN_TYPE_DATE = 'date';
	const COLUMN_TYPE_TIME = 'time';
	const COLUMN_TYPE_DATETIME = 'datetime';
	const COLUMN_TYPE_TINYINT = 'tinyint';
	const COLUMN_TYPE_SMALLINT = 'smallint';
	const COLUMN_TYPE_MEDIUMINT = 'mediumint';
	const COLUMN_TYPE_INT = 'int';
	const COLUMN_TYPE_BIGINT = 'bigint';
	const COLUMN_TYPE_FLOAT = 'float';
	const COLUMN_TYPE_BOOLEAN = 'bool';
	const COLUMN_TYPE_ENUM = 'enum';
	const COLUMN_TYPE_SET = 'set';

	//TODO: add constants for max and min values for numeric types?


	/**
	 * the field configuration as returned by a recordClass
	 *
	 * @var array
	 */
	protected $fieldConf = array();

	/**
	 * the column schema as returned by mysql
	 *
	 * @var array
	 */
	protected $columnSchema = array();

	/**
	 * the column definitions that are relevant for comparison (doesn't include name and position)
	 *
	 * @var array
	 */
	protected static $columnDefinitions = array(
		self::COLUMN_DEFINITION_TYPE,
		self::COLUMN_DEFINITION_DEFAULT,
		self::COLUMN_DEFINITION_IS_NULLABLE,
		self::COLUMN_DEFINITION_CHAR_MAX_LEN,
		self::COLUMN_DEFINITION_CHARSET,
		self::COLUMN_DEFINITION_COLLATION,
		self::COLUMN_DEFINITION_EXTRA
	);

	/**
	 * gets the field configuration from the record class, loads the required dataTypes via ClassFinder and generates a best match column schema for the field configuration
	 *
	 * @param       $key key name for the field as defined in the recordClass
	 * @param array $fieldConf fieldConf as defined in the recordClass
	 *
	 * @throws InvalidArgumentException
	 */
	public function setDefinitionFromRecord( $fieldName, array $fieldConf ) {
		if ( empty( $fieldConf ) || empty( $fieldName ) ) {
			throw new InvalidArgumentException( "\$fieldName and \$fieldConf must be set" );
		}

		$dataTypeClasses = ClassFinder::find( array( $fieldConf[ 'dataType' ] ), true );
		$dataType = $dataTypeClasses[ 0 ]; // we only have one datatype per record field

		$this->fieldConf[ $fieldName ] = $fieldConf;

		$columnSchema = self::generateBestMatchSchemaFromFieldConf( $fieldConf );

		$columnSchema[ self::COLUMN_DEFINITION_NAME ] = $dataType[ ClassFinder::CLASSFILE_KEY_CLASSNAME ]::getColName( $fieldName, $this->fieldConf[ $fieldName ] ); //keys aren't necessarily the same as column names, so we need to get the real name from the datatype

		if ( !isset( $columnSchema[ 'EXTRA' ] ) || $columnSchema[ 'EXTRA' ] === NULL ) {
			$columnSchema[ 'EXTRA' ] = "";
		}

		if ( isset( $columnSchema[ 'COLUMN_DEFAULT' ] ) && is_int( $columnSchema[ 'COLUMN_DEFAULT' ] ) ) {
			$columnSchema[ 'COLUMN_DEFAULT' ] = (string)$columnSchema[ 'COLUMN_DEFAULT' ];
		}

		$this->columnSchema = $columnSchema;
	}

	public function getSchema() {
		return $this->columnSchema;
	}

	/**
	 * stores the column schema as returned by mysql
	 *
	 * @param array $columnSchema
	 *
	 * @throws InvalidArgumentException
	 */
	public function setDefinitionFromTable( array $columnSchema ) {
		if ( empty( $columnSchema ) ) {
			throw new InvalidArgumentException( "\$columnSchema must be set" );
		}

		if ( array_key_exists( self::COLUMN_DEFINITION_CHAR_MAX_LEN, $columnSchema ) && is_string( $columnSchema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] ) && ctype_digit( $columnSchema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] ) ) {
			$columnSchema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = intval( $columnSchema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] );
		}

		if ( $columnSchema[ self::COLUMN_DEFINITION_TYPE ] === self::COLUMN_TYPE_FLOAT && isset( $columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] ) ) {
			$columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] = (float)$columnSchema[ self::COLUMN_DEFINITION_DEFAULT ];
		}

		$this->columnSchema = $columnSchema;
	}

	/**
	 * splits up schema generation into other methods by datatype
	 *
	 * if there's no implementation for a specific datatype, it will return a column schema for a "text" type column
	 *
	 * @static
	 *
	 * @param array $fieldConf
	 *
	 * @return array $columnSchema
	 */
	protected static function generateBestMatchSchemaFromFieldConf( array $fieldConf ) {
		$dt = $fieldConf[ 'dataType' ];
		$dt::adaptFieldConfForDB( $fieldConf );

		if ( is_subclass_of( $fieldConf[ 'dataType' ], 'BaseDTInteger' ) ) {
			$schema = self::generateSchemaForTypeNumeric( $fieldConf );
		} else if ( is_subclass_of( $fieldConf[ 'dataType' ], 'BaseDTString' ) ) {
			$schema = self::generateSchemaForTypeString( $fieldConf );
		} elseif ( is_subclass_of( $fieldConf[ 'dataType' ], 'BaseDTDateTime' ) ) {
			$schema = self::generateSchemaForTypeDateTime( $fieldConf );
		} elseif ( is_subclass_of( $fieldConf[ 'dataType' ], 'BaseDTBool' ) ) {
			$schema = self::generateSchemaForTypeBoolean( $fieldConf );
		} elseif ( is_subclass_of( $fieldConf[ 'dataType' ], 'BaseDTSet' ) ) {
			$schema = self::generateSchemaForTypeSet( $fieldConf );
		} elseif ( is_subclass_of( $fieldConf[ 'dataType' ], 'BaseDTEnum' ) ) {
			$schema = self::generateSchemaForTypeEnum( $fieldConf );
		} elseif ( is_subclass_of( $fieldConf[ 'dataType' ], 'BaseDTFloat' ) ) {
			$schema = self::generateSchemaForTypeFloat( $fieldConf );
		} else {
			echo 'Unhandled datatype ' . $fieldConf[ 'dataType' ];
			$schema = array(
				self::COLUMN_DEFINITION_TYPE => self::COLUMN_TYPE_TEXT
			);
		}

		return $schema;
	}

	/**
	 * generates a column schema for string type columns
	 *
	 * uses the maxLen variable to determine the exact type (char, varchar, text) and sets default values for unspecified definitions
	 *
	 * @static
	 *
	 * @param array $fieldConf
	 *
	 * @return array $columnSchema
	 */
	public static function generateSchemaForTypeString( array $fieldConf ) {
		$schema = array();

		// determine type and maxLen
		if ( empty( $fieldConf[ 'maxLen' ] ) || intval( $fieldConf[ 'maxLen' ] ) < 0 ) {
			$fieldConf[ 'maxLen' ] = 65535;
		}

		if ( $fieldConf[ 'maxLen' ] > 255 ) { // no maximum length or greater than 255 forces a "text" typ

			if ( $fieldConf[ 'maxLen' ] <= 65535 ) {
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_TEXT;
				$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = 65535; // TEXT
			} else if ( $fieldConf[ 'maxLen' ] <= 16777215 ) {
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_MEDIUMTEXT;
				$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = 16777215; // MEDIUMTEXT
			} else {
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_LONGTEXT;
				$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = 4294967295; // LONGTEXT
			}

		} else if ( $fieldConf[ 'maxLen' ] < 256 ) {
			if ( empty( $fieldConf[ 'fixedLen' ] ) ) { // fixedLen == true means it's a char, otherwise it's a varchar
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_VARCHAR;
				$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = $fieldConf[ 'maxLen' ];
			} else {
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_CHAR;
				$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = $fieldConf[ 'maxLen' ];
			}
		}

		//set default
		$schema[ self::COLUMN_DEFINITION_DEFAULT ] = $fieldConf[ 'default' ];

		//set nullable
		$schema[ self::COLUMN_DEFINITION_IS_NULLABLE ] = ( !empty( $fieldConf[ 'nullable' ] ) ? 'YES' : 'NO' );

		//set character set
		$schema[ self::COLUMN_DEFINITION_CHARSET ] = ( !empty( $fieldConf[ 'charset' ] ) ? $fieldConf[ 'charset' ] : 'utf8' );

		//set collation
		$schema[ self::COLUMN_DEFINITION_COLLATION ] = ( !empty( $fieldConf[ 'collation' ] ) ? $fieldConf[ 'collation' ] : 'utf8_general_ci' );

		//set auto increment
		$schema[ self::COLUMN_DEFINITION_EXTRA ] = NULL; // string types can't have auto_increment

		//set precision
		$schema[ self::COLUMN_DEFINITION_PRECISION ] = NULL; // only applicable for numeric types

		//set scale
		$schema[ self::COLUMN_DEFINITION_SCALE ] = NULL; // only applicable for numeric types

		return $schema;
	}

	public static function generateSchemaForTypeEnum( array $fieldConf ) {
		$schema = array();

		if ( empty( $fieldConf[ 'values' ] ) ) {
			throw new InvalidArgumentException( 'Column of type ENUM must have values set' );
		}

		$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_ENUM;

		$schema[ self::COLUMN_DEFINITION_VALUES ] = $fieldConf[ 'values' ];

		$schema[ self::COLUMN_DEFINITION_DEFAULT ] = NULL;

		//set nullable
		$schema[ self::COLUMN_DEFINITION_IS_NULLABLE ] = ( !empty( $fieldConf[ 'nullable' ] ) ? 'YES' : 'NO' );

		//set character set
		$schema[ self::COLUMN_DEFINITION_CHARSET ] = ( !empty( $fieldConf[ 'charset' ] ) ? $fieldConf[ 'charset' ] : 'utf8' );

		//set collation
		$schema[ self::COLUMN_DEFINITION_COLLATION ] = ( !empty( $fieldConf[ 'collation' ] ) ? $fieldConf[ 'collation' ] : 'utf8_general_ci' );

		$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = max( array_map( 'strlen', $fieldConf[ 'values' ] ) );

		//set auto increment
		$schema[ self::COLUMN_DEFINITION_EXTRA ] = NULL; // boolean types can't have auto_increment

		//set precision
		$schema[ self::COLUMN_DEFINITION_PRECISION ] = NULL; // only applicable for numeric types

		//set scale
		$schema[ self::COLUMN_DEFINITION_SCALE ] = NULL; // only applicable for numeric types

		return $schema;
	}

	public static function generateSchemaForTypeSet( array $fieldConf ) {
		$schema = array();

		if ( empty( $fieldConf[ 'values' ] ) ) {
			throw new InvalidArgumentException( 'Column of type SET must have values set' );
		}

		$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_SET;

		$schema[ self::COLUMN_DEFINITION_VALUES ] = $fieldConf[ 'values' ];

		$schema[ self::COLUMN_DEFINITION_DEFAULT ] = NULL;

		//set nullable
		$schema[ self::COLUMN_DEFINITION_IS_NULLABLE ] = ( !empty( $fieldConf[ 'nullable' ] ) ? 'YES' : 'NO' );

		//set character set
		$schema[ self::COLUMN_DEFINITION_CHARSET ] = ( !empty( $fieldConf[ 'charset' ] ) ? $fieldConf[ 'charset' ] : 'utf8' );

		//set collation
		$schema[ self::COLUMN_DEFINITION_COLLATION ] = ( !empty( $fieldConf[ 'collation' ] ) ? $fieldConf[ 'collation' ] : 'utf8_general_ci' );

		$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = max( array_map( 'strlen', $fieldConf[ 'values' ] ) );

		//set auto increment
		$schema[ self::COLUMN_DEFINITION_EXTRA ] = NULL; // boolean types can't have auto_increment

		//set precision
		$schema[ self::COLUMN_DEFINITION_PRECISION ] = NULL; // only applicable for numeric types

		//set scale
		$schema[ self::COLUMN_DEFINITION_SCALE ] = NULL; // only applicable for numeric types

		return $schema;
	}

	public static function generateSchemaForTypeDateTime( array $fieldConf ) {
		$schema = array();

		switch ( $fieldConf[ 'dataType' ] ) {
			case 'DTPubStartDateTime':
			case 'DTPubEndDateTime':
			case 'DTDateTime':
			case 'DTMTime':
			case 'DTCTime':
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_DATETIME;
				break;
			case 'DTDate':
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_DATE;
				break;
			case 'DTTime':
				$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_TIME;
				break;
		}

		$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = NULL;

		//set default
		$schema[ self::COLUMN_DEFINITION_DEFAULT ] = NULL;

		//set nullable
		$schema[ self::COLUMN_DEFINITION_IS_NULLABLE ] = ( !empty( $fieldConf[ 'nullable' ] ) ? 'YES' : 'NO' );

		//set character set
		$schema[ self::COLUMN_DEFINITION_CHARSET ] = NULL; // 'utf8';

		//set collation
		$schema[ self::COLUMN_DEFINITION_COLLATION ] = NULL; // 'utf8_general_ci';

		//set auto increment
		$schema[ self::COLUMN_DEFINITION_EXTRA ] = NULL; // string types can't have auto_increment

		//set precision
		$schema[ self::COLUMN_DEFINITION_PRECISION ] = NULL; // only applicable for numeric types

		//set scale
		$schema[ self::COLUMN_DEFINITION_SCALE ] = NULL; // only applicable for numeric types

		return $schema;
	}

	public static function generateSchemaForTypeBoolean( array $fieldConf ) {
		$schema = array();

		$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_TINYINT; // till mysql natively supports boolean

		$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = NULL;

		//set default
		$schema[ self::COLUMN_DEFINITION_DEFAULT ] = $fieldConf[ 'default' ] === NULL ? NULL : ( $fieldConf[ 'default' ] ? '1' : '0' ); // till mysql natively supports boolean

		//set nullable
		$schema[ self::COLUMN_DEFINITION_IS_NULLABLE ] = ( !empty( $fieldConf[ 'nullable' ] ) ? 'YES' : 'NO' );

		//set character set
		$schema[ self::COLUMN_DEFINITION_CHARSET ] = NULL; // 'utf8';

		//set collation
		$schema[ self::COLUMN_DEFINITION_COLLATION ] = NULL; // 'utf8_general_ci';

		//set auto increment
		$schema[ self::COLUMN_DEFINITION_EXTRA ] = NULL; // boolean types can't have auto_increment

		//set precision
		$schema[ self::COLUMN_DEFINITION_PRECISION ] = NULL; // only applicable for numeric types

		//set scale
		$schema[ self::COLUMN_DEFINITION_SCALE ] = NULL; // only applicable for numeric types

		return $schema;
	}

	/**
	 * generates column schema for integer type columns
	 *
	 * uses the maxLen variable to determine the exact type (bigint, int,smallint,tinyint, etc.) and sets default values for unspecified definitions
	 * not that the precision definition is for number of digits, not maximum value
	 *
	 * @static
	 *
	 * @param array $fieldConf
	 *
	 * @return array $columnSchema
	 */
	public static function generateSchemaForTypeNumeric( array $fieldConf ) {

		$schema = array();

		static $bitWidths = array(
			'8' => self::COLUMN_TYPE_TINYINT,
			'16' => self::COLUMN_TYPE_SMALLINT,
			'24' => self::COLUMN_TYPE_MEDIUMINT,
			'32' => self::COLUMN_TYPE_INT,
			'64' => self::COLUMN_TYPE_BIGINT
		);

		foreach ( $bitWidths as $bits => $columnType ) {
			if ( $fieldConf[ 'bitWidth' ] <= $bits ) {
				$schema[ self::COLUMN_DEFINITION_TYPE ] = $columnType;
				break;
			}
		}

		$schema[ self::COLUMN_DEFINITION_DATATYPE ] = $fieldConf[ 'dataType' ]; // save datatype so we can use is_a later for update check

		$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = NULL; // only applicable for string types

		//set default
		$schema[ self::COLUMN_DEFINITION_DEFAULT ] = ( !empty( $fieldConf[ 'autoInc' ] ) ? NULL : $fieldConf[ 'default' ] ); // auto_increment columns can't have a default value

		//set nullable
		$schema[ self::COLUMN_DEFINITION_IS_NULLABLE ] = ( !empty( $fieldConf[ 'nullable' ] ) ? 'YES' : 'NO' );

		//set character set
		$schema[ self::COLUMN_DEFINITION_CHARSET ] = ( !empty( $fieldConf[ 'charset' ] ) ? $fieldConf[ 'charset' ] : NULL ); //TODO: probably should always set to NULL

		//set collation
		$schema[ self::COLUMN_DEFINITION_COLLATION ] = NULL; // only applicable for string types

		//set auto increment
		$schema[ self::COLUMN_DEFINITION_EXTRA ] = ( !empty( $fieldConf[ 'autoInc' ] ) ? 'auto_increment' : NULL );

		//set scale
		$schema[ self::COLUMN_DEFINITION_SCALE ] = 0; // not used as float/double is a different datatype

		return $schema;
	}

	/**
	 * generates column schema for float type columns
	 *
	 * @param array $fieldConf
	 *
	 * @return array $columnSchema
	 */
	public static function generateSchemaForTypeFloat( array $fieldConf ) {
		$schema = array();

		$schema[ self::COLUMN_DEFINITION_TYPE ] = self::COLUMN_TYPE_FLOAT;

		$schema[ self::COLUMN_DEFINITION_DATATYPE ] = $fieldConf[ 'dataType' ]; // save datatype so we can use is_a later for update check

		$schema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ] = NULL; // only applicable for string types

		// set default
		$schema[ self::COLUMN_DEFINITION_DEFAULT ] = $fieldConf[ 'default' ] === NULL ? NULL : (float)$fieldConf[ 'default' ];

		// set nullable
		$schema[ self::COLUMN_DEFINITION_IS_NULLABLE ] = ( !empty( $fieldConf[ 'nullable' ] ) ? 'YES' : 'NO' );

		// set character set
		$schema[ self::COLUMN_DEFINITION_CHARSET ] = NULL;

		// set collation
		$schema[ self::COLUMN_DEFINITION_COLLATION ] = NULL; // only applicable for string types

		// set auto increment
		$schema[ self::COLUMN_DEFINITION_EXTRA ] = NULL;

		// set scale
		$schema[ self::COLUMN_DEFINITION_SCALE ] = NULL; // not used

		// set precision
		$schema[ self::COLUMN_DEFINITION_PRECISION ] = 12; // default value

		return $schema;
	}

	/**
	 * gets the (lowercase) value of a definition
	 *
	 * @param $key name of definition (use the class constants!)
	 *
	 * @return mixed
	 */
	public function getValue( $key ) {
		return $this->columnSchema[ $key ];
	}

	/**
	 * compares each own definition with that of the specified $other column
	 *
	 * @param IDBColumnDefinition $other
	 *
	 * @return array $definitionResult
	 */
	public function compare( IDBColumnDefinition $other ) {
		$definitionResult = array();

		$colEquals = true;

		foreach ( self::$columnDefinitions as $definition ) {

			$equals = ( $this->columnSchema[ $definition ] === $other->getValue( $definition ) );

			if ( !$equals ) {
				$colEquals = false;
			}
		}

		if ( !$colEquals ) {
			$definitionResult = $this->determineCanUpdate( $other );
		}

		$definitionResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] = $colEquals;

		return $definitionResult;
	}

	/**
	 * splits the string generation into several methods by datatype
	 *
	 * @return string
	 */
	public function getCreate() {
		switch ( $this->columnSchema[ self::COLUMN_DEFINITION_TYPE ] ) {
			case self::COLUMN_TYPE_BIGINT:
			case self::COLUMN_TYPE_INT:
			case self::COLUMN_TYPE_MEDIUMINT:
			case self::COLUMN_TYPE_SMALLINT:
			case self::COLUMN_TYPE_TINYINT:
				$result = $this->createNumeric();
				break;
			case self::COLUMN_TYPE_CHAR:
			case self::COLUMN_TYPE_VARCHAR:
			case self::COLUMN_TYPE_TEXT:
			case self::COLUMN_TYPE_MEDIUMTEXT:
			case self::COLUMN_TYPE_LONGTEXT:
				$result = $this->createString();
				break;
			case self::COLUMN_TYPE_TIME:
			case self::COLUMN_TYPE_DATETIME:
			case self::COLUMN_TYPE_DATE:
				$result = $this->createDate();
				break;
			case self::COLUMN_TYPE_BOOLEAN:
				$result = $this->createBoolean();
				break;
			case self::COLUMN_TYPE_ENUM:
				$result = $this->createEnum();
				break;
			case self::COLUMN_TYPE_SET:
				$result = $this->createSet();
				break;
			case self::COLUMN_TYPE_FLOAT:
				$result = $this->createFloat();
				break;
			default:
				//TODO
				$result = NULL;
				break;
		}

		return $result;
	}

	/**
	 * generates the string used in CREATE TABLE for string type columns
	 *
	 * @return string
	 */
	protected function createString() {
		switch ( $this->columnSchema[ self::COLUMN_DEFINITION_TYPE ] ) {
			case self::COLUMN_TYPE_CHAR:
			case self::COLUMN_TYPE_VARCHAR:
				$maxLen = $this->columnSchema[ self::COLUMN_DEFINITION_CHAR_MAX_LEN ];
				break;
			case self::COLUMN_TYPE_TEXT:
			case self::COLUMN_TYPE_MEDIUMTEXT:
			case self::COLUMN_TYPE_LONGTEXT:
				$maxLen = NULL;
				break;
		}

		$string =
				$this->columnSchema[ self::COLUMN_DEFINITION_TYPE ]
				. ( !empty( $maxLen ) ? '(' . $maxLen . ')' : '' )
				. ( isset( $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] ) && $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] !== NULL
						? ' DEFAULT "' . $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] . '"'
						: '' )
				. ( isset( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] )
						? ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? ' NOT NULL' : '' )
						: '' );

		return $string;
	}

	/**
	 * generates the string used in CREATE TABLE for numeric columns
	 *
	 * @return string
	 */
	protected function createNumeric() {
		$string =
				$this->columnSchema[ self::COLUMN_DEFINITION_TYPE ]
				. ( $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] !== NULL ? ' DEFAULT "' . $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] . '"' : '' )
				. ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? ' NOT NULL' : '' )
				. ( !empty( $this->columnSchema[ self::COLUMN_DEFINITION_EXTRA ] ) ? ' auto_increment' : '' );

		return $string;
	}

	protected function createFloat() {
		$string =
				$this->columnSchema[ self::COLUMN_DEFINITION_TYPE ]
				. ( $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] !== NULL ? ' DEFAULT "' . $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] . '"' : '' )
				. ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? ' NOT NULL' : '' );

		return $string;
	}

	protected function createDate() {
		$string =
				$this->columnSchema[ self::COLUMN_DEFINITION_TYPE ]
				. ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? '' : ' DEFAULT NULL' )
				. ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? ' NOT NULL' : ' NULL' );

		return $string;
	}

	protected function createBoolean() {
		$string =
				$this->columnSchema[ self::COLUMN_DEFINITION_TYPE ]
				. ( $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] !== NULL ? ' DEFAULT ' . ( $this->columnSchema[ self::COLUMN_DEFINITION_DEFAULT ] ? 'TRUE' : 'FALSE' ) : '' )
				. ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? ' NOT NULL' : '' );

		return $string;
	}

	protected function createEnum() {
		$string =
				$this->columnSchema[ self::COLUMN_DEFINITION_TYPE ]
				. '("' . implode( '","', $this->columnSchema[ self::COLUMN_DEFINITION_VALUES ] ) . '")'
				. ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? ' NOT NULL' : '' );

		return $string;
	}

	protected function createSet() {
		$string =
				$this->columnSchema[ self::COLUMN_DEFINITION_TYPE ]
				. '("' . implode( '","', $this->columnSchema[ self::COLUMN_DEFINITION_VALUES ] ) . '")'
				. ( strtolower( $this->columnSchema[ self::COLUMN_DEFINITION_IS_NULLABLE ] ) == 'no' ? ' NOT NULL' : '' );

		return $string;
	}

	/**
	 * splits the determination into several methods by column type
	 *
	 * @param IDBColumnDefinition $other
	 *
	 * @return array
	 */
	protected function determineCanUpdate( IDBColumnDefinition $other ) {

		$result = array();

		switch ( $this->columnSchema[ self::COLUMN_DEFINITION_TYPE ] ) {
			case self::COLUMN_TYPE_BIGINT:
			case self::COLUMN_TYPE_INT:
			case self::COLUMN_TYPE_MEDIUMINT:
			case self::COLUMN_TYPE_SMALLINT:
			case self::COLUMN_TYPE_TINYINT:
			case self::COLUMN_TYPE_FLOAT:
				$result = $this->compareNumeric( $other );
				break;
			case self::COLUMN_TYPE_CHAR:
			case self::COLUMN_TYPE_VARCHAR:
			case self::COLUMN_TYPE_TEXT:
			case self::COLUMN_TYPE_MEDIUMTEXT:
			case self::COLUMN_TYPE_LONGTEXT:
				$result = $this->compareString( $other );
				break;
			case self::COLUMN_TYPE_TIME:
			case self::COLUMN_TYPE_DATETIME:
			case self::COLUMN_TYPE_DATE:
				$result = $this->compareDate( $other );
				break;
			case self::COLUMN_TYPE_BOOLEAN:
				$result = $this->compareBoolean( $other );
				break;
			case self::COLUMN_TYPE_ENUM:
			case self::COLUMN_TYPE_SET:
				$result = $this->compareEnum( $other );
				break;
			default:

				break;
		}

		return $result;
	}

	/**
	 * compares numeric column types
	 *
	 * @param IDBColumnDefinition $other
	 *
	 * @return array
	 */
	protected function compareNumeric( IDBColumnDefinition $other ) {

		$definitionResults = array();
		$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_TRUE;

		foreach ( self::$columnDefinitions as $definition ) {

			$ownValue = $this->columnSchema[ $definition ];
			$otherValue = $other->getValue( $definition );


			$canUpdateDefinition = DBTableDefinition::SAFE_UPDATE_UNKNOWN; //TODO: for each definition, check if there's any other definition that might prohibit a safe update (e.g. auto_increment only works on primary key)

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_UNKNOWN && $canUpdateColumn == DBTableDefinition::SAFE_UPDATE_TRUE ) { // we don't want to revert from "false" to "unknown"
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_UNKNOWN;
			}

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_FALSE ) {
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_FALSE;
			}

			$definitionResults[ $definition ] = array(
				DBTableDefinition::RESULT_EQUALS => $ownValue === $otherValue,
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateDefinition,
				DBTableDefinition::RESULT_EXPECTED => $ownValue,
				DBTableDefinition::RESULT_ACTUAL => $otherValue
			);
		}

		return array(
			DBTableDefinition::RESULT_SUMMARY => array(
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateColumn
			),
			DBTableDefinition::PROPERTIES => $definitionResults
		);
	}

	/**
	 * compares string type columns
	 *
	 * @param IDBColumnDefinition $other
	 *
	 * @return array
	 */
	protected function compareString( $other ) {
		$definitionResults = array();
		$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_TRUE;

		foreach ( self::$columnDefinitions as $definition ) {
			$ownValue = $this->columnSchema[ $definition ];
			$otherValue = $other->getValue( $definition );

			$canUpdateDefinition = DBTableDefinition::SAFE_UPDATE_UNKNOWN; //TODO: for each definition, check if there's any other definition that might prohibit a safe update (e.g. auto_increment only works on primary key)

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_UNKNOWN && $canUpdateColumn == DBTableDefinition::SAFE_UPDATE_TRUE ) { // we don't want to revert from "false" to "unknown"
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_UNKNOWN;
			}

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_FALSE ) {
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_FALSE;
			}

			$definitionResults[ $definition ] = array(
				DBTableDefinition::RESULT_EQUALS => $ownValue === $otherValue,
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateDefinition,
				DBTableDefinition::RESULT_EXPECTED => $ownValue,
				DBTableDefinition::RESULT_ACTUAL => $otherValue
			);
		}

		return array(
			DBTableDefinition::RESULT_SUMMARY => array(
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateColumn
			),
			DBTableDefinition::PROPERTIES => $definitionResults
		);
	}

	/**
	 * compares date columns
	 *
	 * @param IDBColumnDefinition $other
	 *
	 * @return array
	 */
	protected function compareDate( $other ) {
		$definitionResults = array();
		$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_TRUE;

		foreach ( self::$columnDefinitions as $definition ) {
			$ownValue = $this->columnSchema[ $definition ];
			$otherValue = $other->getValue( $definition );

			$canUpdateDefinition = DBTableDefinition::SAFE_UPDATE_UNKNOWN; //TODO: for each definition, check if there's any other definition that might prohibit a safe update (e.g. auto_increment only works on primary key)

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_UNKNOWN && $canUpdateColumn == DBTableDefinition::SAFE_UPDATE_TRUE ) { // we don't want to revert from "false" to "unknown"
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_UNKNOWN;
			}

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_FALSE ) {
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_FALSE;
			}

			$definitionResults[ $definition ] = array(
				DBTableDefinition::RESULT_EQUALS => $ownValue === $otherValue,
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateDefinition,
				DBTableDefinition::RESULT_EXPECTED => $ownValue,
				DBTableDefinition::RESULT_ACTUAL => $otherValue
			);
		}

		return array(
			DBTableDefinition::RESULT_SUMMARY => array(
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateColumn
			),
			DBTableDefinition::PROPERTIES => $definitionResults
		);
	}

	/**
	 * compares boolean column types
	 *
	 * @param IDBColumnDefinition $other
	 *
	 * @return array
	 */
	protected function compareBoolean( IDBColumnDefinition $other ) {

		$definitionResults = array();
		$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_TRUE;

		foreach ( self::$columnDefinitions as $definition ) {
			$ownValue = $this->columnSchema[ $definition ];
			$otherValue = $other->getValue( $definition );


			$canUpdateDefinition = DBTableDefinition::SAFE_UPDATE_UNKNOWN; //TODO: for each definition, check if there's any other definition that might prohibit a safe update (e.g. auto_increment only works on primary key)

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_UNKNOWN && $canUpdateColumn == DBTableDefinition::SAFE_UPDATE_TRUE ) { // we don't want to revert from "false" to "unknown"
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_UNKNOWN;
			}

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_FALSE ) {
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_FALSE;
			}

			$definitionResults[ $definition ] = array(
				DBTableDefinition::RESULT_EQUALS => $ownValue === $otherValue,
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateDefinition,
				DBTableDefinition::RESULT_EXPECTED => $ownValue,
				DBTableDefinition::RESULT_ACTUAL => $otherValue
			);
		}

		return array(
			DBTableDefinition::RESULT_SUMMARY => array(
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateColumn
			),
			DBTableDefinition::PROPERTIES => $definitionResults
		);
	}

	protected function compareEnum( IDBColumnDefinition $other ) {

		$definitionResults = array();
		$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_TRUE;

		foreach ( self::$columnDefinitions as $definition ) {
			$ownValue = $this->columnSchema[ $definition ];
			$otherValue = $other->getValue( $definition );


			$canUpdateDefinition = DBTableDefinition::SAFE_UPDATE_UNKNOWN; //TODO: for each definition, check if there's any other definition that might prohibit a safe update (e.g. auto_increment only works on primary key)

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_UNKNOWN && $canUpdateColumn == DBTableDefinition::SAFE_UPDATE_TRUE ) { // we don't want to revert from "false" to "unknown"
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_UNKNOWN;
			}

			if ( $canUpdateDefinition === DBTableDefinition::SAFE_UPDATE_FALSE ) {
				$canUpdateColumn = DBTableDefinition::SAFE_UPDATE_FALSE;
			}

			$definitionResults[ $definition ] = array(
				DBTableDefinition::RESULT_EQUALS => $ownValue === $otherValue,
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateDefinition,
				DBTableDefinition::RESULT_EXPECTED => $ownValue,
				DBTableDefinition::RESULT_ACTUAL => $otherValue
			);
		}

		return array(
			DBTableDefinition::RESULT_SUMMARY => array(
				DBTableDefinition::RESULT_CAN_UPDATE => $canUpdateColumn
			),
			DBTableDefinition::PROPERTIES => $definitionResults
		);
	}
}

?>
