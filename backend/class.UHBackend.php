<?php

require_once STROOT . '/urlhandler/interface.IURLHandler.php';
require_once STROOT . '/user/class.User.php';
require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/util/class.NLS.php';
require_once STROOT . '/storage/class.DBColumnDefinition.php';
require_once STROOT . '/user/permission/class.PermissionStorageFilter.php';
require_once STROOT . '/storage/record/interface.IRecord.php';
require_once STROOT . '/wizard/class.Wizard.php';
require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/datatype/class.BaseDTRecordReference.php';

require_once STROOT . '/file/class.RCFile.php'; // for downloads
require_once STROOT . '/log/class.Log.php';

require_once STROOT . '/log/class.RCContentEdit.php';

require_once STROOT . '/gfx/class.GFX.php';
require_once STROOT . '/cache/class.FileCache.php';

require_once STROOT . '/sync/class.SyncRecord.php';
require_once STROOT . '/pubdate/class.RCPubDateEntries.php';

require_once STROOT . '/lib/json/JSON.php';

class UHBackend implements IURLHandler {
	protected $requestInfo;
	protected $urlRecord;

	/** @var IRBStorage */
	protected $storage;

	/** @var User */
	protected $user;

	protected $userConfig;
	protected $config;
	protected $isIframe = false;

	protected $recordClasses;

	const PARAM_AJAX = 'ajax';

	const PARAM_REQUEST_TYPE = 'requestType';
	const PARAM_REQUEST_VALUE = 'requestValue';
	const PARAM_RECORD_ID = 'recordID';
	const PARAM_MAIN_RECORD_CLASS = 'mainRecordClass';
	const PARAM_MAIN_RECORD = 'mainRecord';
	const PARAM_REQUESTING_FIELDNAME = 'requestFieldName';
	const PARAM_REQUESTING_RECORDCLASS = 'requestingRecordClass';

	const PARAM_PREVIOUS_RECORDCLASS = 'previousEditedRecordClass';
	const PARAM_PREVIOUS_RECORDID = 'previousEditedRecordID';

	const PARAM_RECORDCLASS = 'recordClass';
	const PARAM_FOR_EDITING = 'forEditing';
	const PARAM_LIMIT_START = 'limitStart';
	const PARAM_LIMIT_COUNT = 'limitCount';
	const PARAM_FIND = 'find';
	const PARAM_PARENT = 'parent';
	const PARAM_SORT = 'sort';
	const PARAM_FILTER = 'filter';
	const PARAM_IS_SEARCH_FIELD = 'isSearchField';
	const PARAM_EXCLUDE = 'exclude';
	const PARAM_DISPLAY_HIERARCHIC = 'displayHierarchic';
	const PARAM_INTERFACE_LANG = 'beLang';
	const PARAM_INTERFACE_THEME = 'beTheme';
	const PARAM_REQUEST_TIME = 'time';
	const PARAM_CURRENTLY_EDITING = 'editing';
	const PARAM_CURRENTLY_EDITING_PARENT = 'editingParent';
	const PARAM_CURRENTLY_EDITING_CLASS = 'editingClass';
	const PARAM_FILEPRIMARY = 'file';
	const PARAM_EXTENSION_CLASS = 'extensionClass';
	const PARAM_EXTENSION_METHOD = 'extensionMethod';
	const PARAM_STAT_TYPE = 'statType';
	const PARAM_STAT_CLASS = 'statClass';
	const PARAM_ADDITIONAL_PUBLISH = 'additionalPublish';

	const PARAM_MESSAGE = 'msg';

	const REQUEST_TYPE_LANGUAGE = 'selectLanguage';
	const REQUEST_TYPE_DOMAIN_GROUP = 'selectDomainGroup';
	const REQUEST_TYPE_LIST = 'getList';
	const REQUEST_TYPE_RECORD = 'getRecord';
	const REQUEST_TYPE_SAVE_RECORD = 'saveRecord';
	const REQUEST_TYPE_PUBLISH_RECORD = 'publishRecord';
	const REQUEST_TYPE_HIDE_RECORD = 'hideRecord';
	const REQUEST_TYPE_DELETE_RECORD = 'deleteRecord';
	const REQUEST_TYPE_SYNC_RECORD = 'syncRecord';
	const REQUEST_TYPE_PREVIEW_RECORD = 'previewRecord';
	const REQUEST_TYPE_REVERT_RECORD = 'revertRecord';
	const REQUEST_TYPE_DELAY_PUBLISH = 'delayPublish';
	const REQUEST_TYPE_DELAY_UNPUBLISH = 'delayUnpublish';
	const REQUEST_TYPE_DELETE_PUBLISH = 'existingPublish';
	const REQUEST_TYPE_DELETE_UNPUBLISH = 'existingUnpublish';
	const REQUEST_TYPE_BELANG = 'changeBELang';
	const REQUEST_TYPE_BETHEME = 'changeBETheme';
	const REQUEST_TYPE_MESSAGES = 'getMessages';
	const REQUEST_TYPE_LOG = 'log';
	const REQUEST_TYPE_DOWNLOAD = 'download';
	const REQUEST_TYPE_END_EDITING = 'endEditing';
	const REQUEST_TYPE_COPY_CLIPBOARD = 'copyToClipboard';
	const REQUEST_TYPE_REMOVE_CLIPBOARD = 'removeFromClipboard';
	const REQUEST_TYPE_INSERT_CLIPBOARD = 'insertFromClipboard';
	const REQUEST_TYPE_COPY_PAGE = 'copyPage';
	const REQUEST_TYPE_EXTENSION = 'extension';
	const REQUEST_TYPE_STATS = 'stats';
	const REQUEST_TYPE_LOGIN_EXTENSION = 'loginExtension';
	const REQUEST_TYPE_GETPUBDATE = 'getPubDate';
	const REQUEST_TYPE_GET_PROFILE_PAGE = 'getProfilePage';
	const REQUEST_TYPE_GET_PUBLISHABLE_REFERENCES = 'getPublishableReferences';

	const REQUEST_TYPE_LOGOUT = 'logout';
	const REQUEST_TYPE_LOGIN = 'login';

	const LOGNAME = 'UHBackend'; // name used for uds logging

	protected $isAjaxRequest;
	protected $isBackendUser;

	public function __construct() {
		$this->userConfig = array();
		$this->config = array();
	}

	public function handleURL( IRequestInfo $requestInfo, RCUrl $url, IRBStorage $storage ) {
		$this->requestInfo = $requestInfo;
		$this->urlRecord = $url;
		$this->storage = $storage;

		// FIXME: only use transaction when needed
		$tx = $storage->startTransaction();

		$this->isAjaxRequest = (bool)$requestInfo->getQueryParam( static::PARAM_AJAX );

		$this->user = User::getCurrent();

		$this->isBackendUser = $this->user->authenticated && isset( $this->user->record ) && $this->user->record->is_backendAllowed;

		if ( $this->isAjaxRequest ) {
			try {
				// FIXME: don't use getGPParam - it should be well defined if it's a get or post param (and if it may be both, comment on it!)
				$requestType = $this->requestInfo->getGPParam( self::PARAM_REQUEST_TYPE );

				if ( $requestType === self::REQUEST_TYPE_LOGOUT ) {
					$this->logoutUser();
				} else if ( $requestType === self::REQUEST_TYPE_LOGIN_EXTENSION ) {
					 // TODO: no tx needed here
					$this->loginExtensionRequest( $this->requestInfo->getGPParam( self::PARAM_EXTENSION_CLASS ), $this->requestInfo->getGPParam( self::PARAM_EXTENSION_METHOD ) );
				} else if ( $this->isBackendUser ) { 
					switch ( $requestType ) {
						case self::REQUEST_TYPE_DOMAIN_GROUP:
							$this->switchDomainGroup();
							break;
						case self::REQUEST_TYPE_LANGUAGE:
							$this->switchLanguage();
							break;
						case self::REQUEST_TYPE_BELANG:
							$this->changeBELanguage( $this->requestInfo->getGPParam( self::PARAM_INTERFACE_LANG ) );
							break;
						case self::REQUEST_TYPE_BETHEME:
							$this->changeBETheme( $this->requestInfo->getGPParam( self::PARAM_INTERFACE_THEME ) );
							break;
						case self::REQUEST_TYPE_RECORD:
							$this->recordRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ), (bool)$this->requestInfo->getGPParam( self::PARAM_FOR_EDITING ), $this->requestInfo->getGPParam( self::PARAM_REQUESTING_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_PREVIOUS_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_PREVIOUS_RECORDID ) );
							break;
						case self::REQUEST_TYPE_LIST:
							$this->recordClassRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ) );
							break;
						case self::REQUEST_TYPE_LOGIN:
							$this->loginUser();
							break;
						case self::REQUEST_TYPE_GET_PROFILE_PAGE:
							$this->getProfilePage();
							break;
						case self::REQUEST_TYPE_EXTENSION:
							$this->extensionRequest( $this->requestInfo->getGPParam( self::PARAM_EXTENSION_CLASS ), $this->requestInfo->getGPParam( self::PARAM_EXTENSION_METHOD ) );
							break;
						case self::REQUEST_TYPE_STATS:
							$this->statRequest( $this->requestInfo->getGPParam( self::PARAM_STAT_TYPE ), $this->requestInfo->getGPParam( self::PARAM_STAT_CLASS ) );
							break;
						case self::REQUEST_TYPE_INSERT_CLIPBOARD:
							$this->insertFromClipboard( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_COPY_PAGE:
							$this->copyPage( $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ), $this->requestInfo->getGPParam( self::PARAM_PARENT ) );
							break;
						case self::REQUEST_TYPE_REMOVE_CLIPBOARD:
							$this->removeFromClipboard( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_COPY_CLIPBOARD:
							$this->copyToClipboard( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_END_EDITING:
							$this->recordEndEditing( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_GET_PUBLISHABLE_REFERENCES:
							$this->getPublishableReferences( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_PUBLISH_RECORD:
							$this->recordPublishRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ), $this->requestInfo->getGPParam( 'doAction' ), $this->requestInfo->getGPParam( self::PARAM_ADDITIONAL_PUBLISH ) );
							break;
						case self::REQUEST_TYPE_DELAY_PUBLISH:
						case self::REQUEST_TYPE_DELAY_UNPUBLISH:
							$this->recordDelayPublish( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ), $this->requestInfo->getGPParam( 'pubDate' ), $this->requestInfo->getGPParam( 'pubTime' ), $this->requestInfo->getGPParam( 'constants' ) );
							break;
						case self::REQUEST_TYPE_DELETE_PUBLISH:
						case self::REQUEST_TYPE_DELETE_UNPUBLISH:
							$this->recordDeletePublish( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ), $this->requestInfo->getGPParam( 'doAction' ), $this->requestInfo->getGPParam( 'properties' ) );
							break;
						case self::REQUEST_TYPE_SAVE_RECORD:
							$this->recordSaveRequest( $this->requestInfo->getQueryParam( self::PARAM_RECORDCLASS ) );
							break;
						case self::REQUEST_TYPE_PREVIEW_RECORD:
							$this->recordPreviewRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_HIDE_RECORD:
							$this->recordHideRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ), $this->requestInfo->getGPParam( 'doAction' ) );
							break;
						case self::REQUEST_TYPE_SYNC_RECORD:
							// FIXME: move to SyncRecord
							$this->recordSyncRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_DELETE_RECORD:
							$this->recordDeleteRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ), $this->requestInfo->getGPParam( 'doAction' ) );
							break;
						case self::REQUEST_TYPE_REVERT_RECORD:
							$this->recordRevertRequest( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;
						case self::REQUEST_TYPE_MESSAGES:
							$this->getMessages( $this->requestInfo->getGPParam( self::PARAM_REQUEST_TIME ), $this->requestInfo->getGPParam( self::PARAM_CURRENTLY_EDITING ), $this->requestInfo->getGPParam( self::PARAM_CURRENTLY_EDITING_CLASS ), $this->requestInfo->getGPParam( self::PARAM_CURRENTLY_EDITING_PARENT ) );
							break;
						case self::REQUEST_TYPE_LOG: 
							// TODO: no tx needed here
							Log::write( "Log Request:", json_decode( $this->requestInfo->getGPParam(  self::PARAM_MESSAGE ), true ) );
							$this->ajaxSuccess();
							break;
						case self::REQUEST_TYPE_DOWNLOAD: 
							// TODO: no tx needed here
							$this->handleDownload( $this->requestInfo->getQueryParam( self::PARAM_FILEPRIMARY ) );
							break;
						case self::REQUEST_TYPE_GETPUBDATE:
							$this->getPubDate( $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS ), $this->requestInfo->getGPParam( self::PARAM_RECORD_ID ) );
							break;

						default: // unknown ajax request by backendUser
							if ($recordClass = $this->requestInfo->getGPParam( self::PARAM_RECORDCLASS )) {
								// dynamically require RC in case it's not loaded yet
								if ( ClassFinder::find( array( $recordClass ), true ) ) {
									// TODO: check if RC implements interface for handleBackendAction
									// TODO: provide recordClass with a way to interact with backend functions
									//TODO: remove duplicate code from recordSaveRequest
									// try to forward request
									$originalRecord = $recordClass::handleBackendAction( $this->storage, $requestType, $this->requestInfo );

									$values = $originalRecord->getFormValues( array_keys( $originalRecord->getFormFields( $this->storage ) ) );

									$values = $this->setRecordStati( $recordClass, array( $values ) );

									$actions = $this->getRecordActionsForDomainGroup( $recordClass, $values[ 0 ] );

									$recordClass::modifyActionsForRecordInstance( $values[ 0 ], $actions );

									$this->ajaxSuccess(array(
										'items' => $values,
										'actions' => $actions
									));
								} else {
									throw new UnknownRequestException();
								}
							
							} else {
								throw new UnknownRequestException();
							}

					}
				} else { // ajax request other than logout by not-backenduser
					if ( $requestType === self::REQUEST_TYPE_LOGIN ) { // failed login
						if ( $authException = User::getCurrent()->authException ) {
							Log::write( "Auth Exception:", $authException );
						}

						throw new LoginFailException( 'Login failed' ); // FIXME: log + lock user after x retries
					} else { // unknown request by not backend-logged in user
						throw new UnknownRequestException();
					}
				}
			} catch ( Exception $e ) {
				try {
					$tx->rollback();
				} catch ( Exception $rollbackException ) {
					Log::write( $rollbackException, $e );
				}

				if ( !( $e instanceof UnknownRequestException ) ) {
					Log::write( $e );
				}

				$this->ajaxFail( $e );

				return self::RETURN_CODE_HANDLED;
			}
		} else {
			if ( $this->isBackendUser ) { // user logged in
				try {
					$this->setUserLoggedInData();
				} catch ( Exception $e ) {
					Log::write( $e );

					// logout user, so we don't get into an error loop
					$this->clearUserContentEdit();
					$this->user->logoutAndKillSession();

					$this->isBackendUser = false;
					$this->setNoUserData();

				}
			} else {
				$this->setNoUserData();
			}

			$this->displayBackend();
		}

		// FIXME: only use transaction when needed
		$tx->commit();


		return self::RETURN_CODE_HANDLED;
	}

	protected function statRequest( $statType = NULL, $statClass = NULL ) {
		if ( empty( $statType ) ) {
			throw new InvalidArgumentException( '$statType must be set' );
		}

		if ( $statType === 'general' ) {
			$this->sendGeneralStats();
		} else {
			if ( empty( $statClass ) ) {
				throw new InvalidArgumentException( '$statClass must be set' );
			}

			$ret = $statClass::getClassStatistics( $this->storage );

			$this->ajaxSuccess( $ret );
		}
	}

	protected function sendGeneralStats() {
		$data = array();

		$recordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, false );

		foreach ( $recordClasses as $className => $classInfo ) {
			if ( in_array( $className::BACKEND_TYPE, array( Record::BACKEND_TYPE_CONTENT, Record::BACKEND_TYPE_EXT_CONTENT ) ) ) {
				$query = 'SELECT COUNT(*) as count FROM ' . $this->storage->escapeObjectName( $className::getTableName() );

				if ( $liveFieldName = $className::getDataTypeFieldName( 'DTSteroidLive' ) ) {
					$query .= ' WHERE ' . $this->storage->escapeObjectName( $liveFieldName ) . ' = ' . DTSteroidLive::LIVE_STATUS_PREVIEW;
				}

				$res = $this->storage->fetchAll( $query );

				$data[ ] = array(
					'text' => $className,
					'y' => $res[ 0 ][ 'count' ]
				);
			}
		}

		usort( $data, function ( $a, $b ) {
			return $a[ 'y' ] > $b[ 'y' ] ? -1 : ( $a[ 'y' ] == $b[ 'y' ] ? 0 : 1 );
		} );

		$ret = array();
		$others = 0;
		$showCount = 10;

		foreach ( $data as $dat ) {
			if ( $showCount >= 0 ) {
				$ret[ 'recordNumbers' ][ ] = $dat;
				$showCount--;
			} else {
				$others += $dat[ 'y' ];
			}
		}

		$ret[ 'recordNumbers' ][ ] = array(
			'text' => 'others',
			'y' => $others
		);

		$this->ajaxSuccess( $ret );
	}

	protected function extensionRequest( $extensionClass = NULL, $extensionMethod = NULL ) {
		if ( !$extensionClass || !$extensionMethod ) {
			throw new InvalidArgumentException( '$extensionClass and $extensionMethod must be set' );
		}

		ClassFinder::find( array( $extensionClass ), true );

		// FIXME: extensionClass should implement an interface which should be checked on, otherwise this could lead to security problems!
		// FIXME: check if ClassFinder was successful!

		if ( $extensionClass::HAS_RESPONSE ) {
			$result = $extensionClass::handleRequest( $this->storage, $this->requestInfo, $extensionMethod );

			$this->ajaxSuccess( $result );
		} else {
			$extensionClass::handleRequest( $this->storage, $this->requestInfo, $extensionMethod );
		}

	}

	protected function loginExtensionRequest( $extensionClass = NULL, $extensionMethod = NULL ) {
		$this->extensionRequest( "LE" . $extensionClass, $extensionMethod );
	}

	protected function loginUser() {
		if ( !$this->user->record->backendPreference ) {
			$this->user->record->backendPreference = RCBackendPreferenceUser::get( $this->storage, NULL, false );
			$this->user->record->backendPreference->language = $this->getCurrentLanguage();
			$this->user->record->save();
		}

		$this->user->loadUserPreferences();

		$this->setUserLoggedInData();

		$this->ajaxSuccess( $this->config );
	}

	protected function setUserLoggedInData() {
		$this->setUserData();

		$this->setUserBEConf(); // might throw exception if user doesn't have any permissions!

		$this->setModuleData();
	}

	/**
	 * Adds data to config for login screen
	 *
	 * Data set here is only relevant when no user is logged in yet = the login screen is displayed
	 *
	 * @return void
	 */
	protected function setNoUserData() {
		$this->config[ 'loginext' ] = array();

		$classes = ClassFinder::getAll( ClassFinder::CLASSTYPE_LOGIN_EXTENSION, true );

		foreach ( $classes as $className => $class ) {
			$className::getLoginExt( $this->config[ 'loginext' ] );
		}

		$this->setCustomCSS();
	}

	protected function setCustomCSS() {
		if ( !isset( $this->config[ 'customCSSPaths' ] ) ) {
			$this->config[ 'customCSSPaths' ] = array();

			$recordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );

			foreach ( $recordClasses as $className => $classInfo ) {
				if ( $customCSSPath = $className::getCustomBackendCSSPath() ) {
					$this->config[ 'customCSSPaths' ][ ] = $customCSSPath;
				}
			}
		}
	}

	protected function clearUserContentEdit() {
		$contentEdit = $this->storage->selectRecords( 'RCContentEdit', array( 'where' => array( 'creator', '=', array( $this->user->record ) ) ) );

		foreach ( $contentEdit as $rc ) {
			try {
				$rc->delete();
			} catch ( NoActionPerformedException $e ) {
				//TODO: why can this happen?
			}
		}
	}

	protected function logoutUser() {
		$this->clearUserContentEdit();

		if ( !isset( $this->user->logoutException ) ) {
			$this->ajaxSuccess();
		} else {
			Log::write( $this->user->logoutException->getMessage());
		}
	}

	protected function switchDomainGroup() {
		$selectedDomainGroup = $this->requestInfo->getGPParam( self::PARAM_RECORD_ID );

		$this->user->setSelectedDomainGroup( RCDomainGroup::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $selectedDomainGroup ) ) );

		if ( $this->user->getSelectedDomainGroup()->{Record::FIELDNAME_PRIMARY} == $selectedDomainGroup ) {

			$this->setUserBEConf();

			$this->setModuleData();

			$this->ajaxSuccess( $this->config );
		} else {
			throw new NoChangeException();
		}
	}

	protected function switchLanguage() {
		$selectedLanguage = $this->requestInfo->getGPParam( self::PARAM_RECORD_ID );

		$this->user->setSelectedLanguage( RCLanguage::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $selectedLanguage ) ) );

		if ( $this->user->getSelectedLanguage()->{Record::FIELDNAME_PRIMARY} == $selectedLanguage ) {

			$this->setUserBEConf();

			$this->setModuleData();

			$this->ajaxSuccess( $this->config );
		} else {
			throw new NoChangeException();
		}
	}

	protected function changeBELanguage( $beLang ) {
		$this->user->record->backendPreference->language = $beLang;
		$this->user->record->backendPreference->save();

		$this->ajaxSuccess();
	}

	protected function changeBETheme( $beTheme ) {
		$this->user->record->backendPreference->theme = $beTheme;
		$this->user->record->backendPreference->save();

		$this->ajaxSuccess();
	}

	protected function getConfigLanguages() {
		static $configLanguages;

		if ( $configLanguages === NULL ) {
			$configLanguages = explode( '/', Config::get( 'localconf' )->getKey( 'backend', 'languages' ) );
		}

		return $configLanguages;
	}

	private final static function getThemeFromDirectory( $directory, $isDijit ) {
		$themeName = basename( $directory );

		$ret = array(
			'name' => $themeName,
			'label' => ucfirst( $themeName ),
			'stylesheet' => Filename::getPathWithoutWebroot( $directory ) . '/' . $themeName . '.css',
			'id' => ( $isDijit ? 'dijit-' : 'steroid-' ) . $themeName
		);

		// check for additional stylesheet override for dijit themes
		if ( $isDijit ) {
			$stylesheetOverride = STROOT . '/res/static/css/themes/' . $ret[ 'id' ] . '-override.css';

			if ( is_readable( $stylesheetOverride ) ) {
				$ret[ 'stylesheet-override' ] = Filename::getPathWithoutWebroot( $stylesheetOverride );
			}
		}

		return $ret;
	}

	protected function getAvailableThemes() {
		static $themes;

		if ( $themes === NULL ) {
			$themes = array();

			// dijit themes first - try to match dirs which have a form subdir
			$directories = glob( STROOT . '/res/static/js/dev/dijit/themes/*/form', GLOB_ONLYDIR ); // | GLOB_NOSORT

			foreach ( $directories as $directory ) {
				$directory = substr( $directory, 0, -strlen( '/form' ) );
				$themes[ ] = self::getThemeFromDirectory( $directory, true );
			}


			// custom steroid themes
			$directories = glob( STROOT . '/res/static/css/themes/*', GLOB_ONLYDIR ); // | GLOB_NOSORT

			foreach ( $directories as $directory ) {
				$themes[ ] = self::getThemeFromDirectory( $directory, false );
			}
		}

		return $themes;
	}

	protected function getConfigDefaultLanguage() {
		static $defaultLanguage;

		if ( $defaultLanguage === NULL ) {
			$languages = $this->getConfigLanguages();

			$defaultLanguage = $languages[ 0 ];
		}

		return $defaultLanguage;
	}

	protected function getDefaultTheme() {
		static $defaultTheme;

		if ( $defaultTheme === NULL ) {
			$defaultTheme = Config::get( 'localconf' )->getKey( 'backend', 'default_theme' );

			if ( $defaultTheme !== NULL ) {
				$themes = $this->getAvailableThemes();

				foreach ( $themes as $theme ) {
					if ( $theme[ 'id' ] === $defaultTheme ) {
						$defaultTheme = $theme;
						break;
					}
				}
			}

			if ( $defaultTheme === NULL ) {
				$availableThemes = $this->getAvailableThemes();

				if ( $availableThemes ) {
					$defaultTheme = reset( $availableThemes );
				} else {
					throw new Exception( "No theme found" );
				}
			}
		}

		return $defaultTheme;
	}

	protected function getCurrentLanguage() {
		static $currentLanguage;

		if ( $currentLanguage === NULL ) {
			if ( $this->requestInfo->getGPParam( self::PARAM_INTERFACE_LANG ) ) {
				$currentLanguage = $this->requestInfo->getGPParam( self::PARAM_INTERFACE_LANG );
			} else if ( $this->user && $this->user->record && $this->user->record->backendPreference && $this->user->record->backendPreference->exists() && $this->user->record->backendPreference->language ) {
				$currentLanguage = $this->user->record->backendPreference->language;
			} else {
				$currentLanguage = $this->getConfigDefaultLanguage();
			}
		}

		return $currentLanguage;
	}

	protected function getCurrentTheme() {
		static $currentTheme;

		if ( $currentTheme === NULL ) {
			if ( $this->user && $this->user->record && $this->user->record->backendPreference && $this->user->record->backendPreference->exists() && $this->user->record->backendPreference->theme ) {
				$userTheme = $this->user->record->backendPreference->theme;

				$themes = $this->getAvailableThemes();

				foreach ( $themes as $theme ) {
					if ( $theme[ 'id' ] === $userTheme ) {
						$currentTheme = $theme;
						break;
					}
				}

				if ( $currentTheme === NULL ) {
					$currentTheme = $this->getDefaultTheme();
				}

			} else {
				$currentTheme = $this->getDefaultTheme();
			}
		}

		return $currentTheme;
	}

	protected function setCurrentLanguage() {
		$languages = $this->getConfigLanguages();

		$this->config[ 'interface' ][ 'languages' ][ 'default' ] = $this->getConfigDefaultLanguage();;
		$this->config[ 'interface' ][ 'languages' ][ 'current' ] = $this->getCurrentLanguage();

		// array_values( array_diff( $languages, array( $this->config[ 'interface' ][ 'languages' ][ 'current' ] ) ) );
		$this->config[ 'interface' ][ 'languages' ][ 'available' ] = $languages;
	}

	protected function setCurrentTheme() {
		$themes = $this->getAvailableThemes();

		$this->config[ 'interface' ][ 'themes' ][ 'default' ] = $this->getDefaultTheme();
		$this->config[ 'interface' ][ 'themes' ][ 'current' ] = $this->getCurrentTheme();
		$this->config[ 'interface' ][ 'themes' ][ 'available' ] = $themes;
	}

	protected function setAuthenticator(){
		$conf = Config::getDefault();

		$authenticators = $conf->getSection( 'authenticator' );
		$auth = NULL;

		if ($authenticators !== NULL) {
			foreach($authenticators as $className => $path){
				require_once WEBROOT . '/' . $path;

				if ($className::AUTH_TYPE === User::AUTH_TYPE_BE) {
					$auth = $className;
					break;
				}
			}
		}

		if($auth === NULL){
			throw new Exception('No authenticator found');
		}

		$this->config[ 'login' ][ 'class' ] = $auth;
	}

	protected function setLocalBEConf() {
		$this->config[ 'interface' ][ 'basePath' ] = $this->urlRecord->url;
		$this->config[ 'interface' ][ 'ajaxQuery' ] = array( self::PARAM_AJAX => 1 );

		$this->setAuthenticator();
		$this->setCurrentLanguage();
		$this->setCurrentTheme();

		if ( $this->user->record ) {
			$this->config[ 'changeLog' ] = $this->getChangeLog();
			$this->config[ 'messageBox' ] = $this->getMessageBox();
		}

		$clipboard = array();

		$clipboardRecords = $this->storage->selectRecords( 'RCClipboard', array( 'fields' => '*', 'where' => array(
			'creator', '=', array( $this->user->record )
		) ) );

		foreach ( $clipboardRecords as $rec ) {
			$contentRec = $rec->recordPrimary;
			$recClass = get_class( $contentRec );

			switch ( $contentRec::BACKEND_TYPE ) {
				case Record::BACKEND_TYPE_WIDGET:
					$recClass = 'widget';
				default:
					if ( !isset( $clipboard[ $recClass ] ) ) {
						$clipboard[ $recClass ] = array();
					}

					$clipboard[ $recClass ][ ] = array(
						'recordClass' => $rec->recordClass,
						'values' => $contentRec->getFormValues( array_keys( $contentRec->getFormFields( $this->storage ) ) )
					);
					break;
			}
		}

		$this->config[ 'clipboard' ] = $clipboard;
	}

	protected function getMessageBox( $requestTime = null ) {
		// TODO: use query caching (need to allow caching in backend filter for doing that)
		// TODO: use request time instead of time
		// TODO: use datetime format const
		// TODO: parse time only once for messageBox + changeLog
		$queryStruct = array(
			'fields' => '*',
			'where' => array(
				'ctime',
				'>',
				array( $requestTime ? : DTDateTime::valueFromTimestamp( time() - 60 * 60 * 24 * 7 ) ), // FIXME: don't use time()
				'AND',
				'(',
				'(',
				'(',
				'user',
				'=',
				array( 0 ),
				'OR',
				'user',
				'=',
				NULL,
				')',
				'AND',
				'domainGroup',
				'=',
				array( $this->user->getSelectedDomainGroup() ),
				')',
				'OR',
				'user',
				'=',
				array( $this->user->record ),
				'OR',
				'sendToAll',
				'=',
				array( 1 ),
				')'
			),
			'orderBy' => array( 'ctime' => RBStorage::ORDER_BY_DESC )
		);

		$messageBoxEntries = $this->storage->selectRecords( 'RCMessageBox', $queryStruct, 0, 10 );

		$content = array();

		if ( $this->user->record->backendPreference ) {
			$language = $this->user->record->backendPreference->language;
		} else {
			$conf = Config::get( 'localconf' );
			$langConf = $conf->getSection( 'backend' );
			$langs = explode( '/', $langConf[ 'languages' ] );
			$language = reset( $langs );
		}

		foreach ( $messageBoxEntries as $entry ) {
			if ( !empty( $entry->nlsMessage ) ) {
				$text = NLS::getTranslation( $entry->nlsMessage, $entry->nlsRC, $language );

				if ( !empty( $entry->nlsData ) ) {
					$text = NLS::fillPlaceholders( $text, $entry->nlsData );
				}

				$text = $text . $entry->text;
			} else {
				$text = $entry->text;
			}

			$text = !empty( $text ) ? NLS::replaceObjectNames( $text, $language ) : '';

			if ( !empty( $entry->nlsTitle ) ) {
				$title = NLS::getTranslation( $entry->nlsTitle, $entry->nlsRC, $language );

				if ( !empty( $entry->nlsTitleData ) ) {
					$title = NLS::fillPlaceholders( $title, $entry->nlsTitleData );
				}

				$title = $title . $entry->title;
			} else {
				$title = $entry->title;
			}

			$title = !empty( $title ) ? NLS::replaceObjectNames( $title, $language ) : '';

			//this sucks but there's not enough time to do it correctly (as always)

			$json = new Services_JSON( SERVICES_JSON_LOOSE_TYPE );

			$data = $json->decode( $entry->nlsData );

			if ( isset( $data[ '_exception' ] ) ) {
				$eText = NLS::getTranslation( $data[ '_exception' ][ '_exceptionClass' ] . '.message', 'error', $language );

				if ( isset( $data[ '_exception' ][ 'rc' ] ) ) {
					$data[ '_exception' ][ 'rc' ] = '#' . $data[ '_exception' ][ 'rc' ] . '#';
				}

				$eText = NLS::fillPlaceholders( $eText, json_encode( $data[ '_exception' ] ) );

				$eText = !empty( $eText ) ? NLS::replaceObjectNames( $eText, $language ) : '';

				$text .= '<br/><br/>' . $eText;
			}

			$content[ ] = array(
				'date' => $entry->ctime,
				'title' => $title,
				'text' => $text,
				'creator' => $entry->creator->getTitle(),
				'alert' => $entry->alert,
				'user' => $entry->user ? $entry->user->primary : NULL // TODO: Record::FIELDNAME_PRIMARY
			);
		}

		return $content;
	}

	protected function getChangeLog() {
		// TODO: use caching (need to allow caching in backend filter for doing that)
		// TODO: use request time instead of time
		// TODO: use datetime format const
		// TODO: parse time only once for messageBox + changeLog

		// FIXME: don't use time()
		// FIXME: use StorageInterval
		$changeLogEntries = $this->storage->selectRecords( 'RCChangeLog', array( 'fields' => '*', 'where' => array( 'ctime', '>', array( DTDateTime::valueFromTimestamp( time() - ( 60 * 60 * 24 * 7 ) ) ) ), 'orderBy' => array( 'ctime' => RBStorage::ORDER_BY_DESC ) ), 0, 10 );

		$content = array();

		foreach ( $changeLogEntries as $entry ) {
			$content[ ] = array(
				'date' => $entry->ctime,
				'title' => $entry->title,
				'text' => $entry->getFormatted(),
				'creator' => $entry->creator->getTitle(),
				'alert' => $entry->alert
			);
		}

		return $content;
	}

	protected function recordDeletePublish( $recordClass = NULL, $recordID = NULL, $do = NULL ) {

		if ( $do === 'Publish' ) {
			$do = RCPubDateEntries::DO_PUBLISH;
		} else {
			$do = RCPubDateEntries::DO_UNPUBLISH;
		}


		$pubDateRecord = RCPubDateEntries::get(
			$this->storage,
			array(
				'do' => $do,
				'recordType' => $recordClass,
				'elementId' => $recordID
			),
			Record::TRY_TO_LOAD
		);

		$previewRecord = $pubDateRecord->elementId;

		if ( $pubDateRecord->exists() ) {
			$pubDateRecord->delete();
		}

		$res = $previewRecord->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );
		$res = $this->setRecordStati( $recordClass, array( $res ) );

		$actions = $this->getRecordActionsForDomainGroup( $recordClass, $res[ 0 ] );
		$recordClass::modifyActionsForRecordInstance( $res[ 0 ], $actions );

		$this->ajaxSuccess(
			array(
				'items' => $res,
				'actions' => $actions
			),
			true
		);

	}

	protected function recordDelayPublish( $recordClass = NULL, $recordID = NULL, $pubDate = NULL, $pubTime = NULL, $do = NULL ) {

		if ( $do === 'Publish' ) {
			$do = RCPubDateEntries::DO_PUBLISH;
			$contDo = RCPubDateEntries::DO_UNPUBLISH;
		} else {
			$do = RCPubDateEntries::DO_UNPUBLISH;
			$contDo = RCPubDateEntries::DO_PUBLISH;
		}

		$pubDateRecord = RCPubDateEntries::get(
			$this->storage,
			array(
				'do' => $do,
				'recordType' => $recordClass,
				'elementId' => $recordID
			),
			Record::TRY_TO_LOAD
		);

		$pubDateContRecord = RCPubDateEntries::get(
			$this->storage,
			array(
				'do' => $contDo,
				'recordType' => $recordClass,
				'elementId' => $recordID
			),
			Record::TRY_TO_LOAD
		);

		$pubDate = $pubDate . ' ' . $pubTime;

		if ( $contDo === RCPubDateEntries::DO_PUBLISH && $pubDateContRecord->exists() ) {
			if ( $pubDateContRecord->pubDate >= $pubDate ) {
				throw new InvalidPubdateException( 'Please set a date for unpublishing subsequently to publishing date' );
			}
		}

		if ( !$pubDateRecord->exists() ) {
			$pubDateRecord->pubDate = $pubDate;
			$pubDateRecord->save();
		}

		$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

		$res = $previewRecord->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );
		$res = $this->setRecordStati( $recordClass, array( $res ) );

		$actions = $this->getRecordActionsForDomainGroup( $recordClass, $res[ 0 ] );
		$recordClass::modifyActionsForRecordInstance( $res[ 0 ], $actions );

		$this->ajaxSuccess(
			array(
				'items' => $res,
				'actions' => $actions
			),
			true
		);

	}

	protected function getCopyableReferences( $recordClass = NULL, $recordPrimary = NULL, array $changes = NULL ) {
		if ( !( $recordClass && $recordPrimary && $changes ) ) {
			throw new InvalidArgumentException( '$recordClass, $recordPrimary and $changes must be set' );
		}

		$records = array();

		$originalRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => (int)$recordPrimary ), Record::TRY_TO_LOAD );

		if ( !$originalRecord->exists() ) {
			throw new RecordDoesNotExistException( 'Record of class ' . $recordClass . ' with primary ' . $recordPrimary . ' does not exist' );
		}

		$tmp = $originalRecord->getCopyableReferences( $changes );

		foreach ( $tmp as $record ) {
			if ( $domainGroupFieldName = $record->getDataTypeFieldName( 'DTSteroidDomainGroup' ) ) {
				if ( $record->{$domainGroupFieldName}->primary == $this->user->getSelectedDomainGroup()->primary ) {
					$records[ ] = $record;
				}
			}
		}

		return $records;
	}

	protected function getPublishableReferences( $recordClass = NULL, $recordPrimary = NULL ) {
		if ( !( $recordClass && $recordPrimary ) ) {
			throw new InvalidArgumentException( '$recordClass, $recordPrimary and changes must be set' );
		}

		$perms = $this->user->getRecordClassPermissionsForDomainGroup( $recordClass );

		if ( !$perms[ RCPermission::ACTION_PERMISSION_PUBLISH ] ) {
			throw new ActionDeniedException( 'User may not publish ' . $recordClass, array(
				'rc' => $recordClass,
				'action' => 'publish'
			) );
		}
// FILTER on ALL
		$userFilter = new PermissionStorageFilter( $this->user, NULL );
		$this->storage->registerFilter( $userFilter );

		$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordPrimary ), Record::TRY_TO_LOAD );

		$missingReferences = array();

		$liveRecord = $previewRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ), $missingReferences );

		$res = array();

		$res[ 'required' ] = $this->getAffectedRecordOutput( $missingReferences, $previewRecord );

		$copyableReferences = $this->getCopyableReferences( $recordClass, $recordPrimary, array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) );
		$res[ 'optional' ] = $this->getAffectedRecordOutput( $copyableReferences, $previewRecord );

		$this->ajaxSuccess( array(
			'items' => $res
		), true );
	}

	protected function recordPublishRequest( $recordClass = NULL, $recordID = NULL, $doPublish = false, $additionalPublish = NULL ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}

		$perms = $this->user->getRecordClassPermissionsForDomainGroup( $recordClass );

		if ( !$perms[ RCPermission::ACTION_PERMISSION_PUBLISH ] ) {
			throw new ActionDeniedException( 'User may not publish ' . $recordClass, array(
				'rc' => $recordClass,
				'action' => 'publish'
			) );
		}
// FILTER on recordClass
		$userFilter = new PermissionStorageFilter( $this->user, $recordClass );
		$this->storage->registerFilter( $userFilter );

		$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ), Record::TRY_TO_LOAD );

		$missingReferences = array();

		$liveRecord = $previewRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ), $missingReferences );

		$classes[ 'required' ] = $this->getAffectedRecordOutput( $missingReferences, $previewRecord );
		$classes[ 'optional' ] = array();

//		$classes[ 'optional' ] = $this->getAffectedRecordOutput( $this->getCopyableReferences( $recordClass, $recordID, array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) ), $previewRecord );

// FIXME: should not be able to publish if some record in §missingReferences is marked as 'missing', as this would lead to unexpected behaviour
		if ( $doPublish || ( empty( $classes[ 'required' ] ) && empty( $classes[ 'optional' ] ) ) ) {

			foreach ( $missingReferences as $record ) {
				if ( $record->getMeta( 'missing' ) ) {
					$copiedRecord = $record->getMeta( 'copied' );

					$copiedRecord->save();
				}
			}

			$liveRecord->save();

			$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

			$res = $previewRecord->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );

			$res = $this->setRecordStati( $recordClass, array( $res ) );

			$actions = $this->getRecordActionsForDomainGroup( $recordClass, $res[ 0 ] );

			$recordClass::modifyActionsForRecordInstance( $res[ 0 ], $actions );

			if ( $additionalPublish ) {
				$additionalPublish = explode( ',', $additionalPublish );

				foreach ( $additionalPublish as $recordID ) {
					$recordConf = explode( '_', $recordID );

					$additionalRC = $recordConf[ 0 ];
					$additionalPrimary = $recordConf[ 1 ];

					$additionalRecord = $additionalRC::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $additionalPrimary ), Record::TRY_TO_LOAD );

					if ( !$additionalRecord->exists() ) {
						continue;
					}

					$missingReferences = array();

					$additionalRecordLive = $additionalRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ), $missingReferences );

					$additionalRecordLive->save();
				}
			}

			$this->ajaxSuccess( array(
				'items' => $res,
				'actions' => $actions
			), true );
		} else {
			$this->ajaxFail( new MissingReferencesException( '', $classes ) );
		}
	}

	protected function recordEndEditing( $recordClass = NULL, $recordID = NULL ) {
		if ( empty( $recordClass ) || empty( $recordID ) ) {
			throw new InvalidArgumentException( '$recordClass and $recordID must be set' );
		}

		if ( !is_subclass_of( $recordClass, 'IRecord' ) ) {
			$this->ajaxSuccess();
			return;
		}

		$currentContentEditRec = $this->storage->selectFirstRecord( 'RCContentEdit', array( 'where' => array( 'recordClass', '=', array( $recordClass ), 'AND', 'recordPrimary', '=', array( (int)$recordID ), 'AND', 'creator', '=', array( $this->user->record ) ) ) );

		if ( $currentContentEditRec ) {
			try {
				$currentContentEditRec->delete();
			} catch ( NoActionPerformedException $e ) {
				//TODO: why can this happen?
			}
		}

		ClassFinder::find( $recordClass, true );

		$recordClass::endEditing( $this->storage, $recordID );

		$this->ajaxSuccess();
	}

	protected function insertFromClipboard( $recordClass = NULL, $recordPrimary = NULL ) {
		if ( empty( $recordClass ) || empty( $recordPrimary ) ) {
			throw new InvalidArgumentException( '$recordClass and $recordPrimary must be set' );
		}

		$clipboardRecord = RCClipboard::get( $this->storage, array(
			'recordClass' => $recordClass,
			'recordPrimary' => $recordPrimary,
			'creator' => $this->user->record
		), Record::TRY_TO_LOAD );

		$contentRec = $clipboardRecord->recordPrimary;

		$this->ajaxSuccess( array(
			'exists' => (bool)count( $contentRec->{'element:RCElementInArea'} )
		) );
	}

	protected function copyToClipboard( $recordClass, $recordPrimary ) {
		$record = $recordClass::get( $this->storage, array(
			Record::FIELDNAME_PRIMARY => $recordPrimary
		), Record::TRY_TO_LOAD );

		if ( !$record->exists() ) {
			throw new RecordDoesNotExistException( 'Cannot copy record to clipboard as it does not exist anymore', array(
				'rc' => $recordClass
			) );
		}

		$clipboardRecord = RCClipboard::get( $this->storage, array(
			'recordClass' => $recordClass,
			'recordPrimary' => $record,
			'creator' => $this->user->record
		), Record::TRY_TO_LOAD );

		$exists = $clipboardRecord->exists();

		if ( !$exists ) {
			$clipboardRecord->save();
		}

		$this->ajaxSuccess( array(
			'items' => array( $clipboardRecord->recordPrimary->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) ) ),
			'exists' => $exists ) );
	}

	protected function copyPage( $recordPrimary, $parent ) {
		$page = RCPage::get( $this->storage, array(
			Record::FIELDNAME_PRIMARY => (int)$recordPrimary
		), Record::TRY_TO_LOAD );

		if ( !$page->exists() ) {
			throw new RecordDoesNotExistException( 'Cannot copy record to clipboard as it does not exist anymore', array(
				'rc' => 'RCPage'
			) );
		}

		$parent = RCPage::get( $this->storage, array(
			Record::FIELDNAME_PRIMARY => $parent
		), Record::TRY_TO_LOAD );

		if ( !$parent->exists() ) {
			throw new TargetDoesNotExistException( 'Target parent does not exist', array(
					'rc' => 'RCPage',
					'field' => 'parent'
				)
			);
		}

		$newPage = $page->duplicate($parent);

		$newPage->save();

		$this->ajaxSuccess();
	}

	protected function removeFromClipboard( $recordClass = NULL, $recordPrimary = NULL ) {
		if ( empty( $recordClass ) || empty( $recordPrimary ) ) {
			throw new InvalidArgumentException( '$recordClass and $recordPrimary must be set' );
		}

		$clipboardRecord = RCClipboard::get( $this->storage, array(
			'recordClass' => $recordClass,
			'recordPrimary' => $recordPrimary,
			'creator' => $this->user->record
		), Record::TRY_TO_LOAD );

		if ( $clipboardRecord->exists() ) {
			$clipboardRecord->delete();
		}

		$this->ajaxSuccess();
	}

	/**
	 * Saves record as correct and efficient as possible
	 * 
	 * - load record with paths according to postData keys
	 * - set data recursively, correctly tracking changes
	 * - only save changed records
	 */
	final private function saveRecord( $recordClass, $postData ) {
		
		if (!$postData[Record::FIELDNAME_PRIMARY]) {
			// in case record is a new one, simply use recordClass::get 
			$record = $recordClass::get( $this->storage, $postData, false );
	
			$record->save();
		} else {
			// if record is an already existing one, we load the existing from db
			$queryStruct = array(
				RBStorage::SELECT_FIELDNAME_WHERE => array(
					Record::FIELDNAME_PRIMARY, '=', array( $postData[Record::FIELDNAME_PRIMARY] )
				),
				RBStorage::SELECT_FIELDNAME_FIELDS => Record::arrayKeysToPathSet($postData)
			);

			$record = $this->storage->selectFirstRecord( $recordClass, $queryStruct, /* $start */ NULL, /* $getTotal */ NULL, /* $vals */ NULL, /* $name */ NULL, /* $noAutoSelect */ false );
		
			$dirtyTracking = array();

			$record->setValues( $postData, false, '', $dirtyTracking );
			
			$savePaths = Record::getSavePathsFromDirtyTracking( $dirtyTracking );

			$record->save( $savePaths );


		}
			
		return $record;
	}

	protected function recordSaveRequest( $recordClass = NULL ) {
		if ( empty( $recordClass ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName must be set' );
		}
// FILTER on recordClass
		$userFilter = new PermissionStorageFilter( $this->user, $recordClass );
		$this->storage->registerFilter( $userFilter );

		$this->isIframe = $this->requestInfo->getGPParam( 'isIframe' );

		if ( is_subclass_of( $recordClass, 'IRecord' ) ) {
			$postData = $this->requestInfo->getPost();

			$record = $this->saveRecord( $recordClass, $postData );

			$this->updateContentEdit( $recordClass, $record );


			if ( $mTimeField = $recordClass::getDataTypeFieldName( 'DTMTime' ) ) {
				$record->{$mTimeField} = $_SERVER[ 'REQUEST_TIME' ];
			}

			$values = $record->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );

			$values = $this->setRecordStati( $recordClass, array( $values ) );

			$actions = $this->getRecordActionsForDomainGroup( $recordClass, $values[ 0 ] );

			$recordClass::modifyActionsForRecordInstance( $values[ 0 ], $actions );

			$this->ajaxSuccess( array(
				'items' => $values,
				'actions' => $actions
			), true );
		} else if ( is_subclass_of( $recordClass, 'Wizard' ) ) {
			$result = $recordClass::save( $this->storage, $this->requestInfo->getPost(), $this->user );

			$this->ajaxSuccess( $result );
		}
	}

	protected function syncRecord( $recordClass = NULL, $recordID = NULL ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}

		$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

		$previewRecord->doSync( true );

		$res = $previewRecord->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );

		$res = $this->setRecordStati( $recordClass, array( $res ) );

		$actions = $this->getRecordActionsForDomainGroup( $recordClass, $res[ 0 ] );

		$recordClass::modifyActionsForRecordInstance( $res[ 0 ], $actions );

		return $res;
	}

	protected function hideRecord( $recordClass = NULL, $recordID = NULL, $doHide = false ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}

		$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

		$idField = $recordClass::getDataTypeFieldName( 'DTSteroidID' );

		$liveIdentity = array(
			$recordClass::getDataTypeFieldName( 'DTSteroidLive' ) => DTSteroidLive::LIVE_STATUS_LIVE,
			$idField => $previewRecord->{$idField}
		);

		if ( $langField = $recordClass::getDataTypeFieldName( 'DTSteroidLanguage' ) ) {

			$liveIdentity[ $langField ] = $previewRecord->{$langField}->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) );
		}

		$liveRecord = $recordClass::get( $this->storage, $liveIdentity, Record::TRY_TO_LOAD );

		if ( !$liveRecord->exists() ) {
			$res = $previewRecord->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );

			$res = $this->setRecordStati( $recordClass, array( $res ) );

			return $res;
		}

		$changes = array();

		$liveRecord->delete( $changes );

		$classes = $this->getAffectedRecordOutput( $changes, $liveRecord );

		if ( $doHide || empty( $classes ) ) {
			$liveRecord->delete();

			$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

			$res = $previewRecord->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );

			$res = $this->setRecordStati( $recordClass, array( $res ) );
		} else {
			throw new AffectedReferencesException( '', $classes );
		}

		return $res;
	}

	protected function recordPreviewRequest( $recordClass = NULL, $recordID = NULL ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}

		try {
			$page = NULL;

			$record = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

			if ( $recordClass == 'RCPage' ) {
				$page = $record;
			} else {
				if ( $pageField = $record->getDataTypeFieldName( 'DTSteroidPage' ) ) {
					$page = $record->{$pageField};
				}
			}

			$res = RCPreviewSecret::getNewPreviewUrl( $page );

			$this->ajaxSuccess( array(
				'items' => $res
			), true );

		} catch ( Exception $e ) {
			$this->ajaxFail( $e );
		}
	}

	protected function recordSyncRequest( $recordClass = NULL, $recordID = NULL ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}
// FILTER
		$userFilter = new PermissionStorageFilter( $this->user, $recordClass );
		$this->storage->registerFilter( $userFilter );

		try {
			$res = $this->syncRecord( $recordClass, $recordID );

			$actions = $this->getRecordActionsForDomainGroup( $recordClass, $res[ 0 ] );

			$recordClass::modifyActionsForRecordInstance( $res[ 0 ], $actions );

			$this->ajaxSuccess( array(
				'items' => $res,
				'actions' => $actions
			), true );
		} catch ( AffectedReferencesException $e ) {
			$this->ajaxFail( $e );
		}
	}

	protected function recordHideRequest( $recordClass = NULL, $recordID = NULL, $doHide = false ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}

		$perms = $this->user->getRecordClassPermissionsForDomainGroup( $recordClass );

		if ( !$perms[ RCPermission::ACTION_PERMISSION_HIDE ] ) {
			throw new ActionDeniedException( 'User may not hide ' . $recordClass, array(
				'rc' => $recordClass,
				'action' => 'hide'
			) );
		}
// FILTER
		$userFilter = new PermissionStorageFilter( $this->user, $recordClass );
		$this->storage->registerFilter( $userFilter );

		try {
			$res = $this->hideRecord( $recordClass, $recordID, $doHide );

			$actions = $this->getRecordActionsForDomainGroup( $recordClass, $res[ 0 ] );

			$recordClass::modifyActionsForRecordInstance( $res[ 0 ], $actions );

			$this->ajaxSuccess( array(
				'items' => $res,
				'actions' => $actions
			), true );
		} catch ( AffectedReferencesException $e ) {
			$this->ajaxFail( $e );
		}
	}

	protected function recordRevertRequest( $recordClass = NULL, $recordID = NULL ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}
// FILTER
		$userFilter = new PermissionStorageFilter( $this->user, $recordClass );
		$this->storage->registerFilter( $userFilter );

		$previewRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

		$idField = $recordClass::getDataTypeFieldName( 'DTSteroidID' );

		$liveIdentity = array(
			$recordClass::getDataTypeFieldName( 'DTSteroidLive' ) => DTSteroidLive::LIVE_STATUS_LIVE,
			$idField => $previewRecord->getFieldValue( $idField )
		);

		if ( $langField = $recordClass::getDataTypeFieldName( 'DTSteroidLanguage' ) ) {

			$liveIdentity[ $langField ] = $previewRecord->getFieldValue( $langField )->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) );
		}

		$liveRecord = $recordClass::get( $this->storage, $liveIdentity );

		$missingReferences = array();

		$previewRecord = $liveRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ), $missingReferences );

		$previewRecord->save();

		$res = $previewRecord->getFormValues( array_keys( $recordClass::getFormFields( $this->storage ) ) );

		$res = $this->setRecordStati( $recordClass, array( $res ) );

		$actions = $this->getRecordActionsForDomainGroup( $recordClass, $res[ 0 ] );

		$recordClass::modifyActionsForRecordInstance( $res[ 0 ], $actions );

		$this->ajaxSuccess( array(
			'items' => $res,
			'actions' => $actions
		), true );
	}

	protected function recordDeleteRequest( $recordClass = NULL, $recordID = NULL, $doDelete = false ) {
		if ( empty( $recordClass ) || empty( $recordID ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}

		$perms = $this->user->getRecordClassPermissionsForDomainGroup( $recordClass );

		if ( !$perms[ RCPermission::ACTION_PERMISSION_DELETE ] ) {
			throw new ActionDeniedException( 'User may not delete ' . $recordClass, array(
				'rc' => $recordClass,
				'action' => 'delete'
			) );
		}
// FILTER
		$userFilter = new PermissionStorageFilter( $this->user, $recordClass );
		$this->storage->registerFilter( $userFilter );

		$record = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

		$changes = array();

		$record->delete( $changes );

		$classes = $this->getAffectedRecordOutput( $changes, $record );

		if ( $doDelete ) {
			$recordDomainGroup = NULL;

			if ( $domainGroupField = $record->getDataTypeFieldName( 'DTSteroidDomainGroup' ) ) {
				$recordDomainGroup = $record->{$domainGroupField};
			}

			$recordActions = $this->getRecordActions( $record );

			if ( !in_array( Record::ACTION_DELETE, $recordActions, true ) ) {
				throw new RecordActionDeniedException( 'Deleting is currently disabled, please use "Hide" instead', array(
					'rc' => $recordClass,
					'action' => 'delete'
				) );
			}

			if ( $recordClass::getDataTypeFieldName( 'DTSteroidLive' ) ) {
				$this->hideRecord( $recordClass, $recordID, true );
			}

			$mainRecordTitle = $record->getTitle();
			$mainRecordClass = get_class($record);

			$record->delete();

			$this->createDeletionNotifications( $mainRecordClass, $mainRecordTitle, $classes );

			$this->ajaxSuccess();
		} else {
			$this->ajaxFail( new AffectedReferencesException( '', $classes ) );
		}
	}

	protected function createDeletionNotifications( $mainRecordClass, $mainRecordTitle, $classes = array() ) {
		if ( empty( $classes ) ) {
			return;
		}

		foreach ( $classes as $domainGroupTitle => $recordClasses ) {
			if ( $domainGroupTitle === $this->user->getSelectedDomainGroup()->getTitle() ) {
				continue;
			}

			$userPermJoins = $this->storage->selectRecords( 'RCDomainGroupLanguagePermissionUser', array( 'where' => array(
				'domainGroup.title', '=', array( $domainGroupTitle )
			) ) );

			$users = array();

			foreach ( $userPermJoins as $userPermJoin ) {
				if ( !in_array( $userPermJoin->permission, array( User::PERMISSION_TITLE_DEV, User::PERMISSION_TITLE_MASTER ) ) && !in_array( $userPermJoin->user, $users, true ) ) {
					$users[ ] = $userPermJoin->user;
				}
			}

			foreach ( $users as $user ) {
				if ( !$user->backendPreference || !$user->backendPreference->language ) {
					continue;
				}

				$messageBody = '';

				foreach ( $recordClasses as $recordClassName => $records ) {
					$messageBody .= '<br/><b>' . ( $recordClassName::BACKEND_TYPE == Record::BACKEND_TYPE_WIDGET ? 'Widget ' : '' ) . '#' . $recordClassName . '#' . ( $recordClassName::BACKEND_TYPE == Record::BACKEND_TYPE_WIDGET ? ' in' : '' ) . '</b><br/>';

					foreach ( $records as $title => $data ) {
						$messageBody .= $title . '<br/>';
					}
				}

				$message = RCMessageBox::get( $this->storage, array(
					'creator' => $this->user->record,
					'user' => $user,
					'alert' => true,
					'text' => $messageBody,
					'nlsTitle' => '_messageBox.recordDeleted.title',
					'nlsMessage' => '_messageBox.recordDeleted.text',
					'nlsData' => json_encode( array(
						'recordClass' => '#' . $mainRecordClass . '#',
						'recordTitle' => $mainRecordTitle,
						'domainGroup' => $this->user->getSelectedDomainGroup()->getTitle(),
						'targetDomainGroup' => $domainGroupTitle,

					) ),
					'nlsRC' => 'generic'
				), false );

				$message->save();
			}
		}
	}

	protected function getAffectedRecordOutput( $changes = array(), IRecord $mainRecord ) {
		$classes = array();

		foreach ( $changes as $record ) {
			$record->getAffectedRecordData( $mainRecord, $classes, $changes );
		}

		return $classes;
	}

	protected function recordRequest( $recordClassName = NULL, $recordID = NULL, $forEditing = false, $requestingRecordClass = NULL, $previousRecordClass = NULL, $previousRecordID = NULL ) {
		if ( empty( $recordClassName ) || !ClassFinder::find( array( $recordClassName ), true ) || $recordID == NULL ) {
			throw new InvalidArgumentException( '$recordClassName and $recordID must be set' );
		}

		if ( $recordID !== 'new' && $forEditing === true && is_subclass_of( $recordClassName, 'IRecord' ) ) {
			$beingEdited = $this->storage->selectFirstRecord( 'RCContentEdit', array( 'where' => array( 'recordClass', '=', array( $recordClassName ), 'AND', 'recordPrimary', '=', array( $recordID ), 'AND', 'creator', '!=', array( $this->user->record ) ) ) );

			if ( $beingEdited ) {
				throw new RecordIsLockedException( $beingEdited->creator->getTitle() );
			}
		}

		$actions = array();

		if ( is_subclass_of( $recordClassName, 'IRecord' ) && $forEditing === true ) {
			// in case user has no permission, transaction will be rolled back, and thus change to content edit will be undone
			$this->updateContentEdit( $recordClassName, $recordID, $previousRecordClass, $previousRecordID );
		}
// FILTER
		$userFilter = new PermissionStorageFilter( $this->user, $recordClassName );
		$this->storage->registerFilter( $userFilter );

		if ( $recordID === 'new' ) {
			if ( is_subclass_of( $recordClassName, 'IRecord' ) && !is_subclass_of( $recordClassName, 'ElementRecord' ) ) {
				$perms = $this->user->getRecordClassPermissionsForDomainGroup( $recordClassName );

				if ( !$perms[ RCPermission::ACTION_PERMISSION_CREATE ] ) {
					throw new ActionDeniedException( 'User may not create ' . $recordClassName, array(
						'rc' => $recordClass,
						'action' => 'create'
					) );
				}
			}

			$res = $this->createNewRecord( $recordClassName );
		} else {
			try {
				$res = $this->selectExistingRecord( $recordClassName, $recordID, $forEditing, $requestingRecordClass );
			} catch ( RecordIsLockedException $e ) {
				$this->ajaxFail( $e );
				return;
			}
		}

		if ( is_subclass_of( $recordClassName, 'IRecord' ) ) {
			if ( $forEditing ) {
				$actions = $this->getRecordActionsForDomainGroup( $recordClassName, $res[ 0 ] );

				$recordClassName::modifyActionsForRecordInstance( $res[ 0 ], $actions );
			}
		} else if ( is_subclass_of( $recordClassName, 'Wizard' ) ) {
			$actions = $recordClassName::getAvailableActions();
		}

		$this->ajaxSuccess( array(
			'items' => $res,
			'actions' => $actions
		), true );
	}

	/**
	 * @param String                       $recordClassName
	 * @param IRecord|String|integer|float $recordID
	 * @param null                         $previousRecordClass
	 * @param null                         $previousRecordID
	 */
	protected function updateContentEdit( $recordClassName, $recordID, $previousRecordClass = NULL, $previousRecordID = NULL ) {
		$this->deleteOldContentEditRecords();

		if ( $previousRecordClass && $previousRecordID && ( $contentEdit = $this->storage->selectFirstRecord( 'RCContentEdit', array( 'where' => array( 'recordClass', '=', array( $previousRecordClass ), 'AND', 'recordPrimary', '=', array( $previousRecordID ) ) ) ) ) ) {
			try {
				$contentEdit->delete();
			} catch ( NoActionPerformedException $e ) {
				//TODO: find out why that can happen
			}
		}

		if ( $recordID !== 'new' ) { // in case we create a new Record, opening the form triggers a request with recordID = 'new'
			$data = array(
				'recordClass' => $recordClassName,
				'recordPrimary' => $recordID,
				'creator' => $this->user->record,
				'lastAliveMessage' => $this->requestInfo->getRequestTime()
			);

			$contentEdit = RCContentEdit::get( $this->storage, $data, false );

			$contentEdit->recordPrimary->readOnly = true;

			$contentEdit->save();
			
			$contentEdit->recordPrimary->readOnly = false;
			
		}

		ClassFinder::find( $recordClassName, true );

		$recordClassName::updateContentEdit( $this->storage, $recordID, $this->requestInfo, $previousRecordClass, $previousRecordID, $this->requestInfo->getPostParam( 'parent' ) );
	}

	protected function getRecordActionsForDomainGroup( $recordClassName, $record ) {
		return $this->user->getRecordActionsForDomainGroup( $recordClassName, $record );
	}

	protected function getRecordActions( $record ) {
		return $this->getRecordActionsForDomainGroup( get_class( $record ), $record );

	}

	protected function createNewRecord( $recordClassName = NULL ) {
		if ( empty( $recordClassName ) || !ClassFinder::find( array( $recordClassName ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName must be set' );
		}

		$extraParams = array();

		$extraParams[ 'language' ] = $this->requestInfo->getPostParam( 'contentLanguage' ); // TODO: constify
		$extraParams[ 'domainGroup' ] = $this->requestInfo->getPostParam( 'contentDomainGroup' ); // TODO: constify
		$extraParams[ 'parent' ] = $this->requestInfo->getPostParam( 'parent' ); // TODO: constify
		$extraParams[ 'user' ] = $this->user; // TODO: constify
		$extraParams[ 'recordClasses' ][ ] = $recordClassName; // TODO: constify

		$defaultValues = $recordClassName::getDefaultValues( $this->storage, array_keys( $recordClassName::getFormFields( $this->storage ) ), $extraParams );

		return array( $defaultValues );
	}


	protected function getQueryFields( array $fieldDefs = NULL ) {
		if ( empty( $fieldDefs ) ) {
			throw new InvalidArgumentException( '$fieldDefs must be set' );
		}

		$fields = array();

		foreach ( $fieldDefs as $fieldName => $fieldDef ) {
			if ( is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTRecordReference' ) || is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTForeignReference' ) ) {

				$queryStruct = array(
					'fields' => $this->addFieldsToQueryStruct( $fieldDef[ 'recordClass' ]::getListTitleFieldsCached() )
				);

				$fields[ $fieldName ] = $queryStruct;
			} else {
				$fields[ ] = $fieldName;
			}
		}

		return $fields;
	}

	protected function handleUserIsAlive( $currentlyEditing = NULL, $editingClass = NULL, $editingParent = NULL ) {
		//update RCContentEdit records
		if ( $currentlyEditing !== NULL && $editingClass !== NULL ) {
			$where = array( 'creator', '=', array( $this->user->record ) );

			if ( $currentlyEditing !== 'new' ) {
				array_push( $where, 'AND', 'recordClass', '=', array( $editingClass ), 'AND', 'recordPrimary', '=', array( $currentlyEditing ) );
			}

			$CERecs = $this->storage->selectRecords( 'RCContentEdit', array( 'where' => $where ) );

			foreach ( $CERecs as $rec ) {
				$rec->lastAliveMessage = $this->requestInfo->getRequestTime();

				$rec->save();
			}
		}

		if ( ( $currentlyEditing !== NULL && $currentlyEditing !== 'new' || $editingParent !== NULL ) && $editingClass !== NULL ) {
			ClassFinder::find( $editingClass, true );

			$editingClass::handleUserAlive( $this->storage, $this->requestInfo, $currentlyEditing, $editingParent );
		}

		$this->deleteOldContentEditRecords();

	}

	protected function deleteOldContentEditRecords() {
		// delete records where user hasn't been alive for more than 90 seconds
		$CERecs = $this->storage->selectRecords( 'RCContentEdit', array( 'where' => array( 'lastAliveMessage', '<', array( DTDateTime::valueFromTimestamp( $this->requestInfo->getRequestTime() - 90 ) ) ) ) );

		foreach ( $CERecs as $rec ) {
			try {
				$rec->delete();
			} catch ( NoActionPerformedException $e ) {
				//TODO: why can this happen?
			}
		}
	}

	protected function getMessages( $requestTime = null, $currentlyEditing = NULL, $editingClass = NULL, $editingParent = NULL ) {
		$this->handleUserIsAlive( $currentlyEditing, $editingClass, $editingParent );

		$messages = $this->getMessageBox( $requestTime );

		$this->ajaxSuccess( $messages );
	}

	final protected function handleDownload( $filePrimary ) {
		$filePrimary = (int)$filePrimary;

		if ( !$filePrimary ) {
			throw new InvalidArgumentException( 'invalid file primary' );
		}
// FILTER
		$userFilter = new PermissionStorageFilter( $this->user, 'RCFile' );
		$this->storage->registerFilter( $userFilter );

		$record = $this->storage->selectFirstRecord( 'RCFile', array( 'fields' => '*', 'where' => array( Record::FIELDNAME_PRIMARY, '=', array( $filePrimary ) ) ) );

		if ( $record === NULL ) {
			throw new Exception( 'file with given primary could not be found' );
		}

		if ( $record->lockToDomainGroup && $record->domainGroup !== $this->user->getSelectedDomainGroup() ) {
			throw new RecordLimitedToDomainGroupException( 'file may not be accessed outside its original domainGroup', array(
				'rc' => get_class( $record ),
				'record' => $record->getTitle(),
				'targetRecord' => $record->domainGroup->getTitle()
			) );
		}

		$permsInDomainGroup = $this->user->getRecordClassPermissionsForDomainGroup( 'RCFile', $record->domainGroup );

		if ( !$permsInDomainGroup ) {
			throw new AccessDeniedException( 'access denied for file', array(
				'rc' => 'RCFile'
			) );
		}

		Responder::sendFile( $record->getFullFileName(), NULL, NULL, NULL, $record->getNiceDownloadFilename() );
	}

	protected function selectExistingRecord( $recordClassName = NULL, $recordID = NULL, $forEditing = false, $requestingRecordClass = NULL ) {
		if ( empty( $recordClassName ) || $recordID == NULL || !ClassFinder::find( array( $recordClassName ), true ) ) {
			throw new InvalidArgumentException( '$recordClassName, $recordID and $fieldsToSelect must be set' );
		}

		$record = $recordClassName::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $recordID ) );

		if ( $forEditing || ( $recordClassName == 'RCTemplate' && $requestingRecordClass == 'RCPage' ) ) { // needed for template switching
			$fields = array_keys( $recordClassName::getFormFields( $this->storage ) );
		} else {
			$titleFields = array_keys( $recordClassName::getTitleFieldsCached() );
			$ownFields = array_keys( $recordClassName::getOwnFieldDefinitions() );

			$fields = array_merge( $titleFields, $ownFields );
		}

		$res = $record->getFormValues( $fields );

		$res = $this->setRecordStati( $recordClassName, array( $res ) );

		$res[ 0 ][ '_title' ] = $record->getTitle();

		return $res;
	}

	protected function recordClassRequest( $recordClassName = NULL ) {
		if ( empty( $recordClassName ) || !ClassFinder::find( array( $recordClassName ), true ) ) {
			throw new InvalidArgumentException( '$recordClass must be set and exist' );
		}

		$mainRecordClass = $this->requestInfo->getPostParam( self::PARAM_MAIN_RECORD_CLASS );

		if ( $mainRecordClass && !ClassFinder::find( array( $mainRecordClass ), true ) ) {
			throw new InvalidArgumentException( '$mainRecordClass not found' );
		}

		$currentDomainGroup = $this->requestInfo->getPostParam( 'contentDomainGroup' ) ? : $this->user->getSelectedDomainGroup()->primary;

		$perms = $this->user->getRecordClassPermissionsForDomainGroup( $recordClassName, $currentDomainGroup );

		if ( !$perms ) {
			throw new AccessDeniedException( 'Access denied for recordClass "' . $recordClassName . '" in domainGroup ' . $currentDomainGroup, array(
				'rc' => $recordClassName
			) );
		}
// FILTER
		$userFilter = new PermissionStorageFilter( $this->user, $recordClassName );
		$this->storage->registerFilter( $userFilter );

		$fieldsToSelect = $this->getQueryFields( $recordClassName::getExpandedListFields( $this->user ) );

		$limitStart = $this->requestInfo->getPostParam( self::PARAM_LIMIT_START );
		$limitCount = $this->requestInfo->getPostParam( self::PARAM_LIMIT_COUNT );

		if ( !$limitCount || $limitCount == 'Infinity' ) {
			$limitCount = NULL;
		}

		$sorting = json_decode( $this->requestInfo->getPostParam( self::PARAM_SORT ), true );
		$filter = json_decode( $this->requestInfo->getPostParam( self::PARAM_FILTER ), true );
		$filter = is_array( $filter ) ? $filter : array();
		$exclude = json_decode( $this->requestInfo->getPostParam( self::PARAM_EXCLUDE ), true );

		$isSearchField = $this->requestInfo->getPostParam( self::PARAM_IS_SEARCH_FIELD );

		$mainRecord = json_decode( $this->requestInfo->getPostParam( self::PARAM_MAIN_RECORD ), true );
		$requestFieldName = $this->requestInfo->getPostParam( self::PARAM_REQUESTING_FIELDNAME );
		$requestingRecordClass = $this->requestInfo->getPostParam( self::PARAM_REQUESTING_RECORDCLASS );

		if ( $requestingRecordClass ) {
			ClassFinder::find( array( $requestingRecordClass ), true );
		}

		$find = $this->requestInfo->getPostParam( self::PARAM_FIND );
		$parent = $this->requestInfo->getPostParam( self::PARAM_PARENT );

		$parentFieldName = $recordClassName::getDataTypeFieldName( 'DTParentReference' );

		$displayHierarchic = $this->requestInfo->getPostParam( self::PARAM_DISPLAY_HIERARCHIC ) == 'true';

		$currentLanguage = $this->requestInfo->getPostParam( 'contentLanguage' ) ? : $this->user->getSelectedLanguage()->primary;

		//	$queryStruct = array( 'fields' => $fieldsToSelect );
		$queryStruct = array( 'fields' => $recordClassName::getPrimaryKeyFields() );


		if ( $sorting !== NULL ) {
			$this->generateSortingStruct( $queryStruct, $recordClassName, $sorting );
		} elseif ( $sorting = $recordClassName::getDefaultSorting() ) {
			// [JB 10.5.2013] Removed selecting sorting fields, because they can be lazy loaded and don't need to be selected just for sorting
			$queryStruct[ 'orderBy' ] = $sorting;
		}

		if ( $liveField = $recordClassName::getDataTypeFieldName( 'DTSteroidLive' ) ) {
			$this->addWhereOrAnd( $queryStruct );

			array_push( $queryStruct[ 'where' ], $liveField, '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) );
		}

		$queryStructWithoutRootParent = $queryStruct;

		if ( $displayHierarchic ) {
			if ( $this->isDefaultFilter( $recordClassName, $filter ) ) {
				$this->addWhereOrAnd( $queryStruct );

				array_push( $queryStruct[ 'fields' ], $parentFieldName );
				array_push( $queryStruct[ 'where' ], $parentFieldName, '=', $parent !== NULL ? array( $parent ) : NULL );
			} else {
				$displayHierarchic = false;
			}
		}

		if ( !empty( $exclude ) ) {
			$this->addWhereOrAnd( $queryStruct );

			array_push( $queryStruct[ 'where' ], Record::FIELDNAME_PRIMARY, '!=', $exclude );
		}

		// filter
		if ( $requestingRecordClass ) {
			$requestingRecordClass::modifySelect( $queryStruct, $this->storage, $filter, $mainRecordClass, $recordClassName, $requestFieldName, $requestingRecordClass );
		}

		if ( $mainRecordClass ) {
			$mainRecordClass::modifySelect( $queryStruct, $this->storage, $filter, $mainRecordClass, $recordClassName, $requestFieldName, $requestingRecordClass );
		}

		$recordClassName::modifySelect( $queryStruct, $this->storage, $filter, $mainRecordClass, $recordClassName, $requestFieldName, $requestingRecordClass );

		// cleanup old content edit entries
		$this->deleteOldContentEditRecords();

		if ( !$requestFieldName ) {
			$this->forceDomainGroupRestriction( $queryStruct, $recordClassName );
		}

		if ( $limitStart !== NULL ) {
			$res = $this->storage->select( $recordClassName, $queryStruct, $limitStart, $limitCount, true, NULL, NULL, true );
			$total = $this->storage->getFoundRecords();
		} else {
			$res = $this->storage->select( $recordClassName, $queryStruct, NULL, NULL, NULL, NULL, NULL, true );
		}

		$records = $this->createFilteredRecords( $res, $recordClassName, $mainRecordClass, $requestingRecordClass, $requestFieldName, $parent, $displayHierarchic );

		$res = array();

		foreach ( $records as $key => $record ) {
			$res[ $key ] = $record->listFormat( $this->user, $filter, $isSearchField );

			$res[ $key ][ 'liveStatus' ] = $record->getLiveStatus();

			if ( $customStatus = $record->getBackendListRowCSSClass() ) {
				$res[ $key ][ 'rowClass' ] = $customStatus;
			}

			$CEFieldDef = RCContentEdit::getFieldDefinition( 'recordPrimary' );

			if ( in_array( $record::BACKEND_TYPE, $CEFieldDef[ 'allowedBackendTypes' ] ) ) {
				$contentEdit = $record->{'recordPrimary:RCContentEdit'};

				if ( $contentEdit && $contentEdit[ 0 ] && $contentEdit[ 0 ]->creator !== $this->user->record ) {
					$contentEdit = reset( $contentEdit );

					$editor = $contentEdit->creator->getTitle();

					$res[ $key ][ '_editedBy' ] = $editor;
					$res[ $key ][ 'beingEdited' ] = true;
				}
			}
		}

		$res = $this->setRecordStati( $recordClassName, $res );

		$data = array();

		if ( $displayHierarchic ) { // see if records have children

			$primaries = array();

			foreach ( $res as $record ) {
				$primaries[ ] = $record[ Record::FIELDNAME_PRIMARY ];
			}

			$queryStruct = array(
				'fields' => array(
					Record::FIELDNAME_PRIMARY,
					$parentFieldName
				),
				'where' => array(
					$parentFieldName,
					'=',
					$primaries
				)
			);
			// filter
			if ( $requestingRecordClass ) {
				$requestingRecordClass::modifySelect( $queryStruct, $this->storage, $filter, $mainRecordClass, $recordClassName, $requestFieldName, $requestingRecordClass );
			}

			if ( $mainRecordClass ) {
				$mainRecordClass::modifySelect( $queryStruct, $this->storage, $filter, $mainRecordClass, $recordClassName, $requestFieldName, $requestingRecordClass );
			}

			$recordClassName::modifySelect( $queryStruct, $this->storage, $filter, $mainRecordClass, $recordClassName, $requestFieldName, $requestingRecordClass );

			$children = $this->storage->select( $recordClassName, $queryStruct );

			// this now also sorts items so that records with children are displayed first
			$tmp = array();
			$tmp2 = array();

			foreach ( $res as &$item ) {
				foreach ( $children as $child ) {
					if ( $child[ $parentFieldName ] == $item[ Record::FIELDNAME_PRIMARY ] ) {
						$item[ 'children' ] = true;
						continue;
					}
				}

				if ( isset( $item[ 'children' ] ) ) {
					$tmp2[ ] = $item;
				} else {
					$tmp[ ] = $item;
				}
			}

			unset( $item );

			$res = array_merge( $tmp2, $tmp );

			$data[ 'identifier' ] = Record::FIELDNAME_PRIMARY;
			$data[ 'label' ] = $recordClassName::getListTitleFieldsCached();
		}

		$data[ 'items' ] = $res;

		if ( $limitStart !== NULL ) {
			$rangeFrom = $limitStart;
			$rangeTo = $limitStart + count( $res );

			$data[ 'total' ] = $limitStart . '-' . min( $total, $rangeTo ) . '/' . $total;
		}

		$this->ajaxSuccess( $data );
	}

	protected function createFilteredRecords( array $res, $recordClassName, $mainRecordClass, $requestingRecordClass, $requestFieldName, $requestWithParent, $displayHierarchic ) {
		$records = array();

		foreach ( $res as $key => $item ) {
			$record = $recordClassName::get( $this->storage, $item, Record::TRY_TO_LOAD );

			// Filter pages according to page permissions
			if ( $recordClassName === 'RCPage' && !$mainRecordClass && !$requestingRecordClass ) {
				if ( $record->userHasPagePermission( $this->user ) ) {
					$records[ ] = $record;
					continue;
				}

				$allowedParentPages = $this->user->getAllowedParentPages( $record->domainGroup );

				if ( empty( $allowedParentPages ) ) { // no explicit permissions == no restriction
					$records[ ] = $record;
					continue;
				}

				if ( $displayHierarchic && !$requestWithParent ) { // root page(s)
					foreach ( $allowedParentPages as $parentPage ) {
						$records[ ] = $parentPage;
					}
				} else { // e.g. when searching or expanding a parent
					if ( $requestWithParent ) {
						$records[ ] = $record;
						continue;
					}
				}

				// Filter permissions in list according to user's own permissions
			} else if ( $recordClassName == 'RCPermission' && !$mainRecordClass && !$requestingRecordClass ) {
				$recordDomainGroupField = $recordClassName::getDataTypeFieldName( 'DTSteroidDomainGroup' );
				$recordLanguageField = $recordClassName::getDataTypeFieldName( 'DTSteroidLanguage' );

				$domainGroup = $recordDomainGroupField ? $record->{$recordDomainGroupField} : $this->user->getSelectedDomainGroup();
				$language = $recordLanguageField ? $record->{$recordLanguageField} : $this->user->getSelectedLanguage();

				$highestPermissionIndex = $this->user->getHighestPermissionIndexByDomainGroupLanguage( $domainGroup, $language );

				if ( $highestPermissionIndex > -1 ) { // user has a named permission
					$permIndex = array_search( $record->title, User::$permissionPriority );

					if ( $permIndex === false || ( $permIndex <= $highestPermissionIndex ) ) { // record is a named permission of lower priority or a custom permission
						$records[ ] = $record;
					}
				} else { // user only has custom permissions, so only display the same permissions he has
					$userPermissionJoins = $this->user->record->{'user:RCDomainGroupLanguagePermissionUser'};

					foreach ( $userPermissionJoins as $userPermissionJoin ) {
						if ( $record->title === $userPermissionJoin->permission->title ) {
							$records[ ] = $record;
							break;
						}
					}
				}
			} else {
				$records[ ] = $record;
			}
		}

		return $records;
	}

	protected function forceDomainGroupRestriction( &$queryStruct, $recordClass ) {
		if ( $domainGroupFieldName = $recordClass::getDataTypeFieldName( 'DTSteroidDomainGroup' ) ) {
			if ( empty( $queryStruct[ 'where' ] ) || array_search( $domainGroupFieldName, $queryStruct[ 'where' ], true ) === false ) {
				if ( empty( $queryStruct[ 'where' ] ) ) {
					$queryStruct[ 'where' ] = array();
				} else {
					$queryStruct[ 'where' ][ ] = 'AND';
				}

				$queryStruct[ 'where' ][ ] = $domainGroupFieldName;
				$queryStruct[ 'where' ][ ] = '=';
				$queryStruct[ 'where' ][ ] = $this->user->getSelectableDomainGroups();
			}
		}
	}

	protected function isDefaultFilter( $recordClassName = NULL, array $filter ) {
		if ( $recordClassName == 'RCDomainGroup' ) {
			return empty( $filter );
		}

		if ( $recordClassName == 'RCPage' ) {
			if ( empty( $filter ) ) {
				return true;
			}

			if ( count( $filter ) == 1 ) {
				return in_array( RCPage::getDataTypeFieldName( 'DTSteroidDomainGroup' ), $filter[ 0 ][ 'filterFields' ] );
			}

			return false;
		}

		return true; //TODO: add for all hierarchic records?
	}

	protected function addWhereOrAnd( &$queryStruct ) {
		if ( isset( $queryStruct[ 'where' ] ) && !empty( $queryStruct[ 'where' ] ) ) {
			$queryStruct[ 'where' ][ ] = 'AND';
		} else {
			$queryStruct[ 'where' ] = array();
		}
	}

	protected function addFieldsToQueryStruct( $val ) {
		foreach ( $val as $k => $v ) {
			if ( is_array( $v ) ) {
				$val[ $k ] = array(
					'fields' => $this->addFieldsToQueryStruct( $v )
				);
			}
		}

		return $val;
	}

	protected function setRecordStati( $recordClassName, $res ) {
		$idFieldName = $recordClassName::getDataTypeFieldName( 'DTSteroidID' );

		if ( $this->recordClassHasListActions( $recordClassName ) ) {
			foreach ( $res as &$item ) {
				$this->setRecordListActions( $recordClassName, $item );
			}

			unset( $item );
		}

		if ( !$idFieldName || empty( $res ) ) {
			return $res;
		}

		$ids = array();

		foreach ( $res as $record ) {
			$ids[ ] = $record[ $idFieldName ];
		}

		$queryStruct = array(
			'fields' => array(
				Record::FIELDNAME_PRIMARY,
				$idFieldName
			),
			'where' => array(
				$idFieldName,
				'=',
				$ids
			)
		);

		$languageField = $recordClassName::getDataTypeFieldName( 'DTSteroidLanguage' );

		if ( $languageField ) {
			$languageIDField = RCLanguage::getDataTypeFieldName( 'DTSteroidID' );

			$queryStruct[ 'fields' ][ $languageField ] = array(
				'fields' => array(
					$languageIDField,
					'iso639'
				)
			);
		}

		if ( $liveFieldName = $recordClassName::getDataTypeFieldName( 'DTSteroidLive' ) ) {
			if ( !$mTimeFieldName = $recordClassName::getDataTypeFieldName( 'DTMTime' ) ) {
				throw new LogicException( 'Cannot get record status without mtime field' );
			}

			$queryStruct[ 'fields' ][ ] = $liveFieldName;
			$queryStruct[ 'fields' ][ ] = $mTimeFieldName;
		}

		$stati = $this->storage->select( $recordClassName, $queryStruct );

		$temp = array();

		foreach ( $stati as $status ) {
			$temp[ $status[ Record::FIELDNAME_PRIMARY ] ] = $status;
		}

		$stati = $temp;

		foreach ( $res as &$item ) {
			// if record is marked as pubdate record, get pubdates from cron table if exist
			if ( $recordClassName::PUBDATE_RECORD ) {
				$item[ 'publishDate' ] = NULL;
				$tiem[ 'unpublishDate' ] = NULL;

				$cronEntries = $this->storage->selectRecords(
					'RCPubDateEntries',
					array(
						'where' => array(
							'recordType',
							'=',
							array( $recordClassName ),
							'AND',
							'elementId',
							'=',
							array( $item[ 'primary' ] )
						)
					)
				);

				foreach ( $cronEntries as $c ) {

					switch ( $c->do ) {
						case RCPubDateEntries::DO_PUBLISH:
							$item[ 'publishDate' ] = $c->pubDate;
							break;

						case RCPubDateEntries::DO_UNPUBLISH:
							$item[ 'unpublishDate' ] = $c->pubDate;
							break;
					}

				}

			}

			$item[ 'stati' ] = array();

			foreach ( $stati as $primary => $status ) {
				if ( $item[ $idFieldName ] == $status[ $idFieldName ] ) {

					if ( $languageField ) {
						if ( !isset( $item[ 'stati' ][ 'languages' ] ) ) {
							$item[ 'stati' ][ 'languages' ] = array();
						}

						if ( $liveFieldName ) {
							$item[ 'stati' ][ 'languages' ][ $status[ $languageField ][ $languageIDField ] ][ $status[ $liveFieldName ] ] = $status[ Record::FIELDNAME_PRIMARY ];
						} else {
							$item[ 'stati' ][ 'languages' ][ $status[ $languageField ][ $languageIDField ] ] = $status[ Record::FIELDNAME_PRIMARY ];
						}
					} else {
						if ( $liveFieldName ) {
							$item[ 'stati' ][ $status[ $liveFieldName ] ] = $status[ Record::FIELDNAME_PRIMARY ];
						}
					}
				}
			}

			if ( $liveFieldName ) {
				if ( $languageField ) {
					foreach ( $item[ 'stati' ][ 'languages' ] as $languageID => $langStati ) {
						if ( !isset( $langStati[ 1 ] ) ) {
							$item[ 'stati' ][ 'languages' ][ $languageID ][ 'status' ] = Record::RECORD_STATUS_PREVIEW;
							continue;
						}

						if ( strtotime( $stati[ $langStati[ 1 ] ][ $mTimeFieldName ] ) < strtotime( $stati[ $langStati[ 0 ] ][ $mTimeFieldName ] ) ) {
							$item[ 'stati' ][ 'languages' ][ $languageID ][ 'status' ] = Record::RECORD_STATUS_MODIFIED;
						} else {
							$item[ 'stati' ][ 'languages' ][ $languageID ][ 'status' ] = Record::RECORD_STATUS_LIVE;
						}
					}
				} else {
					if ( !isset( $item[ 'stati' ][ 1 ] ) ) {
						$item[ 'stati' ][ 'status' ] = Record::RECORD_STATUS_PREVIEW;
						continue;
					}
					if ( strtotime( $stati[ $item[ 'stati' ][ 1 ] ][ $mTimeFieldName ] ) < strtotime( $stati[ $item[ 'stati' ][ 0 ] ][ $mTimeFieldName ] ) ) {
						$item[ 'stati' ][ 'status' ] = Record::RECORD_STATUS_MODIFIED;
					} else {
						$item[ 'stati' ][ 'status' ] = Record::RECORD_STATUS_LIVE;
					}
				}
			}
		}

		unset( $item );

		return $res;
	}

	protected function setRecordListActions( $recordClassName = NULL, array &$item ) {
		$possibleActions = array(
			'publishRecord',
			'hideRecord',
			'previewRecord',
			'copyRecord',
			'syncRecord'
		);

		$actions = $this->getRecordActionsForDomainGroup( $recordClassName, $item );

		$recordClassName::modifyActionsForRecordInstance( $item, $actions );

		$item[ '_actions' ] = array_values( array_intersect( $possibleActions, $actions ) );
	}

	protected function generateSortingStruct( &$queryStruct, $recordClassName, $sorting = NULL ) {
		if ( empty( $queryStruct ) || empty( $recordClassName ) ) {
			throw new InvalidArgumentException( '$queryStruct and $recordClassName must be set' );
		}

		$sortingConf = array();

		foreach ( $sorting as $sortConf ) {
			$sortingConf[ $sortConf[ 'attribute' ] ] = ( isset( $sortConf[ 'descending' ] ) && $sortConf[ 'descending' ] ) ? RBStorage::ORDER_BY_DESC : RBStorage::ORDER_BY_ASC;
		}

		foreach ( $sortingConf as $fieldName => $sortConf ) {
			if ( $fieldName === '_title' ) {
				$titleFields = array_keys( $recordClassName::getTitleFieldsCached() );
				$firstTitleField = array_shift( $titleFields );

				$fieldDef = $recordClassName::getFieldDefinitionByPath( $firstTitleField );
				$fieldName = $firstTitleField;
			} else {
				$fieldDef = $recordClassName::getFieldDefinitionByPath( $fieldName );
			}

			if ( is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTForeignReference', true ) || is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTRecordReference', true ) ) {
				$foreignTitleFields = $fieldDef[ 'recordClass' ]::getListTitleFieldsCached();

				$tmp = array_keys( $foreignTitleFields );

				if ( !in_array( $fieldName . '.' . $tmp[ 0 ], $queryStruct[ 'fields' ] ) && !in_array( $fieldName . '.*', $queryStruct[ 'fields' ] ) ) {
					$queryStruct[ 'fields' ] [ ] = $fieldName . '.*'; //FIXME: implement path resolving for orderBy in RBStorage so we don't need this
				}

				$queryStruct[ 'orderBy' ][ $fieldName . '.' . $tmp[ 0 ] ] = $sortConf;
			} else {
				$queryStruct[ 'orderBy' ][ $fieldName ] = $sortConf;
			}
		}

		if ( isset( $queryStruct[ 'orderBy' ] ) && $recordClassName::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) && !array_key_exists( Record::FIELDNAME_PRIMARY, $queryStruct[ 'orderBy' ] ) ) {
			$queryStruct[ 'orderBy' ][ Record::FIELDNAME_PRIMARY ] = RBStorage::ORDER_BY_ASC;
		}
	}

	protected function getWizardData( $wizard ) {
		if ( empty( $wizard ) || !is_subclass_of( $wizard, 'Wizard' ) ) {
			throw new InvalidArgumentException( '$wizard must be set and be a subclass of Wizard' );
		}

		$classPath = ClassFinder::getClassLocation( $wizard );

		$data = array(
			'className' => $wizard,
			'formFields' => $wizard::getFormFields( $this->storage ),
			'conditionalFieldConf' => $wizard::getConditionalFieldConf(),
			'isCore' => false, // wizards can never be in core
			'customJS' => $wizard::getCustomJSConfig(),
			'classLocation' => $classPath,
			'fieldSets' => $wizard::getFieldSets( $this->storage )
		);

		return $data;
	}

	protected function getModuleData( $recordClass, array $permissions = NULL ) {
		if ( !class_exists( $recordClass ) ) { // we might have permissions for record classes that don't exist anymore
			// TODO: log
			return NULL;
		}

		if ( empty( $recordClass ) || !is_subclass_of( $recordClass, 'IRecord' ) ) {
			throw new InvalidArgumentException( '$recordClass must be set and be a subclass of IRecord' );
		}

//		// TODO: support records without primary field (1st step: primary key made of single field, 2nd step: primary key made of multiple fields)
//		if ( !array_key_exists( Record::FIELDNAME_PRIMARY, $listFields ) ) { // datagrid and objectstore don't support records where idProperty consists of multiple fields (as is the case with most join records), so for now we exclude them from the backend
//			return NULL;
//		}

		// TODO: get sorting from user preferences

		$defaultSorting = $recordClass::getDefaultSorting();

		$classPath = ClassFinder::getClassLocation( $recordClass );

		$isCore = ST::pathIsCore( $classPath );

		$data = array(
			'className' => $recordClass,
			'isHierarchic' => (bool)$recordClass::getDataTypeFieldName( 'DTParentReference' ),
			'startHierarchic' => $recordClass::LIST_MODE_START_HIERARCHIC,
			'hasPrimaryField' => $recordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ),
			'titleFields' => $recordClass::getTitleFieldsCached(),
			'listTitleFields' => $recordClass::getListTitleFieldsCached(),
			'listFields' => $recordClass::getListFieldsForJS( $this->user ),
			'formFields' => $recordClass::getFormFields( $this->storage ),
			'filterFields' => $this->cleanUpFilterFields( $recordClass::getFilterFields( $this->user, $this->storage ) ),
			'mayWrite' => $permissions[ 'mayWrite' ],
			'isDependency' => $permissions[ 'isDependency' ],
			'restrictToOwn' => $permissions[ 'restrictToOwn' ],
			'defaultSort' => $defaultSorting,
			'liveField' => $recordClass::getDataTypeFieldName( 'DTSteroidLive' ),
			'languageField' => $recordClass::getDataTypeFieldName( 'DTSteroidLanguage' ),
			'idField' => $recordClass::getDataTypeFieldName( 'DTSteroidID' ),
			'allowCreateInSelection' => $recordClass::ALLOW_CREATE_IN_SELECTION,
			'sortingField' => $recordClass::getDataTypeFieldName( 'DTSteroidSorting' ),
			'customJS' => $recordClass::getCustomJSConfig(),
			'isCore' => $isCore,
			'listOnly' => $recordClass::LIST_ONLY,
			'fieldSets' => $this->getRecordClassFieldSets( $recordClass ),
			'hasListActions' => $this->recordClassHasListActions( $recordClass ),
			'possibleListActionCount' => $this->getPossibleListActionCount( $recordClass ),
			'mayCreate' => $this->userMayCreateRecordClass( $recordClass ),
			'isPubdateRecord' => $recordClass::PUBDATE_RECORD
		);

		if ( !$isCore ) {
			$data[ 'classLocation' ] = $classPath;

			foreach ( $data[ 'formFields' ] as $fieldName => &$fieldDef ) {
				$dtPath = ClassFinder::getClassLocation( $fieldDef[ 'dataType' ] );

				if ( !ST::pathIsCore( $dtPath ) ) {
					$fieldDef[ 'classLocation' ] = $dtPath;
				}
			}

			unset( $fieldDef );
		}

		$conditionalFieldConf = $recordClass::getConditionalFieldConf();

		if ( !empty( $conditionalFieldConf ) ) {
			$data[ 'conditionalFieldConf' ] = $conditionalFieldConf;
		}

		if ( $recordClass::BACKEND_TYPE == Record::BACKEND_TYPE_WIDGET ) {
			$data[ 'widgetType' ] = $recordClass::WIDGET_TYPE;
		}

		return $data;
	}

	protected function getPubDate( $record, $recordId ) {

		$records = $this->storage->selectRecords(
			'RCPubDateEntries',
			array(
				'where' => array(
					'recordType',
					'=',
					array( $record ),
					'AND',
					'elementId',
					'=',
					array( $recordId )
				)
			)
		);

		$this->ajaxSuccess( $records, true );
	}

	protected function userMayCreateRecordClass( $recordClass = NULL ) {
		if ( empty( $recordClass ) ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		$perms = $this->user->getRecordClassPermissionsForDomainGroup( $recordClass );

		return (bool)$perms[ RCPermission::ACTION_PERMISSION_CREATE ];
	}

	protected function getPossibleListActionCount( $recordClass = NULL ) {
		if ( empty( $recordClass ) ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		$count = 0;

		if ( $recordClass::getDataTypeFieldName( 'DTSteroidLive' ) ) { //publish, hide
			$count += 2;
		}

		if ( $recordClass::getDataTypeFieldName( 'DTSteroidPage' ) ) { // preview
			$count++;
		}

		if ( $recordClass == 'RCPage' ) { //copy
			$count++;
		}

		if ( is_subclass_of( $recordClass, 'SyncRecord' ) ) {
			$count++;
		}

		return $count;
	}

	protected function getRecordClassFieldSets( $recordClass = NULL ) {
		if ( empty( $recordClass ) ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		$fieldSets = $recordClass::getFieldSets( $this->storage );

		foreach ( $this->recordClasses as $className => $fileInfo ) {
			if ( $addFieldSet = $className::addToFieldSets( $recordClass ) ) {
				foreach ( $addFieldSet as $fieldSetName => $fields ) {
					if ( !isset( $fieldSets[ $fieldSetName ] ) ) {
						$fieldSets[ $fieldSetName ] = array();
						$fieldSets[ '__addedBy' ][ $fieldSetName ] = $className;
					}

					$fieldSets[ $fieldSetName ] = array_merge( $fieldSets[ $fieldSetName ], $fields );
				}
			}
		}

		return $fieldSets;
	}

	protected static function recordClassHasListActions( $recordClass = NULL ) {
		if ( !$recordClass ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		return $recordClass === 'RCPage' || (bool)$recordClass::getDataTypeFieldName( 'DTSteroidPage' ) || (bool)$recordClass::getDataTypeFieldName( 'DTSteroidLive' ) || is_subclass_of( $recordClass, 'SyncRecord' );
	}

	protected function cleanUpFilterFields( array $filterFields ) { // removes domainGroup and language filters if there's only 1 of them
		foreach ( $filterFields as $fieldName => $fieldConf ) {
			if ( $fieldConf[ 'dataType' ] === 'DTSteroidDomainGroup' ) {
				$totalDomainGroups = $this->storage->select( 'RCDomainGroup', NULL, 0, 0, true );

				if ( $totalDomainGroups === 1 ) {
					unset( $filterFields[ $fieldName ] );
				}
			}

			if ( $fieldConf[ 'dataType' ] === 'DTSteroidLanguage' ) {
				$totalLanguages = $this->storage->select( 'RCLanguage', array( 'where' => array( RCLanguage::getDataTypeFieldName( 'DTSteroidLive' ), '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) ) ), 0, 0, true );

				if ( $totalLanguages === 1 ) {
					unset( $filterFields[ $fieldName ] );
				}
			}
		}

		return $filterFields;
	}

	protected function setModuleData() {
		$this->recordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );
		$permJoins = $this->user->record->{'user:RCDomainGroupLanguagePermissionUser'};

		$perms = array();

		foreach ( $permJoins as $permJoin ) {
			if ( $permJoin->domainGroup == $this->user->getSelectedDomainGroup() && $permJoin->language == $this->user->getSelectedLanguage() ) {
				$perms[ ] = $permJoin->permission;
			}
		}

		$this->setCustomCSS();

		foreach ( $this->recordClasses as $className => $fileInfo ) {
			$mayWrite = false;
			$isDependency = true;
			$restrictToOwn = true;

			foreach ( $perms as $perm ) {
				$permEntityJoins = $perm->getFieldValue( 'permission:RCPermissionPermissionEntity' );
				$permEntities = array();

				foreach ( $permEntityJoins as $permEntityJoin ) {
					$permEntities[ ] = $permEntityJoin->permissionEntity;
				}

				foreach ( $permEntities as $permEntity ) {
					if ( $permEntity->recordClass === $className ) {
						if ( $permEntity->mayWrite ) {
							$mayWrite = true;
						}

						if ( !$permEntity->isDependency ) {
							$isDependency = false;
						}

						if ( !$permEntity->restrictToOwn ) {
							$restrictToOwn = false;
						}
					}
				}
			}


			if ( $moduleData = $this->getModuleData( $className, array( 'mayWrite' => $mayWrite, 'isDependency' => $isDependency, 'restrictToOwn' => $restrictToOwn ) ) ) {
				$this->config[ 'recordClasses' ][ $className::BACKEND_TYPE ][ ] = $moduleData;
			}
		}

		$availableWizards = $this->user->getAvailableWizards();

		foreach ( $availableWizards as $wizard ) {
			$this->config[ 'wizards' ][ ] = $this->getWizardData( $wizard );
		}
	}

	protected function getDefaultLanguage() {
		$defaultLanguage = NULL;

		$languages = $this->user->getSelectableLanguages();


		foreach ( $languages as $language ) {
			if ( $language->iso639 == $this->config[ 'interface' ][ 'languages' ][ 'current' ] ) {
				$defaultLanguage = $language;
			}
		}

		if ( !$defaultLanguage ) {
			foreach ( $languages as $language ) {
				if ( $language->isDefault ) {
					$defaultLanguage = $language;
					break;
				}
			}

			if ( !$defaultLanguage ) {
				if ( $languages ) {
					$defaultLanguage = $languages[ 0 ];
				} else {
					$localPrimary = $this->user->record ? $this->user->record->getFieldValue( Record::FIELDNAME_PRIMARY ) : 'Unknown';
					throw new Exception( 'Unable to determine current language - no permissions for user? Local primary: ' . $localPrimary );
				}
			}
		}

		return $defaultLanguage;
	}

	protected function setUserBEConf() {
		$this->user->record->load();

		$this->setLocalBEConf();

		$selectedDomainGroup = $this->user->getSelectedDomainGroup();

		if(!$selectedDomainGroup){
			$perm = $this->storage->selectFirstRecord( 'RCDomainGroupLanguagePermissionUser', array(
				'where' => array(
					'user',
					'=',
					array( $this->user->record )
				)
			) );

			if($perm === NULL){
				throw new LoginFailException("No permissions for user with primary " . $this->user->record->primary);
			}

			$selectedDomainGroup = $perm->domainGroup;

			$this->user->setSelectedDomainGroup($selectedDomainGroup);
		}

		if ( !$this->user->getSelectedLanguage() ) {
			$defaultLang = $this->getDefaultLanguage(); // might throw exception if user doesn't have any permissions!

			$this->user->setSelectedLanguage( $defaultLang );
		}

		// Already handled in setLocalBEConf
		// $this->config[ 'interface' ][ 'languages' ][ 'current' ] = $this->user->record->backendPreference->language;
		// $this->config[ 'interface' ][ 'themes' ][ 'current' ] = $this->user->record->backendPreference->theme;

		$this->config[ 'system' ][ 'domainGroups' ][ 'current' ] = $selectedDomainGroup->load()->getValues();

		$this->config[ 'system' ][ 'languages' ][ 'current' ] = $this->user->getSelectedLanguage()->load()->getValues();

		$this->setAvailableDomainGroups();
		$this->setAvailableLanguages();
	}

	protected function setAvailableDomainGroups() {
		$domainGroupsAvailable = array();

		$selectableDomainGroups = $this->user->getSelectableDomainGroups();

		// FIXME: array_filter? array_udiff? ...
		// array_search, array_values, array_chunk, array_combine, array_count_values, array_product, array_pad, array_replace, array_reverse,....
		foreach ( $selectableDomainGroups as $domainGroupRecord ) {
			$values = $domainGroupRecord->load()->getValues();

			$values[ 'hasTracking' ] = false;

			$domains = $domainGroupRecord->{'domainGroup:RCDomain'};

			foreach ( $domains as $domainRecord ) {
				if ( !$domainRecord->disableTracking ) {
					$values[ 'hasTracking' ] = true;
					break;
				}
			}

			$domainGroupsAvailable[ ] = $values;
		}

		$this->config[ 'system' ][ 'domainGroups' ][ 'available' ] = $domainGroupsAvailable;
	}

	protected function setAvailableLanguages() {
		$availableLanguages = array();
		$languages = $this->user->getSelectableLanguages();

		// FIXME: array_filter? array_udiff? ...
		// array_search, array_values, array_chunk, array_combine, array_count_values, array_product, array_pad, array_replace, array_reverse,....
		foreach ( $languages as $languageRecord ) {
			$values = $languageRecord->load()->getValues();

			if ( $values[ Record::FIELDNAME_PRIMARY ] == $this->config[ 'system' ][ 'languages' ][ 'current' ][ Record::FIELDNAME_PRIMARY ] ) {
				continue;
			}

			$availableLanguages[ ] = $values;
		}

		$this->config[ 'system' ][ 'languages' ][ 'available' ] = $availableLanguages;
	}

	protected function displayBackend() {
		$this->setLocalBEConf();
		// TODO: gzip support!
		
		// transitional webix flag
		if (isset($_GET['webix'])) {
			require STROOT . '/res/pagetemplates/backend_webix.php';
		} else {
			require STROOT . '/res/pagetemplates/backend.php';
		}	
	}

	protected function ajaxSuccess( $data = NULL, $jsonForceObject = false ) {
		$response = array( 'success' => true );

		if ( $data !== NULL ) {
			$response[ 'data' ] = $data;
		}

		$this->ajaxSend( $response, $jsonForceObject );
	}

	/**
	 * @param Exception
	 *
	 * @return void
	 */
	protected function ajaxFail( Exception $e ) {
		$ret = array(
			'success' => false,
			'error' => get_class( $e ),
			'message' => $e->getMessage()
		);

		if ( $e instanceof SteroidException ) {
			$ret[ 'data' ] = $e->getData();
		}

		$this->ajaxSend( $ret );
	}

	protected function ajaxSend( $reply, $jsonForceObject = false ) {
		$reply = json_encode( $reply, $jsonForceObject ? JSON_FORCE_OBJECT : 0 );

		if ( $this->isIframe ) {
			// make sure charset + content-type is set correctly
			header( 'Content-Type: text/html; charset=utf-8' );

// TODO: gzip support!

			$this->iframeReply = $reply;
			require STROOT . '/res/pagetemplates/iframeSend.php';
		} else {
			header( 'Content-Type: application/json; charset=utf-8' );

// TODO: gzip support!
			echo $reply;
		}
	}

	protected function setUserData() {
		if ( isset( $this->user->record ) ) {
			$this->user->record->load();

			$this->userConfig[ 'config' ] = $this->getRecordConfigAsJson( 'RCUser' );

// TODO: this might fail to work in case user got multiple permissions
			if ( $permissions = $this->user->record->{'user:RCDomainGroupLanguagePermissionUser'} ) {
				$this->userConfig[ 'config' ][ 'permission' ] = $permissions[ 0 ]->permission->title;
			}

			$values = $this->getRecordValuesAsJson( 'RCUser', array( $this->user->record->primary ) );

			$this->userConfig[ 'values' ] = array_shift( $values );

			$this->config[ 'User' ] = $this->userConfig;
		}
	}

	protected function getRecordValuesAsJson( $recordClass = '', array $primaryFields = NULL ) {
		if ( empty( $primaryFields ) || empty( $recordClass ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$primaryFields and $recordClass must be set and exist' );
		}

		$values = array();

		foreach ( $primaryFields as $primary ) {
			$record = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $primary ), Record::TRY_TO_LOAD );

			$record->load();
			$values[ ] = $record->getValues();
		}

		return $values;
	}

	protected function getRecordConfigAsJson( $recordClass = '' ) {
		if ( empty( $recordClass ) || !ClassFinder::find( array( $recordClass ), true ) ) {
			throw new InvalidArgumentException( '$recordClass must be set and exist' );
		}

		return $recordClass::getOwnFieldDefinitions();
	}

	protected function getProfilePage() { //FIXME: remove from core!
		$page = $this->storage->selectFirstRecord( 'RCPage', array( 'where' => array( 'domainGroup', '=', array( $this->user->getSelectedDomainGroup() ), 'AND', 'live', '=', array( DTSteroidLive::LIVE_STATUS_LIVE ), 'AND', 'template.filename', 'LIKE', array( '%profile.php' ) ) ) );

		$this->ajaxSuccess( array(
			'url' => $page->getUrlForPage( $page, false )
		) );
	}
}

class RecordIsLockedException extends Exception {
}

class WarningException extends Exception {
}

class LoginFailException extends Exception {
}

class LogoutFailException extends Exception {
}

class NoChangeException extends Exception {
}

class UnknownRequestException extends Exception {
}

class AffectedReferencesException extends SteroidException {
}

class MissingReferencesException extends AffectedReferencesException {
}

class RecordLimitedToDomainGroupException extends SteroidException {

}

class InvalidPubDateException extends SteroidException {

}
