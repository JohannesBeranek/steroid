<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/interface.IDataType.php';
/**
 * Basic abstract class for datatypes
 *
 * @package steroid\datatype
 */
abstract class DataType implements IDataType {

	protected $storage;

	/**
	 * @var array reference to passed values
	 */
	protected $values;

	/**
	 * @var string name of datatype - may be used to infer fieldnames in db and other stuff
	 */
	protected $fieldName;

	/**
	 * @var the column name of this datatype in db
	 */
	protected $colName;

	/**
	 * @var array may hold additional parameters - defined here so subclasses use the same method for argument passing (array vs. multiple)
	 */
	protected $config;

	/**
	 * @var IRecord reference to the record instance this datatype belongs to
	 */
	protected $record;

	/**
	 * @var bool whether or not the datatype's value has been changed. only dirty fields are saved to db
	 */
	protected $isDirty = false;

	private static $backendJSExtensions = array();
	private static $backendJSExtensionsLoaded;


	public static function addBackendJSExtension( $file, $recordClass, $field, $priority ) {
		$file = Filename::getPathInsideWebroot( $file );

		if ( !is_readable( $file ) ) {
			throw new Exception( 'Unable to read file ' . $file );
		}

		$webFilename = Filename::getPathWithoutWebroot( $file );

		self::$backendJSExtensions[ $recordClass ][ $field ][ $priority ] = $webFilename;
	}


	/**
	 * @param IStorage $storage
	 * @param IRecord  $record
	 * @param array    $values
	 * @param null     $fieldName
	 * @param array    $config
	 */
	public function __construct( IStorage &$storage, IRecord $record, array &$values, $fieldName = NULL, array $config = NULL ) {
		if ( $fieldName === NULL || $config === NULL ) {
			throw new InvalidArgumentException( '$fieldName and $config must be set' );
		}

		$this->record = $record;
		$this->values = & $values;
		$this->fieldName = $fieldName;
		$this->colName = static::getColName( $fieldName, $config );
		$this->config = $config;
		$this->storage = & $storage;
	}
	
	public function cleanup() {
		unset($this->record);
		unset($this->values);
		unset($this->fieldName);
		unset($this->colName);
		unset($this->config);
		unset($this->storage);
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		if ( !isset( $fieldConf[ 'default' ] ) ) {
			return NULL;
		}

		return $fieldConf[ 'default' ];
	}

	public static function getColName( $fieldName, array $config = NULL ) {
		return $fieldName;
	}

	public static function getAdditionalJoinConditions( $owningRecordClass, $fieldName, array $config ) {
		return NULL;
	}

	public function setValue( $data = NULL, $loaded = false ) {
		if ( !array_key_exists( $this->colName, $this->values ) || ( $data !== $this->values[ $this->colName ] ) || ( $loaded && $this->isDirty ) ) {
			$this->values[ $this->colName ] = $data;

			$this->isDirty = !$loaded;
		}
	}

	public function setRawValue( $data = NULL, $loaded = false ) {
		$this->setValue( $data, $loaded );
	}

	public function setRealValue( $data = NULL, $loaded = false ) {
		// stub	
	}

	public function hasBeenSet() {
		return array_key_exists( $this->colName, $this->values );
	}

	public function unload() {
		if ($this->colName !== NULL) {
			unset( $this->values[$this->colName] );
		}
		
		$this->isDirty = false;
	}

	public function hasValidValue() {
		return !empty( $this->config[ 'nullable' ] ) ? array_key_exists( $this->colName, $this->values ) : !empty( $this->values[ $this->colName ] );
	}

	public function beforeSave( $isUpdate ) {
		// stub
	}

	public function recordMaySave() {
		return true;
	}


	public function setDefault( array $saveResult ) {
		if ( $this->colName && array_key_exists( 'default', $this->config ) && !array_key_exists( $this->colName, $this->values ) ) {
			$this->setValue( $this->config[ 'default' ], true );
		}
	}

	public function updateDirtyAfterSave() {
		if ( $this->hasBeenSet() ) {
			$this->isDirty = false;
		}
	}

	public function afterSave( $isUpdate, array $saveResult ) {
		// stub
	}

	public function beforeDelete( array &$basket = NULL ) {
		// stub
	}

	public function afterDelete( array &$basket = NULL ) {
		// stub
	}

	public function getValue() {
		return isset( $this->values[ $this->colName ] ) ? $this->values[ $this->colName ] : NULL;
	}

	public function rescueValue() {
		return isset( $this->values[ $this->colName ] ) ? $this->values[ $this->colName ] : NULL;
	}

	public function getFormValue() {
		// this makes sure the value is lazily loaded if needed
		return $this->record->{$this->fieldName};
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'dirty':
				return $this->isDirty;
				break;
			case 'colName':
				return $this->colName;
				break;
		}


		throw new InvalidArgumentException(get_called_class() . ' does not have property ' . $name);
	}

	public static function completeConfig( &$config, $recordClass, $fieldName ) {
		// stub
	}

	public static function getFormConfig( IRBStorage $storage, $owningRecordClass, $fieldName, $fieldDef ) {
		if ( self::$backendJSExtensionsLoaded === NULL ) {
			self::$backendJSExtensionsLoaded = true;

			// collect backend JS Extensions, making sure they're included
			ClassFinder::getAll( ClassFinder::CLASSTYPE_BACKEND_EXTENSION, true );
		}

		if ( isset( self::$backendJSExtensions[ $owningRecordClass ][ $fieldName ] ) ) {
			$fieldDef[ 'extensions' ] = self::$backendJSExtensions[ $owningRecordClass ][ $fieldName ];
		}

		return $fieldDef;
	}

	public static function getTitleFields( $fieldName, $config ) {
		return $fieldName;
	}

	public static function fillTitleFields( $fieldName, &$titleFields, $config ) {
		$recordClass = $config[ 'recordClass' ];

		$recordClass::fillTitleFields( $titleFields, $recordClass::getAllFieldDefinitions() );
	}

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedOriginRecords ) {
		$values[ $this->fieldName ] = $this->record->{$this->fieldName};
	}

// FIXME: use interface
	public function earlyCopy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedOriginRecords ) {
		// stub
	}

	public static function adaptFieldConfForDB( array &$fieldConf ) {
		// stub
	}

// FIXME: use interface
	public function refresh() {
		// stub
	}
	
// FIXME: use interface
	public function notifySaveComplete() {
		// stub
	}

// TODO: maybe use interface for this?
	public function checkForDelete() {
		return false;
	}

	public function fillUpValues( array $values, $loaded ) {
		throw new Exception( 'Unexpected fillUpValues call on datatype not implementing this functionality.' );
	}

	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {
		// stub
	}

	public static function isRequiredForPermissions() {
		return false;
	}

	public static function listFormat( User $user, IRBStorage $storage, $fieldName, $fieldDef, $value ) {
		return $value;
	}

	public static function expandListField( $listFields, $path, $currentRecordClass ) {
		// stub
	}

	public function getFieldName() {
		return $this->fieldName;
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, array $fieldDef ) {
		foreach ( $userFilters as $idx => $filterConf ) {
			if ( in_array( $fieldName, $filterConf[ 'filterFields' ] ) ) {
				if ( !isset( $queryStruct[ 'where' ] ) ) {
					$queryStruct[ 'where' ] = array();
				} else if ( !empty( $queryStruct[ 'where' ] ) ) {
					if ( isset( $filterConf[ 'filterModifier' ] ) ) {
						$queryStruct[ 'where' ][ ] = $filterConf[ 'filterModifier' ];
					} else {
						$queryStruct[ 'where' ][ ] = 'AND';
					}
				}

				$queryStruct[ 'where' ][ ] = $fieldName;
				$queryStruct[ 'where' ][ ] = '=';
				$queryStruct[ 'where' ][ ] = (array)$filterConf[ 'filterValue' ];

				unset( $userFilters[ $idx ] );
			}
		}
	}
}

class InvalidValueForFieldException extends SteroidException {
}

?>