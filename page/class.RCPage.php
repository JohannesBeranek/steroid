<?php

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTText.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/language/class.DTSteroidLanguage.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/url/class.DTUrlForeignReference.php';
require_once STROOT . '/page/class.DTPageAreaJoinForeignReference.php';
require_once STROOT . '/page/class.DTPageMenuItem.php';
require_once STROOT . '/template/class.RCTemplate.php';
require_once STROOT . '/request/class.RequestInfo.php';

require_once STROOT . '/template/class.DTTemplate.php';
require_once STROOT . '/storage/record/class.DTRecordClassSelect.php';

require_once __DIR__ . '/class.DTSteroidPage.php';
require_once STROOT . '/datatype/class.DTSelect.php';

require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/util/st_parse_url.php';


class RCPage extends Record {
	const PUBDATE_RECORD = true;
	const BACKEND_TYPE = Record::BACKEND_TYPE_CONTENT;

	const ABSOLUTE_DOMAIN_ONLY = 1;
	const ABSOLUTE_NO_PROTOCOL = '';
	const ABSOLUTE_HTTP = 'http';
	const ABSOLUTE_HTTPS = 'https';
	const ABSOLUTE_AUTO = true;

	const ROBOTS_NOINDEX = 'noindex';
	const ROBOTS_NOFOLLOW = 'nofollow';
	const ROBOTS_NONE = 'none';

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'id', 'language', 'live' ) ),
			'title' => DTKey::getFieldDefinition( array( 'title' ), false, false ), // non unique key for increasing search performance in db
			'k_parent' => DTKey::getFieldDefinition( array( 'parent' ), false, false ),
			'k_forward' => DTKey::getFieldDefinition( array( 'forwardTo' ), false, false )
		);
	}


	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'title' => DTString::getFieldDefinition( 255, false, NULL, false ),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'language' => DTSteroidLanguage::getFieldDefinition(),
			'template' => DTTemplate::getFieldDefinition( true ),
			'parent' => DTParentReference::getFieldDefinition(),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'customUrl' => DTSteroidUrl::getFieldDefinition( NULL, true ),
			'forwardTo' => DTRecordReference::getFieldDefinition( 'RCPage' ),
			'pageType' => DTRecordClassSelect::getFieldDefinition( array( 'RCPage', array( 'RCPage', 'pageTypeFilter' ) ), false, 'RCPage' ),
			'robots' => DTSelect::getFieldDefinition( array( self::ROBOTS_NOINDEX, self::ROBOTS_NOFOLLOW, self::ROBOTS_NONE ), true ),
			'description' => DTText::getFieldDefinition( NULL, true ),
			'domainGroup' => DTSteroidDomainGroup::getFieldDefinition(),
			'page:RCPageUrl' => DTUrlForeignReference::getFieldDefinition( array( 'min' => 1 ) ),
			'page:RCPageArea' => DTPageAreaJoinForeignReference::getFieldDefinition(),
			'page:RCMenuItem' => DTPageMenuItem::getFieldDefinition(),
			'image' => DTImageRecordReference::getFieldDefinition()
		);
	}

	public static function pageTypeFilter() {
		return DTRecordClassSelect::getRecordClassesWithDataType( 'DTSteroidPage' );
	}

	public static function getEditableFormFields( array $fields = NULL ) {
		$fields = parent::getEditableFormFields( $fields );
		$fields[ ] = 'page:RCPageUrl';
		$fields[ ] = 'page:RCMenuItem';
		$fields[ ] = 'page:RCPageArea';

		return $fields;
	}

	protected static function addPermissionsForReferencesNotInFormFields() {
		return array(
			'page:RCPageUrl',
			'page:RCDefaultParentPage'
		);
	}

	public static function fillRequiredPermissions( array &$permissions, $titleOnly = false ) {
		parent::fillRequiredPermissions( $permissions, $titleOnly );

		if ( !isset( $permissions[ 'RCArea' ] ) ) {
			$permissions[ 'RCArea' ] = array(
				'mayWrite' => true,
				'isDependency' => true,
				'restrictToOwn' => false
			);
		}

		if ( !isset( $permissions[ 'RCPubDateEntries' ] ) ) {
			$permissions[ 'RCPubDateEntries' ] = array(
				'mayWrite' => true,
				'isDependency' => true,
				'restrictToOwn' => false
			);
		}
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass ) {
		parent::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass );

		if ( $recordClass === 'RCPage' && ( !$requestingRecordClass || $requestingRecordClass === 'RCPage' ) && !$requestFieldName ) {
			// filter out any pages that are not static in page module list
			if ( !isset( $queryStruct[ 'where' ] ) ) {
				$queryStruct[ 'where' ] = array();
			} else if ( !empty( $queryStruct[ 'where' ] ) ) {
				$queryStruct[ 'where' ][ ] = 'AND';
			}

			$queryStruct[ 'where' ][ ] = 'pageType';
			$queryStruct[ 'where' ][ ] = '=';
			$queryStruct[ 'where' ][ ] = array( 'RCPage' );
		}
	}

	protected function getCopiedForeignFields() {
		$fields = parent::getCopiedForeignFields();
		$fields[ ] = 'page:RCPageArea';
		$fields[ ] = 'page:RCPageUrl';

		return $fields;
	}

	public static function getTargetPage( RCPage $page ) {
		$visited = array( $page );

		while ( ( $newPage = $page->forwardTo ) && ( !in_array( $newPage, $visited, true ) ) ) {
			$page = $newPage;
			$visited[ ] = $page;
		}

		return $page;
	}

	public function listFormat( User $user, array $filter, $isSearchField = false ) { // [JB 19.02.2013] made this none static, so we can operate on the record we already got
		$values = parent::listFormat( $user, $filter, $isSearchField );

		$values[ '_parent' ] = NULL;

		if ( $this->parent ) {
			$values[ '_parent' ] = $this->parent->primary;
		}

		if ( $isSearchField ) {
			$values[ '_title' ] = $this->domainGroup->getTitle() . ' -> ' . $values[ '_title' ];
		}

		return $values;
	}

	/**
	 * Should be used for linking
	 *
	 * Passed urls are treated as per rfc3986 [ http://tools.ietf.org/html/rfc3986 ]
	 *
	 * $page->getUrlForPage() // link to current page
	 * $page->getUrlForPage( $page ) // link to current page keeping current url query parameters
	 * $page->getUrlForPage( $params ) // link to current page with params mixed into current url query parameters
	 * $page->getUrlForPage( $page, $params ) // link to current page with params
	 * $page->getUrlForPage( $otherPage ) // link to other page ; current url query parameters are not kept automatically!
	 * $page->getUrlForPage( $otherPage, true ) // link to other page, using current url query parameters
	 * $page->getUrlForPage( $urlAsString ) // link to external url
	 * $page->getUrlForPage( $urlAsString, $params ) // link to external url, mix in $params
	 * $page->getUrlForPage( true, false ) // absolute link to current page without keeping url params
	 * $page->getUrlForPage( $params, NULL, true ) // link to current page with only $params, removing/overwriting already existing params
	 * $page->getUrlForPage( RCPage::ABSOLUTE_HTTPS ) // absolute link to current page starting with 'https' (possible values: RCPage::ABSOLUTE_*)
	 *
	 * @param null|string|array|RCPage    $page null or params array to use current page, string for external linking, RCPage record to link to given page
	 * @param null|array                  $params key-value pairs to set GET params on generated url ; will keep current params for current page ; params may be unset by passing NULL as value for param key
	 * @param null|boolean|string|integer $forceAbsolute NULL or one of RCPage::ABSOLUTE_*
	 * @param null|boolean                $cancelParams remove already existing url query parameters ( can be useful in combination with set $params for current page )
	 *
	 * @return string
	 */
	public function getUrlForPage( $page = NULL, $params = NULL, $forceAbsolute = NULL, $cancelParams = NULL ) {
		if ( is_string( $page ) && $page !== self::ABSOLUTE_HTTP && $page !== self::ABSOLUTE_HTTPS && $page !== self::ABSOLUTE_NO_PROTOCOL ) {
			$urlParts = st_parse_url( $page );

			$url = '';

			if ( empty( $urlParts[ 'host' ] ) && $forceAbsolute ) {
				$p = $params instanceof RCPage ? $params : $this;

				$urlPrefix = $this->getUrlForPage( $p, NULL, true );

				$prefixParts = st_parse_url( $urlPrefix );

				$urlParts[ 'host' ] = $prefixParts[ 'host' ];
				$urlParts[ 'scheme' ] = $prefixParts[ 'scheme' ];
			}

			if ( !empty( $urlParts[ 'scheme' ] ) ) {
				$url = $urlParts[ 'scheme' ] . ':';
			} else if ( $forceAbsolute ) { // $page is string and $forceAbsolute is set
				if ($forceAbsolute === self::ABSOLUTE_AUTO) {
					$url = RequestInfo::getCurrent()->getServerInfo(RequestInfo::PROXY_SAFE_IS_HTTPS, false) ? 'https:' : 'http:';
				} else if ($forceAbsolute === self::ABSOLUTE_HTTP || $forceAbsolute === self::ABSOLUTE_HTTPS) {
					$url = $forceAbsolute . ':';
				}
			}
			
			if ( !empty( $urlParts[ 'host' ] ) ) {
				if ( isset( $urlParts[ 'user' ] ) ) {

					if ( isset( $urlParts[ 'pass' ] ) ) {
						Log::write( new Exception( 'Usage of password in url is discouraged as by rfc3986 section 3.2.1 [ http://tools.ietf.org/html/rfc3986#section-3.2.1 ]' ) );

						$url .= ':' . $urlParts[ 'pass' ];
					}

					$url .= '@';
				}
				$url .= '//' . $urlParts[ 'host' ];
				if ( !empty( $urlParts[ 'port' ] ) ) $url .= ':' . $urlParts[ 'port' ];
			}

			$url .= isset( $urlParts[ 'path' ] ) ? $urlParts[ 'path' ] : '/';

			if ( $params ) {
				if ( !empty( $urlParts[ 'query' ] ) ) {
					parse_str( $urlParts[ 'query' ], $requestParams );

					foreach ( $params as $k => $v ) {
						if ( $v === NULL ) { // unset param if it exists
							unset( $requestParams[ $k ] );
						} else {
							$requestParams[ $k ] = $v;
						}
					}
				} else {
					$requestParams = array();

					if ( is_array( $params ) ) {
						foreach ( $params as $k => $v ) {
							if ( $v !== NULL ) {
								$requestParams[ $k ] = $v;
							}
						}
					} elseif ( $params === true ) {
						$request = RequestInfo::getCurrent();
						$requestParams = array_merge( $request->getQueryParams(), $requestParams );
					}
				}

				if ( !empty( $requestParams ) ) $url .= '?' . http_build_query( $requestParams );
			} else {
				if ( !empty( $urlParts[ 'query' ] ) ) $url .= '?' . $urlParts[ 'query' ];
			}


			if ( !empty( $urlParts[ 'fragment' ] ) ) $url .= '#' . $urlParts[ 'fragment' ];

		} else {
			// $page is not string or $page === 'http' or $page === 'https' or $page === ''
			
			if ( $page === NULL ) {
				$page = $this;
			} else if ( is_array( $page ) ) {
				$cancelParams = $forceAbsolute;
				$forceAbsolute = $params;
				$params = $page;
				$page = $this;
			} else if ( is_bool( $page ) || is_string( $page ) ) { // $page might be 'http' or 'https' or ''
				$forceAbsolute = $page;
				$page = $this;
			}

			// TODO: maybe we don't need this if $page === $this ?
			$page = self::getTargetPage( $page );

			$urlJoinsLoaded = $page->fieldHasBeenSet( 'page:RCPageUrl' );

			// first check if fields aren't already set and only select when needed
			if ( !$urlJoinsLoaded ) {
				$page->load( array( 'page:RCPageUrl', 'page:RCPageUrl.url', 'page:RCPageUrl.url.*' ) );
			}

			$urlRecords = $page->collect( 'page:RCPageUrl.url' );

			foreach ( $urlRecords as $rec ) {
				if ( $rec->returnCode == 200 ) {
					$urlRecord = $rec;
					break;
				}
			}

			if ( !isset( $urlRecord ) ) {
				if ( $urlRecord = reset( $urlRecords ) ) {
					Log::write( 'No primary url for page ' . $page->{Record::FIELDNAME_PRIMARY} . ', took non-primary url ' . $urlRecord->url );
				} else {
					throw new Exception( 'Unable to get any url for page ' . Debug::getStringRepresentation( $page->getValues() ) );
				}
			}

			$url = '';


			if ( ( $urlRecord->domainGroup !== $this->domainGroup ) || $forceAbsolute || $forceAbsolute === self::ABSOLUTE_NO_PROTOCOL ) {
				
				$domains = $urlRecord->domainGroup->{'domainGroup:RCDomain'};

				foreach ( $domains as $domain ) {
					if ( $domain->returnCode == 200 ) {
						$domainRecord = $domain;
						break;
					}
				}

				if ( !isset( $domainRecord ) ) {
					throw new Exception( 'Unable to get primary domain for page ' . Debug::getStringRepresentation( $page->getValues() ) );
				}

				if ( $forceAbsolute ) {
					if ( is_string( $forceAbsolute ) ) {
						// case empty string: don't prepend protocol + ':'
						// case https: make sure target domain may use https
						if ( $forceAbsolute !== '' && ( $forceAbsolute !== 'https' || ( !$domainRecord->noSSL && ( !( $webSection = Config::section( 'web' ) ) || empty( $webSection[ 'disableHTTPS' ] ) ) ) ) ) {
							$url .= $forceAbsolute . ':';
						}
					} else if ( $forceAbsolute === true ) {
						$url .= RequestInfo::getCurrent()->getServerInfo(RequestInfo::PROXY_SAFE_IS_HTTPS, false) ? 'https:' : 'http:';
					}
				}

				if ( $forceAbsolute === 1 ) { // case 1: don't prepend anything past primary domain
					$url .= $domain->domain;
				} else {
					$url .= '//' . $domain->domain;
				}

			}

			$url .= $urlRecord->url;

			if ( ( ( $page === $this && $params !== false ) || $params === true ) && !$cancelParams ) { // mix url params if we stay on the same page or $params === true
				$request = RequestInfo::getCurrent();
				$requestParams = $request->getQueryParams();

				if ( is_array( $params ) ) {
					foreach ( $params as $k => $v ) {
						if ( $v === NULL ) { // unset param if it exists
							unset( $requestParams[ $k ] );
						} else {
							$requestParams[ $k ] = $v;
						}
					}
				}
			} else if ( $params ) { // add new url params for other pages
				$requestParams = array();

				if ( is_array( $params ) ) {
					foreach ( $params as $k => $v ) {
						if ( $v !== NULL ) {
							$requestParams[ $k ] = $v;
						}
					}
				}
			}

			if ( !empty( $requestParams ) ) {
				$url .= '?' . http_build_query( $requestParams );
			}
		}

		return $url;
	}

	/**
	 * @return RCElementRecord[]
	 *
	 * pass a classname for $filter to only get elements of that class
	 */
	public function getElements( $omitHidden = false, $filter = NULL ) {
		$areas = $this->collect( 'page:RCPageArea.area' );
		$area = reset( $areas );

		if ( $filter === NULL ) {
			$ret = & $areas;
		} else {
			$ret = array();
		}


		while ( $area ) {
			if ( $filter !== NULL && get_class( $area ) === $filter ) {
				$ret[ ] = $area;
			}

			$children = $area->getChildren();

			if ( $children !== NULL ) {
				foreach ( $children as $child ) {
					if ( $omitHidden && $child->hidden ) continue;
					$areas[ ] = $child->element;
				}
			}

			$area = next( $areas );
		}

		return $ret;
	}

	public function getRootPage() {
		$p = $this;

		while ( $p->parent ) {
			$p = $p->parent;
		}

		return $p;
	}

	public function isChildOf( RCPage $page = NULL ) {
		if ( $page === NULL || $page === $this ) return false;

		$parent = $this;

		while ( $parent = $parent->parent ) {
			if ( $parent === $page ) return true;
		}

		return false;
	}

	public function getDistance( RCPage $page ) {
		if ( $page === NULL ) return false;

		$distance = 0;
		$parent = $this;

		do {
			if ( $parent === $page ) return $distance;
			$distance++;
		} while ( $parent = $parent->parent );

		return false;
	}

	public function equalsOrChildOf( RCPage $page = NULL ) {
		return $page === $this || $this->isChildOf( $page );
	}

	public static function getDefaultValues( IStorage $storage, array $fieldsToSelect, array $extraParams = NULL ) {
		$defaults = parent::getDefaultValues( $storage, $fieldsToSelect, $extraParams );

		$template = RCTemplate::get( $storage, $defaults[ 'template' ], Record::TRY_TO_LOAD );

		$areas = array();

		$removeIdentity = function ( &$el ) use ( &$removeIdentity ) {
			$el[ 'primary' ] = null;
			$el[ 'id' ] = null;

			unset( $el[ '_liveStatus' ] );

			if ( isset( $el[ 'area:RCElementInArea' ] ) ) {
				foreach ( $el[ 'area:RCElementInArea' ] as &$elementInArea ) {
					$elementInArea[ 'primary' ] = null;

					$removeIdentity( $elementInArea[ 'element' ] );
				}
			}
		};

		foreach ( $template->{'template:RCTemplateArea'} as $key => $templateArea ) {
			//TODO: get RCElementInArea records recursively
			$area = array();

			$templateArea->load();

//			$area[ 'title' ] = $templateArea->title;
			$area[ 'sorting' ] = $templateArea->sorting;
			$area[ 'columns' ] = $templateArea->columns;
			$area[ 'fixed' ] = $templateArea->fixed;
			$area[ 'key' ] = $templateArea->key;

			$areaFields = array_keys( RCArea::getFormFields( $storage ) );

			$modArea = $templateArea->area->getFormValues( $areaFields );

			$removeIdentity( $modArea );

			$area[ 'area' ] = $modArea;

			$areas[ $key ] = $area;
		}

		$defaults[ 'page:RCPageArea' ] = $areas;

		return $defaults;
	}

	public static function getDefaultSorting() {
		return array(
			'title' => DB::ORDER_BY_ASC,
			Record::FIELDNAME_PRIMARY => DB::ORDER_BY_ASC
		);
	}

	protected static function getDisplayedListFields() {
		return array( 'title', 'forwardTo', 'domainGroup', 'page:RCPageUrl.url.url' );
	}

	public static function getDisplayedFilterFields() {
		return array(
			'domainGroup',
			'template',
			'parent',
			'creator'
		);
	}

	public function modifyMayWrite( $mayWrite = false, User $user ) {
		return $mayWrite && $this->userHasPagePermission( $user );
	}

	public static function getAvailableActions( $mayWrite = false, $mayPublish = false, $mayHide = false, $mayDelete = false, $mayCreate = false ) {
		$actions = parent::getAvailableActions( $mayWrite, $mayPublish, $mayHide, $mayDelete, $mayCreate );

		if ( $mayWrite ) {
			$actions[ ] = self::ACTION_COPY;
		}

		return $actions;
	}

	public function beforeSave( $isUpdate, $isFirst ) {
		parent::beforeSave( $isUpdate, $isFirst );

		// prevent creation of second start page
		if ( $this->parent === NULL ) {
			$queryStruct = array( 'fields' => array( 'primary', 'title' ), 'where' => array( 'parent', '=', NULL, 'AND', 'domainGroup', '=', array( $this->domainGroup ) ) );

			if ( isset( $this->id ) || $this->exists() ) {
				array_push( $queryStruct[ 'where' ], 'AND', 'id', '!=', array( $this->id ) );
			}

			$rootPage = $this->storage->selectFirst( 'RCPage', $queryStruct );

			if ( $rootPage ) {
				throw new RootPageExistsException( 'Please choose a parent page for "' . $this->title . '"', array(
					'record' => $this->title
				) );
			}
		}

		if ( $this->parent && $this->parent->live != $this->live ) {
			throw new Exception( 'Parent and child page live values do not match!' );
		}
	}

	public static function getFieldSets( RBStorage $storage ) {
		return array(
			'fs_main' => array(
				'title',
				'template',
				'parent',
				'forwardTo',
				'image',
				'description'
			),
			'fs_url' => array(
				'customUrl',
				'page:RCPageUrl'
			),
			'fs_search' => array(
				'robots'
			),
//			'fs_menu' => array(
//				'page:RCMenuItem'
//			),
			'fs_pageContent' => array(
				'page:RCPageArea'
			)
		);
	}

	public function userHasPagePermission( User $user ) {
		if ( !$this->exists() ) {
			return true;
		}

		$allowedParentPages = $user->getAllowedParentPages( $this->domainGroup );

		foreach ( $allowedParentPages as $parentPage ) {
			if ( $this === $parentPage || $this->isChildOf( $parentPage ) ) {
				return true;
			}
		}

		if ( !empty( $allowedParentPages ) ) { // we have a restriction and the current page isn't allowed
			return false;
		}

		return true; // no explicitly allowed parent pages means user has access to all
	}

	// FIXME: we should not skip field notification but instead handle it differently if needed in fields afterSave function
	protected function afterSave( $isUpdate, $isFirst, array $saveResult ) {
		unset( $this->afterSaveFields[ 'parent' ] );

		foreach ( $this->afterSaveFields as $fieldName => $field ) {
			if ( is_subclass_of( $field, 'BaseDTForeignReference' ) ) {
				$fieldDef = $this->getFieldDefinition( $fieldName );
				$foreignRC = $fieldDef[ 'recordClass' ];
				$pageField = $foreignRC::getDataTypeFieldName( 'DTSteroidPage' );

				if ( $pageField && ( $fieldName === $pageField . ':' . $foreignRC ) && $this->pageType !== $foreignRC ) {
					unset( $this->afterSaveFields[ $fieldName ] );
				}
			}
		}

		parent::afterSave( $isUpdate, $isFirst, $saveResult );
	}

	// FIXME: we should not skip field notification but instead handle it differently if needed in fields beforeDelete function
	protected function getBeforeDeleteFields() {
		$fields = array();

		foreach ( $this->fields as $fieldName => $field ) {
			if ( is_subclass_of( $field, 'BaseDTForeignReference' ) ) {
				$fieldDef = $this->getFieldDefinition( $fieldName );
				$foreignRC = $fieldDef[ 'recordClass' ];
				$pageField = $foreignRC::getDataTypeFieldName( 'DTSteroidPage' );

				if ( $pageField && $fieldName === $pageField . ':' . $foreignRC && $this->pageType !== $foreignRC ) {
					continue;
				}
			}

			$fields[ ] = $fieldName;
		}

		return $fields;
	}


	public function hideFromAffectedRecordData() {
		return $this->pageType !== 'RCPage';
	}

	public function duplicate($newParent){

		// duplicate basic values
		$newPage = RCPage::get( $this->storage, array(
			'title'       => $this->title . ' (copy)',
			'language'    => $newParent->language,
			'domainGroup' => $newParent->domainGroup,
			'live'        => DTSteroidLive::LIVE_STATUS_PREVIEW,
			'parent'      => $newParent,
			'creator'     => $this->creator,
			'pageType'    => $this->pageType,
			'template'    => $this->template,
			'description' => $this->description,
			'robots'      => $this->robots,
			'image'       => $this->image
		), false );

		// duplicate page areas and widgets
		$newPageAreas = array();
		$pageAreas = $this->{'page:RCPageArea'};

		foreach($pageAreas as $pageArea){
			$newPageAreas[] = RCPageArea::get($this->storage, array(
				'page' => $newPage,
				Record::FIELDNAME_SORTING => $pageArea->{Record::FIELDNAME_SORTING},
				'columns' => $pageArea->columns,
				'fixed' => $pageArea->fixed,
				'key' => $pageArea->key,
				'area' => $pageArea->area->duplicate()
			), false);
		}

		$newPage->{'page:RCPageArea'} = $newPageAreas;

		return $newPage;
	}
}

class NoParentPageException extends SteroidException {

}

class NoRootPageException extends NoParentPageException {

}

class RootPageExistsException extends SteroidException {

}
