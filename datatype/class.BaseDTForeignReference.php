<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';
require_once STROOT . '/storage/record/interface.IRecord.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';

require_once STROOT . '/util/class.Debug.php';

/**
 * class for foreign references (e.g. join tables, where the datatype's record is referenced by another record)
 */
abstract class BaseDTForeignReference extends DataType {
	protected $changeStack;
	protected $value;

	protected $oldValue;
	protected $lastValueSetInternally;

	const CHANGE_ADD = 'add';
	const CHANGE_REMOVE = 'remove';

	public static function getRecordClassForFieldName( $fieldName ) {
		return substr( strrchr( $fieldName, ':' ), 1 );
	}

	public static function getRecordClassStatically( $fieldName, array $fieldDefinition ) {
		return self::getRecordClassForFieldName( $fieldName );
	}

	/**
	 * Get column name
	 *
	 * returns NULL because foreign references are stored in the respective foreign record's table
	 *
	 * @static
	 *
	 * @param null  $fieldName
	 * @param array $config
	 *
	 * @return null|string
	 */
	public static function getColName( $fieldName = NULL, array $config = NULL ) {
		return NULL;
	}

	public function cleanup() {
		$foreignFieldName = $this->getForeignFieldName();
		
		$value = $this->value;
		$this->value = NULL;
		
		if ( $value !== NULL ) {
			foreach ($value as $val) {
				$val->unloadField( $foreignFieldName );
			}
			
			unset($val);
		}
		
		unset($value);

		$oldValue = $this->oldValue;
		$this->oldValue = NULL;

		if ( $oldValue !== NULL ) {
			foreach ($oldValue as $val) {
				$val->unloadField( $foreignFieldName );
			}

			unset($val);
		}

		unset($oldValue);
		
		parent::cleanup();
		
		$this->changeStack = NULL;
	}

	public function getForeignFieldName() {
		return strstr( $this->fieldName, ':', true );
	}

	public function getRecordClass() {
		return $this->getRecordClassForFieldName( $this->fieldName );
	}


	/**
	 * Update record primary
	 *
	 * Sets/updates the primary field value of the datatype's record
	 *
	 * @throws InvalidArgumentException
	 */
	public function updateRecordPrimary() {
		if ( empty( $this->value ) || !( $colName = $this->record->getColumnName( Record::FIELDNAME_PRIMARY ) ) || !isset( $this->values[ $colName ] ) ) {
			return;
		}

		$foreignFieldName = $this->getForeignFieldName();

		foreach ( $this->value as $foreignRecord ) {
			$foreignRecord->refreshField( $foreignFieldName );
		}
	}

	public function setRawValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		// overwrite to do nothing
	}

	public function setRealValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		$this->setValue( $data, $loaded, $path, $dirtyTracking );
	}

	protected function addNestedRecords( array &$records ) {
		// stub
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, array $fieldDef ) {
		if ( $foreignRecordClassDefinition = self::getOtherSelectableRecordClassDefinition( $recordClass, $fieldDef[ 'recordClass' ] ) ) {
			$foreignField = $foreignRecordClassDefinition[ 'fieldName' ];
			$fieldName = $fieldName . ( isset( $foreignField ) ? '.' . $foreignField : '' ) . '.primary';
		}

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

	final private function ensureOldValue( $loaded, $records ) {
		if ( $this->oldValue === NULL ) {
			if ( $loaded ) {
				$this->oldValue = $records;
			} else if ($this->record->exists()) {
// FIXME: might override values of records in _setValue
				$this->oldValue = $this->getForeignRecords();
			} else {
				$this->oldValue = array();
			}
		}
	}

// FIXME: implement dirtyTracking (currently it's just passed on) - this way we should be able to only save SOME of the referenced records
	protected function _setValue( $data, $loaded, $setInternally = false, $path = NULL, array &$dirtyTracking = NULL ) {
		$records = array();

		$recordClass = $this->getRecordClass();
		$hasSorting = $recordClass::fieldDefinitionExists( Record::FIELDNAME_SORTING );
		$needMerge = false;

		$foreignFieldName = $this->getForeignFieldName();

		if ( !is_array( $data ) && !( $data instanceof Traversable ) ) {
			throw new Exception( "Invalid data passed to " . get_class( $this->record ) . "->" . $this->fieldName );
		}


		foreach ( $data as $record ) {
			if ( is_array( $record ) ) {
				

				$record[ $foreignFieldName ] = $this->record; // make it easier / more likely to fetch indexed record
				
				if ($this->oldValue === NULL) {
					$foreignRecord = $recordClass::get( $this->storage, $record, $loaded, $path, $dirtyTracking );
				} else {
					unset($foreignRecord);
					
					if (!isset($hasPrimaryField)) {
						$hasPrimaryField = $recordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY );
					}
					
					if ($hasPrimaryField && isset( $record[Record::FIELDNAME_PRIMARY] )) {
						
						foreach ($this->oldValue as $oldRecord) {
							if (isset($oldRecord->{Record::FIELDNAME_PRIMARY}) && $oldRecord->{Record::FIELDNAME_PRIMARY} === (int)$record[Record::FIELDNAME_PRIMARY]) {
								$foreignRecord = $oldRecord;
								
								// also set any possible additional values
								$foreignRecord->setValues($record, $loaded, $path, $dirtyTracking);
								break;
							}
						}
						
						if (!isset($foreignRecord)) {
							// fallback
							$foreignRecord = $recordClass::get( $this->storage, $record, $loaded, $path, $dirtyTracking );
						}
					} else {
						// need to check via other unique key
						if (!isset($uniqueKeys)) {
							$uniqueKeys = $recordClass::getUniqueKeys();
							
							// filter key on Record::FIELDNAME_PRIMARY
							if ($hasPrimaryField) {
								foreach($uniqueKeys as $key => $keyDef) {
									if (count($keyDef['fieldNames']) === 1 && $keyDef['fieldNames'][0] === Record::FIELDNAME_PRIMARY) {
										unset($uniqueKeys[$key]);
										break;
									}
								}
							}
						}
						
						
						if ($uniqueKeys) {
							// only get here if recordClass has uniqueKeys besides Record::FIELDNAME_PRIMARY
							foreach ($uniqueKeys as $keyDef) {
								$hasAllFields = true;
								
								foreach($keyDef['fieldNames'] as $fieldName) {
									if (!isset($record[$fieldName])) {
										$hasAllFields = false;
										break;
									}
								}
								
								if ($hasAllFields) {	
									foreach ($this->oldValue as $oldRecord) {
										$matchesAllFields = true;
										
										foreach($keyDef['fieldNames'] as $fieldName) {
											if (!isset($oldRec->{$fieldName}) || $oldRec->{$fieldName} != $record[$fieldName]) {
												// uses comparison with automatic type coercion as values in $record are not guaranteed to be of correct type
												$matchesAllFields = false;
												break;
											}
										}
										
										if ($matchesAllFields) {
											$foreignRecord = $oldRecord;
											
											// also set any possible additional values
											$foreignRecord->setValues($record, $loaded, $path, $dirtyTracking);
											break;
										}
										
									}
									
									if (isset($foreignRecord)) {
										break;
									}
								}
							}
							
							if (!isset($foreignRecord)) {
								// no hit on any key
								$foreignRecord = $recordClass::get( $this->storage, $record, $loaded, $path, $dirtyTracking );
							}
						} else {
							 // no unique keys besides primary field - fallback
							$foreignRecord = $recordClass::get( $this->storage, $record, $loaded, $path, $dirtyTracking );
						}
					}
				}

			} else if ( $record instanceof IRecord ) {
				$foreignRecord = $record;
			} else if ( $record === '' || $record === NULL ) { // ignore empty string and null values
				continue;
			} else {
				throw new InvalidArgumentException( 'Can only set multiple instances of IRecord or data arrays as value of a foreign reference.' );
			}
			
			if (!$foreignRecord || !($foreignRecord instanceof IRecord)) {
				throw new Exception(Debug::getStringRepresentation($foreignRecord));
			}

			// TODO: this is problematic with values with same sorting values or mixed sets of values with sorting and values without sorting
			// TODO: use record::getDefaultSorting()
			if ( $hasSorting && ( isset( $foreignRecord->{Record::FIELDNAME_SORTING} ) || $foreignRecord->exists() ) ) {
				$sortValue = $foreignRecord->{Record::FIELDNAME_SORTING};

				if ( !empty( $records[ $sortValue ] ) && ( $records[ $sortValue ] instanceof IRecord ) ) {
					$rec = $records[ $sortValue ];

					$records[ $sortValue ] = array( $rec );
					
					// use boolean to determine if we need merging later on (or can skip it) 
					// for better performance in default case where we need no merging
					
				}

				$records[ $sortValue ][ ] = $foreignRecord;
				
				$needMerge = true;
			} else {
				$records[ ] = $foreignRecord;
			}
		}

		if ( $hasSorting ) {
			ksort( $records );

			if ($needMerge) {
				$recs = array();
	
				foreach ( $records as $rcs ) {
					if ( is_array( $rcs ) ) {
						$recs = array_merge( $recs, array_values( $rcs ) );
					} else {
						$recs[ ] = $rcs;
					}
				}
	
				$records = $recs;
			}
		}

		$this->ensureOldValue( $loaded, $records );

		$this->addNestedRecords( $records );

		if ( $loaded && $this->changeStack !== NULL ) {
			foreach ( $this->changeStack as $todo ) {
				switch ( $todo[ 0 ] ) {
					case self::CHANGE_ADD:
						if ( !in_array( $todo[ 1 ], $records, true ) ) {
							$records[ ] = $todo[ 1 ];
						}
						break;
					case self::CHANGE_REMOVE:
						if ( ( $key = array_search( $todo[ 1 ], $records, true ) ) !== false ) {
							unset( $records[ $key ] );
						}
						break;
				}
			}

		} else {
			$this->changeStack = NULL;
		}

		$this->value = $records;

		// TODO: only set dirty if value changed or we upgraded to !dirty
		if ((bool)$loaded === $this->isDirty) {
			$this->isDirty = !$loaded; 
			
			if (!$loaded) {
				$dirtyTracking[$path] = true;
			}
		}

		$this->lastValueSetInternally = $setInternally;
	}

	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		if ( $data === NULL ) { // enable calling setValue with an empty array/NULL to remove all records
			$data = array();
		}

		$this->_setValue( $data, $loaded, false, $path, $dirtyTracking );

		if (!$loaded && $this->oldValue !== NULL) {
			// notify
			$foreignFieldName = $this->getForeignFieldName();
			$basket = NULL;

			// don't use array_diff here, as it does string comparison
			foreach ( $this->oldValue as $oldRec ) {
				if ( !in_array( $oldRec, $this->value, true ) ) {
					$oldRec->notifyReferenceRemoved( $this->record, $foreignFieldName, __FUNCTION__, $basket );
				}
			}

			foreach ( $this->value as $newRec ) {
				if ( !in_array( $newRec, $this->oldValue, true ) ) {
					$newRec->notifyReferenceAdded( $this->record, $foreignFieldName, $loaded );
				}
			}
		}
	}

	public function getValue() {
		return ( $this->value === NULL ? array() : $this->value );
	}

	public static function listFormat( User $user, IRBStorage $storage, $fieldName, $fieldDef, $value ) {
		return $value->getTitle();
	}

	public function getFormValue() {
		$val = $this->record->{$this->fieldName};

		if ( empty( $val ) ) {
			$val = NULL;
		}

		if ( $val ) {
			$recordClass = $this->getRecordClass();

			$fields = static::getForeignFormFields( $recordClass );

			$foreignFieldName = $this->getForeignFieldName();

			if ( ( $key = array_search( $foreignFieldName, $fields ) ) !== false ) { // remove our field
				unset( $fields[ $key ] );
			}

			$formValues = array();

			$alienRecordDefinition = $this->getOtherSelectableRecordClassDefinition( get_class( $this->record ), $recordClass );
			$alienRecordClass = $alienRecordDefinition[ 'recordClass' ];

			$liveField = $alienRecordClass::getDataTypeFieldName( 'DTSteroidLive' );

			foreach ( $val as $rec ) {
				if ( !$liveField || ( $liveField && $rec->{$alienRecordDefinition[ 'fieldName' ]}->{$liveField} === DTSteroidLive::LIVE_STATUS_PREVIEW ) ) {
					$formValue = $rec->getFormValues( $fields );

					$formValues[ ] = $formValue;
				}
			}

			$val = $formValues;
		}

		return $val;
	}

	protected function getForeignFormFields( $recordClass = NULL ) {
		if ( !$recordClass ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		return $recordClass::getPrimaryKeyFields();
	}

	public function hasBeenSet() {
		return $this->value !== NULL;
	}


	public function updateDirtyAfterSave() {
		// make sure dirty is not unset right after save
	}

	/**
	 * After save
	 *
	 * @param array $saveResult
	 */
	public function afterSave( $isUpdate, array $saveResult, array $savePaths = NULL ) {		
		$this->updateRecordPrimary();

		if ( !$this->isDirty ) {
			return;
		}

		$recordClass = $this->getRecordClass();

		$primaryKeys = $recordClass::getPrimaryKeyFields();

		$foreignFieldName = $this->getForeignFieldName();

		// $currentRecords = $this->getForeignRecords(); // shouldn't trigger re-adding, as loaded recs should be notified of removal upon load

		$keepRecords = array();

		$newRecords = array();

		// TODO: make it possible to use unique sorting key (at the moment we can get conflicts because old records don't get delete before new ones get inserted/updated)
		foreach ( $this->oldValue as $currentRecord ) {
			if ( !in_array( $currentRecord, $this->value, true ) ) {	
				if ( $this->config[ 'requireSelf' ] ) {
					$currentRecord->checkForDelete();
				} else {
					$currentRecord->save( $savePaths );
				}
			} else {
				$keepRecords[] = $currentRecord; // save existing record as well, might have new values (and record might not even be dirty itself, but some referenced record on xth level)
			}
		}
		


		// re-save existing records first, so we don't get into unique sorting key conflicts
		foreach ( $keepRecords as $record ) {
			$record->save( $savePaths );
		}

		// check new records
		foreach ( $this->value as $setRecord ) {
			if ( !in_array( $setRecord, $this->oldValue, true ) ) {
				$newRecords[ ] = $setRecord; // is new record, save it
			} 
		}

		foreach ( $newRecords as $record ) {
			$record->save( $savePaths );
		}

		parent::afterSave( $isUpdate, $saveResult, $savePaths );

		// trust in copy on write - this must not be a reference!
		$this->oldValue = $this->value;
		
		$this->isDirty = false;
	}

	public static function completeConfig( &$config, $recordClass, $fieldName ) {
		$config[ 'recordClass' ] = static::getRecordClassForFieldName( $fieldName );
	}


	/**
	 * Before save
	 *
	 * if datatype has been configured with requireSelf = true, it will delete all records of the configured recordClass which reference the datatype's record
	 */
	public function beforeDelete( array &$basket = NULL ) {
// FIXME: this seems not correct - should use current set records maybe?
		$foreignRecords = $this->getForeignRecords();
		

		if ( isset( $this->config[ 'requireSelf' ] ) && $this->config[ 'requireSelf' ] ) {
			while( $foreignRecord = array_pop($foreignRecords)) {
				if ( $basket !== NULL || !$foreignRecord->isDeleted() ) {
					$foreignRecord->delete( $basket );
				}
			}
		} else { // [JB 11.02.2013] even if foreign ref is not required we need to make sure that referencing record has it's value set to NULL
		
// FIXME: basket might be wrong as records requiring reference fields won't be put into basket
			if ($basket === NULL) {
				$foreignFieldName = $this->getForeignFieldName();
	
				while ( $foreignRecord = array_pop( $foreignRecords ) ) {
					if ( $foreignRecord->{$foreignFieldName} !== NULL ) {
						$foreignRecord->{$foreignFieldName} = NULL;
						$foreignRecord->save();
					}
				}
			}
		}
	}

	protected function getForeignRecords() {
		$foreignRecordClass = $this->getRecordClass();

		$foreignRecords = $this->storage->selectRecords( $foreignRecordClass,
			array(
				'where' => array( $this->getForeignFieldName(), '=', array( $this->record->{Record::FIELDNAME_PRIMARY} ) ),
				'fields' => '*'
			)
		);
		
		return $foreignRecords;
	}

	public function load() {
		if ( !$this->hasBeenSet() || $this->dirty ) {
			$this->setValue( $this->getForeignRecords(), true );
		}

		return $this->value;
	}

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedRecords ) {
		$vals = $this->record->{$this->fieldName};

		$newVals = array();

		foreach ( $vals as $val ) {
			if ( ( $key = array_search( $val, $originRecords, true ) ) !== false ) {
				$newVal = $copiedRecords[ $key ];
			} else {
				$newVal = $val->copy( $changes, $missingReferences, $originRecords, $copiedRecords );
			}

			$newVals[ ] = $newVal;

			if ( !in_array( $val, $missingReferences, true ) && !$newVal->exists() ) {
				$missingReferences[ ] = $val;
			}
		}

		$values[ $this->fieldName ] = $newVals;
	}

	public function notifyReferenceRemoved( IRecord $originRecord, $triggeringFunction, array &$basket = NULL ) {
		if ( $this->hasBeenSet() ) {
			$val = $this->record->getFieldValue( $this->fieldName ); // would lead to huge recursion in case of lazy loading (which is why we do lazy stuff separate)

			if ( $basket === NULL && ( $key = array_search( $originRecord, $val, true ) ) !== false ) {
				unset( $val[ $key ] );
// FIXME: allow for dirty tracking
				$this->_setValue( $val, false );
			}
		} else if ( $basket === NULL && $this->record->exists() ) { // this is needed so we don't dirty records because of notify as well as preventing recursion through hundreds of records
			$this->changeStack[ ] = array( self::CHANGE_REMOVE, $originRecord );
		}
	}

	public function notifyReferenceAdded( IRecord $originRecord, $loaded ) {
		if ( $this->hasBeenSet() ) {	
			$val = $this->record->getFieldValue( $this->fieldName ); // would lead to huge recursion in case of lazy loading (which is why we do lazy stuff separate)

			if ( !in_array( $originRecord, $val, true ) ) {
				// if other record/value comes from db and we got a manually set value,
				// tell other record that it's been removed
				if ($loaded && $this->isDirty && !$this->lastValueSetInternally) {
					$basket = NULL;
					$originRecord->notifyReferenceRemoved($this->record, $this->getForeignFieldName(), __FUNCTION__, $basket);
				} else {
					$val[ ] = $originRecord;

//				$this->_setValue( $val, $loaded );
					// JB 23.1.2014 We actually can't know if the resulting value is the same as in db, so we set loaded false in any case
// FIXME: allow for dirty tracking
					$this->_setValue( $val, false, true );
				}
			}
		} else if ( $this->record->exists() ) { // this is needed so we don't dirty records because of notify as well as preventing recursion through hundreds of records
			if ( !$loaded ) {
				$this->changeStack[ ] = array( self::CHANGE_ADD, $originRecord );
			} // else we get the record reference anyway when this foreign ref is loaded
		} else {
// FIXME: allow for dirty tracking
			$this->_setValue( array( $originRecord ), false );
		}
	}

	public function getReferenceCount() {
		if ( $this->hasBeenSet() || $this->record->exists() ) {
			return count( $this->record->{$this->fieldName} );
		}

		return 0;
	}
	
	public function unload() {
		// loop guard
		if ( $this->value !== NULL ) {
			$foreignFieldName = $this->getForeignFieldName();
				
			$value = $this->value;
			$this->value = NULL;

			foreach ($value as $val) {
				$val->unloadField( $foreignFieldName );
			}


			$value = $this->oldValue;
			$this->oldValue = NULL;

			foreach ($value as $val) {
				$val->unloadField( $foreignFieldName );
			}

			unset($value);
			unset($val);
			
			$this->changeStack = NULL;
			
			parent::unload();
		}
	}

	

	public static function getFormConfig( IRBStorage $storage, $owningRecordClass, $fieldName, $fieldDef ) {
		$fieldDef = parent::getFormConfig( $storage, $owningRecordClass, $fieldName, $fieldDef );

		$foreignRecordClass = $fieldDef[ 'recordClass' ];

		if ( !$foreignRecordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ) {
			$fieldDef[ 'selectableRecordClassConfig' ] = self::getOtherSelectableRecordClassDefinition( $owningRecordClass, $foreignRecordClass );
		}

		return $fieldDef;
	}

	public static function getOtherSelectableRecordClassDefinition( $mainRecordClass, $foreignRecordClass ) {
		$foreignFieldDefinitions = $foreignRecordClass::getOwnFieldDefinitions();

		foreach ( $foreignFieldDefinitions as $fieldName => $fieldDef ) {
			if ( is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTRecordReference' ) && isset( $fieldDef[ 'recordClass' ] ) && $fieldDef[ 'recordClass' ] != $mainRecordClass ) {
				$recordClass = $fieldDef[ 'recordClass' ];

				return array(
					'recordClass' => $fieldDef[ 'recordClass' ],
					'fieldName' => $fieldName,
					'titleFields' => self::fillIncompleteTitleFieldDefinitions( $recordClass::getTitleFieldsCached(), $fieldDef[ 'recordClass' ] )
				);
			}
		}

		return NULL;
	}

	protected static function fillIncompleteTitleFieldDefinitions( $titleFields, $foreignRecordClass ) {

		$completeFields = array();

		foreach ( $titleFields as $k => $v ) {
			if ( !is_array( $v ) ) {
				$foreignFieldDefinitions = $foreignRecordClass::getAllFieldDefinitions();

				if ( is_subclass_of( $foreignFieldDefinitions[ $v ][ 'dataType' ], 'BaseDTRecordReference' ) || is_subclass_of( $foreignFieldDefinitions[ $v ][ 'dataType' ], 'BaseDTForeignReference' ) ) {
					$alienRecordClass = $foreignFieldDefinitions[ $v ][ 'recordClass' ];
					$completeFields[ $v ] = self::fillIncompleteTitleFieldDefinitions( $alienRecordClass::getTitleFieldsCached(), $alienRecordClass );
				} else {
					$completeFields[ $k ] = $v;
				}
			} else {
				$completeFields[ $k ] = $v;
			}
		}

		return $completeFields;
	}

	protected static function getRequiredPermissions( $fieldDef, $fieldName, $currentForeignPerms, $permissions, $owningRecordClass ) {
		$owningRecordPerms = $permissions[ $owningRecordClass ];

		return array(
			'mayWrite' => $owningRecordPerms[ 'mayWrite' ]
		);
	}

	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {
		$owningRecordPerms = $permissions[ $owningRecordClass ];
		$foreignRecordClass = $fieldDef[ 'recordClass' ];

		$needUpdate = false;

		if ( !isset( $permissions[ $foreignRecordClass ] ) ) {
			$permissions[ $foreignRecordClass ] = array(
				'mayWrite' => 0,
				'isDependency' => 1,
				'restrictToOwn' => 0
			);

			$needUpdate = true;
		}

		if ( !$titleOnly ) {
			$requiredPermissions = static::getRequiredPermissions( $fieldDef, $fieldName, isset( $permissions[ $foreignRecordClass ] ) ? $permissions[ $foreignRecordClass ] : NULL, $permissions, $owningRecordClass );

			if ( $requiredPermissions && $requiredPermissions[ 'mayWrite' ] > $permissions[ $foreignRecordClass ][ 'mayWrite' ] ) {
				$permissions[ $foreignRecordClass ][ 'mayWrite' ] = $requiredPermissions[ 'mayWrite' ];
				$needUpdate = true;
			}
		}

		if ( $needUpdate ) {
			$foreignRecordClass::fillRequiredPermissions( $permissions, $titleOnly );
		}
	}
}
