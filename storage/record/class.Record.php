<?php
/**
 * @package steroid\record
 */

require_once __DIR__ . '/interface.IRecord.php';
require_once STROOT . '/backend/interface.IBackendModule.php';

require_once __DIR__ . '/interface.IRecordHookBeforeSave.php';
require_once __DIR__ . '/interface.IRecordHookAfterSave.php';
require_once __DIR__ . '/interface.IRecordHookBeforeDelete.php';
require_once __DIR__ . '/interface.IRecordHookAfterDelete.php';
require_once __DIR__ . '/interface.IRecordHookAfterStartTransaction.php';
require_once __DIR__ . '/interface.IRecordHookAfterCommit.php';
require_once __DIR__ . '/interface.IRecordHookAfterRollback.php';

require_once STROOT . '/datatype/interface.IContributeBitShift.php';
require_once STROOT . '/datatype/interface.IUseBitShift.php';

require_once STROOT . '/datatype/class.DTSteroidPrimary.php';

require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/storage/class.DBTableDefinition.php';
require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/datatype/class.BaseDTForeignReference.php';
require_once STROOT . '/datatype/class.BaseDTRecordReference.php';

require_once STROOT . '/util/class.Config.php';


/**
 * base record class
 *
 * TODO:
 * - unify functon naming
 * - declare more functions final
 * - don't use __get internally
 * - rename protected variables to starting with _ so we never get a conflict with fieldNames
 * - remove stubs / replace them with interfaces where it makes sense
 * - group overridable functions in file
 * - more documentation
 *
 * @package steroid\record
 */
abstract class Record implements IRecord, IBackendModule, JsonSerializable {
	/**
	 * the system's internal primary field name
	 */

	const FIELDNAME_PRIMARY = 'primary';
	const FIELDNAME_SORTING = 'sorting';

	const BACKEND_TYPE_CONTENT = 'content';
	const BACKEND_TYPE_EXT_CONTENT = 'ext_content';
	const BACKEND_TYPE_DEV = 'dev';
	const BACKEND_TYPE_ADMIN = 'admin';
	const BACKEND_TYPE_CONFIG = 'config';
	const BACKEND_TYPE_SYSTEM = 'system';
	const BACKEND_TYPE_WIDGET = 'widget';
	const BACKEND_TYPE_UTIL = 'util';
	const BACKEND_TYPE_UNKNOWN = 'unknown';

	const PUBDATE_RECORD = false;

	// default backend type
	const BACKEND_TYPE = self::BACKEND_TYPE_UNKNOWN;

	const ACTION_CREATE = 'createRecord';
	const ACTION_SAVE = 'saveRecord';
	const ACTION_DELETE = 'deleteRecord';
	const ACTION_PUBLISH = 'publishRecord';
	const ACTION_HIDE = 'hideRecord';
	const ACTION_PREVIEW = 'previewRecord';
	const ACTION_TRANSLATE = 'translateRecord';
	const ACTION_REVERT_TO_LIVE = 'revertRecord';
	const ACTION_COPY = 'copyRecord';

	const LIST_ONLY = false;
	const LIST_MODE_START_HIERARCHIC = true;

	const MAY_FILTER_BY_ME = true;

// FIXME: add action for view

	const ALLOW_CREATE_IN_SELECTION = 0;

	const TRY_TO_LOAD = 0;

	const HOOK_TYPE_BEFORE_SAVE = 'hookBeforeSave';
	const HOOK_TYPE_AFTER_SAVE = 'hookAfterSave';
	const HOOK_TYPE_BEFORE_DELETE = 'hookBeforeDelete';
	const HOOK_TYPE_AFTER_DELETE = 'hookAfterDelete';
	const HOOK_TYPE_AFTER_START_TRANSACTION = 'hookAfterStartTransaction';
	const HOOK_TYPE_AFTER_COMMIT = 'hookAfterCommit';
	const HOOK_TYPE_AFTER_ROLLBACK = 'hookAfterRollback';

	const RECORD_STATUS_PREVIEW = 0;
	const RECORD_STATUS_LIVE = 1;
	const RECORD_STATUS_MODIFIED = 2;
	const RECORD_STATUS_NOT_APPLICABLE = 3;

	const CACHE_KEY = 'Record';
	const CACHE_LOCK_KEY = 'Record.lock';

	const CACHE_KEY_FOREIGN_REFERENCES = 'recordForeignReferences';
	const CACHE_KEY_FILES = 'recordFileIncludes';

	/** @var IRBStorage */
	protected $storage;

	/**
	 * Holds the instantiated DataTypes which manage the internal values
	 * Filled in constructor, so this may be NULL initially
	 *
	 * @var array
	 */
	protected $fields;

	protected $indexed;
	protected $deleted;
	protected $metaData;


	/**
	 * @var array static indexed caching of records
	 */
	private static $records = array();

	/**
	 * @var array used by pushIndex/popIndex to save and restore index state for memory management
	 */
	private static $oldIndex = array();


	private static $useCache;

	private static $recordClasses;

	private static $saveOriginRecord;
	private static $copyOriginRecord;
	private static $saveValueLock;
	private static $notifyOnSaveComplete;

	private static $foreignReferences = array();

	/** @var IRecordHookBeforeSave[] */
	private static $hookBeforeSave = array();
	private static $hookBeforeSaveByRecordClass = array();

	/** @var IRecordHookAfterSave[] */
	private static $hookAfterSave = array();
	private static $hookAfterSaveByRecordClass = array();

	/** @var IRecordHookBeforeDelete[] */
	private static $hookBeforeDelete = array();
	private static $hookBeforeDeleteByRecordClass = array();

	/** @var IRecordHookAfterDelete[] */
	private static $hookAfterDelete = array();
	private static $hookAfterDeleteByRecordClass = array();

	/** @var IRecordHookAfterCommit[] */
	private static $hookAfterCommit = array();

	/** @var IRecordHookAfterRollback[] */
	private static $hookAfterRollback = array();

	/** @var IRecordHookAfterStartTransaction[] */
	private static $hookAfterStartTransaction = array();
	
	/*
	 * Used to determine how often 
	 * gc_collect_cycles should be called
	 * after a record has been deleted
	 */
	public static $runGCCollectCyclesAfterRecordsDeletedNum = 100;
	
	private static $recordsDeletedSinceLastGCCollectCycles = 0; 

	/**
	 * Shouldn't ever be directly accessed unless you REALLY REALLY know what you're doing
	 *
	 * @var array
	 */
	protected $values = array();

	/**
	 * last values set with loaded = true
	 *
	 * @var array
	 */
	protected $valuesLastLoaded = array();

	/**
	 * @var bool used to guard against circular dependencies leading to endless loops in save
	 */
	protected $currentlySaving = false;

	protected $saveIsUpdate;
	protected $beforeSaveFields;
	protected $setDefaultFields;
	protected $updateDirtyAfterSaveFields;
	protected $afterSaveFields;
	protected $autoIncSet;
	protected $hasSaved;
	protected $saveResult;


	/**
	 * @var bool used to guard against circular dependencies leading to endless loops in delete
	 */
	protected $isDeleting = false;

	// variables for copying
	public $isCopying;
	protected $copyIdentityFields;
	protected $copyIdentityFieldsBackup;
	protected $copiedKeysForIdentity;
	protected $copyFields;
	protected $copyForeignFields;
	protected $copiedIdentityValues;
	protected $copiedValues;
	protected $copiedForeignValues;
	protected $copiedRecord;

	protected $deleteUnreferencedOnSaveFinish;
	protected $deleteBasket;

	public $skipDelete;
	public $skipSave;
	public $readOnly;

	// Helper for performance optimizations
	// TODO: subclasses of Record don't need these static fields, but with only static qualifier they all have their own instance
	public static $trackedFields;
	public static $currentPath;
	public static $currentSavePath;

	public $path;

	/**
	 * cleans up to prepare for garbage collection
	 */
	protected function cleanup() {
		// remove ourselves from callbacks
		while ( ( $key = array_search( $this, self::$notifyOnSaveComplete, true ) ) !== false ) {
			unset(self::$notifyOnSaveComplete[$key]);
		}
		
		// correct internal state (loadedFields, values, etc)
		foreach ( $this->fields as $field ) {
			$field->cleanup();
		}
		
			
		unset($this->values);
		unset($this->valuesLastLoaded);
		
		unset($this->metaData);
					
					
		// disconnect fields
		unset($this->fields);
		
		unset($this->storage);
		
	
	}

	public static function addHook( $object, $hookType, $recordClasses = NULL ) {
		$hookTypes = (array)$hookType;

		if ( $recordClasses !== NULL ) $recordClasses = (array)$recordClasses;

		foreach ( $hookTypes as $hookType ) {
			switch ( $hookType ) {
				case self::HOOK_TYPE_BEFORE_SAVE:
					if ( !( $object instanceof IRecordHookBeforeSave ) ) {
						throw new Exception( '$object must implement IRecordHookBeforeSave.' );
					}

					if ( $recordClasses === NULL ) {
						self::$hookBeforeSave[ ] = $object;
					} else {
						foreach ( $recordClasses as $recordClass ) {
							self::$hookBeforeSaveByRecordClass[ $recordClass ][ ] = $object;
						}
					}
					break;
				case self::HOOK_TYPE_AFTER_SAVE:
					if ( !( $object instanceof IRecordHookAfterSave ) ) {
						throw new Exception( '$object must implement IRecordHookAfterSave.' );
					}

					if ( $recordClasses === NULL ) {
						self::$hookAfterSave[ ] = $object;
					} else {
						foreach ( $recordClasses as $recordClass ) {
							self::$hookAfterSaveByRecordClass[ $recordClass ][ ] = $object;
						}
					}
					break;
				case self::HOOK_TYPE_BEFORE_DELETE:
					if ( !( $object instanceof IRecordHookBeforeDelete ) ) {
						throw new Exception( '$object must implement IRecordHookBeforeDelete.' );
					}

					if ( $recordClasses === NULL ) {
						self::$hookBeforeDelete[ ] = $object;
					} else {
						foreach ( $recordClasses as $recordClass ) {
							self::$hookBeforeDeleteByRecordClass[ $recordClass ][ ] = $object;
						}
					}
					break;
				case self::HOOK_TYPE_AFTER_DELETE:
					if ( !( $object instanceof IRecordHookAfterDelete ) ) {
						throw new Exception( '$object must implement IRecordHookAfterDelete.' );
					}

					if ( $recordClasses === NULL ) {
						self::$hookAfterDelete[ ] = $object;
					} else {
						foreach ( $recordClasses as $recordClass ) {
							self::$hookAfterDeleteByRecordClass[ $recordClass ][ ] = $object;
						}
					}
					break;
				case self::HOOK_TYPE_AFTER_START_TRANSACTION:
					if ( !( $object instanceof IRecordHookAfterStartTransaction ) ) {
						throw new Exception( '$object must implement IRecordHookAfterStartTransaction.' );
					}

					self::$hookAfterStartTransaction[ ] = $object;
					break;
				case self::HOOK_TYPE_AFTER_COMMIT:
					if ( !( $object instanceof IRecordHookAfterCommit ) ) {
						throw new Exception( '$object must implement IRecordHookAfterCommit.' );
					}

					self::$hookAfterCommit[ ] = $object;
					break;
				case self::HOOK_TYPE_AFTER_ROLLBACK:
					if ( !( $object instanceof IRecordHookAfterRollback ) ) {
						throw new Exception( '$object must implement IRecordHookAfterRollback.' );
					}

					self::$hookAfterRollback[ ] = $object;
					break;
				default:
					throw new Exception( 'Invalid hook type: ' . $hookType );
			}
		}
	}

	public static function storageStartTransaction( IRBStorage $storage, $newTransactionLevel ) {
		// @Hook: IRecordAfterStartTransaction
		foreach ( self::$hookAfterStartTransaction as $hook ) {
			$hook->recordHookAfterStartTransaction( $storage, $newTransactionLevel );
		}
	}

	public static function storageCommit( IRBStorage $storage, $newTransactionLevel ) {
		// @Hook: IRecordHookAfterCommit
		foreach ( self::$hookAfterCommit as $hook ) {
			$hook->recordHookAfterCommit( $storage, $newTransactionLevel );
		}
	}

	public static function storageRollback( IRBStorage $storage, $newTransactionLevel ) {
		// @Hook: IRecordHookAfterRollback
		foreach ( self::$hookAfterRollback as $hook ) {
			$hook->recordHookAfterRollback( $storage, $newTransactionLevel );
		}
	}

	public function modifyMayWrite( $mayWrite = false, User $user ) {
		return $mayWrite;
	}

	public static function getAvailableActions( $mayWrite = false, $mayPublish = false, $mayHide = false, $mayDelete = false, $mayCreate = false ) {
		$user = User::getCurrent();

		$actions = array();

		if ( static::getDataTypeFieldName( 'DTSteroidLive' ) && ( static::getDataTypeFieldName( 'DTSteroidPage' ) || get_called_class() == 'RCPage' ) ) {
			$actions[ ] = self::ACTION_PREVIEW;
		}

		if ( $mayWrite ) {
			$actions[ ] = self::ACTION_SAVE;
		}

		if ( $mayDelete ) {
			$actions[ ] = self::ACTION_DELETE;
		}

		if ( static::getDataTypeFieldName( 'DTSteroidLive' ) ) {
			if ( $mayHide && $mayPublish && $mayDelete ) {
				$actions[ ] = self::ACTION_REVERT_TO_LIVE;
			}

			if ( $mayPublish ) {
				$actions[ ] = self::ACTION_PUBLISH;
			}

			if ( $mayHide ) {
				$actions[ ] = self::ACTION_HIDE;
			}
		}

		return $actions;
	}

	// TODO: skip decamelize function for table names, should be good in camelcase as well ; in that case we can remove this function alltogether
	public static function getTableName() {
		static $tableName;

		if ( $tableName === NULL ) {
			// strtolower + preg_replace = decamelize function
			$tableName = 'rc_' . str_replace( '__', '_', strtolower( preg_replace( "/(?<!^)((?<![[:upper:]])[[:upper:]]+?|[[:upper:]][[:lower:]])/", "_$0", substr( get_called_class(), 2 ) ) ) );
		}

		return $tableName;
	}

	public static function getTableCharset() {
		return NULL;
	}

	public static function getTableCollation() {
		return NULL;
	}

	public static function getTableEngine() {
		return NULL;
	}


	protected static function getTitleFields() {
		$fieldDefinitions = static::getOwnFieldDefinitions();

		if ( array_key_exists( 'title', $fieldDefinitions ) ) {
			return array( 'title' => 'title' );
		}

		$keys = static::getAllKeys();


		foreach ( $keys as $keyName => $key ) {
			if ( !empty( $key[ 'unique' ] ) || $keyName === 'primary' ) {
				foreach ( $key[ 'fieldNames' ] as $fieldName ) {
					if ( is_subclass_of( $fieldDefinitions[ $fieldName ][ 'dataType' ], 'BaseDTString' ) ) { // unique key with a string field
						$fieldNames = $key[ 'fieldNames' ];
						break;
					}
				}
			}

			if ( isset( $fieldNames ) ) break;
		}

		if ( !isset( $fieldNames ) ) {
			$fieldNames = $keys[ 'primary' ][ 'fieldNames' ];
		}

		// live is always 0 in backend, so we remove every possible included DTSteroidLive field, if we have more than 1 field 
		// this also takes care of the case when someone decided to use live field for title (which doesn't make sense of course)
		if ( count( $fieldNames ) > 1 ) {
			foreach ( $fieldNames as $k => $fieldName ) {
				if ( $fieldDefinitions[ $fieldName ][ 'dataType' ] === 'DTSteroidLive' ) {
					unset( $fieldNames[ $k ] );
				}
			}
		}

		return array_combine( $fieldNames, $fieldNames );
	}

	protected static function getListTitleFields() {
		return static::getTitleFields();
	}

	public static function fillTitleFields( &$titleFields, $fieldDefs ) {
		foreach ( $titleFields as $fn => $titleField ) {
			if ( is_array( $titleField ) ) {
				$dt = $fieldDefs[ $fn ][ 'dataType' ];

				$dt::fillTitleFields( $fn, $titleField, $fieldDefs[ $fn ] );
			} else {
				$dt = $fieldDefs[ $titleField ][ 'dataType' ];

				$titleFields[ $titleField ] = $dt::getTitleFields( $titleField, $fieldDefs[ $titleField ] );
			}
		}
	}

	final public static function getTitleFieldsCached() {
		static $titleFields;

		if ( $titleFields === NULL ) {
			$fieldDefs = static::getAllFieldDefinitions();

			$titleFields = static::getOwnTitleFields();

			$titleFields = array_combine( $titleFields, $titleFields );

			static::fillTitleFields( $titleFields, $fieldDefs );
		}

		return $titleFields;
	}

	final public static function getListTitleFieldsCached() {
		static $titleFields;

		if ( $titleFields === NULL ) {
			$fieldDefs = static::getAllFieldDefinitions();

			$titleFields = static::getListTitleFields();

			$titleFields = array_combine( $titleFields, $titleFields );

			static::fillTitleFields( $titleFields, $fieldDefs );
		}

		return $titleFields;
	}

	final public static function getOwnTitleFields() {
		static $ownTitleFields;

		if ( $ownTitleFields === NULL ) {
			$ownTitleFields = static::getTitleFields();
		}

		return $ownTitleFields;
	}

	public static function getColumnName( $fieldName = NULL ) {
		$colNames = static::_getColNames();

		return isset( $colNames[ $fieldName ] ) ? $colNames[ $fieldName ] : NULL;
	}

	/**
	 * Used for caching column names
	 *
	 * @return array
	 */
	final protected static function _getColNames() {
		static $colNames;

		if ( $colNames === NULL ) {
			$colNames = array();

			$fieldDefs = static::getOwnFieldDefinitions();

			foreach ( $fieldDefs as $fieldName => $fieldConf ) {
				$dataType = $fieldConf[ 'dataType' ];

				$colNames[ $fieldName ] = $dataType::getColName( $fieldName, $fieldConf );
			}
		}

		return $colNames;
	}


	protected static function getFieldDefinitionsForField( $fieldName ) {
		if ( strpos( $fieldName, ':' ) === false ) {
			$fieldDefs = static::getOwnFieldDefinitions();
		} else {
			$fieldDefs = static::getForeignReferences();
		}

		return $fieldDefs;
	}

	public static function fieldDefinitionExists( $fieldName ) {
		$fieldDefs = static::getFieldDefinitionsForField( $fieldName );

		return isset( $fieldDefs[ $fieldName ] );
	}


	public static function getFieldDefinition( $fieldName, $failGracefully = false ) {
		$fieldDefs = static::getFieldDefinitionsForField( $fieldName );

		if ( !array_key_exists( $fieldName, $fieldDefs ) ) {
			if ( $failGracefully ) {
				return NULL;
			} else {
				throw new InvalidArgumentException( 'Record of class "' . get_called_class() . '" has no field "' . $fieldName . '"' );
			}
		}

		return $fieldDefs[ $fieldName ];
	}

	public static function getFieldDefinitionByPath( $path, $failGracefully = false ) {
		$pathParts = explode( '.', $path );

		$currentRecordClass = get_called_class();

		$last = count( $pathParts ) - 1;

		foreach ( $pathParts as $k => $pathPart ) {
			$fieldDef = $currentRecordClass::getFieldDefinition( $pathPart, $failGracefully );

			if ( $failGracefully && $fieldDef === NULL ) {
				return NULL;
			}

			if ( $k !== $last ) { // last doesn't need to be a recordReference of any sort
				$currentRecordClass = $currentRecordClass::getRecordClassStatically( $pathPart, $fieldDef );
			}
		}

		return $fieldDef;
	}

	public function getChainByPath( $path ) {
		$pathParts = explode( '.', $path );

		$chain = array();

		return $this->_getChainByPath( $this, $chain, $pathParts );
	}

	private final function _getChainByPath( IRecord $currentRec, array $chain, array $pathParts ) {
		$pathPart = array_shift( $pathParts );


		$vals = $currentRec->getFieldValue( $pathPart );

		$chain[ ] = $currentRec;

		if ( $vals ) {
			if ( !is_array( $vals ) ) {
				$vals = array( $vals );
			}

			if ( $pathParts ) {
				foreach ( $vals as $val ) {
					if ( $newChain = $this->_getChainByPath( $val, $chain, $pathParts ) ) {
						return $newChain; // finished chain, return without popping
					}
				}
			} else {
				// finished chain, return without popping
				$vals = (array)$vals;
				$chain[ ] = reset( $vals );

				return $chain;
			}
		}

		array_pop( $chain );

		return false;
	}


	protected static function getRecordClassStatically( $fieldName, $fieldDef ) {
		return $fieldDef[ 'recordClass' ];
	}

	protected static function getFieldDefinitions() {
		return array();
	}

	protected static function getKeys() {
		return array();
	}

	public static function getDataTypeFieldName( $dataType ) {
		static $fieldNames = array();

		if ( !isset( $fieldNames[ $dataType ] ) ) {
			$fieldNames[ $dataType ] = false;

			$fieldDefs = static::getAllFieldDefinitions();

			foreach ( $fieldDefs as $fieldName => $fieldDef ) {
				if ( $fieldDef[ 'dataType' ] === $dataType ) {
					$fieldNames[ $dataType ] = $fieldName;
					break;
				}
			}
		}

		return $fieldNames[ $dataType ] === false ? NULL : $fieldNames[ $dataType ];
	}


	public static function getDefaultValues( IStorage $storage, array $fieldsToSelect, array $extraParams = NULL ) {
		$defaults = array();

		$extraParams[ 'recordClasses' ][ ] = get_called_class();

		// FIXME: add titlefields where appropriate outside of this function instead of here (which means adding them always, which in turn hurts performance and partially may lead to unexpected behaviour)
		$fieldsToSelect = array_merge( $fieldsToSelect, array_keys( static::getTitleFieldsCached() ) );

		foreach ( $fieldsToSelect as $fieldName ) {
			$fieldDef = static::getFieldDefinition( $fieldName );

			$dataType = $fieldDef[ 'dataType' ];
			$default = $dataType::getDefaultValue( $storage, $fieldName, $fieldDef, $extraParams );

			$defaults[ $fieldName ] = $default;
		}

		return $defaults;
	}

	/**
	 * Used for caching statically defined field definitions
	 *
	 * @throws InvalidArgumentException
	 * @return array
	 */
	final public static function getOwnFieldDefinitions() {
		static $ownFieldDefs;

		if ( $ownFieldDefs === NULL ) {
			$calledClass = get_called_class();


			if ( function_exists( 'apc_fetch' ) ) {
				$key = WEBROOT . '_ofd_' . $calledClass;

				$fieldDefinitions = apc_fetch( $key );
			} else {
				$fieldDefinitions = false;
			}

			if ( $fieldDefinitions === false ) {

				$fieldDefinitions = static::getFieldDefinitionsCached();
				static::addGeneratedFieldDefinitions( $fieldDefinitions );


				if ( !is_array( $fieldDefinitions ) ) {
					throw new InvalidArgumentException( 'Field definitions of record class "' . $calledClass . '" must be array' );
				}

				// filter predefined foreign references
				foreach ( $fieldDefinitions as $fieldName => $fieldDefinition ) {
					if ( strpos( $fieldName, ':' ) !== false ) {
						unset( $fieldDefinitions[ $fieldName ] );
					} else {
						$dt = $fieldDefinition[ 'dataType' ];
						$dt::completeConfig( $fieldDefinitions[ $fieldName ], $calledClass, $fieldName );
					}
				}

				if ( isset( $key ) ) {
					apc_store( $key, $fieldDefinitions );
				}
			}

			$ownFieldDefs = $fieldDefinitions;
		}

		return $ownFieldDefs;
	}

	protected static function addGeneratedFieldDefinitions( array &$fieldDefinitions ) {
		// stub
	}

	final public static function getAllKeys() {
		static $keyDefs;

		if ( $keyDefs === NULL ) {
			$keys = static::getKeys();
			static::addGeneratedKeys( $keys );

			// make sure we have a unique key if we have a primary field
			$fields = static::getOwnFieldDefinitions();

			if ( array_key_exists( self::FIELDNAME_PRIMARY, $fields ) ) {
				$hasPrimaryUnique = false;

				foreach ( $keys as $keyName => $keyDef ) {
					if ( ( $keyName == 'primary' || $keyDef[ 'unique' ] ) && count( $keyDef[ 'fieldNames' ] ) == 1 && $keyDef[ 'fieldNames' ][ 0 ] == self::FIELDNAME_PRIMARY ) {
						$hasPrimaryUnique = true;
						break;
					}
				}

				if ( !$hasPrimaryUnique ) {
					$keys[ 'auto_primary_unique' ] = DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ), true );
				}
			}

			$keyDefs = $keys;
		}

		return $keyDefs;
	}

	protected static function addGeneratedKeys( array &$keys ) {
		// stub
	}

	final public static function getPrimaryKeyFields() {
		static $pk;

		if ( $pk === NULL ) {
			$keys = static::getAllKeys();
			$pk = $keys[ 'primary' ][ 'fieldNames' ];
		}

		return $pk;
	}

	final public static function getUniqueKeys() {
		static $uk;

		if ( $uk === NULL ) {
			$uk = array();
			$keys = static::getAllKeys();

			foreach ( $keys as $keyName => $key ) {
				if ( $keyName === 'primary' || $key[ 'unique' ] ) {
					$uk[ $keyName ] = $key;
				}
			}

		}

		return $uk;
	}

	final public static function getUniqueKeyFields() {
		static $uk;

		if ( $uk === NULL ) {
			$uniqueKeys = static::getUniqueKeys();

			$uk = array();

			foreach ( $uniqueKeys as $uniqueKey ) {
				$uk = array_merge( $uk, $uniqueKey[ 'fieldNames' ] );
			}
		}

		return $uk;
	}

	/**
	 * Used for caching field definitions (static and dynamic)
	 *
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	final public static function getAllFieldDefinitions() {
		static $fieldDefs;

		if ( $fieldDefs === NULL ) {
			$ownFieldDefinitions = static::getOwnFieldDefinitions();

			$ref = static::getForeignReferences();

			$fieldDefs = array_merge( $ownFieldDefinitions, $ref );

		}

		return $fieldDefs;
	}

	// TODO: getListFields should be the one for overriding (regarding naming convention)
	// TODO: can we make this private or protected?
	final public static function getListFields( User $user ) {
		$fieldDefs = static::getAllFieldDefinitions();
		$fields = static::getDisplayedListFields();

		if ( empty( $fields ) ) {
			$fields = self::getTitleFields();
		}

		if ( array_key_exists( self::FIELDNAME_PRIMARY, $fieldDefs ) && !in_array( self::FIELDNAME_PRIMARY, $fields ) ) {
			$fields[ ] = self::FIELDNAME_PRIMARY;
		}

		if ( $idFieldName = static::getDataTypeFieldName( 'DTSteroidID' ) ) {
			$fields[ ] = $idFieldName;
		}

		if ( $languageFieldName = static::getDataTypeFieldName( 'DTSteroidLanguage' ) ) {
			$fields[ ] = $languageFieldName;
		}

		if ( $liveFieldName = static::getDataTypeFieldName( 'DTSteroidLive' ) ) {
			$fields[ ] = $liveFieldName;
		}

		$listFieldDefinitions = array();

		foreach ( $fields as $path ) {
			if ( isset( $fieldDefs[ $path ] ) ) {
				$fieldDef = $fieldDefs[ $path ];
			} else {
				$fieldDef = static::getFieldDefinitionByPath( $path );
			}

			if ( self::isFieldAccessible( $user, $path, $fieldDef ) ) {
				$listFieldDefinitions[ $path ] = $fieldDef;
			}

		}

		return $listFieldDefinitions;
	}

	protected static function isFieldAccessible( User $user, $fieldName, $fieldDef ) {
		$dt = $fieldDef[ 'dataType' ];

		if ( is_subclass_of( $dt, 'BaseDTRecordReference' ) ) {
			$perms = $user->getRecordClassPermissionsForDomainGroup( $dt::getRecordClassStatically( $fieldName, $fieldDef ), $user->getSelectedDomainGroup() );

			return !empty( $perms );
		} elseif ( is_subclass_of( $dt, 'BaseDTForeignReference' ) ) {

			$foreignRecordClass = $fieldDef[ 'recordClass' ];

			$perms = $user->getRecordClassPermissionsForDomainGroup( $foreignRecordClass, $user->getSelectedDomainGroup() );

			if ( empty( $perms ) ) {
				return false;
			}

			$otherClassConf = $dt::getOtherSelectableRecordClassDefinition( get_called_class(), $foreignRecordClass );

			$perms = $user->getRecordClassPermissionsForDomainGroup( $otherClassConf[ 'recordClass' ], $user->getSelectedDomainGroup() );

			if ( empty( $perms ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Used when collecting backend JS config
	 *
	 * This may be overriden to use custom JS Datatypes for displaying fields in list view
	 */
	public static function getListFieldsForJS( User $user ) {
		return static::getListFields( $user );
	}

	/**
	 * Get list fields in a form optimized for select query, so we save on lazy load calls
	 */
	final public static function getExpandedListFields( User $user ) {
		$listFields = static::getListFields( $user );

		$currentRecordClass = get_called_class();

		// do not use reference here to avoid recursively looping expanded fields again
		foreach ( $listFields as $path => $fieldDef ) {
			$dt = $fieldDef[ 'dataType' ];

			$dt::expandListField( $listFields, $path, $currentRecordClass );
		}

		return $listFields;
	}

	protected static function getDisplayedListFields() {
		return array_keys( static::getOwnFieldDefinitions() );
	}

	protected static function getEditableFormFields() {
		return array_keys( static::getOwnFieldDefinitions() );
	}

	// TODO: stub removal
	protected static function addToEditableFormFields( $recordClass, $fieldName, &$editableFormFields ) {
		// stub
	}


	final protected static function getEditableFormFieldsCached() {
		static $editableFormFields;

		if ( $editableFormFields === NULL ) {
			$editableFormFields = static::getEditableFormFields();

			$foreignReferences = static::getForeignReferences();
			$calledClass = get_called_class();


			foreach ( $foreignReferences as $fieldName => $fieldDefinition ) {
				$foreignRecordClass = substr( strrchr( $fieldName, ':' ), 1 );

				// allow foreign references to add themselves to the list of editable form fields
				$foreignRecordClass::addToEditableFormFields( $calledClass, $fieldName, $editableFormFields );
			}
		}

		return $editableFormFields;
	}

	/**
	 * Preferred override point for setting filter fields
	 *
	 * May return a mixed array of fieldnames and fieldname => fieldDefinition mappings
	 */
	protected static function getDisplayedFilterFields() {
		return static::getAllFieldDefinitions();
	}

	/**
	 * May be overriden if needed
	 *
	 * Record::getDisplayedFilterFields() is the preferred override point
	 */
	public static function getFilterFields( User $user, IRBStorage $storage ) {
		$fields = static::getDisplayedFilterFields();

		$filterFields = array();

		$calledClass = get_called_class();

		foreach ( $fields as $path => $pathOrFieldDef ) {
			if ( is_array( $pathOrFieldDef ) ) {
				$fieldDef = $pathOrFieldDef;
			} else {
				$fieldDef = static::getFieldDefinitionByPath( $pathOrFieldDef );
				$path = $pathOrFieldDef;
			}

			$dt = $fieldDef[ 'dataType' ];

			if ( static::isFilterableField( $user, $dt, $fieldDef ) ) {
				$fieldDef = $dt::getFormConfig( $storage, $calledClass, $path, $fieldDef );

				$fieldDef[ 'nullable' ] = true; // so we don't get "required" messages with empty filters

				if ( isset( $fieldDef[ 'constraints' ] ) ) {
					if ( isset( $fieldDef[ 'constraints' ][ 'min' ] ) && $fieldDef[ 'constraints' ][ 'min' ] ) {
						$fieldDef[ 'constraints' ][ 'min' ] = 0;
					}

//					$fieldDef[ 'constraints' ][ 'max' ] = 1;
				}

				$filterFields[ $path ] = $fieldDef;
			}
		}

		return $filterFields;
	}

	// TODO: move to datatype
	protected static function isFilterableField( User $user, $fieldName, $fieldDef ) {
		$dt = $fieldDef[ 'dataType' ];

		return ( ( is_subclass_of( $dt, 'BaseDTRecordReference' ) || is_subclass_of( $dt, 'BaseDTForeignReference' ) )
				&& !is_subclass_of( $dt, 'BaseDTStaticInlineRecordEdit' )
				&& isset( $fieldDef[ 'recordClass' ] )
				&& !( is_subclass_of( $dt, 'BaseDTForeignReference' ) && $fieldDef[ 'recordClass' ] === get_called_class() ) // no foreign child references
				&& !in_array( $fieldDef[ 'recordClass' ]::BACKEND_TYPE, array( Record::BACKEND_TYPE_WIDGET, Record::BACKEND_TYPE_DEV ) )
				&& $fieldDef[ 'recordClass' ]::MAY_FILTER_BY_ME
				&& !in_array( $fieldDef[ 'dataType' ], array( 'DTSteroidPage' /*, 'DTSteroidDomainGroup'*/ ) )
				&& !is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTAreaJoinForeignReference' ) )
		&& self::isFieldAccessible( $user, $fieldDef, $fieldDef );
	}

	public static function getFormFields( IRBStorage $storage, array $fields = NULL ) {
		$fieldDefs = static::getAllFieldDefinitions();

		if ( empty( $fields ) ) {
			$fields = static::getEditableFormFieldsCached();
		}

		if ( isset( $fieldDefs[ self::FIELDNAME_PRIMARY ] ) && !in_array( self::FIELDNAME_PRIMARY, $fields ) ) {
			$fields[ ] = self::FIELDNAME_PRIMARY;
		}

		// FIXME: use formRequired of datatype or some other more natural + flexible logic
		foreach ( $fieldDefs as $fieldName => $fieldDef ) {
			switch ( $fieldDef[ 'dataType' ] ) {
				case 'DTSteroidID':
				case 'DTSteroidLive':
				case 'DTSteroidLanguage':
				case 'DTSteroidCreator':
				case 'DTSteroidDomainGroup':
					if ( !in_array( $fieldName, $fields ) ) {
						$fields[ ] = $fieldName;
					}
					break;
				case 'DTCTime':
				case 'DTMTime':
					if ( ( $k = array_search( $fieldName, $fields ) ) !== false ) {
						unset( $fields[ $k ] );
					}
					break;
			}
		}

		$formFields = array();

		$calledClass = get_called_class();

		foreach ( $fields as $fieldName ) {
			$dt = $fieldDefs[ $fieldName ][ 'dataType' ];

			$fieldDef = $dt::getFormConfig( $storage, $calledClass, $fieldName, $fieldDefs[ $fieldName ] );

			$formFields[ $fieldName ] = $fieldDef;
		}

		static::setReadOnlyFields( $formFields, $storage );

		return $formFields;
	}

	protected static function setReadOnlyFields( array &$formFields, RBStorage $storage ) {
		$user = User::getCurrent();

		if ( !$user || !$user->record ) {
			return;
		}

		$permissions = array();

		$permissionEntityJoins = $storage->selectRecords( 'RCPermissionPermissionEntity', array( 'fields' => array( '*', 'fieldPermission.*' ), 'where' => array(
			'permission.permission:RCDomainGroupLanguagePermissionUser.domainGroup', '=', '%1$s',
			'AND', 'permission.permission:RCDomainGroupLanguagePermissionUser.language', '=', '%2$s',
			'AND', 'permission.permission:RCDomainGroupLanguagePermissionUser.user', '=', '%3$s',
			'AND', 'permissionEntity.recordClass', '=', '%4$s',
		) ), NULL, NULL, NULL, array( $user->getSelectedDomainGroup(), $user->getSelectedLanguage(), $user->record, get_called_class() ), 'Record::setReadOnlyFields', true );

		$readOnlyFields = array_keys( $formFields );

		foreach ( $permissionEntityJoins as $permissionEntityJoin ) {
			if ( $permissionEntityJoin->fieldPermission && $permissionEntityJoin->fieldPermission->readOnlyFields ) {
				$readOnlyFields = array_intersect( $readOnlyFields, explode( ',', $permissionEntityJoin->fieldPermission->readOnlyFields ) );
			} else {
				return; // we have a permission without field restrictions, so we can quit early
			}
		}

		foreach ( $readOnlyFields as $fieldName ) {
			$formFields[ $fieldName ][ 'readOnly' ] = true;
		}
	}

	// TODO: stub removal
	public static function addToFieldDefinitions( $recordClass, array $existingFieldDefinitions ) {
		return NULL;
	}

	/**
	 * Used for caching defined field definitions
	 *
	 * @return array
	 */
	final public static function getFieldDefinitionsCached() {
		static $fieldDefinitions;

		if ( $fieldDefinitions === NULL ) {
			$calledClass = get_called_class();

			if ( function_exists( 'apc_fetch' ) ) {
				$key = WEBROOT . '_fd_' . $calledClass;
				$fieldDefinitions = apc_fetch( $key );
			} else {
				$fieldDefinitions = false;
			}

			if ( $fieldDefinitions === false ) {
				$fieldDefinitions = static::getFieldDefinitions();

				$classes = self::getRecordClasses();

				$conf = Config::getDefault();
				$authenticators = $conf->getSection('authenticator');

				foreach($authenticators as $auth => $path){
					require_once(WEBROOT . '/' . $path);

					if($auth::AUTH_TYPE === User::AUTH_TYPE_BE){
						$classes[$auth] = $path;
						break;
					}
				}

				foreach ( $classes as $class => $classDefinition ) {
					$newFields = $class::addToFieldDefinitions( $calledClass, $fieldDefinitions );

					if ( $newFields && !empty( $newFields ) ) {
						foreach ( $newFields as $fieldName => $fieldDef ) {
							$fieldDef[ 'addedByClass' ] = $class;
							$fieldDefinitions[ $fieldName ] = $fieldDef;
						}
					}

				}

				if ( isset( $key ) ) {
					apc_store( $key, $fieldDefinitions );
				}
			}
		}

		return $fieldDefinitions;
	}

	final private static function getRecordClasses() {
		if ( self::$recordClasses === NULL ) { // runs once for all recordClasses
			$classNames = array();

			if ( self::$useCache === NULL ) {
				if ( $cacheType = Config::key( 'record', 'cache' ) ) {
					$cache = Cache::getBestMatch( $cacheType );

					$checked = false;

					if ( $cache->exists( self::CACHE_KEY ) ) {
						try {
							self::includeForeignReferenceCache( $cache );
						} catch ( ErrorException $e ) { // might get a warning "exception" in case a file doesn't exist anymore - in that case we need to regenerate cache
							if ( $e->getSeverity() === E_WARNING && $e->getFile() === __FILE__ ) {
								// one of the included record classes doesn't exist anymore OR cache file does not exist, (re)create it
								$cache->lock( self::CACHE_LOCK_KEY );

								// cache might have been recreated in the mean time - try again (this way we prevent cache being created multiple times under high load)
								try {
									self::includeForeignReferenceCache( $cache );
								} catch ( ErrorException $e ) {
									self::createForeignReferenceCache( $cache );
								} catch ( Exception $e ) {
									$cache->unlock( self::CACHE_LOCK_KEY );

									throw $e;
								}

								$cache->unlock( self::CACHE_LOCK_KEY );
							} else {
								// rethrow
								throw $e;
							}
						}
					} else {
						self::createForeignReferenceCache( $cache );
					}

				} else {
					self::$useCache = false;

					self::fillRecordClasses();
				}
			} else { // self::$useCache has already been set
				self::fillRecordClasses();
			}
		}

		return self::$recordClasses;
	}

	final private static function includeForeignReferenceCache( $cache ) {
		$cacheEntry = json_decode( $cache->get( self::CACHE_KEY ), true );

		$includeFiles = $cacheEntry[ self::CACHE_KEY_FILES ];

		foreach ( $includeFiles as $file ) {
			include_once $file;
			self::$useCache = true;
			self::fillRecordClasses();
		}

		self::$foreignReferences = $cacheEntry[ self::CACHE_KEY_FOREIGN_REFERENCES ];
	}

	final private static function createForeignReferenceCache( $cache ) {
		$recordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, false );

		$filenames = array();
		$cacheEntry = array();

		try {
			foreach ( $recordClasses as $recordClass ) {
				$filename = $recordClass[ ClassFinder::CLASSFILE_KEY_FULLPATH ];

				include_once $filename;

				$filenames[ ] = $filename;
			}

			$cacheEntry[ self::CACHE_KEY_FILES ] = $filenames;
		} catch ( ErrorException $e ) {
			// if we still fail here, just disable caching and go with classfinder
			self::$useCache = false;
		}

		// if we get here, we might try to go a little farther and write
		// complete foreign reference definitions
		self::fillRecordClasses();


		foreach ( $recordClasses as $recordClass ) {
			$className = $recordClass[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];

			self::fillForeignReferences( $className );
		}

		$cacheEntry[ self::CACHE_KEY_FOREIGN_REFERENCES ] = self::$foreignReferences;

		$cache->set( self::CACHE_KEY, json_encode( $cacheEntry ) );
	}

	/**
	 * Used for caching foreign references
	 *
	 * @return array
	 */
	final public static function getForeignReferences() {
		$calledClass = get_called_class();

		if ( !isset( self::$foreignReferences[ $calledClass ] ) ) { // runs once per recordClass

			if ( self::$recordClasses === NULL ) { // runs once for all recordClasses
				// we don't care about return value
				self::getRecordClasses();

				if ( self::$useCache !== false && isset( self::$foreignReferences[ $calledClass ] ) ) {
					return self::$foreignReferences[ $calledClass ];
				}
			}

			// ----
			self::fillForeignReferences( $calledClass );
		}

		return self::$foreignReferences[ $calledClass ];
	}

	// FIXME: use cache interface, integrate with other cache
	final private static function fillRecordClasses() {

		// Prevents missing inclusions
		$recordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );

		if ( function_exists( 'apc_fetch' ) ) {
			$key = WEBROOT . '_Record::fillRecordClasses()';

			self::$recordClasses = apc_fetch( $key );
		} else {
			self::$recordClasses = false;
		}

		if ( self::$recordClasses === false ) {
			if ( self::$useCache === false ) {
				// FIXME: ClassFinder getAll call should be here

				foreach ( $recordClasses as $recordClass ) {
					$className = $recordClass[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];

					$classNames[ ] = $className;
				}
			} else { // use cache: all record classes should be included and thus existing by now
				$recordClasses = get_declared_classes();

				foreach ( $recordClasses as $recordClass ) {
					if ( substr( $recordClass, 0, 2 ) == 'RC' ) {
						$classNames[ ] = $recordClass;
					}
				}
			}

			self::$recordClasses = array_fill_keys( $classNames, array() );

			foreach ( $classNames as $className ) {
				$fieldDefs = $className::getOwnFieldDefinitions();

				self::$recordClasses[ $className ] = array();

				foreach ( $fieldDefs as $fieldName => $fieldDef ) {
					// TODO: why do we only store record references?
					if ( is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTRecordReference' ) ) {
						self::$recordClasses[ $className ][ $fieldName ] = $fieldDef;
					}
				}
			}

			if ( isset( $key ) ) {
				apc_store( $key, self::$recordClasses );
			}
		}
	}

	final private static function fillForeignReferences( $calledClass ) {
		$foreignReferences = array();

		foreach ( self::$recordClasses as $className => $referenceFields ) {
			foreach ( $referenceFields as $fieldName => $fieldDef ) {
				$dt = $fieldDef[ 'dataType' ];
				$dt::getForeignReferences( $calledClass, $className, $fieldName, $fieldDef, $foreignReferences );
			}
		}

		// check if we have predefined foreign references, those will override generated ones
		$fieldDefinitions = $calledClass::getFieldDefinitionsCached();

		foreach ( $fieldDefinitions as $fieldName => $fieldDefinition ) {
			if ( strpos( $fieldName, ':' ) !== false ) {
				$foreignReferences[ $fieldName ] = $fieldDefinition;
			}
		}

		foreach ( $foreignReferences as $fieldName => $foreignConfig ) {
			$dt = $foreignReferences[ $fieldName ][ 'dataType' ];
			$dt::completeConfig( $foreignReferences[ $fieldName ], $calledClass, $fieldName );
		}

		self::$foreignReferences[ $calledClass ] = $foreignReferences;
	}

	public function fillUpValues( array $values, $loaded ) {
		foreach ( $values as $fieldName => $value ) {
			if ( !isset( $this->fields[ $fieldName ] ) ) {
				throw new Exception( 'Trying to set ' . Debug::getStringRepresentation( $value ) . ' on field ' . $fieldName . ', but ' . get_called_class() . ' has no such field.' );
			}

			if ( !$this->fields[ $fieldName ]->hasBeenSet() ) {
				$this->_setValue( $fieldName, $value, $loaded );
			} else if ( is_array( $value ) && ( $rec = $this->{$fieldName} ) && $rec instanceof IRecord ) { // BaseDTRecordReference
				$this->fields[ $fieldName ]->fillUpValues( $value, $loaded );
			} else if ( !$loaded ) {
				$this->_setValue( $fieldName, $value, $loaded );
			} // else if ($loaded) -> values have already been set in ::get when constructing new record OR we already have such a record and don't want to override already set values
		}
	}

	protected static function getLoadableKeys() {
		return static::getAllKeys();
	}

	// TODO: automatically switch $loaded = false to TRY_TO_LOAD if at least one complete unique key or the primary key was passed?
	final public static function get( IRBStorage $storage, array $values = NULL, $loaded = true ) {
		$recordClass = get_called_class();

		if ( empty( $values ) ) {
			return new $recordClass( $storage );
		}

		// FIXME: indexing with record references should work with objects(records) as well		
//			$primaryFields = static::getPrimaryKeyFields();		
		$found = false;

		if ( isset( self::$records[ $recordClass ] ) ) {
			$keys = static::getAllKeys();

			foreach ( $keys as $keyName => $key ) {
				if ( $keyName != 'primary' && !$key[ 'unique' ] ) {
					continue; // skip non-unique, non-primary keys
				}

				unset( $pt );
				if ( !isset( self::$records[ $recordClass ][ $keyName ] ) ) {
					self::$records[ $recordClass ][ $keyName ] = array();
				}

				$pt =& self::$records[ $recordClass ][ $keyName ];
				$found = true;

				foreach ( $key[ 'fieldNames' ] as $field ) {

					if ( $pt === NULL ) {
						$pt = array();
					}

					if ( !isset( $values[ $field ] ) ) {
						$found = false;
						break;
					}

					$val = $values[ $field ];

					if ( is_array( $val ) ) {
						if ( isset( $val[ self::FIELDNAME_PRIMARY ] ) && ( $val[ self::FIELDNAME_PRIMARY ] !== '' ) ) {
							$val = $val[ self::FIELDNAME_PRIMARY ];
						} else {
							$found = false;
							break;
						}
					} else if ( $val instanceof IRecord ) {
						while ( $val instanceof IRecord && isset( $val->{self::FIELDNAME_PRIMARY} ) ) { // TODO: theoretically this could lead to an endless loop (not in practice)
							$val = $val->getFieldValue( self::FIELDNAME_PRIMARY );
						}

						if ( $val instanceof IRecord ) {
							$found = false;
							break;
						}
					}

					if ( is_float( $val ) ) { // cast float to string, as otherwise php will cast it to int, which would result in index collision for e.g. 48.3 and 48.4
						$val = (string)$val;
					}

					// don't check for existence here, it's created safely by referencing if it doesn't exist
					$pt =& $pt[ $val ];
				}

				if ( $found && $pt ) {
					break;
				}
			}
		}

		if ( !$found || !isset( $pt ) ) {
			if ( $loaded === self::TRY_TO_LOAD || !is_bool( $loaded ) ) {
				if ( $loaded !== self::TRY_TO_LOAD ) {
					$fields = $loaded;
					$loaded = self::TRY_TO_LOAD;
				} else {
					$fields = array_keys( $values );
				}

				// first check if we have at least one complete key to load
				$possibleKeys = static::getLoadableKeys();

				foreach ( $possibleKeys as $k => $possibleKey ) {
					if ( !$possibleKey[ 'unique' ] && $k != 'primary' ) {
						unset( $possibleKeys[ $k ] );
						continue;
					}

					foreach ( $possibleKey[ 'fieldNames' ] as $fieldName ) {
						if ( !isset( $values[ $fieldName ] ) ) {
							unset( $possibleKeys[ $k ] );
							break;
						}
					}
				}

				if ( $possibleKeys ) {
					$where = array();

					$i = 0;

					foreach ( $values as $k => $v ) {
						if ( $i > 0 ) {
							$where[ ] = 'AND';
						}

						if ( is_array( $v ) ) {
							if ( isset( $v[ self::FIELDNAME_PRIMARY ] ) && ( $v[ self::FIELDNAME_PRIMARY ] !== '' ) ) {
								$v = $v[ self::FIELDNAME_PRIMARY ];
							} else {
								$i = 0;
								break;
							}
						} else if ( $v instanceof IRecord ) {
							while ( $v instanceof IRecord && isset( $v->{self::FIELDNAME_PRIMARY} ) ) {
								$v = $v->getFieldValue( self::FIELDNAME_PRIMARY );
							}

							if ( $v instanceof IRecord ) {
								$i = 0;
								break;
							}
						}

						// TODO: only works with scalar values, should work with other values as well (might yield problems in case of complex record reference relations)
						array_push( $where, $k, '=', array( $v ) );

						$i++;
					}

					if ( $recordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) && is_array( $fields ) && !in_array( Record::FIELDNAME_PRIMARY, $fields ) ) {
						$fields[ ] = Record::FIELDNAME_PRIMARY;
					}

					if ( $i ) {
						$rec = $storage->selectFirstRecord( $recordClass, array( 'fields' => $fields, 'where' => $where ) );

						if ( $rec ) { // as storage might not load all values, we just fill them up (think about record references)
							$rec->fillUpValues( $values, true );
							return $rec;
						}

					}

				}

				$loaded = false;
			}

			return new $recordClass( $storage, $values, $loaded === true );
		}

		// TODO: shouldn't we just always override all values?
		$pt->fillUpValues( $values, $loaded === true );

		return $pt;
	}

	public static function getAllRecords() {
		if ( empty( self::$records ) ) {
			return array();
		}


		$getRecs = function ( array $from, array &$bucket ) use ( &$getRecs ) {
			foreach ( $from as $item ) {
				if ( $item instanceof IRecord ) {
					$bucket[ ] = $item;
				} elseif ( is_array( $item ) ) {
					$getRecs( $item, $bucket );
				}
			}
		};

		$recs = array();

		$getRecs( self::$records, $recs );

		return $recs;
	}


	public static function getRecordCount() {
		if ( empty( self::$records ) ) {
			return 0;
		}

		$getRecs = function ( array $from, &$count ) use ( &$getRecs ) {
			foreach ( $from as $item ) {
				if ( $item instanceof Irecord ) {
					$count++;
				} elseif ( is_array( $item ) ) {
					$getRecs( $item, $count );
				}
			}
		};

		$count = 0;

		$getRecs( self::$records, $count );

		return $count;
	}

	// pushIndex + popIndex can be used to keep memory usage in control when doing several large operations with many records
	public static final function pushIndex() {
		self::$oldIndex[] = self::$records;
	}

	public static final function popIndex() {
		if ( self::$oldIndex !== NULL ) {
			$popIndex = array_pop( self::$oldIndex );

			if ( $popIndex !== NULL ) {
				self::$records = $popIndex;
			}
			
			if ( !self::$oldIndex ) {
				self::$oldIndex = NULL;
			}
		}
	}

	public function removeFromIndex() {
		$recordClass = get_called_class();

		// remove indexing by primary key fields
		$keys = static::getAllKeys();

		foreach ( $keys as $keyName => $key ) {
			if ( !$key[ 'index' ] || ( $keyName !== 'primary' && !$key[ 'unique' ] ) ) {
				continue; // skip non-unique, non-primary keys
			}

			$fields = $key[ 'fieldNames' ];

			$pt =& self::$records[ $recordClass ][ $keyName ];

			$lastField = array_pop( $fields );
			$lastCol = $this->getColumnName( $lastField );

			foreach ( $fields as $field ) {
				$col = $this->getColumnName( $field );

				if ( $pt === NULL || !isset( $this->values[ $col ] ) ) {
					break;
				}

				$indexValue = $this->values[ $col ];

				if ( is_float( $indexValue ) ) { // cast float to string, as otherwise php will cast it to int, which would result in index collision for e.g. 48.3 and 48.4
					$indexValue = (string)$indexValue;
				}

				$pt =& $pt[ $indexValue ];

			}

			if ( isset( $this->values[ $lastCol ] ) && ( $lastVal = $this->values[ $lastCol ] ) !== NULL ) {
				if ( is_float( $lastVal ) ) { // cast float to string, as otherwise php will cast it to int, which would result in index collision for e.g. 48.3 and 48.4
					$lastVal = (string)$lastVal;
				}

				if ( isset( $pt[ $lastVal ] ) && $pt[ $lastVal ] === $this ) {
					unset( $pt[ $lastVal ] );
				}
			}
		}

		$this->indexed = false;
	}


	protected function index() {
		$recordClass = get_called_class();

		$keys = static::getAllKeys();

		if ( !isset( self::$records[ $recordClass ] ) ) {
			self::$records[ $recordClass ] = array();
		}

		foreach ( $keys as $keyName => $key ) {
			if ( !$key[ 'index' ] || ( $keyName !== 'primary' && !$key[ 'unique' ] ) ) {
				continue; // skip non-primary, non-unique keys
			}

			if ( !isset( self::$records[ $recordClass ][ $keyName ] ) ) {
				self::$records[ $recordClass ][ $keyName ] = array();
			}

			$pt =& self::$records[ $recordClass ][ $keyName ];

			foreach ( $key[ 'fieldNames' ] as $field ) {
				if ( $pt === NULL ) {
					$pt = array();
				}

				$col = $this->getColumnName( $field );

				if ( !isset( $this->values[ $col ] ) ) {
					break; // skip this key
				}

				$indexValue = $this->values[ $col ];

				if ( is_float( $indexValue ) ) { // cast float to string, as otherwise php will cast it to int, which would result in index collision for e.g. 48.3 and 48.4
					$indexValue = (string)$indexValue;
				}

				// don't check for existence here, it's created safely by referencing if it doesn't exist
				$pt =& $pt[ $indexValue ];
			}

			if ( !isset( $this->values[ $col ] ) ) {
				continue; // skip this key
			}

			if ( $pt === NULL ) {
				$pt = $this;
				$this->indexed = true;
			} else if ( $pt !== $this ) {
				throw new Exception( 'Indexing conflict for ' . get_called_class() . ' on key ' . $keyName . ' with values ' . Debug::getStringRepresentation( $this->values ) . ' ; other record has ' . Debug::getStringRepresentation( $pt->getValues() ) );
			}
		}
	}


// ------------- Constructor ------------ 

	// no lazy instancing of fields here as we can't do much without touching all fields (see beforeSave/beforeDelete/...) 
	protected function __construct( IRBStorage $storage, array $values = NULL, $loaded = true ) {
		$this->storage = $storage;

		$fieldDefs = static::getAllFieldDefinitions();

		foreach ( $fieldDefs as $fieldName => $fieldConfig ) {
			if ( !is_array( $fieldConfig ) ) {
				throw new InvalidDataTypeDefinitionException();
			}

			$dataTypeClassName = $fieldConfig[ 'dataType' ];

			if ( !class_exists( $dataTypeClassName ) ) {
				ClassFinder::find( array( $dataTypeClassName ), true );
			}

			$this->fields[ $fieldName ] = new $dataTypeClassName( $this->storage, $this, $this->values, $fieldName, $fieldConfig );
		}

		if ( $values !== NULL ) {
			$this->setValues( $values, $loaded );
		}
	}


	public function setStorage( IRBStorage $storage ) {
		if ( $storage === NULL ) {
			throw new Exception( '$storage may not be NULL.' );
		}

		if ( $storage !== $this->storage ) {
			$this->storage = $storage;

			foreach ( $this->fields as $field ) {
				/* @var $field IDataType */

				if ( $field->hasBeenSet() ) {
					$field->setDirty( true );
				}
			}
		}
	}

	public function getStorage() {
		return $this->storage;
	}

	protected function isIndexField( $fieldName ) {
		$keys = static::getAllKeys();

		foreach ( $keys as $keyName => $key ) {
			if ( $keyName != 'primary' && !$key[ 'unique' ] ) {
				continue; // skip non-unique, non-primary keys
			}

			if ( in_array( $fieldName, $key[ 'fieldNames' ], true ) ) {
				return true;
			}

		}

		return false;
	}

	/**
	 * Field value should always be set using this function internally
	 *
	 * @param string $fieldName
	 * @param mixed  $value
	 * @param bool   $loaded
	 *
	 * @throws InvalidArgumentException
	 */
	protected function _setValue( $fieldName, $value, $loaded = false ) {
		if ( !isset( $this->fields[ $fieldName ] ) ) {
			throw new InvalidArgumentException( '[' . get_called_class() . '] Invalid fieldname: "' . $fieldName . '"' );
		} else if ( $this->deleted ) {
			throw new LogicException( 'Trying to _setValue on deleted record of type ' . get_called_class() . ' with values ' . Debug::getStringRepresentation( $this->values ) );
		}

		$isIndexField = $this->isIndexField( $fieldName );

		if ( $this->indexed && $isIndexField ) {
			$this->removeFromIndex();
		}

		$this->fields[ $fieldName ]->setValue( $value, $loaded );

		if ( $isIndexField ) {
			$this->index();
		}

		if ( $loaded ) {
			$this->valuesLastLoaded[ $fieldName ] = $value;
		}
	}

	public function setValues( array $values, $loaded = false ) {
		// doesn't use _setValue anymore ; better performance, less recursion, and removes some problems with infinite recursion via notifications
		if ( $this->deleted ) {
			throw new LogicException( 'Trying to _setValue on deleted record of type ' . get_called_class() . ' with values ' . Debug::getStringRepresentation( $this->values ) );
		}

		// rearrange values so primary and primary key fields come first
		$vals = array_merge( array_intersect_key( $this->fields, $values ), $values );

		$uniqueKeyFields = array();
		$keys = static::getAllKeys();

		foreach ( $keys as $keyName => $key ) {
			if ( $keyName != 'primary' && !$key[ 'unique' ] ) {
				continue; // skip non-unique, non-primary keys
			}

			$uniqueKeyFields = array_merge( $uniqueKeyFields, $key[ 'fieldNames' ] );
		}

		$indexedField = false;

		foreach ( $vals as $fieldName => $value ) {
			if ( !isset( $this->fields[ $fieldName ] ) ) {
				throw new Exception( 'There is no field ' . get_called_class() . '->' . $fieldName );
			}


			if ( $indexedField === false ) {
				$indexedField = in_array( $fieldName, $uniqueKeyFields, true );
			}

			// need to remove as soon as we encounter one indexed field
			if ( $this->indexed && $indexedField ) {
				$this->removeFromIndex();
			}

			$this->fields[ $fieldName ]->setRawValue( $value, $loaded );

			// WRONG: need to reindex after each value, cause we might have a foreign reference with a reference to this record, which in turn would ::get a new Record resulting in an indexing conflict later on
			// -> this is a problem when changing values of multiple fields of a key, as between changing the values we might get an already taken key
			// FIX: setRawValue might never set anything but raw values		
		}

		if ( $indexedField ) {
			$this->index();
		}

		foreach ( $vals as $fieldName => $value ) {
			$this->fields[ $fieldName ]->setRealValue( $value, $loaded );
		}

		if ( $loaded ) {
			$this->valuesLastLoaded = array_merge( $this->valuesLastLoaded, $values );
		}
	}

	public function load( array $fieldNames = NULL ) {
		if ( $fieldNames === NULL ) {

			// don't load foreign references on generic load
			$fieldDefs = static::getOwnFieldDefinitions();

			$fieldNames = array_keys( $fieldDefs );
		} else {
			foreach ( $fieldNames as $k => $fieldName ) {
				if ( $fieldName === '*' ) {
					$fieldNames = array_merge( $fieldNames, array_keys( $this->getOwnFieldDefinitions() ) );
					$au = true;
					unset( $fieldNames[ $k ] );
					continue;
				}

//				if (!array_key_exists( $fieldName, $this->fields)) {
//					throw new InvalidFieldAccessException('Record of class "' . get_called_class() . '" has no field "' . $fieldName . '"');
//				}
			}

			if ( !empty( $au ) ) {
				$fieldNames = array_unique( $fieldNames );
			}
		}

		$notLoadedFields = array();

		foreach ( $fieldNames as $fieldName ) {
			if ( !isset( $this->fields[ $fieldName ] ) || !$this->fields[ $fieldName ]->hasBeenSet() ) { // in case of a path we don't check anything
				$notLoadedFields[ ] = $fieldName;
			}
		}

		if ( empty( $notLoadedFields ) ) { // nothing to do
			return $this;
		}

		$currentClass = get_called_class();

		// name + passing true for last addWhereIdentity parameter makes this query (not the result!) cached
		$queryStruct = array(
			'fields' => $notLoadedFields,
			'name' => 'Record_load_' . $currentClass . '_' . implode( '_', $notLoadedFields )
		);

		$this->addWhereIdentity( $queryStruct, false, true );

		$values = $this->storage->selectFirst( $currentClass, $queryStruct );

		if ( $values !== NULL ) {
			$this->setValues( array_intersect_key( $values, array_flip( $notLoadedFields ) ), true );
		}


		return $this;
	}


	public function exists() {
		// quick check without db access: do we have a datatype which was loaded?
		$ownFields = static::getOwnFieldDefinitions();

		foreach ( $ownFields as $fieldName => $fieldDef ) {
			if ( $this->fields[ $fieldName ]->hasBeenSet() && !$this->fields[ $fieldName ]->dirty ) {
				return true; // assume record exists as soon as at least one field is marked to be loaded from db
			}
		}

		// sure check with db access: check if record exists in db using primary fields
		$queryStruct = array( 'name' => get_called_class() . '->exists' );

		try {
			$this->addWhereIdentity( $queryStruct, false, true );
		} catch ( Exception $e ) {
			return false; // in case we're unable to load, this record doesn't exist
		}

		return (bool)$this->storage->select( get_called_class(), $queryStruct, 0, 0, true );
	}

	public function addWhereIdentity( array &$queryStruct, $rescueValues = false, $cachable = false ) {
		if ( $cachable ) {
			if ( !isset( $queryStruct[ 'vals' ] ) ) {
				$queryStruct[ 'vals' ] = array();
				$valCount = 0;
			} else {
				$valCount = count( $queryStruct[ 'vals' ] );
			}
		}

		if ( isset( $this->values[ self::FIELDNAME_PRIMARY ] ) ) {
			if ( $cachable ) {
				// TODO: merge possibly existing where 
				$queryStruct[ 'where' ] = array( self::FIELDNAME_PRIMARY, '=', '%' . ( ++$valCount ) . '$s' );
				$queryStruct[ 'vals' ][ ] = $this->values[ self::FIELDNAME_PRIMARY ];
				$queryStruct[ 'name' ] .= '_s_primary';
			} else {
				// TODO: merge possibly existing where
				$queryStruct[ 'where' ] = array( self::FIELDNAME_PRIMARY, '=', array( $this->values[ self::FIELDNAME_PRIMARY ] ) );
			}
		} else {
			$queryStruct[ 'where' ] = array();


			$keys = static::getAllKeys();

			// prefer primary key
			if ( $keys[ 'primary' ][ 'index' ] ) {
				$possibleKeys = array( $keys[ 'primary' ][ 'fieldNames' ] );
			}

			unset( $keys[ 'primary' ] );

			foreach ( $keys as $keyName => $keyDef ) {
				if ( $keyDef[ 'unique' ] ) {
					$possibleKeys[ ] = $keyDef[ 'fieldNames' ];
				}
			}

			foreach ( $possibleKeys as $fields ) {
				if ( $cachable ) {
					$addVals = array();
					$addValCount = $valCount;
					$addName = '_s';
				}


				foreach ( $fields as $fieldName ) {
					if ( !( $col = $this->getColumnName( $fieldName ) ) || !$this->fields[ $fieldName ]->hasValidValue() ) { // [JB 15.02.2013] implemented hasValidValue so we don't needlessly query db
						if ( !isset( $this->values[ $col ] ) && ( !$rescueValues || $this->fields[ $fieldName ]->rescueValue() === NULL ) ) {
							$missing = $fieldName;
							$queryStruct[ 'where' ] = array(); // TODO: handle possibly existing where
							break;
						}
					}

					$val = $rescueValues ? $this->fields[ $fieldName ]->rescueValue() : $this->values[ $col ];

					if ( $queryStruct[ 'where' ] ) {
						$queryStruct[ 'where' ][ ] = 'AND';
					}

					if ( $cachable ) {
						array_push( $queryStruct[ 'where' ], $fieldName, '=', '%' . ( ++$addValCount ) . '$s' );
						$addVals[ ] = $val;
						$addName .= '_' . $fieldName;
					} else {
						array_push( $queryStruct[ 'where' ], $fieldName, '=', array( $val ) );
					}
				}

				if ( $queryStruct[ 'where' ] ) { // TODO: handle possibly existing where
					break;
				}
			}

			if ( !$queryStruct[ 'where' ] ) {
				throw new Exception( 'Unable to add `where` identity for Record of class ' . get_called_class() . '; missing: ' . $missing . '; other values: ' . Debug::getStringRepresentation( $this->values ) );
			} else if ( $cachable ) {
				$queryStruct[ 'vals' ] = array_merge( $queryStruct[ 'vals' ], $addVals );
				$queryStruct[ 'name' ] .= $addName;
			}
		}
	}

	public function isDirty( $checkForeign = true ) {
		foreach ( $this->fields as $fieldName => $dataType ) {
			if ( ( $checkForeign || $dataType->colName ) && $dataType->dirty ) {
				return true;
			}
		}

		return false;
	}

	public function isSaving() {
		return $this->currentlySaving;
	}

	public function isDeleted() {
		return (bool)$this->deleted;
	}

	/**
	 * called before the record is saved, calls beforeSave() on each of its fields
	 */
	protected function beforeSave( $isUpdate, $isFirst ) {
		if ( isset( Record::$trackedFields ) ) {
			while ( $field = array_shift( $this->beforeSaveFields ) ) {
				self::$currentSavePath[ ] = $field->getFieldName();

				$field->beforeSave( $isUpdate );

				array_pop( self::$currentSavePath );
			}
		} else {
			while ( $field = array_shift( $this->beforeSaveFields ) ) {
				$field->beforeSave( $isUpdate );
			}
		}

	}

	/**
	 * called after the record has been saved, calls afterSave() on each of its fields
	 */
	protected function afterSave( $isUpdate, $isFirst, array $saveResult ) {
		if ( isset( Record::$trackedFields ) ) {
			while ( $field = array_shift( $this->afterSaveFields ) ) {
				self::$currentSavePath[ ] = $field->getFieldName();

				$field->afterSave( $isUpdate, $saveResult );
				array_pop( self::$currentSavePath );
			}
		} else {
			while ( $field = array_shift( $this->afterSaveFields ) ) {
				$field->afterSave( $isUpdate, $saveResult );
			}
		}
	}

	protected function setDefaults( $isFirst, array $saveResult ) {
		if ( !$this->autoIncSet && !empty( $saveResult[ 'insertID' ] ) ) {
			$autoIncField = static::getAutoIncrementField();

// 13.12.2013 Johannes Beranek : changed hasBeenSet() check to isset(), so we also set the autoIncField if it has been set to NULL before
			if ( $autoIncField !== NULL && !isset( $this->{$autoIncField} ) ) {
				// $autIncField has to be a field with a column on this record, so we can send the value directly without using _setValue
				$this->fields[ $autoIncField ]->setRawValue( $saveResult[ 'insertID' ], true );
			}

			$this->autoIncSet = true;
		}

		while ( $field = array_shift( $this->setDefaultFields ) ) {
			$field->setDefault( $saveResult );
		}
	}

	protected function _save() {
		return $this->storage->save( $this, $this->saveIsUpdate ); // FIXME: does this do dirty check?
	}

	public function scheduleCheckOnSaveComplete() {
		if ( !in_array( $this, self::$notifyOnSaveComplete, true ) ) {
			self::$notifyOnSaveComplete[ ] = $this;
		}
	}

	public function notifySaveComplete() {
		if ( $this->deleteUnreferencedOnSaveFinish ) {
			if ( !$this->satisfyRequireReferences() ) {
				$this->delete( $this->deleteBasket );
			}

			$this->deleteUnreferencedOnSaveFinish = false;
			unset( $this->deleteBasket );
		}

		if (!$this->isDeleted()) {
			foreach ( $this->fields as $fieldName => $dt ) {
				$dt->notifySaveComplete();
			}
		}
	}

	public function checkForDelete() {
		foreach ( $this->fields as $fieldName => $dt ) {
			if ( $dt->checkForDelete() ) {
				$this->delete();
				return true;
			}
		}

		return false;
	}

	protected function saveInit() {
		$currentClass = get_called_class();

		if ( $this->deleted ) {
			throw new Exception( 'Trying to save deleted record of type ' . $currentClass . ' with values ' . Debug::getStringRepresentation( $this->values ) );
		}

		// save path tracking to better fix errors automatically


		$this->beforeSaveFields = $this->fields;
		$this->updateDirtyAfterSaveFields = $this->fields;
		$this->afterSaveFields = $this->fields;

		$this->saveIsUpdate = $this->exists();

		if ( !$this->saveIsUpdate ) {
			$this->setDefaultFields = $this->fields;
		}

		$this->hasSaved = false;
		$this->autoIncSet = false;

		if ( self::$saveOriginRecord === NULL ) {
			self::$saveOriginRecord = $this;
			self::$notifyOnSaveComplete = array();
			self::$saveValueLock = array();

			if ( isset( self::$trackedFields ) ) {
				self::$currentSavePath = array();
			}
		}


		// prevent save jumping to different live or language values		
		if ( !isset( self::$saveValueLock[ 'DTSteroidLive' ] ) && ( $liveFieldName = $this->getDataTypeFieldName( 'DTSteroidLive' ) ) !== NULL && $this->isReadable( $liveFieldName ) ) {
			self::$saveValueLock[ 'DTSteroidLive' ] = $this->getFieldValue( $liveFieldName );
		}

		if ( !isset( self::$saveValueLock[ 'DTSteroidLanguage' ] ) && ( $languageFieldName = $this->getDataTypeFieldName( 'DTSteroidLanguage' ) ) !== NULL && $this->isReadable( $languageFieldName ) ) {
			self::$saveValueLock[ 'DTSteroidLanguage' ] = $this->getFieldValue( $languageFieldName );
		}


		// @Hook: IRecordHookBeforeSave
		foreach ( self::$hookBeforeSave as $hook ) {
			$hook->recordHookBeforeSave( $this->storage, $this, $this->saveIsUpdate );
		}

		if ( !empty( self::$hookBeforeSaveByRecordClass[ $currentClass ] ) ) {
			foreach ( self::$hookBeforeSaveByRecordClass[ $currentClass ] as $hook ) {
				$hook->recordHookBeforeSave( $this->storage, $this, $this->saveIsUpdate );
			}
		}
	}

	protected function saveComplete() {
		$currentClass = get_called_class();

		// @Hook: IRecordHookAfterSave			
		foreach ( self::$hookAfterSave as $hook ) {
			$hook->recordHookAfterSave( $this->storage, $this, $this->saveIsUpdate );
		}

		if ( !empty( self::$hookAfterSaveByRecordClass[ $currentClass ] ) ) {
			foreach ( self::$hookAfterSaveByRecordClass[ $currentClass ] as $hook ) {
				$hook->recordHookAfterSave( $this->storage, $this, $this->saveIsUpdate );
			}
		}
	}

	public function save() {


		// [beranek_johannes] 06.08.2014: added check for $this->currentlySaving to prevent 
		// change in skipSave and readOnly while saving to influence saving
		if ( !$this->currentlySaving ) {
			if ( !empty( $this->skipSave ) || !empty( $this->readOnly ) ) {
				return $this;
			}

			if ( self::$saveValueLock !== NULL ) {
				$primaryField = $this->getDataTypeFieldName( 'DTSteroidPrimary' );

				foreach ( self::$saveValueLock as $dataType => $value ) {
					$field = $this->getDataTypeFieldName( $dataType );

					if ( $field !== NULL ) {
						if ( $this->isReadable( $field ) && ( $readValue = $this->getFieldValue( $field ) ) !== NULL ) {
							if ( $readValue !== $value ) {
								// short circuit in case values do not match
								return $this;
							}
						} else if ( $primaryField !== NULL ) {
							// try to match via primary field value interpolation
							$interpolatedValue = $this->fields[ $primaryField ]->interpolateValue( $dataType );

							if ( $interpolatedValue !== NULL && $interpolatedValue !== $value ) {
								// short circuit in case values do not match
								return $this;
							}
						}
					}
				}


			}
		}


		$isFirst = !$this->currentlySaving;

		if ( $isFirst ) { // prevent recursion loop
			$this->currentlySaving = true;

			$this->saveInit();
		}


		if ( $this->beforeSaveFields ) {
			// parameters after the first 2 are superfluous but should help when debugging
			$this->beforeSave( $this->saveIsUpdate, $isFirst, get_called_class() );
		}

		if ( !$this->hasSaved ) {
			// [JB 21.5.2013] record may have lost part of its primary key, in which case record will be deleted afterwards and there is no reason to save the record anymore
			$maySave = true;

			foreach ( $this->fields as $field ) {
				if ( !$field->recordMaySave() ) {
					$maySave = false;
					break;
				}
			}

			if ( $maySave ) {
				$this->saveResult = $this->_save();
			} else {
				$this->saveResult = array( 'action' => RBStorage::SAVE_ACTION_NONE, 'affectedRows' => 0 );
				// TODO: should we cancel setDefaultFields && updateDirtyAfterSaveFields here?
			}

			$this->hasSaved = true;
		}

		if ( $this->setDefaultFields ) {
			$this->setDefaults( $isFirst, $this->saveResult );
		}

		if ( $this->updateDirtyAfterSaveFields ) {
			while ( $field = array_shift( $this->updateDirtyAfterSaveFields ) ) {
				$field->updateDirtyAfterSave();
			}
		}

		if ( $this->afterSaveFields ) {
			// parameters after the first 3 are superfluous but should help when debugging
			$this->afterSave( $this->saveIsUpdate, $isFirst, $this->saveResult, get_called_class() );
		}

		if ( $isFirst ) {
			$this->currentlySaving = false;
			$this->beforeSaveFields = NULL;
			$this->afterSaveFields = NULL;
			$this->setDefaultFields = NULL;
			$this->updateDirtyAfterSaveFields = NULL;
			// $this->saveIsUpdate = NULL; // we keep this for after save hooks
			$this->hasSaved = NULL;

			if ( self::$saveOriginRecord === $this ) {
				self::$saveOriginRecord = NULL;

				// while ( self::$notifyOnSaveComplete && ( $rec = array_shift( self::$notifyOnSaveComplete ) ) ) {
				while ( self::$notifyOnSaveComplete && ( $rec = array_pop( self::$notifyOnSaveComplete ) ) ) {
					$rec->notifySaveComplete();
				}

				self::$notifyOnSaveComplete = NULL;
				self::$saveValueLock = NULL;
			}

			$this->saveComplete();

		}

		return $this;
	}

	protected function _delete() {
		$this->storage->deleteRecord( $this );

		$this->deleted = true;
		
		// remove from index 		
		$this->removeFromIndex();		
	}

	/**
	 * Delete record
	 *
	 * Deletes record and other records dependent on this record if passed $basket is null,
	 * otherwise simulates deletion and adds records which would have been deleted to passed $basket
	 *
	 * @param array $basket
	 */
	final public function delete( array &$basket = NULL ) {
		if ( !empty( $this->skipDelete ) || !empty( $this->readOnly ) ) return;

		if ( $this->isDeleting ) {
			return;
		}

		if ( $this->deleted ) {
			throw new Exception( 'Trying to delete already deleted record of type ' . get_called_class() . ' with values ' . Debug::getStringRepresentation( $this->values ) );
		}

		if ( $basket !== NULL ) {
			$basket[ ] = $this;
		}

		$this->isDeleting = true;

		$this->beforeDelete( $basket );

		if ( $basket === NULL ) {
			$this->_delete();
		}

		$this->afterDelete( $basket );

		$this->isDeleting = false;
	}

	public function fillCalculationFields() {
		$calculationParts = array();
		$calculationFields = array();

		foreach ( $this->fields as $fieldName => $dataType ) {
			if ( $dataType instanceof IContributeBitShift ) {
				$dataType->fillBitShiftCalculationParts( $calculationParts );
			}
		}

		foreach ( $this->fields as $fieldName => $dataType ) {
			if ( $dataType instanceof IUseBitShift ) {
				$dataType->getBitShiftCalculation( $calculationParts, $calculationFields );
			}
		}

		return $calculationFields;
	}

	public function fieldHasBeenSet( $fieldName ) {
		return $this->fields[ $fieldName ]->hasBeenSet();
	}

	public function isReadable( $fieldName ) {
		return $this->fields[ $fieldName ]->hasBeenSet() || $this->exists();
	}

	public function __isset( $fieldName ) { // TODO: add magic __isset method to datatypes
		return ( isset( $this->fields[ $fieldName ] ) && $this->fields[ $fieldName ]->hasBeenSet() && $this->fields[ $fieldName ]->getValue() !== NULL );
	}


	public function __get( $fieldName ) {
		return $this->getFieldValue( $fieldName );
	}

	public function getFieldValue( $fieldName, $lazyLoadAll = false ) {
		if ( $this->deleted ) {
			throw new Exception( 'Trying to access deleted record' );
		}
		
		if ( !isset( $this->fields[ $fieldName ] ) ) {
			throw new InvalidFieldAccessException( 'Record of class "' . get_called_class() . '" has no field "' . $fieldName . '"' );
		}

		// Helper for performance optimizations
		if ( isset( Record::$trackedFields ) ) {
			if ( !is_array( Record::$currentPath ) ) {
				Record::$currentPath = array();
			}

			if ( !Record::$currentPath ) {
				Record::$currentPath[ ] = get_called_class();
			}

			array_push( Record::$currentPath, $fieldName );

			$path = implode( '.', Record::$currentPath );

			if ( $this->path !== NULL ) {
				$path = explode( '.', $path );
				array_shift( $path );
				$path = $this->path . '.' . implode( '.', $path );
			}
		}

		if ( !$this->fields[ $fieldName ]->hasBeenSet() ) { // load will throw exception anyway if we're unable to load
			if ( isset( $path ) ) {
				if ( !isset( Record::$trackedFields[ 'ref' ][ $path ] ) ) {
					Record::$trackedFields[ 'ref' ][ $path ] = 'unloaded';
				}
			}

			if ( $lazyLoadAll ) {
				if ( $lazyLoadAll === true ) {
					$this->load( array( '*', $fieldName ) );
				} else {
					if ( !in_array( $fieldName, $lazyLoadAll ) ) {
						$lazyLoadAll[ ] = $fieldName;
					}

					$this->load( $lazyLoadAll );
				}
			} else {
				$this->load( array( $fieldName ) );
			}


		} else if ( isset( $path ) ) {
			if ( !isset( Record::$trackedFields[ 'ref' ][ $path ] ) ) {
				Record::$trackedFields[ 'ref' ][ $path ] = 'loaded';
			}

		}

		$value = $this->fields[ $fieldName ]->getValue();

		// Helper for performance optimizations
		if ( isset( Record::$trackedFields ) ) {
			if ( $value instanceof Record ) {
				$value->path = $path;
			}

			array_pop( Record::$currentPath );

			if ( count( Record::$currentPath ) === 1 ) {
				Record::$currentPath = array();
			}
		}


		return $value;
	}

	public function collect( $path, $hardFail = false ) {
		if ( !$path ) throw new Exception( 'You need to provide a path to collect records.' );

		$pathParts = is_array( $path ) ? $path : explode( '.', $path );

		$stack = array( $this );

		foreach ( $pathParts as $pathPart ) {
			$newStack = array();

			foreach ( $stack as $item ) {
				if ( $item instanceof IRecord ) {
					$collected = $item->getFieldValue( $pathPart );

					if ( is_array( $collected ) ) {
						if ( $collected ) {
							$newStack = array_merge( $newStack, array_values( $collected ) );
						} elseif ( $hardFail ) {
							throw new Exception( get_class( $item ) . ' with values ' . Debug::getStringRepresentation( $item->getValues() ) . ' has no values in ' . $pathPart );
						}
					} else {

						$newStack[ ] = $collected;
					}
				} elseif ( $hardFail ) {
					throw new Exception( get_class( $item ) . ' with values ' . Debug::getStringRepresentation( $item->getValues() ) . ' has no values in ' . $pathPart );
				}
			}

			if ( !$newStack ) break;

			$stack = $newStack;
		}

		return $newStack;
	}

	public function __set( $fieldName, $value ) {
		$this->_setValue( $fieldName, $value );
	}

	public function getValues() {
		return $this->values;
	}

	public function getFormValues( array $fields ) {
		$ret = array();

		$fields = array_merge( $fields, array_keys( static::getTitleFieldsCached() ) );

		if ( $sortingField = static::getDataTypeFieldName( 'DTSteroidSorting' ) ) {
			$fields = array_merge( $fields, array( $sortingField ) );
		}

		if ( static::fieldDefinitionExists( 'primary' ) ) {
			$fields = array_merge( $fields, array( static::FIELDNAME_PRIMARY ) );
		}

		if ( $this->exists() ) {
			$this->load( $fields );
		}

		foreach ( $fields as $field ) {
			$ret[ $field ] = $this->fields[ $field ]->getFormValue();
		}

		$ret[ '_liveStatus' ] = $this->getLiveStatus();

		return $ret;
	}


	public function getFieldNameOfDataType( $dataTypeClass = NULL ) {

		if ( empty( $dataTypeClass ) || !( $dataTypeClass instanceof IDataType ) ) {
			throw new InvalidArgumentException( '$dataTypeClass must be set and be an instance of IDataType' );
		}

		foreach ( $this->fields as $fieldName => $dataType ) {
			if ( get_class( $dataType ) == $dataTypeClass ) {
				return $fieldName;
			}
		}

		return NULL;
	}

	public static function getAutoIncrementField() {
		static $autoIncrementField = false;

		if ( $autoIncrementField === false ) {
			$autoIncrementField = NULL;

			$fieldDefs = static::getOwnFieldDefinitions();

			foreach ( $fieldDefs as $fieldName => $fieldDef ) {
				if ( isset( $fieldDef[ 'autoInc' ] ) && $fieldDef[ 'autoInc' ] ) {
					$autoIncrementField = $fieldName;
				}
			}
		}

		return $autoIncrementField;
	}

	protected function requireReferences() {
		$fieldDefinitions = static::getForeignReferences();

		foreach ( $fieldDefinitions as $fieldDef ) {
			if ( isset( $fieldDef[ 'constraints' ] ) && isset( $fieldDef[ 'constraints' ][ 'min' ] ) && ( (int)$fieldDef[ 'constraints' ][ 'min' ] ) > 0 ) {
				// this way we can distinguish between "require any one reference" and "require constraints met"
				// return val true means any one reference, 1 = check constraints
				return 1;
			}
		}

		return false;
	}

	protected function satisfyRequireReferences() {
		$requireReferences = $this->requireReferences();

		if ( $requireReferences === 1 ) { // check constraints
			// TODO: what to do if we exceed constraint max setting? throw exception?

			$fieldDefinitions = static::getForeignReferences();
			$fields = array_intersect_key( $this->fields, $fieldDefinitions );

			$ret = true;

			foreach ( $fields as $fieldName => $field ) {
				$fd = $fieldDefinitions[ $fieldName ];
				$ct = NULL;

				if ( isset( $fd[ 'constraints' ] ) ) {
					$constraints = $fd[ 'constraints' ];

					if ( isset( $constraints[ 'min' ] ) ) {
						$min = (int)$constraints[ 'min' ];

						if ( $min > 0 ) {
							$ct = $field->getReferenceCount();

							if ( $ct < $min ) {
								return false;
							}
						}
					}

					if ( isset( $constraints[ 'max' ] ) ) {
						$max = (int)$constraints[ 'max' ];

						// ignore erroneous max <= 0
						if ( $max > 0 ) {
							if ( !isset( $ct ) ) {
								$ct = $field->getReferenceCount();
							}

							if ( $ct > $max ) {
								throw new Exception( 'Exceeding set max constraint' );
							}
						}
					}
				}
			}
		} else {
			$ret = $this->getReferenceCount( NULL, true );
		}

		return $ret;
	}

	public function getReferenceCount( array $fields = NULL, $returnBool = false ) {
		$referenceCount = 0;

		if ( $fields === NULL ) {
			$fieldDefinitions = static::getForeignReferences();
			$fields = array_intersect_key( $this->fields, $fieldDefinitions );
		}

		foreach ( $fields as $fieldName => $field ) {
			$referenceCount += $field->getReferenceCount();

			if ( $referenceCount > 0 && $returnBool ) {
				return true;
			}
		}


		return $returnBool ? false : $referenceCount;
	}

	public function notifyReferenceRemoved( IRecord $originRecord, $reflectingFieldName, $triggeringFunction, array &$basket = NULL ) {
		if ( $reflectingFieldName && isset( $this->fields[ $reflectingFieldName ] ) ) { // filter for dynamic references
			$this->fields[ $reflectingFieldName ]->notifyReferenceRemoved( $originRecord, $triggeringFunction, $basket );
		}

		// BaseDTRecordReference notifies from doNotifications and beforeDelete
		// BaseDTForeignReference notifies from setValue
		if ( $triggeringFunction == 'beforeDelete' && $this->requireReferences() ) {
			if ( self::$saveOriginRecord ) { // triggered by saving, so we might actually get a new reference later on
				if ( !in_array( $this, self::$notifyOnSaveComplete, true ) ) {
					self::$notifyOnSaveComplete[ ] = $this;
				}

				$this->deleteUnreferencedOnSaveFinish = true;

				//
				$this->deleteBasket =& $basket; // need to keep reference to basket for later deletion
			} else if ( !$this->satisfyRequireReferences() ) { // purely triggered by deletion, so delete right away
				$this->delete( $basket );
			}
		}
	}

	public function notifyReferenceAdded( IRecord $originRecord, $reflectingFieldName, $loaded ) {
		if ( $reflectingFieldName && isset( $this->fields[ $reflectingFieldName ] ) ) {
			$this->fields[ $reflectingFieldName ]->notifyReferenceAdded( $originRecord, $loaded );
		}
	}

	public function refreshField( $fieldName ) {
		$isIndexField = $this->isIndexField( $fieldName );

		if ( $this->indexed && $isIndexField ) {
			$this->removeFromIndex();
		}

		$this->fields[ $fieldName ]->refresh();

		if ( $isIndexField ) {
			$this->index();
		}
	}

	/**
	 * called before the record is deleted, calls beforeDelete() on each of its fields
	 */
	protected function beforeDelete( array &$basket = NULL ) {
		$currentClass = get_called_class();

		// @Hook: before delete
		foreach ( self::$hookBeforeDelete as $hook ) {
			$hook->recordHookBeforeDelete( $this->storage, $this, $basket );
		}

		if ( !empty( self::$hookBeforeDeleteByRecordClass[ $currentClass ] ) ) {
			foreach ( self::$hookBeforeDeleteByRecordClass[ $currentClass ] as $hook ) {
				$hook->recordHookBeforeDelete( $this->storage, $this, $basket );
			}
		}

		$beforeDeleteFields = $this->getBeforeDeleteFields();

		foreach ( $beforeDeleteFields as $fieldName ) {
			$this->fields[ $fieldName ]->beforeDelete( $basket );
		}
	}

	protected function getBeforeDeleteFields() {
		return array_keys( $this->fields );
	}
	


	/**
	 * called after the record has been deleted, calls afterDelete() on each of its fields
	 */
	protected function afterDelete( array &$basket = NULL ) {
		foreach ( $this->fields as $field ) {
			$field->afterDelete( $basket );
		}


		
		
		// FIXME: move hooks to own function to make it easier to correctly override this function

		// @Hook: after delete
		foreach ( self::$hookAfterDelete as $hook ) {
			$hook->recordHookAfterDelete( $this->storage, $this, $basket );
		}

		$currentClass = get_called_class();

		if ( !empty( self::$hookAfterDeleteByRecordClass[ $currentClass ] ) ) {
			foreach ( self::$hookAfterDeleteByRecordClass[ $currentClass ] as $hook ) {
				$hook->recordHookAfterDelete( $this->storage, $this, $basket );
			}
		}
		
		
		if ( $basket === NULL ) {
			$this->cleanup();
		
			// free mem every so often
			self::$recordsDeletedSinceLastGCCollectCycles++; 
			
			if ( self::$recordsDeletedSinceLastGCCollectCycles >= self::$runGCCollectCyclesAfterRecordsDeletedNum ) {
				gc_collect_cycles();
				self::$recordsDeletedSinceLastGCCollectCycles = 0;
			}
		}
	}

	public function getTitle() {
		$titleFields = $this->getTitleFieldsCached();
		$fieldDefs = static::getAllFieldDefinitions();

		$titleParts = array();

		foreach ( $titleFields as $fieldName => $subFields ) {
			$dt = $fieldDefs[ $fieldName ][ 'dataType' ];
			if ( is_subclass_of( $dt, 'BaseDTRecordReference' ) ) {
				if ( !$this->{$fieldName} ) {
					continue;
//					throw new Exception( 'Cannot get title of ' . get_class($this) . ' with missing ' . $fieldName ); // removed as it causes problems with records where not all title fields are required
				}
				$titleParts[ ] = $this->{$fieldName}->getTitle();
			} else {
				$titleParts[ ] = $this->{$fieldName};
			}
		}

		return implode( ' ', $titleParts );
	}

	// if implemented, should be used to return human readable, searchable title (no primary ints or the like!)
	public function getStringTitle() {
		if ( isset( $this->fields[ 'title' ] ) ) {
			return $this->getFieldValue( 'title' );
		}

		return NULL;
	}


	protected function getCopiedForeignFields() {
		$fields = array_intersect( static::getEditableFormFieldsCached(), array_keys( static::getForeignReferences() ) );

		return $fields;
	}

	public function getCopiedRecord() {
		return $this->copiedRecord;
	}

	public function getCopyableReferences( array $changes = NULL ) {
		$records = array();

		$foreignReferences = static::getForeignReferences();

		foreach ( $foreignReferences as $fieldName => $fieldDef ) {
			$foreignRecordClass = $fieldDef[ 'recordClass' ];

			$foreignRequired = isset( $fieldDef[ 'constraints' ] ) && isset( $fieldDef[ 'constraints' ][ 'min' ] ) && $fieldDef[ 'constraints' ][ 'min' ] > 0;

			// ignore dynamic references and widgets
			if ( !$foreignRequired && $fieldDef[ 'dataType' ] == 'DTForeignReference' && $fieldDef[ 'requireSelf' ] && $foreignRecordClass::BACKEND_TYPE !== Record::BACKEND_TYPE_WIDGET ) {
				$vals = $this->getFieldValue( $fieldName );

// FIXME: why differentiate between "join records" and other records ?
				$isJoinRecord = !$foreignRecordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY );

				foreach ( $vals as $val ) {
					if ( $isJoinRecord ) {
						$missingReferences = array();

						$nval = $val->copy( $changes, $missingReferences );

						$records = array_merge( $records, $missingReferences );
					} else {
						$nval = $val->getFamilyMember( $changes );

						if ( !$nval->exists() ) {
							$records[ ] = $val;
						}
					}
				}
			}
		}

		return $records;
	}

	public function copy( array $changes, array &$missingReferences, array &$originRecords = NULL, array &$copiedRecords = NULL, array $skipFields = NULL, array &$originValues = NULL, $originFieldName = NULL ) {
		$isEntryPoint = !$this->isCopying;

//		if ( $isEntryPoint && self::$copyOriginRecord === NULL ) {
//			self::$copyOriginRecord = $this;
//			$recordsToBeCopied = array( $this );
//
//			$this->getFormRecords( $recordsToBeCopied, array_keys( $this->getFormFields( $this->storage ) ) );
//
//			foreach ( $recordsToBeCopied as $record ) {
//				$record->setMeta( 'doCopy', true );
//				$record->readOnly = true;
//			}
//		}
//
//		if ( self::$copyOriginRecord !== $this && !$this->getMeta( 'doCopy' ) ) {
//			$copiedRecord = $this->getFamilyMember( $changes );
//
//			if ( $copiedRecord->exists() ) {
//				$this->copiedRecord = $copiedRecord;
//				return $this->copiedRecord;
//			}
//		}

		if ( !$this->isCopying ) {
			$this->copiedIdentityValues = array();
			$this->copiedValues = array();
			$this->copiedForeignValues = array();

			// make sure primary key fields are the first ones
//			$this->copyIdentityFields = static::getPrimaryKeyFields();
//			$this->copyIdentityFields = static::getUniqueKeyFields();
			$this->copyIdentityFields = array_unique( array_merge( static::getPrimaryKeyFields(), static::getUniqueKeyFields() ) );

			$this->copyIdentityFieldsBackup = $this->copyIdentityFields;
			$this->copiedKeysForIdentity = array_keys( static::getUniqueKeys() ); // array( 'primary' );

			// useless to put FIELDNAME_PRIMARY into identity fields at start, as it's either an auto_increment int which won't copy, or a compute DTSteroidPrimary, which won't copy either

			// then come our own field definitions
			$this->copyFields = array_keys( $this->getOwnFieldDefinitions() );

			foreach ( $this->copyFields as $k => $fieldName ) {
				if ( in_array( $fieldName, $this->copyIdentityFields, true ) ) {
					unset( $this->copyFields[ $k ] );
				}
			}

			// and last but not least: foreign references
			$this->copyForeignFields = $this->getCopiedForeignFields();

			$this->isCopying = true;

			// do not use one of these for determining entrypoint, as they may be passed as empty arrays!
			if ( $originRecords === NULL ) {
				$originRecords = array();
			}

			if ( $copiedRecords === NULL ) {
				$copiedRecords = array();
			}
		}

		if ( !empty( $changes ) || !$this->fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ) {
			while ( $fieldName = array_shift( $this->copyIdentityFields ) ) {

				$this->fields[ $fieldName ]->copy( $this->copiedIdentityValues, $changes, $missingReferences, $originRecords, $copiedRecords );

				if ( !isset( $this->copiedIdentityValues[ $fieldName ] ) ) {
					$this->fields[ $fieldName ]->earlyCopy( $this->copiedIdentityValues, $changes, $missingReferences, $originRecords, $copiedRecords );

					if ( !isset( $this->copiedIdentityValues[ $fieldName ] ) ) {
						$keys = static::getLoadableKeys();

						foreach ( $keys as $keyName => $key ) {
							if ( !$key[ 'unique' ] || in_array( $keyName, $this->copiedKeysForIdentity, true ) ) { // skip non unique key (primary key can be skipped anyway as it's been added from the start)
								continue;
							}

							foreach ( $key[ 'fieldNames' ] as $field ) {
								if ( in_array( $field, $this->copyIdentityFieldsBackup, true ) ) { // don't readd fields
									continue;
								}

								$this->copyIdentityFields[ ] = $field;
								$this->copyIdentityFieldsBackup[ ] = $field;
							}

							break;
						}
					}
				}
			}
		}

		if ( $this->copiedRecord === NULL ) {
			$this->copiedRecord = static::get( $this->storage, $this->copiedIdentityValues, self::TRY_TO_LOAD );

			if ( $this->copiedRecord->exists() ) {
				$this->copiedRecord->load( array_merge( $this->copyIdentityFieldsBackup, $this->copyFields, $this->copyForeignFields ) );
			}

			$originRecords[ ] = $this;
			$copiedRecords[ ] = $this->copiedRecord;

			if ( $originFieldName !== NULL && $originValues !== NULL ) {
				$originValues[ $originFieldName ] = $this->copiedRecord;
			}

			$this->copiedIdentityValues = NULL;
		}

		if ( $this->copiedIdentityValues && $this->copiedRecord ) {
			$this->copiedRecord->setValues( $this->copiedIdentityValues );
			$this->copiedIdentityValues = NULL;
		}

		while ( $fieldName = array_shift( $this->copyFields ) ) {
			if ( $skipFields === NULL || !in_array( $fieldName, $skipFields, true ) ) {
				$this->fields[ $fieldName ]->copy( $this->copiedValues, $changes, $missingReferences, $originRecords, $copiedRecords );
			}
		}

		if ( $this->copiedValues ) {
			$this->copiedRecord->setValues( $this->copiedValues );
			$this->copiedValues = NULL;
		}


		while ( $fieldName = array_shift( $this->copyForeignFields ) ) {
			if ( $skipFields === NULL || !in_array( $fieldName, $skipFields, true ) ) {
				$this->fields[ $fieldName ]->copy( $this->copiedForeignValues, $changes, $missingReferences, $originRecords, $copiedRecords );
			}
		}

		if ( $this->copiedForeignValues ) {
			$this->copiedRecord->setValues( $this->copiedForeignValues );
			$this->copiedForeignValues = NULL;
		}

		$copiedRecord = $this->copiedRecord;

		if ( $isEntryPoint ) {
			$this->isCopying = false;
			$this->copyFields = NULL;
			$this->copyForeignFields = NULL;
			$this->copyIdentityFields = NULL;
			$this->copiedRecord = NULL;
		}

		return $copiedRecord;
	}

	public function getFamilyMember( array $changes ) {
		if ( ( $idField = $this->getDataTypeFieldName( 'DTSteroidID' ) ) && ( isset( $changes[ 'live' ] ) || isset( $changes[ 'language' ] ) ) ) {
			$values = array( $idField => $this->getFieldValue( $idField ) );

			$isSame = true;

			if ( $primaryField = $this->getDataTypeFieldName( 'DTSteroidPrimary ' ) ) {
				$values[ $primaryField ] = $values[ $idField ];
			}

			if ( $liveField = $this->getDataTypeFieldName( 'DTSteroidLive' ) ) {
				$values[ $liveField ] = isset( $changes[ 'live' ] ) ? $changes[ 'live' ] : $this->getFieldValue( $liveField );

				if ( isset( $changes[ 'live' ] ) && $changes[ 'live' ] != $this->getFieldValue( $liveField ) ) {
					$isSame = false;
				}

				if ( $primaryField ) {
					$values[ $primaryField ] |= ( $values[ $liveField ] & DTSteroidLive::FIELD_BIT_WIDTH ) << DTSteroidLive::FIELD_BIT_POSITION;
				}
			}

			if ( $languageField = $this->getDataTypeFieldName( 'DTSteroidLanguage' ) ) {
				$values[ $languageField ] = isset( $changes[ 'language' ] ) ? $changes[ 'language' ] : $this->getFieldValue( $languageField );

				if ( isset( $changes[ 'live' ] ) ) {
					$values[ $languageField ] = $this->{$languageField}->getFamilyMember( $changes );
				}

				if ( $isSame && isset( $changes[ 'language' ] ) && $changes[ 'language' ] !== $this->getFieldValue( $languageField ) ) { // TODO: are we always gonna get a language record passed?
					$isSame = false;
				}

				if ( $primaryField ) {
					$values[ $primaryField ] |= ( $values[ $languageField ] & DTSteroidLanguage::FIELD_BIT_WIDTH ) << DTSteroidLanguage::FIELD_BIT_POSITION;
				}
			}

			if ( !$isSame ) {
				return static::get( $this->storage, $values, self::TRY_TO_LOAD );
			}
		} else {
			//check primary key for record references and call getFamilyMember() on them
			$keyFields = $this->getUniqueKeyFields();
			$newVals = array();

			foreach ( $keyFields as $fieldName ) {
				$fieldDef = $this->getFieldDefinition( $fieldName );

				if ( is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTRecordReference' ) ) {
					$nval = $this->getFieldValue( $fieldName );

					$newVals[ $fieldName ] = $nval->getFamilyMember( $changes ); //->{Record::FIELDNAME_PRIMARY};
				} else {
					$missingReferences = array();
					$originRecords = array();
					$copiedRecords = array();

					$this->fields[ $fieldName ]->copy( $newVals, $changes, $missingReferences, $originRecords, $copiedRecords );
				}
			}

			if ( !empty( $newVals ) ) {
				return static::get( $this->storage, $newVals, self::TRY_TO_LOAD );
			}
		}

		return $this;
	}

	public function getMeta( $key ) {
		return isset( $this->metaData ) && isset( $this->metaData[ $key ] ) ? $this->metaData[ $key ] : NULL;
	}

	public function setMeta( $key, $value ) {
		if ( $value !== NULL && !isset( $this->metaData ) ) {
			$this->metaData = array();
		}

		if ( $value === NULL ) {
			if ( isset( $this->metaData ) && isset( $this->metaData[ $key ] ) ) {
				unset( $this->metaData[ $key ] );
			}
		} else {
			$this->metaData[ $key ] = $value;
		}
	}

	protected static function addPermissionsForReferencesNotInFormFields() {
		return array();
	}

	public static function fillRequiredPermissions( array &$permissions, $titleOnly = false ) {

		if ( $permissions === NULL ) {
			$permissions = array();
		}

		$fieldDefs = static::getAllFieldDefinitions();

//		foreach($fieldDefs as $fieldName => $fieldDef){
//			$dt = $fieldDef['dataType'];
//
//			$dt::fillRequiredPermissions( $permissions, $fieldName, $fieldDef, get_called_class(), $titleOnly );
//		}

		$formFields = static::getEditableFormFieldsCached();

		if ( $titleOnly ) {
			$titleFields = static::getTitleFieldsCached();

			foreach ( $fieldDefs as $fieldName => $fieldDef ) {
				$dt = $fieldDef[ 'dataType' ];

				if ( $dt::isRequiredForPermissions() ) {
					$titleFields[ $fieldName ] = $fieldDef[ 'recordClass' ]::getTitleFieldsCached();
				}
			}

			$titleParts = array();

			foreach ( $titleFields as $fieldName => $subFields ) {
				$fieldDef = $fieldDefs[ $fieldName ];
				$dt = $fieldDef[ 'dataType' ];

				if ( is_subclass_of( $dt, 'BaseDTRecordReference' ) ) {
					$dt::fillRequiredPermissions( $permissions, $fieldName, $fieldDef, get_called_class(), $titleOnly );
				}
			}
		} else {
			$formFields = array_merge( $formFields, static::addPermissionsForReferencesNotInFormFields() );
			foreach ( $formFields as $fieldName ) {
				$fieldDef = $fieldDefs[ $fieldName ];

				$dt = $fieldDef[ 'dataType' ];

				$dt::fillRequiredPermissions( $permissions, $fieldName, $fieldDef, get_called_class(), $titleOnly );
			}
		}
	}

	public static function getStaticRecords( RBStorage $storage ) {
		return array();
	}

	public static function isMultiPath( $path ) {
		static $cache = array();

		if ( isset( $cache[ $path ] ) ) {
			return $cache[ $path ];
		}

		$pathParts = explode( '.', $path );
		$rc = get_called_class();

		foreach ( $pathParts as $p ) {
			$fieldDef = $rc::getFieldDefinition( $p );
			$dt = $fieldDef[ 'dataType' ];


			// TODO: move to datatype
			if ( is_subclass_of( $dt, 'BaseDTForeignReference' ) ) {
				$cache[ $path ] = true;
				return true;
			} elseif ( !is_subclass_of( $dt, 'BaseDTRecordReference' ) ) {
				$cache[ $path ] = false;
				return false; // end of path
			}

			$rc = $dt::getRecordClassStatically( $p, $fieldDef );
		}

		$cache[ $path ] = false;
		return false;
	}

	public function listFormat( User $user, array $filter, $isSearchField = false ) { // [JB 19.02.2013] made this none static, so we can operate on the record we already got
		$values = array();

		$listFields = static::getListFields( $user );

		foreach ( $listFields as $path => $fieldDefinition ) {
			$value = $this->collectForList( $path, $filter );
			$values[ $path ] = $this->formatForList( $user, $path, $value );

		}

		// additional title field
		$values[ '_title' ] = $this->getTitle();

		return $values;
	}

	protected function collectForList( $path, array $filter ) {
		return $this->collect( $path );
	}

	protected function formatForList( User $user, $path, $value ) {
		$fieldDef = static::getFieldDefinitionByPath( $path );
		$dt = $fieldDef[ 'dataType' ];

		if ( ( $lastDotPos = strrpos( $path, '.' ) ) !== false ) { // path
			$fieldName = substr( $path, $lastDotPos + 1 );
		} else { // simple fieldName
			$fieldName = $path;
		}

		$vals = array();

		foreach ( $value as $v ) {
			$vals[ ] = $dt::listFormat( $user, $this->storage, $fieldName, $fieldDef, $v );
		}

		if ( !static::isMultiPath( $path ) ) {
			if ( $vals ) {
				$vals = reset( $vals );
			} else {
				$vals = NULL;
			}
		}

		return $vals;
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass ) {
		foreach ( $userFilters as $idx => $filterConf ) {
			if ( count( $filterConf[ 'filterFields' ] ) > 1 ) { // quicksearch + combined record title consisting of multiple fields (e.g. person)
				if ( !isset( $queryStruct[ 'where' ] ) ) {
					$queryStruct[ 'where' ] = array();
				} else if ( !empty( $queryStruct[ 'where' ] ) ) {
					if ( isset( $filterConf[ 'filterModifier' ] ) ) {
						$queryStruct[ 'where' ][ ] = $filterConf[ 'filterModifier' ];
					} else {
						$queryStruct[ 'where' ][ ] = 'AND';
					}
				}

				$queryStruct[ 'where' ][ ] = '(';

				$fieldIdx = 0;

				$filterValue = $filterConf[ 'filterValue' ];

				$addQuery = function ( $fields, &$fieldIdx, $path ) use ( &$addQuery, &$queryStruct, $filterValue, $recordClass, &$storage ) {
					foreach ( $fields as $fieldPath => $fieldName ) {
						$fieldIdx++;

						if ( !empty( $path ) ) {
							$fieldPath = $path . '.' . $fieldPath;
						}

						if ( is_array( $fieldName ) ) {
							$addQuery( $fieldName, $fieldIdx, $fieldPath );
						} else {
							if ( !empty( $path ) ) {
								$fieldName = $path . '.' . $fieldName;
							}

							$fieldDef = $recordClass::getFieldDefinitionByPath( $fieldName );

							$val = str_replace( '*', '%', $storage->escapeLike( $filterValue ) );

							if ( isset( $fieldDef[ 'searchType' ] ) && ( $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_BOTH || $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_PREFIX ) ) {
								$val = '%' . $val;
							}

							if ( isset( $fieldDef[ 'searchType' ] ) && ( $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_BOTH || $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_SUFFIX ) ) {
								$val .= '%';
							}

							$queryStruct[ 'where' ][ ] = $fieldName;
							$queryStruct[ 'where' ][ ] = 'LIKE';
							$queryStruct[ 'where' ][ ] = (array)$val;
							$queryStruct[ 'where' ][ ] = 'OR';
						}
					}
				};

				$addQuery( $filterConf[ 'filterFields' ], $fieldIdx, '' );

				array_pop( $queryStruct[ 'where' ] );

				$queryStruct[ 'where' ][ ] = ')';

				unset( $userFilters[ $idx ] );
			}
		}

		$fieldDefs = static::getAllFieldDefinitions();

		foreach ( $fieldDefs as $fieldName => $fieldDef ) {
			$dt = $fieldDef[ 'dataType' ];

			$dt::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, $fieldDef );
		}
	}

	public static function fillForcedPermissions( array &$permissions ) {
		// stub
	}

	public static function getDefaultSorting() {
		$defaultSorting = array();
		$sortingField = NULL;
		$mtimeField = NULL;
		$ctimeField = NULL;

		// TODO: should always lastly sort on unique column (e.g. primary) so we have a stable sorting
		if ( $sortingField = static::getDataTypeFieldName( 'DTSteroidSorting' ) ) {
			$defaultSorting = array(
				$sortingField => DB::ORDER_BY_DESC
			);
		} else if ( $mtimeField = static::getDataTypeFieldName( 'DTMTime' ) ) {
			$defaultSorting = array(
				$mtimeField => DB::ORDER_BY_DESC
			);
		} else if ( $ctimeField = static::getDataTypeFieldName( 'DTCTime' ) ) {
			$defaultSorting = array(
				$ctimeField => DB::ORDER_BY_DESC
			);
		} else if ( static::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ) {
			$defaultSorting = array(
				Record::FIELDNAME_PRIMARY => DB::ORDER_BY_ASC
			);
		}

		return $defaultSorting;
	}

	public static function getConditionalFieldConf() {
		return array();
	}


	public function getIndexedValues( $fields ) {
		$values = array();

		foreach ( (array)$fields as $field ) {
			$values[ $field ] = implode( ' ', $this->collect( $field ) );
		}

		return $values;
	}

	public static function getCustomJSConfig() {
		return array();
	}

	public function getLiveStatus() {
		if ( !$this->exists() ) {
			throw new LogicException( 'Cannot get status of non-existing record' );
		}

		if ( !static::getDataTypeFieldName( 'DTSteroidLive' ) ) {
			return self::RECORD_STATUS_NOT_APPLICABLE;
		}

		if ( !static::getDataTypeFieldName( 'DTMTime' ) ) {
			throw new LogicException( 'Cannot get record status without mtime field' );
		}

		$sibling = $this->getFamilyMember( array( 'live' => $this->live ? DTSteroidLive::LIVE_STATUS_PREVIEW : DTSteroidLive::LIVE_STATUS_LIVE ) );

		if ( !$sibling || $sibling === $this ) {
			return self::RECORD_STATUS_NOT_APPLICABLE;
		}

		if ( $this->live === DTSteroidLive::LIVE_STATUS_PREVIEW && !$sibling->exists() ) {
			return self::RECORD_STATUS_PREVIEW;
		}

		$ownMtime = strtotime( $this->mtime );
		$siblingMtime = strtotime( $sibling->mtime );

		if ( ( $this->live === DTSteroidLive::LIVE_STATUS_PREVIEW && $ownMtime > $siblingMtime ) || ( $this->live === DTSteroidLive::LIVE_STATUS_LIVE && $ownMtime < $siblingMtime ) ) {
			return self::RECORD_STATUS_MODIFIED;
		}

		return self::RECORD_STATUS_LIVE;
	}

	public static function getFieldSets( RBStorage $storage ) {
		return array();
	}

	public function jsonSerialize() {
		$fields = static::getPrimaryKeyFields();

		$data = array();

		foreach ( $fields as $fieldName ) {
			$data[ $fieldName ] = $this->{$fieldName}; // support lazy loading ; may throw exception
		}

		$data[ '__class' ] = get_called_class();

		return $data;
	}

	public function getBackendListRowCSSClass() {
		return NULL;
	}

	public static function getCustomBackendCSSPath() {
		return NULL;
	}

	public static function modifyActionsForRecordInstance( $values = NULL, &$actions ) {
		// stub
	}

	public static function addToFieldSets( $recordClass = NULL ) {
		return NULL;
	}

	public static function getClassStatistics( RBStorage $storage ) {
		$ret = array();

		if ( self::getDataTypeFieldName( 'DTSteroidLive' ) ) {
			$ret[ 'liveStatus' ] = array(
				'config' => array(
					'theme' => 'steroid/backend/stats/themes/livestatus'
				),
				'data' => self::getLiveStatistics( $storage )
			);
		}

		return $ret;
	}

	protected static function getLiveStatistics( RBStorage $storage ) {
		$res = $storage->fetchAll( 'select t0.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTSteroidID' ) ) . ', if(t1.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTSteroidLive' ) ) . ' is null, 0, IF(t0.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTMTime' ) ) . ' > t1.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTMTime' ) ) . ', 2, 1)) as status from ' . $storage->escapeObjectName( self::getTableName() ) . ' t0 left join ' . $storage->escapeObjectName( self::getTableName() ) . ' t1 on t0.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTSteroidID' ) ) . ' = t1.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTSteroidID' ) ) . ' AND t0.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTSteroidLive' ) ) . ' = ' . DTSteroidLive::LIVE_STATUS_PREVIEW . ' AND t1.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTSteroidLive' ) ) . ' = ' . DTSteroidLive::LIVE_STATUS_LIVE . ' group by t0.' . $storage->escapeObjectName( self::getDataTypeFieldName( 'DTSteroidID' ) ) );

		$ret = array(
			Record::RECORD_STATUS_PREVIEW => 0,
			Record::RECORD_STATUS_LIVE => 0,
			Record::RECORD_STATUS_MODIFIED => 0
		);

		foreach ( $res as $row ) {
			$ret[ $row[ 'status' ] ]++;
		}

		return $ret;
	}

	public static function updateContentEdit( RBStorage $storage, $recordID, IRequestInfo $requestInfo, $previousRecordClass, $previousRecordID, $parent = NULL ) {
		//stub
	}

	public static function endEditing( RBStorage $storage, $recordID ) {
		//stub
	}

	public static function handleUserAlive( RBStorage $storage, IRequestInfo $requestInfo, $recordID = NULL, $editingParent = NULL ) {
		//stub
	}


	public static function startFieldTracking( array &$fields ) {
		Record::$trackedFields = array( 'ref' => &$fields );
	}

	public static function endFieldTracking() {
		Record::$trackedFields = NULL;
	}

	public function hideFromAffectedRecordData() {
		return false;
	}

	public function getAffectedRecordData( IRecord $mainRecord, &$classes, $allAffectedRecords ) {
		if ( !$this->fieldDefinitionExists( Record::FIELDNAME_PRIMARY )
				|| ( get_class( $mainRecord ) == get_called_class() && $this->getFieldValue( Record::FIELDNAME_PRIMARY ) == $mainRecord->getFieldValue( Record::FIELDNAME_PRIMARY ) )
				|| in_array( static::BACKEND_TYPE, array( Record::BACKEND_TYPE_UNKNOWN, Record::BACKEND_TYPE_DEV, Record::BACKEND_TYPE_SYSTEM ) )
				|| $this->hideFromAffectedRecordData()
		) {
			return;
		}


		$data = array(
			'primary' => $this->getFieldValue( Record::FIELDNAME_PRIMARY )
		);


// TODO: move page stuff into RCPage / DTSteroidPage
		if ( static::BACKEND_TYPE === self::BACKEND_TYPE_WIDGET ) {
			$excludePages = array();
			$containingPages = array();

			if ( get_class( $mainRecord ) == 'RCPage' ) {
				$excludePages[ ] = $mainRecord;
			} else if ( $pageField = $mainRecord::getDataTypeFieldName( 'DTSteroidPage' ) ) {
				$excludePages[ ] = $mainRecord->{$pageField};
			}

			foreach ( $allAffectedRecords as $affectedRecord ) {
				if ( get_class( $affectedRecord ) == 'RCPage' ) {
					$excludePages[ ] = $affectedRecord;
				} else if ( $pageField = $affectedRecord::getDataTypeFieldName( 'DTSteroidPage' ) ) {
					if ( $foreignPage = $affectedRecord->{$pageField} ) {
						$excludePages[ ] = $foreignPage;
					}
				}
			}

			$containingPages = $this->getContainingPages();

			if ( !empty( $excludePages ) && !empty( $containingPages ) ) {
				foreach ( $containingPages as $key => $containingPage ) {
					foreach ( $excludePages as $excludePage ) {
						if ( $containingPage == $excludePage ) {
							unset( $containingPages[ $key ] );
							break;
						}
					}
				}
			}

			if ( !empty( $containingPages ) ) {
				foreach ( $containingPages as $containingPage ) {
					$data[ 'title' ] = '(#' . $containingPage->pageType . '#) ' . $containingPage->getTitle() . ( $this->getTitle() ? ( ' -> ' . $this->getTitle() ) : '' );

					$this->addToAffectedRecordData( $classes, $containingPage->domainGroup->getTitle(), $data );
				}
			}
		} else {
			$data[ 'title' ] = $this->getTitle();

			if ( $ownDomainGroupFieldName = $this->getDataTypeFieldName( 'DTSteroidDomainGroup' ) ) {
				$domainGroup = $this->getFieldValue( $ownDomainGroupFieldName )->getTitle();
			} else {
				$domainGroup = 'Global'; // FIXME: constify ; also, if this is not displayed, make sure to use a value which won't happen collide with existing domainGroups
			}

			$this->addToAffectedRecordData( $classes, $domainGroup, $data );
		}
	}

	protected function addToAffectedRecordData( &$classes, $domainGroup, $data ) {
		$currentClass = get_called_class();

		if ( !isset( $classes[ $domainGroup ] ) ) {
			$classes[ $domainGroup ] = array();
		}

		if ( isset( $classes[ $domainGroup ][ $currentClass ] ) ) {
			foreach ( $classes[ $domainGroup ][ $currentClass ] as $title => $_record ) {
				if ( $title === $data[ 'title' ] ) {


//					if ( !isset( $classes[ $domainGroup ][ get_called_class() ][ $title ][ 'count' ] ) ) {
//						$classes[ $domainGroup ][ get_called_class() ][ $title ][ 'count' ] = 2;
//					} else {
//						$classes[ $domainGroup ][ get_called_class() ][ $title ][ 'count' ]++;
//					}

					return;
				}
			}
		} else {
			$classes[ $domainGroup ][ $currentClass ] = array();
		}

		$classes[ $domainGroup ][ $currentClass ][ $data[ 'title' ] ] = $data;
	}
}

class InvalidDataTypeDefinitionException extends Exception {
}

class InvalidFieldAccessException extends SteroidException {
}

class RecordDoesNotExistException extends SteroidException {
}

class TargetDoesNotExistException extends RecordDoesNotExistException {
}

