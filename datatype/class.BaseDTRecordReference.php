<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';
require_once STROOT . '/datatype/class.DTForeignReference.php';
require_once STROOT . '/util/class.ClassFinder.php';

/**
 * Base class for record references
 *
 * This can be extended upon to implement datatypes directly referencing other records
 *
 * @package steroid\datatype
 */
abstract class BaseDTRecordReference extends DataType {
	// TODO: correct $lastRawValue regarding Record->__unset

	/**
	 * @var own value used to store the foreign record instance
	 */
	protected $value;

	protected $lastRawValue;
	protected $lastPersistentRealValue;

	public static function getRecordClassStatically( $fieldName, array $fieldDefinition ) {
		return isset( $fieldDefinition[ 'recordClass' ] ) ? $fieldDefinition[ 'recordClass' ] : NULL;
	}

	/**
	 * Get column name
	 *
	 * returns the column name in the format of ownFieldName_foreignPrimaryColumnName
	 *
	 * @param null  $fieldName
	 * @param array $config
	 *
	 * @return mixed
	 * @throws InvalidArgumentException
	 */
	public static function getColName( $fieldName, array $config = NULL ) {
		if ( empty( $config ) || empty( $config[ 'recordClass' ] ) ) {
			throw new InvalidArgumentException( 'recordClass must be set' );
		}

		$rc = $config[ 'recordClass' ];

		// by not using getColName we avoid having to deal with circular references
		// (e.g. RCFlickrUrl has RCFlickrUser and RCFlickrUser might have RCFlickrUrl)
		return $fieldName . '_' . Record::FIELDNAME_PRIMARY;
	}


	public static function listFormat( User $user, IRBStorage $storage, $fieldName, $fieldDef, $value ) {
		if ( !$value ) return NULL;

		$values = array();

		if ( $value::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ) {
			$values[ Record::FIELDNAME_PRIMARY ] = $value->{Record::FIELDNAME_PRIMARY};
		}

		$values[ '_title' ] = $value->getTitle();

		return $values;
	}
	
	public function cleanup() {
		$foreignFieldName = $this->getForeignFieldName();

		$value = $this->value;
		$this->value = NULL;
		
		if ( $value !== NULL ) {
			$value->unloadField( $foreignFieldName );
		}
		
		unset($value);

		unset($this->lastPersistentRealValue);
		
		parent::cleanup();
		
		$this->lastRawValue = NULL;
	}

	public function getValue() {
		return $this->value;
	}

	public function rescueValue() {
		return isset( $this->values[ $this->colName ] ) ? $this->values[ $this->colName ] : $this->lastRawValue;
	}


	protected function getRecordClass() {
		return $this->config[ 'recordClass' ];
	}

	public function setDefault( array $saveResult ) {
		if ( ( !$this->value || !$this->value->exists() ) && $this->colName && array_key_exists( 'default', $this->config ) ) {
			$this->setValue( $this->config[ 'default' ], true );
		}
	}

	protected function _setValue( $data, $loaded, $skipRaw = false, $skipReal = false, $path = NULL, array &$dirtyTracking = NULL  ) {
		$foreignRecordClass = $this->getRecordClass();

		if ( !class_exists( $foreignRecordClass ) ) {
			ClassFinder::find( array( $foreignRecordClass ), true );
		}

		if ( $data instanceof IRecord ) {
			if ( empty( $foreignRecordClass ) || !( $data instanceof $foreignRecordClass ) ) {
				throw new InvalidArgumentException( 'Field "' . $this->fieldName . '" of record class "' . get_class( $this->record ) . '" expects record of class "' . $foreignRecordClass . '", but "' . get_class( $data ) . '" given' );
			}

			if ( !$skipReal ) {
				if ($data !== $this->value) {
					$this->value = $data;
	
					if ($loaded) {
						$this->lastPersistentRealValue = $this->value;
					}
				}
			}

			if ( isset( $data->{Record::FIELDNAME_PRIMARY} ) || $data->exists() ) {
				if ( !$skipRaw ) {
					parent::setValue( (string)$data->getFieldValue( Record::FIELDNAME_PRIMARY ), $loaded, $path, $dirtyTracking );
				}
			} else {
				if ( !$skipRaw ) {
					parent::setValue( NULL, $loaded, $path, $dirtyTracking );
				} else if ( !$skipReal ) {
// FIXME: only allow upgrade to not dirty in case record is the same
					if ((bool)$loaded === $this->isDirty) {
						$this->isDirty = !$loaded;
						
						if (!$loaded && $dirtyTracking !== NULL) {
							$dirtyTracking[$path] = true;
						}
					}
				}
			}


		} else {
			if ( $data === NULL || $data === '' ) {
				if ( !$skipRaw ) {
					parent::setValue( NULL, $loaded, $path, $dirtyTracking );
				}

				if ( !$skipReal ) {
					$this->value = NULL;

					if ($loaded) {
						$this->lastPersistentRealValue = NULL;
					}
				}
			} else if ( is_array( $data ) ) {
				if ( !$skipReal && $foreignRecordClass ) {
					if ($this->value !== NULL && isset($data[Record::FIELDNAME_PRIMARY]) && isset($this->value->{Record::FIELDNAME_PRIMARY}) && $this->value->{Record::FIELDNAME_PRIMARY} === (int)$data[Record::FIELDNAME_PRIMARY]) {
						// set data on current value
						$this->value->setValues( $data, $loaded, $path, $dirtyTracking );
						
						// dirty does not change here, as the value stays the same (we don't vare if it's fields change)
					} else {
						// synthesize new record
						$this->value = $foreignRecordClass::get( $this->storage, $data, $loaded ? $loaded : Record::TRY_TO_LOAD, $path, $dirtyTracking );
						
						if ($loaded) {
							$this->lastPersistentRealValue = $this->value;
						} 
						
						
						if ((bool)$loaded === $this->isDirty) {
							$this->isDirty = !$loaded;
							
							if (!$loaded && $dirtyTracking !== NULL) {
								$dirtyTracking[$path] = true;
							}
						}
					}
					
				
				}

				if ( !$skipRaw ) {
					parent::setValue( isset( $data[ Record::FIELDNAME_PRIMARY ] ) ? $data[ Record::FIELDNAME_PRIMARY ] : NULL, $loaded, $path, $dirtyTracking );
				}
			} else if ( is_string( $data ) || is_int( $data ) ) {
				if ( !$skipReal && $foreignRecordClass ) {
					if ($this->value !== NULL && isset($this->value->{Record::FIELDNAME_PRIMARY}) && $this->value->{Record::FIELDNAME_PRIMARY} === (int)$data) {
						// in case value is the same, allow upgrading to not-dirty
						if ($loaded) {
							$this->isDirty = false;
						}
					} else {
						// only synthesize record in case it's different from the current one
						$this->value = $foreignRecordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $data ), $loaded ? $loaded : Record::TRY_TO_LOAD, $path, $dirtyTracking );
						
						if ($loaded) {
							$this->lastPersistentRealValue = $this->value;							
						}
						
						if ((bool)$loaded === $this->isDirty) {
							$this->isDirty = !$loaded;
							
							if (!$loaded && $dirtyTracking !== NULL) {
								$dirtyTracking[$path] = true;
							}
						}
					}
				}

				if ( !$skipRaw ) {
					parent::setValue( (string)$data, $loaded, $path, $dirtyTracking );
				}
			} else {
				throw new InvalidArgumentException( 'Unable to handle given type for record reference.' );
			}

		}

	}

	/**
	 * Set value
	 *
	 * takes the following as $data:
	 *
	 * - IRecord: a record instance
	 * - array: an associative array of fieldNames => values
	 * - string/int: the primary value of the foreign record
	 *
	 *
	 * @param null   $data
	 * @param bool   $loaded
	 * @param string $fieldName
	 *
	 * @throws InvalidArgumentException
	 */
	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		$oldValue = $this->value;

		$this->_setValue( $data, $loaded, false, false, $path, $dirtyTracking );

		$this->doNotifications( $oldValue, $loaded );
	}

	public function setRawValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		$this->_setValue( $data, $loaded, false, true, $path, $dirtyTracking );

		$this->lastRawValue = isset( $this->values[ $this->colName ] ) ? $this->values[ $this->colName ] : NULL;
	}

	public function setRealValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		$oldValue = $this->value;

		$this->_setValue( $data, $loaded, true, false, $path, $dirtyTracking );

		$this->doNotifications( $oldValue, $loaded );
	}

	protected function doNotifications( $oldValue, $loaded ) {
		if ( $oldValue !== $this->value ) {
			$basket = NULL;
			$foreignFieldName = $this->getForeignFieldName();

			if ( $oldValue ) {
				$oldValue->notifyReferenceRemoved( $this->record, $foreignFieldName, __FUNCTION__, $basket );
			}

			if ( $this->value ) {
				// might in turn call notifyReferenceRemoved on this record again
				$this->value->notifyReferenceAdded( $this->record, $foreignFieldName, $loaded );
			}
		}
	}


	public function fillUpValues( array $values, $loaded, $path = NULL, array &$dirtyTracking = NULL ) {
		if ( $this->value ) {
			$this->value->fillUpValues( $values, $loaded, $path, $dirtyTracking );
		} else {
			// need to remove last pathPart as we already have our fieldName appended
			$pathParts = explode('.', $path);
			array_pop($pathParts);
			
			$this->record->setValues( array( $this->fieldName => $values ), $loaded, implode('.', $path), $dirtyTracking );
		}
	}


	/**
	 * Before save
	 *
	 * calls save() on the foreign record
	 *
	 * @throws RecordReferenceMismatchException
	 */
	public function beforeSave( $isUpdate, array &$savePaths = NULL ) {
		Log::write(get_class($this->record) . '.' . $this->fieldName . ' (' . $this->record->getTitle() . ')', $savePaths);
		$valueHadBeenSet = isset( $this->value );

		if ( $this->config[ 'requireForeign' ] && !isset( $this->record->{$this->fieldName} ) && !$this->record->exists() ) { // TODO: change to some generic pre-save-validation-fail exception so it can be caught centrally
			throw new RequiredFieldNotSetException(
				'Record of type ' . $this->getRecordClass() . ' must be set on "' . get_class( $this->record ) . '"->"' . $this->fieldName . '" ; deleted=' . Debug::getStringRepresentation( $this->record->isDeleted() ) . '; values: ' . Debug::getStringRepresentation( $this->values ) . ' ; isset: ' . Debug::getStringRepresentation( isset( $this->value ) ),
				array( 'field' => $this, 'record' => $this->record )
			);
		}

		if ( $this->value !== NULL && ( $this->value->isDirty( true ) || !$this->value->exists() ) ) {
			$this->value->save( $savePaths );

			$this->refresh();
		} else if ( !isset( $this->values[ $this->colName ] ) && $this->value !== NULL && $this->value->exists() ) {
			$this->value->load();

			$this->refresh();
		} else if ( $valueHadBeenSet ) { // need to save record anyway as soon as it has been set, as record itself might not be dirty
			$this->value->save( $savePaths );
		}

		if ($this->lastPersistentRealValue !== NULL) {
			$this->lastPersistentRealValue->scheduleCheckOnSaveComplete();
		}
	}

	public function refresh() {
		if ( isset( $this->value ) && ( isset( $this->value->{Record::FIELDNAME_PRIMARY} ) || $this->value->exists() ) && ( $newPrimary = $this->value->{Record::FIELDNAME_PRIMARY} ) && ( !isset( $this->values[ $this->colName ] ) || $newPrimary != $this->values[ $this->colName ] ) ) {
			$this->setRawValue( $newPrimary, false );
		}

	}

	protected function getForeignFieldName() {
		return $this->fieldName . ':' . get_class( $this->record );
	}

	/**
	 * Before delete
	 *
	 * if datatype has been configured with requireSelf = true, it will delete the foreign record
	 */
	public function beforeDelete( array &$basket = NULL ) {
		if ( $this->record->{$this->fieldName} ) { // support lazy loading
			$this->value->notifyReferenceRemoved( $this->record, $this->getForeignFieldName(), __FUNCTION__, $basket );

			if ($this->value !== NULL) { // null check is needed for circular function calling
				if ( $this->deleteValueOnBeforeDelete()) { 
					if ( !$this->value instanceof IRecord ) {
						throw new exception( debug::getstringrepresentation( $this->value ) . " = \$this->value, not instanceof IRecord!" );
					}

					$this->value->delete( $basket );
				}

				// help with gc
				if ( $basket === NULL ) {
					$this->value = NULL;
				}
			}
		}

		if ( !isset( $this->values[ $this->colName ] ) && isset( $this->lastRawValue ) ) { // in case this is part of the primary key, the value is needed for deletion
			$this->values[ $this->colName ] = $this->lastRawValue;
		}
	}

	protected function deleteValueOnBeforeDelete() {
		return $this->config[ 'requireSelf' ];
	}


	public static function getTitleFields( $fieldName, $config ) {
		$recordClass = $config[ 'recordClass' ];

		return $recordClass::getTitleFieldsCached();
	}

	public function getFormValue() {
		$ret = NULL;
		$val = $this->record->{$this->fieldName};

		if ( $val ) {
			$primaryKeyFields = $val->getPrimaryKeyFields();

			if ( $val->fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) && !in_array( Record::FIELDNAME_PRIMARY, $primaryKeyFields ) ) {
				$primaryKeyFields[ ] = Record::FIELDNAME_PRIMARY;
			}

			$ret = array();

			foreach ( $primaryKeyFields as $fieldName ) {
				$ret[ $fieldName ] = $val->getFieldValue( $fieldName );
			}

			$formValueFields = $this->getFormValueFields();

			$ret = array_merge( $ret, $val->getFormValues( $formValueFields ) );
			$ret[ '_title' ] = $val->getTitle();
		}

		return $ret;
	}

	protected function getFormValueFields() {
		$recordClass = $this->getRecordClass();

		return $recordClass::getOwnTitleFields();
	}

	public function hasBeenSet() {
		return $this->value !== NULL || array_key_exists( $this->colName, $this->values );
	}

	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ( isset( $fieldDef[ 'recordClass' ] ) && ( $fieldDef[ 'recordClass' ] === $recordClass ) ) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTForeignReference::getFieldDefinition( $fieldDef[ 'requireForeign' ] );
		}
	}

	public function notifySaveComplete() {
		if ( $this->checkForDelete() ) {
			$this->record->delete();
		}
	}

	public function recordMaySave() {
		if ( $this->config[ 'requireForeign' ] && ( !$this->value ) && ( !isset( $this->values[ $this->colName ] ) ) && $this->hasBeenSet() ) {
			return false;
		}

		return parent::recordMaySave();
	}

	public function checkForDelete() {
		// TODO: should we check $this->hasBeenSet() ?
		if ( ( !$this->record->isDeleted() ) && $this->config[ 'requireForeign' ] && ( !$this->value ) && ( $this->hasBeenSet() || !$this->record->{$this->fieldName} ) && ( !isset( $this->values[ $this->colName ] ) ) ) {
			return true;
		}

		return false;
	}

	protected function mayCopyReferenced() {
		return false;
	}

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedRecords ) {
		$val = $this->record->getFieldValue( $this->fieldName );

		if ( $val && ( isset( $changes[ 'live' ] ) || isset( $changes[ 'language' ] ) ) ) {
			if ( ( $copiedVal = $val->getCopiedRecord() ) !== NULL ) { 
				$val = $copiedVal;
			} else {
				$mayCopyReferenced = $this->mayCopyReferenced();
				
				if ( $mayCopyReferenced ) {
					$nval = $val->copy( $changes, $missingReferences, $originRecords, $copiedRecords, NULL, $values, $this->fieldName );

					if ( !in_array( $val, $missingReferences, true ) && !$nval->exists() ) {
						$missingReferences[ ] = $val;
					}

					$val->setMeta( 'missing', false );
					$val->setMeta( 'copied', $nval );
				} else if ( $mayCopyReferenced === 0 ) {
					// $mayCopyRefenced should not return 0 unless requireForeign is false 
					$nval = NULL;
				} else if ( $val->isDirty( true ) || ( ( $nval = $val->getFamilyMember( $changes ) ) && !$nval->exists() ) ) {
					// might exist from an old published record, in which case we would still want to copy
				
					if ( $val->getMeta( 'missing' ) !== false ) {
						$val->setMeta( 'missing', true );

						$nval = $val->copy( $changes, $missingReferences, $originRecords, $copiedRecords, NULL, $values, $this->fieldName );

						$val->setMeta( 'copied', $nval );
					}

					if ( !in_array( $val, $missingReferences, true ) ) {
						$missingReferences[ ] = $val;
					}
				} else {
					// 
				}

				$val = $nval;
			}
		}

		$values[ $this->fieldName ] = $val;
	}

	public function earlyCopy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedOriginRecords ) {
		$val = $this->record->getFieldValue( $this->fieldName );

		if ( $val && ( ( isset( $changes[ 'live' ] ) && $liveField = $val->getDataTypeFieldName( 'DTSteroidLive' ) ) || ( isset( $changes[ 'language' ] ) && $langField = $val->getDataTypeFieldName( 'DTSteroidLanguage' ) ) ) ) {
			if ( $copiedRecord = $val->getCopiedRecord() ) {
				$values[ $this->fieldName ] = $copiedRecord;
			}
		}


	}

	public static function adaptFieldConfForDB( array &$fieldConf ) {
		$fieldConfNullable = $fieldConf[ 'nullable' ];
		$foreignRecordClass = $fieldConf[ 'recordClass' ];

		if ( !class_exists( $foreignRecordClass ) ) {
			ClassFinder::find( array( $foreignRecordClass ), true );
		}

		$fieldConf = $foreignRecordClass::getFieldDefinition( 'primary' );
		$fieldConf[ 'nullable' ] = $fieldConfNullable;
		$fieldConf[ 'default' ] = NULL;
		$fieldConf[ 'autoInc' ] = false;
	}


	public function notifyReferenceRemoved( IRecord $originRecord, $triggeringFunction, array &$basket = NULL ) {
		if ( $this->hasBeenSet() || $this->record->exists() ) {
			// need to check this in case we get remove notification after already having a new value
			if ( ( $this->value && ( $originRecord === $this->value ) ) || ( isset( $this->values[ $this->colName ] ) && ( isset( $originRecord->{Record::FIELDNAME_PRIMARY} ) || $originRecord->exists() )	&& $originRecord->{Record::FIELDNAME_PRIMARY} == $this->values[ $this->colName ] ) ) {
				if ( isset( $this->values[ $this->colName ] ) ) { // this is needed so we can still delete the record if this reference is part of the primary key
					$lastRawValue = $this->values[ $this->colName ];
				}

				$this->record->wrapReindex( $this->fieldName, function() {
					$this->_setValue( NULL, false );	
				});
					
				if ( isset( $lastRawValue ) ) {
					$this->lastRawValue = $lastRawValue;
				}
			}
		}
	}

	public function notifyReferenceAdded( IRecord $originRecord, $loaded ) {
		// skip setting if we already have the right value
		if ( $this->value !== NULL && $originRecord === $this->value ) {
			return;
		}

		// FIXME: we actually don't know if the value we get is the same as in DB
		$this->record->wrapReindex( $this->fieldName, function() use ( $originRecord ){
			$this->_setValue( $originRecord, false );
		});
	}
	
	public function unload() {
		// loop guard
		if ( $this->value !== NULL ) {
			$foreignFieldName = $this->getForeignFieldName();
			
			$value = $this->value;
			$this->value = NULL;
			
			$value->unloadField( $foreignFieldName );
			
			unset($value);

			// might be needed for delete afterwards, so we don't unset it here
			// $this->lastRawValue = NULL;
			
			parent::unload();
		}		
	}
	
	protected static function getRequiredPermissions( $fieldDef, $fieldName, $currentForeignPerms, $permissions, $owningRecordClass ) {
		$owningRecordPerms = $permissions[ $owningRecordClass ];

		$mayWrite = $owningRecordPerms[ 'mayWrite' ] && $fieldDef[ 'recordClass' ]::ALLOW_CREATE_IN_SELECTION ? 1 : 0;

		return !$fieldDef[ 'requireForeign' ] || ( !empty( $currentForeignPerms ) && ( !$mayWrite || ( $mayWrite && $currentForeignPerms[ 'mayWrite' ] ) ) ) ? NULL : array(
			'mayWrite' => $mayWrite
		);
	}

	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {
		if ( !isset( $fieldDef[ 'recordClass' ] ) ) {
			return;
		}

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

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, array $fieldDef ) {
		foreach ( $userFilters as $idx => $filterConf ) {
			if ( array_key_exists( $fieldName, $filterConf[ 'filterFields' ] ) && $filterConf[ 'filterType' ] === 'string' ) { // we're searching through the title fields of the referenced recordClass
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

							if ( isset( $fieldDef[ 'searchType' ] ) && $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_BOTH || $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_PREFIX ) {
								$val = '%' . $val;
							}

							if ( isset( $fieldDef[ 'searchType' ] ) && $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_BOTH || $fieldDef[ 'searchType' ] === BaseDTString::SEARCH_TYPE_SUFFIX ) {
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
			} else {
				parent::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, $fieldDef );
			}
		}
	}
}

class CannotCopyRecordException extends SteroidException {
}

class RequiredFieldNotSetException extends SteroidException {
}
