<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/page/class.RCPage.php';
require_once STROOT . '/user/class.User.php';


/**
 * datatype for the system's internal page reference field where the page is created/edited via a foreign record. do not mess with this.
 *
 * @package steroid\page
 */
class DTSteroidPage extends BaseDTRecordReference {

	public static function getFieldDefinition( $parentFromField = null ) {
		return array(
			'dataType'        => get_called_class(),
			'recordClass'     => 'RCPage',
			'nullable'        => false,
			'parentFromField' => $parentFromField,
			'requireForeign'  => true, // when page is deleted, this record is deleted as well
			'requireSelf'     => true, // when this record is deleted, page is deleted as well
			'constraints'     => array( 'min' => 1, 'max' => 1 )
		);
	}

	public static function getFormConfig( IRBStorage $storage, $owningRecordClass, $fieldName, $fieldDef ) {
		$fieldDef = parent::getFormConfig( $storage, $owningRecordClass, $fieldName, $fieldDef );

		$fields = array_keys( RCPage::getFormFields( $storage, RCPage::getEditableFormFields( array() ) ) );

		array_splice( $fields, array_search( 'title', $fields ), 1 );
		array_splice( $fields, array_search( 'parent', $fields ), 1 );
//		array_splice( $fields, array_search( 'robots', $fields ), 1 );
		array_splice( $fields, array_search( 'excludeFromSearch', $fields ), 1 );
		array_splice( $fields, array_search( 'image', $fields ), 1 );

		$fieldDef[ 'formFields' ] = $fields;

		return $fieldDef;
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = null, array $fieldConf = null, array $extraParams = null ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		$rc = $fieldConf[ 'recordClass' ];

		$defaults = $rc::getDefaultValues( $storage, array_keys( $rc::getFormFields( $storage ) ), $extraParams );

		$defaults[ 'pageType' ] = $extraParams[ 'recordClasses' ][ 0 ];

		return $defaults;
	}

	public function getFormValue() {
		$val = $this->record->{$this->fieldName};

		if ( $val ) {
			$fields = array_keys( $val->getFormFields( $this->storage ) );

			$values = $val->getFormValues( $fields );
		}

		return $values;
	}


	protected function deleteValueOnBeforeDelete() {
		return true;
	}

	protected function getNeededPageValues() {
		$pageLiveField        = RCPage::getDataTypeFieldName( 'DTSteroidLive' );
		$pageLanguageField    = RCPage::getDataTypeFieldName( 'DTSteroidLanguage' );
		$pageCreatorField     = RCPage::getDataTypeFieldName( 'DTSteroidCreator' );
		$pageDomainGroupField = RCPage::getDataTypeFieldName( 'DTSteroidDomainGroup' );

		$mainRecordLiveField        = $this->record->getDataTypeFieldName( 'DTSteroidLive' );
		$mainRecordLanguageField    = $this->record->getDataTypeFieldName( 'DTSteroidLanguage' );
		$mainRecordCreatorField     = $this->record->getDataTypeFieldName( 'DTSteroidCreator' );
		$mainRecordDomainGroupField = $this->record->getDataTypeFieldName( 'DTSteroidDomainGroup' );

		if ( $mainRecordDomainGroupField ) {
			$this->value->{$pageDomainGroupField} = $this->record->{$mainRecordDomainGroupField};
		} else {
			$this->value->{$pageDomainGroupField} = User::getCurrent()->getSelectedDomainGroup();
		}

		$this->value->{$pageCreatorField} = $this->record->{$mainRecordCreatorField};

		if ( $mainRecordLanguageField ) {
			$this->value->{$pageLanguageField} = $this->record->{$mainRecordLanguageField};
		} else {
			$this->value->{$pageLanguageField} = $this->storage->selectFirstRecord( 'RCLanguage', array(
				'where' => array(
					'live',
					'=',
					array( $this->record->{$mainRecordLiveField} )
				)
			) );
		}

		$this->value->{$pageLiveField} = $this->record->{$mainRecordLiveField};

		$this->value->pageType = get_class( $this->record );
		$this->value->title    = $this->record->getTitle();
	}

	protected function mayCopyReferenced() {
		return true;
	}

	protected function collectFromParentFromField( IRecord $record ) {
		//FIXME: $record might be a totally different kind of record that's incompatible with this record's config
		$values = $record->collect( $this->config[ 'parentFromField' ] );

		if ( ! $values ) {
			throw new NoParentPageException( 'Could not collect page from path set in "parentFromField" for record "' . $record->getTitle() . '"', array(
				'record' => $record->getTitle()
			) );
		}

		$parentPage = null;

		foreach ( $values as $page ) {
			if ( $page === null ) {
				continue;
			}

			if ( $page->live == $record->live && $page->language === $record->language && $page->domainGroup === $record->domainGroup ) {
				$parentPage = $page;
				break;
			}
		}

		if ( ! $parentPage ) {
			throw new NoParentPageException( 'Could not find a parent page for record "' . $record->getTitle() . '"', array(
				'record' => $record->getTitle()
			) );
		}

		return $parentPage;
	}

	protected function setParentPage() {
		$parentPage = null;

		if ( $this->config[ 'parentFromField' ] !== null ) {
			$parentPage = $this->collectFromParentFromField( $this->record ); // may throw
		} else {
			$valueLiveField = $this->value->getDataTypeFieldName( 'DTSteroidLive' );

			$live = $this->value->{$valueLiveField};

			$mainRecordDomainGroupField = $this->record->getDataTypeFieldName( 'DTSteroidDomainGroup' );

			if ( $mainRecordDomainGroupField ) {
				$domainGroup = $this->record->{$mainRecordDomainGroupField};
			} else {
				$domainGroup = User::getCurrent()->getSelectedDomainGroup();
			}

			$mainRecordLanguageField = $this->record->getDataTypeFieldName( 'DTSteroidLanguage' );

			$language = null;

			if ( $mainRecordLanguageField ) {
				$language = $this->record->{$mainRecordLanguageField};
			}

			if ( ! $language ) {
				$language = $this->storage->selectFirstRecord( 'RCLanguage', array(
					'where' => array(
						'live',
						'=',
						array( $live )
					)
				) );
				//$currentUser = User::getCurrent();

				//if ( $currentUser ) {
				//$language = $currentUser->getSelectedLanguage();
				//} else {
				//	throw new Exception( "Unable to determine language as no current user is set" );
				//}
			}

			if ( ! $language ) {
				$valueLanguageField = $this->value->getDataTypeFieldName( 'DTSteroidLanguage' );

				if ( ! $valueLanguageField || ! ( $language = $this->value->{$valueLanguageField} ) ) {
					throw new LogicException( 'Cannot deduce a language for parent page' );
				}
			}

			// need to select language via id, as RCDefaultParentPage has no live, and thus only a record connected to language in preview state exists
			$where = array(
				'recordClass',
				'=',
				array( get_class( $this->record ) ),
				'AND',
				'domainGroup',
				'=',
				array( $domainGroup ),
				'AND',
				'language.id',
				'=',
				array( $language->id )
			);

			$defaultRecord = $this->storage->selectFirstRecord( 'RCDefaultParentPage', array(
				'fields' => array(
					'*',
					'language' => array( 'fields' => '*' )
				),
				'where'  => $where
			) );

			if ( ! $defaultRecord || ( $parentPage = $defaultRecord->page ) === null || ! ( $parentPage = $parentPage->getFamilyMember( array( 'live' => $live ) ) ) ) {
				$parentPage = $this->storage->selectFirstRecord( 'RCPage', array(
					'where' => array(
						'parent',
						'=',
						null,
						'AND',
						'domainGroup',
						'=',
						array( $domainGroup ),
						'AND',
						'live',
						'=',
						array( $live ),
						'AND',
						'language.id',
						'=',
						array( $language->id )
					)
				) );
			}
		}

		// TODO: what if parent page does not exist? (e.g. in case getFamilyMember returns new record)
		if ( ! $parentPage ) {
			throw new NoRootPageException( 'Cannot create page because there is no parent. Did you create a root page?', array(
				'record'       => $this->value->getTitle(),
				'targetRecord' => $domainGroup ? $domainGroup->getTitle() : $this->record->domainGroup->getTitle()
			) );
		}

		if ( $this->value->live != $parentPage->live ) {
			throw new Exception( 'Parent and child live values do not match!' );
		}

		$this->value->parent = $parentPage;
	}

	// FIXME: move logic to setValue listener
	public function beforeSave( $isUpdate, array &$savePaths = null ) {
		$this->value = $this->record->{$this->fieldName};

		if ( $isUpdate ) {
			$newTitle = $this->record->getTitle();

			if ( $this->value->title !== $newTitle ) {
				$this->value->title = $newTitle;
			}
		} else {
			$this->getNeededPageValues();
		}

		$this->setParentPage();

		parent::beforeSave( $isUpdate, $savePaths );
	}

	protected static function getRequiredPermissions( $fieldDef, $fieldName, $currentForeignPerms, $permissions, $owningRecordClass ) {
		$owningRecordPerms = $permissions[ $owningRecordClass ];

		return ! $owningRecordPerms[ 'mayWrite' ] ? null : array(
			'mayWrite' => 1
		);
	}

	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {
		$titleOnly = false;

		parent::fillRequiredPermissions( $permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly );
	}

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedOriginRecords ) {
		parent::copy( $values, $changes, $missingReferences, $originRecords, $copiedOriginRecords );

		// if parentFromField is set to path, we need to make sure at least one chain on this path gets copied as well
		if ( $this->config[ 'parentFromField' ] !== null ) {
			$copiedRec = $values[ $this->fieldName ];

			// first check, if there isn't already a copied chain on this path
			try {
				// try to collect parentPage on copied record
				$parentPage = $this->collectFromParentFromField( $this->record->getFamilyMember( $changes ) ); // may throw
			} catch ( NoParentPageException $e ) { // all other exceptions may fall through
				// if no parentPage could be found, we need to find a chain and copy it

				$path = $this->config[ 'parentFromField' ];

				// get chain from original record
				$chain = $this->record->getChainByPath( $path );

				if ( ! $chain ) {
					throw new Exception( "Unable to find page for original record" );
				}

				array_shift( $chain ); // remove first record, which is this record

				foreach ( $chain as $rec ) {
					if ( $pageField = $rec->getDataTypeFieldName( 'DTSteroidPage' ) ) {
						// as soon as we encounter an existing parent page, we can skip copying since it implies that all necessary parent pages in the chain exist
						if ( $rec->{$pageField}->getFamilyMember( $changes )->exists() ) {
							break;
						}
					}

					$copiedRec = $rec->copy( $changes, $missingReferences, $originRecords, $copiedOriginRecords );

					// no need to do anything with copiedRec, as notify* functions should handle connections
				}
			}
		}
	}
}