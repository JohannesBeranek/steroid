<?php

/**
 * @package steroid\db
 */

require_once STROOT . '/storage/class.DBTableDefinition.php';
require_once STROOT . '/storage/class.DBColumnDefinition.php';
require_once STROOT . '/storage/class.DBKeyDefinition.php';
require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/clihandler/class.CLIHandler.php';

require_once STROOT . '/util/class.Debug.php';

/**
 * Class that handles table comparison, creation and altering
 * @author codeon_florian
 */

class DBInfo {

	/**
	 * @var IStorage
	 */
	protected $storage;

	/**
	 * Stores the record class files as returned by the ClassFinder
	 *
	 * @var array
	 */
	protected $recordClassesFiles = array();

	/**
	 * stores which record classes and their fields should be compared/updated/created
	 *
	 * @var array
	 */
	protected $recordClassConf = array();

	protected $recordClassNames;

	/**
	 * whether to perform update/create
	 * @var bool
	 */
	protected $update = false;

	/**
	 * whether to perform unsafe updates
	 * @var bool
	 */
	protected $forceUnsafe = false;

	protected $dropColumns = false;

	protected $resetTables = false;

	protected $insertStatic = false;

	protected $dropKeys = false;

	/**
	 * stores the DB, recordClassConf and gets the record class files from ClassFinder
	 *
	 * @param IDB   $DB
	 * @param array $recordClassNames
	 */
	public function __construct( IStorage $storage, array $recordClassNames ){
		$this->storage = $storage;

		$this->recordClassConf = $recordClassNames;

		$this->recordClassNames = $recordClassNames;

	}

	protected function resetTables(){
		$tables = $this->storage->fetchAll('SELECT table_name FROM INFORMATION_SCHEMA.tables WHERE table_schema = ' . $this->storage->escape($this->storage->getDatabase()));

		foreach($tables as $table){
			$table = $table['table_name'];

			$this->storage->dropTable($table);
		}
	}

	/**
	 * creates DBTableDefinition classes for each record and calls compare/update
	 *
	 * update operations use the tableComparison returned by compare() so we only alter what differs
	 *
	 * @param $update
	 * @param $forceUnsafe
	 */
	public function execute($update, $forceUnsafe, $dropColumns, $resetTables, $insertStatic, $dropKeys){
		ClassFinder::clearCache();
		
		$this->recordClassFiles = empty($this->recordClassNames) ? ClassFinder::getAll(ClassFinder::CLASSTYPE_RECORD, true) : ClassFinder::find(array_keys($this->recordClassNames), true);

		$this->update = $update;
		$this->forceUnsafe = $forceUnsafe;
		$this->dropColumns = $dropColumns;
		$this->resetTables = $resetTables;
		$this->insertStatic = $insertStatic;
		$this->dropKeys = $dropKeys;

		if($this->resetTables){
			$this->resetTables();
		}

		$padTo = 0;
		foreach ($this->recordClassFiles as $recordClassFile) {
			$padTo = max($padTo, strlen($recordClassFile[ ClassFinder::CLASSFILE_KEY_CLASSNAME ])); // strlen is okay here, as we don't accept utf8 in classnames
		}

		$summary = array( DBTableDefinition::RESULT_EQUALS => 0, DBTableDefinition::SAFE_UPDATE_TRUE => 0, DBTableDefinition::SAFE_UPDATE_FALSE => 0, DBTableDefinition::SAFE_UPDATE_UNKNOWN => 0, DBTableDefinition::RESULT_RECORD_COUNT => 0 );

		foreach ( $this->recordClassFiles as $idx => $recordClassFile ) {
			$className = $recordClassFile[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];

			$expectedTable = new DBTableDefinition($this->storage);
			$expectedTable->loadDefinitionFromRecordClass( $className );

			echo "Comparing " . CLIHandler::COLOR_CLASSNAME . str_pad($className, $padTo, ' ') . CLIHandler::COLOR_DEFAULT . ': ';

			$tableComparisons = $this->compare($expectedTable, $className);

			$equals = $this->computeEquals( $tableComparisons );

			if (!$equals) {
				if ($update) {
	
					echo 'Updating...' . "\n";
	
					$result = $expectedTable->update( $this->forceUnsafe, $dropColumns, $this->dropKeys, $tableComparisons );
	
					echo "Comparing again after update/create: ";
	
					$tableComparisons = $this->compare( $expectedTable, $className ); // just so we know if the update/create really did what it should
				}
				
				$equals = $this->computeEquals( $tableComparisons );
			}
			
			if (!$equals) {	
				$canUpdate = $this->computeCanUpdate( $tableComparisons );
			
				$summary[ $canUpdate ] ++;
			} else {
				$summary[ DBTableDefinition::RESULT_EQUALS ]++;	
			}

			$summary[DBTableDefinition::RESULT_RECORD_COUNT] += $this->storage->count( $className::getTableName() );
		}

		$longest = max(
			strlen($summary[ DBTableDefinition::RESULT_EQUALS ]),
			strlen($summary[ DBTableDefinition::SAFE_UPDATE_TRUE ]),
			strlen($summary[ DBTableDefinition::SAFE_UPDATE_UNKNOWN ]),
			strlen($summary[ DBTableDefinition::SAFE_UPDATE_FALSE ])
		);

		echo "\n";
		echo CLIHandler::COLOR_DEFAULT . 'Summary:' . "\n";
		echo CLIHandler::COLOR_DEFAULT . '   Equal:                 ' . str_pad($summary[ DBTableDefinition::RESULT_EQUALS ], $longest, ' ', STR_PAD_LEFT) . "\n";
		echo CLIHandler::RESULT_COLOR_SUCCESS . '   Update safe:           ' . str_pad($summary[ DBTableDefinition::SAFE_UPDATE_TRUE ], $longest, ' ', STR_PAD_LEFT) . "\n";
		echo CLIHandler::RESULT_COLOR_WARNING . '   Update safety unknown: ' . str_pad($summary[ DBTableDefinition::SAFE_UPDATE_UNKNOWN ], $longest, ' ', STR_PAD_LEFT) . "\n";
		echo CLIHandler::RESULT_COLOR_FAILURE . '   Update unsafe:         ' . str_pad($summary[ DBTableDefinition::SAFE_UPDATE_FALSE ], $longest, ' ', STR_PAD_LEFT) . "\n";	
		echo CLIHandler::COLOR_DEFAULT . "\n";
		echo CLIHandler::COLOR_DEFAULT . "Total record count: " . str_pad( $summary[ DBTableDefinition::RESULT_RECORD_COUNT ], $longest, ' ', STR_PAD_LEFT ) . "\n";

		echo CLIHandler::COLOR_CLASSNAME . "Don't forget to update permissions if necessary!" . "\n";

		echo CLIHandler::COLOR_DEFAULT . "\n";

		if($this->insertStatic){
			$this->insertStatic();
		}

		return $summary;
	}

	protected function insertStatic(){
		echo CLIHandler::COLOR_DEFAULT . "\n";
		echo CLIHandler::COLOR_DEFAULT . "Inserting static records...\n";

		$tx = $this->storage->startTransaction();

		try{
			foreach ( $this->recordClassFiles as $idx => $recordClassFile ) {
				$className = $recordClassFile[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];

				$records = $className::getStaticRecords( $this->storage );

				$count = count($records);
				$done = 0;

				foreach($records as $values){
					$where = array();

					foreach($values as $fieldName => $value){
						array_push($where, $fieldName, '=', array($value), 'AND');
					}

					array_pop($where);

					$rec = $this->storage->selectFirstRecord($className, array('where' => $where), false);

					if($rec){
						continue;
					}

					$rec = $className::get($this->storage, $values, false);

					$rec->save();

					$done++;
				}

				if ( $count ) {
					echo CLIHandler::RESULT_COLOR_SUCCESS . "Inserted " . $done . " and skipped " . $count . " already existing records by class " . $className . "\n";
				}
			}

			echo CLIHandler::COLOR_DEFAULT . "\n";

			$tx->commit();
		} catch(Exception $e){
			$tx->rollback();
			throw $e;
		}
	}

	protected function computeEquals( $tableComparisons ) {
		$equals = true;
	
		if (isset($tableComparisons[DBTableDefinition::RESULT_SUMMARY])) {
			if ( !is_array($tableComparisons[DBTableDefinition::RESULT_SUMMARY]) || $tableComparisons[ DBTableDefinition::RESULT_SUMMARY ][DBTableDefinition::RESULT_EQUALS ] !== true) {
				$equals = false;
			}
		} else if( $tableComparisons[ DBTableDefinition::RESULT_EXPECTED ][ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] !== true || $tableComparisons[ DBTableDefinition::RESULT_ACTUAL ][ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] !== true){
			$equals = false;
		}
		
		return $equals;
	}

	/**
	 * creates a DBTableDefinition to store the actual table and calls compare() on the $expectedTable
	 *
	 * @param $expectedTable the source table definition
	 * @param $className needed for the tableName
	 *
	 * @return array
	 */
	protected function compare($expectedTable, $className){

		try {
			$actualTable = new DBTableDefinition( $this->storage );
			$actualTable->loadDefinitionFromTable( $className::getTableName() );
			$tableComparison[ DBTableDefinition::RESULT_EXPECTED ] = $expectedTable->compareWithTable( $actualTable, ( isset( $this->recordClassConf[ $className ] ) ? $this->recordClassConf[ $className ] : array() ) );
			$tableComparison[ DBTableDefinition::RESULT_ACTUAL ] = $actualTable->compareWithTable( $expectedTable, ( isset( $this->recordClassConf[ $className ] ) ? $this->recordClassConf[ $className ] : array() ) );
			$this->outputTableComparison( $tableComparison );
		} catch ( TableDoesNotExistException $e ) {
			echo CLIHandler::RESULT_COLOR_FAILURE . 'Table "' . $className::getTableName() . '" does not exist.' . CLIHandler::COLOR_DEFAULT . "\n";
			$tableComparison = array(
				DBTableDefinition::RESULT_SUMMARY => DBTableDefinition::RESULT_NOT_EXISTS
			);
		}

		return $tableComparison;
	}

	protected function computeCanUpdate( $results ) {
		
		if ((isset($results[DBTableDefinition::RESULT_SUMMARY]) && $results[DBTableDefinition::RESULT_SUMMARY] == DBTableDefinition::RESULT_NOT_EXISTS) ||
		(isset($results[DBTableDefinition::RESULT_EXPECTED][DBTableDefinition::RESULT_SUMMARY]) && $results[DBTableDefinition::RESULT_EXPECTED][DBTableDefinition::RESULT_SUMMARY] == DBTableDefinition::RESULT_NOT_EXISTS)) {
			$canUpdate = DBTableDefinition::SAFE_UPDATE_TRUE;
		} else {	
			$result = $results[DBTableDefinition::RESULT_EXPECTED];
			
			$canUpdate = $result[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_CAN_UPDATE ];
	
			switch ( $results[DBTableDefinition::RESULT_ACTUAL][ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_CAN_UPDATE ] ) {
				case DBTableDefinition::SAFE_UPDATE_FALSE:
					$canUpdate = DBTableDefinition::SAFE_UPDATE_FALSE;
					break;
				case DBTableDefinition::SAFE_UPDATE_UNKNOWN:
					if ( $canUpdate == DBTableDefinition::SAFE_UPDATE_TRUE ) {
						$canUpdate = DBTableDefinition::SAFE_UPDATE_UNKNOWN;
					}
					break;
			}
		}
		
		return $canUpdate;
	}

	/**
	 * iterates over $tableComparison and echoes the results
	 *
	 * @param $result
	 *
	 * @return NULL
	 */
	protected function outputTableComparison( $results ) {
		// TODO: refactor the format of $tableComparison so we can call this method recursively
		// TODO: DBColumnDefinition should return false if column doesn't exist, so this method doesn't think the table is up-to-date if a column is missing
		// TODO: send the results to some kind of interface class which does the echoeing (or maybe provides a gui)

		
		$result = $results[DBTableDefinition::RESULT_EXPECTED];

		// table is up-to-date
		if ($result[DBTableDefinition::RESULT_SUMMARY][DBTableDefinition::RESULT_EQUALS] === true && $results[DBTableDefinition::RESULT_ACTUAL][ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] === true) {
			echo CLIHandler::RESULT_COLOR_SUCCESS . "Table is up-to-date, no changes required." . CLIHandler::COLOR_DEFAULT . "\n";
			return;
		}

		$canUpdate = $this->computeCanUpdate( $results );

		switch ( $canUpdate ) {
			case DBTableDefinition::SAFE_UPDATE_TRUE:
				echo CLIHandler::RESULT_COLOR_WARNING . "Table is NOT up-to-date, but is safe to update." . CLIHandler::COLOR_DEFAULT . "\n";
				break;
			case DBTableDefinition::SAFE_UPDATE_FALSE:
				echo CLIHandler::RESULT_COLOR_FAILURE . "Table is NOT up-to-date and NOT safe to update." . CLIHandler::COLOR_DEFAULT . "\n";
				break;
			case DBTableDefinition::SAFE_UPDATE_UNKNOWN:
				echo CLIHandler::RESULT_COLOR_WARNING . "Table is NOT up-to-date and it is unknown whether an update would be safe." . CLIHandler::COLOR_DEFAULT . "\n";
				break;
		}

	//	echo "Affected table properties: \n";

		if ( $result[ DBTableDefinition::PROPERTIES ][DBTableDefinition::RESULT_SUMMARY][DBTableDefinition::RESULT_EQUALS] !== true) {
			foreach ( $result[ DBTableDefinition::PROPERTIES ][ DBTableDefinition::PROPERTIES ] as $propertyName => $propertyResult ) {
				if ( !$propertyResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] && $propertyResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_CAN_UPDATE ] !== DBTableDefinition::SAFE_UPDATE_TRUE ) {
					echo '   Expected "' . $propertyName . '": ' . Debug::getStringRepresentation($propertyResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EXPECTED ]) . "\n";
					echo '   Actual   "' . $propertyName . '": ' . Debug::getStringRepresentation($propertyResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_ACTUAL ]) . "\n";
				}
			}
		}

		// echo results for table columns
		if ( $result[DBTableDefinition::COLUMNS][DBTableDefinition::RESULT_SUMMARY][DBTableDefinition::RESULT_EQUALS] !== true ) {
			foreach ( $result[ DBTableDefinition::COLUMNS ][DBTableDefinition::PROPERTIES] as $key => $columnResult ) {
				if ( $columnResult[DBTableDefinition::RESULT_SUMMARY][DBTableDefinition::RESULT_EQUALS] === DBTableDefinition::RESULT_NOT_EXISTS ) {
					echo CLIHandler::RESULT_COLOR_FAILURE . '   Column "' . $key . '" does not exist' . CLIHandler::COLOR_DEFAULT . "\n";
				} else if ( $columnResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] === false ) {
					echo CLIHandler::RESULT_COLOR_FAILURE . '   Column "' . $key . '"' . CLIHandler::COLOR_DEFAULT . "\n";
					
					foreach ( $columnResult[ DBTableDefinition::PROPERTIES ] as $defKey => $definitionResult ) {
						if ( !$definitionResult[ DBTableDefinition::RESULT_EQUALS ] ) {
							echo '      Expected "' . $defKey . '": ' . Debug::getStringRepresentation($definitionResult[ DBTableDefinition::RESULT_EXPECTED ]) . "\n";
							echo '      Actual   "' . $defKey . '": ' . Debug::getStringRepresentation($definitionResult[ DBTableDefinition::RESULT_ACTUAL ]) . "\n";
						}
					}
				}
			}
		}

		if ( $results[ DBTableDefinition::RESULT_ACTUAL ][ DBTableDefinition::COLUMNS ][ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] !== true ) {
			foreach ( $results[ DBTableDefinition::RESULT_ACTUAL ][ DBTableDefinition::COLUMNS ][ DBTableDefinition::PROPERTIES ] as $key => $columnResult ) {
				if ( $columnResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] === DBTableDefinition::RESULT_NOT_EXISTS ) {
					echo CLIHandler::RESULT_COLOR_FAILURE . '   Column "' . $key . '" no longer exists in recordClass' . CLIHandler::COLOR_DEFAULT . "\n";
				}
			}
		}

		if ( $result[ DBTableDefinition::KEYS ][DBTableDefinition::RESULT_SUMMARY][DBTableDefinition::RESULT_EQUALS] !== true ) {
			foreach ( $result[ DBTableDefinition::KEYS ][ DBTableDefinition::PROPERTIES ] as $keyName => $keyResult ) {
				if ( $keyResult === false) {
					echo CLIHandler::RESULT_COLOR_FAILURE .'   Key "' . $keyName . '" does not exist' . CLIHandler::COLOR_DEFAULT . "\n";
				} else if ( $keyResult[DBTableDefinition::RESULT_SUMMARY][ DBTableDefinition::RESULT_EQUALS ] !== true) {
					
					echo CLIHandler::RESULT_COLOR_FAILURE . '   Definition for key ' . $keyName . ' has changed: ' . CLIHandler::COLOR_DEFAULT . "\n";
					
					echo '      Expected key definition: ' . Debug::getStringRepresentation($keyResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EXPECTED ]) . "\n";
					echo '      Actual   key definition: ' . Debug::getStringRepresentation($keyResult[ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_ACTUAL ]) . "\n";
				}
			}
		}

		if ( $results[ DBTableDefinition::RESULT_ACTUAL ][ DBTableDefinition::KEYS ][ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_EQUALS ] !== true ) {
			foreach ( $results[ DBTableDefinition::RESULT_ACTUAL ][ DBTableDefinition::KEYS ][ DBTableDefinition::PROPERTIES ] as $keyName => $keyResult ) {
				if ( $keyResult === false ) {
					echo CLIHandler::RESULT_COLOR_FAILURE . '   Key "' . $keyName . '" no longer exists in record class' . CLIHandler::COLOR_DEFAULT . "\n";
				}
			}
		}

		if ( !$result[ DBTableDefinition::KEYS ][ DBTableDefinition::RESULT_SUMMARY ][ DBTableDefinition::RESULT_CAN_UPDATE ] ){
			echo "NO Primary key defined!\n";
		}

		echo "\n";

		return;
	}

}
