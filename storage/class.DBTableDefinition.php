<?php
/**
 * @package steroid\db
 */

require_once STROOT . '/storage/interface.IDBTableDefinition.php';
require_once STROOT . '/storage/class.DBColumnDefinition.php';
require_once STROOT . '/storage/class.DBKeyDefinition.php';

/**
 * class used to store, generate and compare table definitions
 *
 * @package steroid\db
 */
class DBTableDefinition implements IDBTableDefinition {

	const PROPERTY_ENGINE = 'ENGINE';
	const PROPERTY_COLLATION = 'TABLE_COLLATION';
	const PROPERTY_CHARSET = 'CHARSET';

	const RESULT = 'result';
	const RESULT_NOT_EXISTS = 'notExists';
	const RESULT_EQUALS = 'isEqual';
	const RESULT_CAN_UPDATE = 'canUpdate';
	const RESULT_EXPECTED = 'expected';
	const RESULT_ACTUAL = 'actual';
	const RESULT_SUMMARY = 'summary';

	const SAFE_UPDATE_TRUE = 1;
	const SAFE_UPDATE_FALSE = 0;
	const SAFE_UPDATE_UNKNOWN = -1;

	const PROPERTIES = 'properties';
	const KEYS = 'keys';
	const COLUMNS = 'columns';

	/**
	 * @var IStorage
	 */
	protected $storage;

	/**
	 * @var
	 */
	protected $recordClassName;

	/**
	 * @var
	 */
	protected $table;

	protected $definitions = array();
	protected $tableColumns = array(); // this is needed for dropping columns

	/**
	 * indices
	 *
	 * @var array
	 */
	protected $keys = array();

	/**
	 * primary keys
	 *
	 * @var array
	 */
	protected $primaryKeys = array();

	/**
	 * @var bool
	 */
	protected $forceUnsafe = false;

	protected $dropColumns = false;

	/**
	 * sets $this->storage
	 *
	 * @param IDB $DB
	 */
	public function __construct(IStorage $storage){
		$this->storage = $storage;
	}

	/**
	 * gets table and column schema from DB and instantiates an IDBColumnDefinition for each column
	 *
	 * @param $table
	 *
	 * @throws InvalidArgumentException
	 */
	public function loadDefinitionFromTable($table){
		if ( empty( $table ) ) {
			throw new InvalidArgumentException( "\$table must be set" );
		}
		$this->table = $table;

		$this->definitions[self::PROPERTIES] = $this->storage->getTableSchema($table);

		if(empty( $this->definitions[ self::PROPERTIES ])){
			throw new TableDoesNotExistException('Table "' . $this->table . '" does not exist');
		}

		$columnSchemas = $this->storage->getColumnSchemaForTable($table);

		foreach($columnSchemas as $columnSchema){
			$columnDefinition = new DBColumnDefinition();
			$columnDefinition->setDefinitionFromTable($columnSchema);
			$this->definitions[self::COLUMNS][$columnDefinition->getValue( DBColumnDefinition::COLUMN_DEFINITION_NAME )] = $columnDefinition;
		}

		$keySchema = $this->storage->getTableKeys($table);

		$keyDefinition = new DBKeyDefinition();
		$this->definitions[self::KEYS] = $keyDefinition->createDefinitionFromTable( $keySchema );
	}

	/**
	 * gets the table and column definitions from the recordClass and instantiates an IDBColumnDefinition for each column
	 *
	 * @param $recordClassName
	 *
	 * @throws InvalidArgumentException
	 */
	public function loadDefinitionFromRecordClass( $recordClassName ){
		if ( empty( $recordClassName ) ) {
			throw new InvalidArgumentException( "\$recordClassName must be set" );
		}

		if (!ClassFinder::find(array($recordClassName), true)) {
			throw new InvalidArgumentException( 'RecordClass "' . $recordClassName . '" does not exist.');
		}
		
		$this->recordClassName = $recordClassName;
		$this->definitions[self::PROPERTIES][self::PROPERTY_CHARSET] = $this->storage->getTableCharset($recordClassName::getTableCharset());
		$this->definitions[ self::PROPERTIES ][ self::PROPERTY_COLLATION ] = $this->storage->getTableCollation( $recordClassName::getTableCollation() );
		$this->definitions[ self::PROPERTIES ][ self::PROPERTY_ENGINE ] = $this->storage->getTableEngine( $recordClassName::getTableEngine() );
		$table = $recordClassName::getTableName();

		$fieldDefinitions = $recordClassName::getOwnFieldDefinitions();

		foreach($fieldDefinitions as $fieldName => $fieldDefinition){
			$columnDefinition = new DBColumnDefinition();
			$columnDefinition->setDefinitionFromRecord($fieldName, $fieldDefinition);
			$this->definitions[self::COLUMNS][$recordClassName::getColumnName( $fieldName )] = $columnDefinition;
		}

		$keyDefinitions = $recordClassName::getAllKeys();

		foreach($keyDefinitions as $keyName => $keyDef){
			$keyDefinition = new DBKeyDefinition();
			$keyDefinition->setDefinitionFromRecord( $recordClassName, $keyName, $keyDef);
			$this->definitions[self::KEYS][ strtoupper($keyName) ] = $keyDefinition;
		}

		$columnSchemas = $this->storage->getColumnSchemaForTable( $recordClassName::getTableName() );

		foreach ( $columnSchemas as $columnSchema ) {
			$columnDefinition = new DBColumnDefinition();
			$columnDefinition->setDefinitionFromTable( $columnSchema );
			$this->tableColumns[ $columnDefinition->getValue( DBColumnDefinition::COLUMN_DEFINITION_NAME ) ] = $columnDefinition;
		}
	}

	/**
	 * splits comparison into two methods for table property and table column comparisons
	 *
	 * @param IDBTableDefinition $other
	 * @param array              $columnNames
	 *
	 * @return array $tableComparison
	 */
	public function compareWithTable( IDBTableDefinition $other, array $columnNames ){

		$propertyResults = $this->compareTableProperties( $other );
		$columnResults = $this->compareTableColumns($other, $columnNames);
		$keyResults = $this->compareTableKeys($other);

		$equals = true;
		$canUpdate = self::SAFE_UPDATE_TRUE;

		if( $propertyResults[ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ] !== true || $columnResults[ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ] !== true || $keyResults[ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ] !== true){
			$equals = false;
		}

		if( $propertyResults[ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] === self::SAFE_UPDATE_UNKNOWN || $columnResults[ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] === self::SAFE_UPDATE_UNKNOWN || $keyResults[ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] === self::SAFE_UPDATE_UNKNOWN){
			$canUpdate = self::SAFE_UPDATE_UNKNOWN;
		}

		if ( $propertyResults[ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] === self::SAFE_UPDATE_FALSE || $columnResults[ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] === self::SAFE_UPDATE_FALSE || $keyResults[ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] === self::SAFE_UPDATE_FALSE ) {
			$canUpdate = self::SAFE_UPDATE_FALSE;
		}

		return array(
			self::RESULT_SUMMARY => array(
				self::RESULT_EQUALS => $equals,
				self::RESULT_CAN_UPDATE => $canUpdate
			),
			self::PROPERTIES => $propertyResults,
			self::COLUMNS => $columnResults,
			self::KEYS => $keyResults
		);
	}

	/**
	 * determines whether the table needs to be altered or created and calls the corresponding methods
	 *
	 * @param       $forceUnsafe
	 * @param array $tableComparison
	 *
	 * @return bool true on success, false on failure
	 */
	public function update($forceUnsafe, $dropColumns, $dropKeys, array $tableComparisons){
		$this->forceUnsafe = $forceUnsafe;
		$this->dropColumns = $dropColumns;
		$this->dropKeys = $dropKeys;

		if (isset($tableComparisons[self::RESULT_SUMMARY])) {
			$tableComparison = $tableComparisons;
		} else {
			$tableComparison = $tableComparisons[ self::RESULT_EXPECTED ];
		}

		if ($tableComparison[self::RESULT_SUMMARY] == self::RESULT_NOT_EXISTS) { // table does not exist
			return $this->createTable();
		}

		if ($tableComparison[self::RESULT_SUMMARY][self::RESULT_EQUALS] === true && $tableComparisons[self::RESULT_ACTUAL][ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ] === true) {
			return true;
		}

		$modifiedProperties = array();
		$modifiedColumns = array();
		$newColumns = array();
		$modifiedKeys = array();
		$newKeys = array();
		$dropColumns = array();
		$dropKeys = array();

		foreach($tableComparison[self::PROPERTIES][ self::PROPERTIES ] as $key => $propertyResult) {
			if(!$propertyResult[self::RESULT_SUMMARY][self::RESULT_EQUALS] && ($propertyResult[self::RESULT_SUMMARY][self::RESULT_CAN_UPDATE] === self::SAFE_UPDATE_TRUE || $this->forceUnsafe)) {
				$modifiedProperties[$key] = $propertyResult[self::RESULT_SUMMARY][self::RESULT_EXPECTED];
			}
		}

		foreach($tableComparison[self::COLUMNS][self::PROPERTIES] as $key => $columnResult) {
			if($columnResult[self::RESULT_SUMMARY][self::RESULT_EQUALS] === self::RESULT_NOT_EXISTS) {
				$newColumns[$key] = $this->definitions[self::COLUMNS][$key];
			} else {
				if ( !$columnResult[ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ] && ( $columnResult[ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] === true || $this->forceUnsafe ) ) {
					$modifiedColumns[ $key ] = $this->definitions[self::COLUMNS][ $key ];
				}
			}
		}

		if ( $tableComparison[ self::KEYS ][self::RESULT_SUMMARY][self::RESULT_EQUALS] !== true && ($tableComparison[self::KEYS][self::RESULT_SUMMARY][self::RESULT_CAN_UPDATE] === self::SAFE_UPDATE_TRUE || $this->forceUnsafe)){
			foreach($tableComparison[self::KEYS][self::PROPERTIES] as $keyName => $keyResult) {
				if($keyResult == false) {
					$newKeys[$keyName] = $this->definitions[self::KEYS][$keyName];
				} else {
					if(!$keyResult[self::RESULT_SUMMARY][self::RESULT_EQUALS]){
						$modifiedKeys[$keyName] = $this->definitions[self::KEYS][$keyName];
					}
				}
			}
		}

		if($this->dropKeys && $tableComparisons[ self::RESULT_ACTUAL ][ self::KEYS ][ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ] !== true){
			foreach ( $tableComparisons[ self::RESULT_ACTUAL ][ self::KEYS ][ self::PROPERTIES ] as $keyName => $keyResult ) {
				if ( $keyResult == false ) {
					$dropKeys[] = $keyName;
				}
			}
		}

		if ($this->dropColumns && isset( $tableComparisons[ self::RESULT_ACTUAL ])) {
			foreach ( $tableComparisons[ self::RESULT_ACTUAL ][ self::COLUMNS ][ self::PROPERTIES ] as $key => $columnResult ) {
				if ( $columnResult[ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ] === self::RESULT_NOT_EXISTS ) {
					$dropColumns[ $key ] = $this->tableColumns[ $key ];
				}
			}
		}

		$recordClass = $this->recordClassName;

		if (!empty($modifiedColumns) || !empty($modifiedKeys) || !empty($modifiedProperties) || !empty($newColumns) || !empty($newKeys) || !empty( $dropKeys ) || !empty($dropColumns)) {
			$this->storage->alterTable( $recordClass::getTableName(), $modifiedColumns, $newColumns, $dropColumns, $modifiedKeys, $newKeys, $dropKeys, ( isset( $modifiedProperties[ self::PROPERTY_ENGINE ] ) ? $modifiedProperties[ self::PROPERTY_ENGINE ] : '' ), ( isset( $modifiedProperties[ self::PROPERTY_CHARSET ] ) ? $modifiedProperties[ self::PROPERTY_CHARSET ] : '' ) );
		} else {
			echo "No update performed \n";
		}
	}

	/**
	 * gets the strings for each column from the IDBColumnDefinition classes and calls the DB->createTable method
	 */
	public function createTable( $isTemporary = false ){

		$recordClassName = $this->recordClassName;

		echo "Creating table for " . CLIHandler::COLOR_CLASSNAME . $recordClassName . CLIHandler::COLOR_DEFAULT . ".\n";

		$this->storage->createTable($isTemporary, $recordClassName::getTableName(), $this->definitions[self::COLUMNS], $this->definitions[self::KEYS], $this->definitions[self::PROPERTIES][ self::PROPERTY_ENGINE ], $this->definitions[self::PROPERTIES][ self::PROPERTY_CHARSET ]);

		//TODO: return value?
	}

	/**
	 * splits the property comparison into several methods by property
	 *
	 * @param IDBTableDefinition $other
	 *
	 * @return array
	 */
	protected function compareTableProperties( IDBTableDefinition $other ){

		$equals = true;
		$result = array();

		$result[ self::PROPERTY_ENGINE ] = $this->compareEngine( $other->getTableDefinitionValue( self::PROPERTY_ENGINE ) );
		$result[ self::PROPERTY_COLLATION ] = $this->compareCollation( $other->getTableDefinitionValue( self::PROPERTY_COLLATION ) );

		if( !$result[ self::PROPERTY_ENGINE ][self::RESULT_SUMMARY][self::RESULT_EQUALS] || !$result[ self::PROPERTY_COLLATION ][ self::RESULT_SUMMARY ][ self::RESULT_EQUALS ]){
			$equals = false;
		}

		return array(
			self::RESULT_SUMMARY => array(
				self::RESULT_EQUALS => $equals,
				self::RESULT_CAN_UPDATE => self::SAFE_UPDATE_UNKNOWN
			),
			self::PROPERTIES => $result
		);
	}

	public function getKeyDefinition($keyName){
		if ( empty( $keyName)) {
			throw new InvalidArgumentException( "\$keyName must be set" );
		}

		return isset($this->definitions[self::KEYS][$keyName]) ? $this->definitions[self::KEYS][ $keyName ] : false;
	}

	protected function compareTableKeys(IDBTableDefinition $other){

		$hasPrimary = false;

		$equals = true;

		$results = array();

		foreach($this->definitions[self::KEYS] as $keyName => $keyDefinition){
			if($keyName == 'PRIMARY'){
				$hasPrimary = true;
			}

			$otherKeyDef = $other->getKeyDefinition( $keyName );

			if(!$otherKeyDef){
				$equals = false;
				$results[ $keyName ] = false;
				continue;
			}

			$keyEquals = $keyDefinition->compare( $otherKeyDef );

			if($keyEquals[self::RESULT_SUMMARY][self::RESULT_EQUALS] !== true){
				$equals = false;
			}

			$results[$keyName] = $keyEquals;
		}

		return array(
			self::RESULT_SUMMARY => array(
				self::RESULT_EQUALS => $equals,
				self::RESULT_CAN_UPDATE => $hasPrimary
			),
			self::PROPERTIES => $results
		);
	}

	/**
	 * returns own IDBColumnDefinition by column name or false if not found
	 *
	 * @param $columnName
	 *
	 * @return mixed IDBColumnDefinition or false if not found
	 * @throws ColumnDefinitionNotFoundException
	 */
	public function getColumnDefinition($columnName){
		foreach($this->definitions[self::COLUMNS] as $key => $columnDefinition){
			if($columnDefinition->getValue( DBColumnDefinition::COLUMN_DEFINITION_NAME ) == $columnName){
				$foundColumnDefinition = $columnDefinition;
			}
		}

		if(empty($foundColumnDefinition)){
			throw new ColumnDefinitionNotFoundException('Column definition for column with name "' . $columnName . '" not found in table "' . (( $recordClassName = $this->recordClassName) ? $recordClassName::getTableName() : $this->table) . '"');
		}

		return $foundColumnDefinition;
	}

	/**
	 * calls compare on each own IDBColumnDefinition with the respective IDBColumnDefinition of the $other IDBTableDefinition
	 *
	 * @param IDBTableDefinition $other
	 * @param $columnNames
	 *
	 * @return array
	 */
	protected function compareTableColumns(IDBTableDefinition $other, $columnNames){
		$equals = true;
		$canUpdate = true;

		$result = array();

		foreach($this->definitions[self::COLUMNS] as $key => $columnDefinition){
			if(empty($columnNames) || in_array( $key, $columnNames)){
				try {
					$result[ $key ] = $columnDefinition->compare( $other->getColumnDefinition( $columnDefinition->getValue( DBColumnDefinition::COLUMN_DEFINITION_NAME ) ) );
				} catch ( ColumnDefinitionNotFoundException $e ) {
					$result[ $key ] = array(
						self::RESULT_SUMMARY => array(
							self::RESULT_EQUALS => self::RESULT_NOT_EXISTS,
							self::RESULT_CAN_UPDATE => self::SAFE_UPDATE_TRUE
						)
					);
				}

				if($result[$key][self::RESULT_SUMMARY][self::RESULT_EQUALS] !== true) {
					$equals = false;

					if (!isset( $result[ $key ][ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ]) || ($result[ $key ][ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] == self::SAFE_UPDATE_UNKNOWN) ) {
						$canUpdate = self::SAFE_UPDATE_UNKNOWN;
					}

					if ( isset( $result[ $key ][ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] ) && ($result[ $key ][ self::RESULT_SUMMARY ][ self::RESULT_CAN_UPDATE ] == self::SAFE_UPDATE_FALSE) ) {
						$canUpdate = self::SAFE_UPDATE_FALSE;
					}
				}
			}
		}

		return array(
			self::RESULT_SUMMARY => array(
				self::RESULT_EQUALS => $equals,
				self::RESULT_CAN_UPDATE => $canUpdate
			),
			self::PROPERTIES => $result
		);
	}

	/**
	 * returns the value for the specified table property (use class constants!)
	 *
	 * @param $key
	 *
	 * @return mixed
	 */
	public function getTableDefinitionValue( $key ){
		return isset( $this->definitions[self::PROPERTIES][ $key ]) ? $this->definitions[self::PROPERTIES][ $key ] : NULL;
	}

	/**
	 * compares the (mysql) table engine. still TODO
	 *
	 * @param $otherValue
	 *
	 * @return array
	 */
	protected function compareEngine( $otherValue ){

		$equals = false;
		$canUpdate = self::SAFE_UPDATE_UNKNOWN;

		$ownValue = $this->definitions[self::PROPERTIES][self::PROPERTY_ENGINE];

		$equals = $ownValue == $otherValue;

		if(!$equals){
			//TODO
		}

		return array(
			self::RESULT_SUMMARY => array(
				self::RESULT_EQUALS => $equals,
				self::RESULT_CAN_UPDATE => $canUpdate,
				self::RESULT_EXPECTED => $ownValue,
				self::RESULT_ACTUAL => $otherValue
			)
		);
	}

	/**
	 * compares the (mysql) table collation. still TODO
	 *
	 * @param $otherValue
	 *
	 * @return array
	 */
	protected function compareCollation( $otherValue ){
		$equals = NULL;
		$canUpdate = self::SAFE_UPDATE_UNKNOWN;

		$ownValue = $this->definitions[self::PROPERTIES][ self::PROPERTY_COLLATION ];

		$equals = $ownValue == $otherValue;

		if ( !$equals ) {
			//TODO
		}

		return array(
			self::RESULT_SUMMARY => array(
				self::RESULT_EQUALS => $equals,
				self::RESULT_CAN_UPDATE => $canUpdate,
				self::RESULT_EXPECTED => $ownValue,
				self::RESULT_ACTUAL => $otherValue
			)
		);
	}
}

class ColumnDefinitionNotFoundException extends Exception {}