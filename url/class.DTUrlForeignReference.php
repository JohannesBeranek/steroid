<?php
/**
 * @package steroid\url
 */

require_once STROOT . '/datatype/class.BaseDTForeignReference.php';
require_once STROOT . '/datatype/class.DTSteroidReturnCode.php';
require_once STROOT . '/url/class.RCUrl.php';
require_once STROOT . '/domain/class.RCDomain.php';
require_once STROOT . '/url/class.UrlUtil.php';


class DTUrlForeignReference extends BaseDTForeignReference {

	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'nullable' => true,
			'requireSelf' => true,
			'constraints' => array( 'min' => 0 )
		);
	}

	public function getFormValue() {
		$urls = parent::getFormValue();

		$url = '';
		$livePage = $this->record->getFamilyMember( array( 'live' => 1 ) );

		if ( $livePage->exists() ) {
			$primaryUrl = NULL;
			$primaryDomain = NULL;

			$urlJoins = $livePage->{'page:RCPageUrl'};

			if ( empty( $urlJoins ) ) {
				return 'NO_URL';
			}

			foreach ( $urlJoins as $urlJoin ) {
				if ( $urlJoin->url->returnCode == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) {
					$primaryUrl = $urlJoin->url;
					break;
				}
			}

			if ( !$primaryUrl ) {
				$primaryUrl = $urlJoins[ 0 ]->url;
			}

			if ( $primaryUrl ) {
				$domains = $primaryUrl->domainGroup->{'domainGroup:RCDomain'};

				foreach ( $domains as $domain ) {
					if ( $domain->returnCode == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) {
						$primaryDomain = $domain;
						break;
					}
				}
			}

			if ( $primaryDomain ) {
				$url = 'http://' . $primaryDomain->domain . $primaryUrl->url;
			} else {
				$url = 'NO_DOMAIN';
			}
		} else {
			$url = 'NO_LIVE';
		}

		return array(
			'primary' => $url,
			'urls' => $urls
		);
	}

	public function beforeSave( $isUpdate ) {
		$recordLiveFieldName = $this->record->getDataTypeFieldName( 'DTSteroidLive' );
		$recordIsLive = $recordLiveFieldName && ( $recordLiveStatus = $this->record->getFieldValue($recordLiveFieldName) );

		if ( $recordIsLive || ( $recordLiveFieldName && $recordLiveStatus === NULL ) ) {
			return;
		}

		$recordLanguageFieldName = $this->record->getDataTypeFieldName( 'DTSteroidLanguage' );
		$recordParentFieldName = $this->record->getDataTypeFieldName( 'DTParentReference' );

		$recordCustomUrlFieldName = $this->record->getDataTypeFieldName( 'DTSteroidUrl' );

		$recordDomainGroupFieldName = $this->record->getDataTypeFieldName( 'DTSteroidDomainGroup' );
		$recordDomainGroup = $this->record->getFieldValue( $recordDomainGroupFieldName );

		$recordTitle = $this->record->getTitle();

		if ( !$recordDomainGroup ) {
			throw new LogicException( 'Cannot handle Urls of class "' . get_class( $this->record ) . '" with title "' . $recordTitle . '" without domainGroup set, values: ' . Debug::getStringRepresentation( $this->record->getValues() ) );
		}

		$urlHandler = $this->storage->selectFirstRecord( 'RCUrlHandler', array( 'where' => array( 'className', '=', array( 'UHPage' ) ) ) ); // TODO: softcode?

		if ( !$urlHandler ) {
			throw new LogicException( 'Cannot handle Urls without "UHPage" url handler' );
		}



		//  if field wasn't loaded but the record exists, $isUpdate should be true
		if ( $isUpdate || isset( $this->record->{$this->fieldName} ) ) {
			$currentUrls = $this->record->getFieldValue($this->fieldName);
		}

		if(!$isUpdate && isset($currentUrls) && count($currentUrls)){
			return;
		}

		$parentRecord = NULL;

		if ( $recordParentFieldName ) { // get parent Urls if record has a parent
			if ( isset( $this->record->{$recordParentFieldName} ) || $this->record->exists() ) {
				$parentRecord = $this->record->{$recordParentFieldName};
			}

			$parentUrls = $this->generateBaseUrls( $recordTitle, $recordLanguageFieldName, $parentRecord );
		} else {
			$parentUrls = array();
		}

		// make sure we translate the urls correctly
		if ( $recordLanguageFieldName ) {
			$oldLocale = setlocale( LC_CTYPE, 0 );
			setlocale( LC_CTYPE, $this->record->{$recordLanguageFieldName}->locale );
		}

		if ( isset( $parentRecord ) ) { // || $this->rootPageExists($urlHandler, $recordDomainGroup)) {
			$generatedUrls = array();

			foreach ( $parentUrls as $url ) {
				$generatedUrls[ ] = ( $url === '/' ? '' : $url ) . '/' . UrlUtil::generateUrlPartFromString( $recordTitle );
			}
		} else {
			$generatedUrls = $parentUrls;
		}

		// in case of !$recordParentFieldName, $generatedUrls will still be an empty array here

		if ( $recordCustomUrlFieldName && ( isset( $this->record->{$recordCustomUrlFieldName} ) || $this->record->exists() ) ) { // get custom Url
			$customUrl = $this->record->{$recordCustomUrlFieldName};

			if ( $customUrl !== NULL && $customUrl !== '' ) { // empty check would be wrong, as field might be "0", which would still be valid
				array_unshift( $generatedUrls, UrlUtil::generateUrlFromString( $customUrl ) );
			}
		}

		// reset locale in case we set it before
		if ( isset( $oldLocale ) ) {
			setlocale( LC_CTYPE, $oldLocale );
		}

		foreach ( $generatedUrls as $key => $url ) { // generate records
			$generatedUrls[ $key ] = $this->createUrlJoinRecordFromUrlString( $url, $urlHandler, $recordDomainGroup );
		}

		if ( !empty( $currentUrls ) ) {
			foreach ( $currentUrls as $key => $join ) {
				if ( in_array( $join, $generatedUrls, true ) ) {
					unset( $currentUrls[ $key ] );
				}
			}

			$generatedUrls = array_merge( $generatedUrls, $currentUrls );
		}

		// if we got a root url, make sure it's the first (and thus gets primary status)
		foreach ( $generatedUrls as $k => $urlJoinRecord ) {
			if ( $urlJoinRecord->url->url === '/' ) {
				unset( $generatedUrls[ $k ] );
				array_unshift( $generatedUrls, $urlJoinRecord );

				break; // there should only be one root url at most, and even if there are more, it doesn't add any benefit moving them all to start
			}
		}

		$urls = array();

		$isFirst = true;

		foreach ( $generatedUrls as $k => $urlJoinRecord ) {
			$url = $urlJoinRecord->url->url;

			if ( in_array( $url, $urls, true ) ) {
				unset( $generatedUrls[ $k ] );
				continue;
			}

			$urls[ ] = $url;

			// TODO: this means no primary url for something without parentRecord but url not '/'
			if ( $isFirst || $urlJoinRecord->url->url === '/' ) {
				$returnCode = DTSteroidReturnCode::RETURN_CODE_PRIMARY;
				$isFirst = false;
			} else {
				$returnCode = DTSteroidReturnCode::RETURN_CODE_ALIAS;
			}

			if ( $urlJoinRecord->url->returnCode !== $returnCode ) { // only set new returnCode in case returnCodes differ, so we avoid unneccessary saving
				$urlJoinRecord->url->returnCode = $returnCode;
			}
		}
		

		// FIXME: using setValue in beforeSave is dangerous, as record might be marked as not dirty and become dirty from this!
		$this->setValue( $generatedUrls, false );
	}

	// TODO: function does something different from its naming
	protected function rootPageExists( $urlHandler, $recordDomainGroup ) {
		$rootUrl = $this->storage->select( 'RCUrl', array( 'where' => array( 'url', '=', array( '/' ), 'AND', 'urlHandler', '=', array( $urlHandler ), 'AND', 'domainGroup', '=', array( $recordDomainGroup ) ) ) );

		return !empty( $rootUrl );
	}

	protected function createUrlJoinRecordFromUrlString( $url, $urlHandler, $recordDomainGroup ) {
		$ct = 0;
		$generatedUrl = $url;

		do {
			if ( $ct > 0 ) {
				$generatedUrl = $url . '-' . $ct;
			}

			$urlRecord = RCUrl::get( $this->storage, array(
				'url' => $generatedUrl,
				'live' => DTSteroidLive::LIVE_STATUS_PREVIEW, // we don't generate live urls as they are copied
				'domainGroup' => $recordDomainGroup
			), '*' );

			if ( !$urlRecord->exists() ) break;

			if ( !isset( $recordExists ) ) {
				$recordExists = $this->record->exists();
			}

			if ( $recordExists ) {
				$pageUrl = $this->storage->selectFirstRecord( 'RCPageUrl', array( 'fields' => '*', 'where' => array( 'url', '=', array( $urlRecord ), 'AND', 'page', '=', array( $this->record ) ) ) );
			}

			$ct++;
		} while ( empty( $pageUrl ) );

		if ( empty( $pageUrl ) ) {
			$pageUrl = RCPageUrl::get( $this->storage, array( 'url' => $urlRecord, 'page' => $this->record ), false );
		}

		$urlRecord->urlHandler = $urlHandler;

		return $pageUrl;
	}

	protected function generateBaseUrls( $recordTitle, $recordLanguageFieldName, IRecord $parentRecord = NULL ) {
		if ( $parentRecord !== NULL ) { // page has a parent, so just take the parent's urls
			$parentUrls = $parentRecord->{'page:RCPageUrl'};

			if ( empty( $parentUrls ) ) {
				throw new LogicException( 'Parent page "' . $parentRecord->getTitle() . '" has no urls' );
			}

			$sortedParentUrls = array();

			foreach ( $parentUrls as $parentUrl ) { // get the actual Url string ($parentUrl is a joinRecord)
				if ( $parentUrl->url->returnCode == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) { // FIXME: make sure we can use '===' here instead of '=='
					array_unshift( $sortedParentUrls, $parentUrl->url->url );
				} else {
					$sortedParentUrls[ ] = $parentUrl->url->url;
				}
			}

			$parentUrls = $sortedParentUrls;
		} else { // page doesn't have a parent, so assume it's the root page
			if ( $recordLanguageFieldName && $this->record->{$recordLanguageFieldName}->isDefault ) {
				$parentUrls = array( '/' );
			} else {
				$language = $this->record->{$recordLanguageFieldName};

				$parentUrls = array( '/' . $language->iso639 ); // TODO: should be configurable
			}
		}

		// returned array always starts with 0 ( important for primary url determining algorithm )
		return $parentUrls;
	}

	public function beforeDelete( array &$basket = NULL ) {
		$foreignRecords = $this->getForeignRecords();

		foreach ( $foreignRecords as $record ) {
			$record->url->delete( $basket );
		}

		parent::beforeDelete( $basket );
	}

	protected static function getRequiredPermissions( $fieldDef, $fieldName, $currentForeignPerms, $permissions, $owningRecordClass ) {
		$owningRecordPerms = $permissions[ $owningRecordClass ];

		return array(
			'mayWrite' => $owningRecordPerms[ 'mayWrite' ]
		);
	}

	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {
		parent::fillRequiredPermissions( $permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly );

		$owningRecordPerms = $permissions[ $owningRecordClass ];

		if ( !isset( $permissions[ 'RCUrl' ] ) || $owningRecordPerms[ 'mayWrite' ] > $permissions[ 'RCUrl' ][ 'mayWrite' ] ) {
			$permissions[ 'RCUrl' ] = array(
				'mayWrite' => $owningRecordPerms[ 'mayWrite' ],
				'isDependency' => 1,
				'restrictToOwn' => 0
			);

			RCUrl::fillRequiredPermissions( $permissions, $titleOnly );
		}
	}

}
