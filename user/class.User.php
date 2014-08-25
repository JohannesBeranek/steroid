<?php

require_once STROOT . '/request/interface.IRequestInfo.php';
require_once __DIR__ . '/interface.IUserAuthentication.php';
require_once STROOT . '/util/class.StringFilter.php';
require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/user/class.RCUser.php';
require_once STROOT . '/user/permission/class.RCDomainGroupLanguagePermissionUser.php';

require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/user/class.RCBackendPreferenceUser.php';

require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/language/class.RCLanguage.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';

class User {
	/** @var boolean */
	protected $authenticated;

	/** @var boolean */
	protected $new;

	/** @var RCUser */
	protected $record;
	protected $session;

	protected $lastRecord;

	/** @var IRBStorage */
	protected $storage;

	/** @var Config */
	protected $conf;

	protected $authException;
	protected $logoutException;

	protected $authenticator;

	protected static $currentUser;

	// Backend
	protected $backendPreference;
	protected $permissions;

	protected $selected;

	protected $domainGroups;
	protected $languages;

	protected $permsInDomainGroup;
	protected $availableRecordClasses;
	protected $availableWizards;


	const PARAM_LOGIN = 'login';
	const PARAM_LOGOUT = 'logout';

	// must match fieldNames in $backendPreference
	const SELECTED_DOMAIN_GROUP = 'selectedDomainGroup';
	const SELECTED_LANGUAGE = 'selectedLanguage';

	const PERMISSION_TITLE_DEV = '__dev__';
	const PERMISSION_TITLE_ADMIN = '__admin__';
	const PERMISSION_TITLE_MASTER = '__master__';

	const CLI_USERNAME = '__cli__';

	const DEFAULT_LANGUAGE = 'en';

	public static $permissionPriority = array(
		self::PERMISSION_TITLE_ADMIN,
		self::PERMISSION_TITLE_MASTER,
		self::PERMISSION_TITLE_DEV
	);

	public function __construct( Config $conf, IRBStorage $storage ) {
		$this->authenticated = false;
		$this->storage = $storage;
		$this->conf = $conf;

		$this->selected = array();
	}

	public static function init( Config $conf, IRequestInfo $requestInfo, IRBStorage $storage ) {
		static::initSession( $requestInfo );

		$user = new User( $conf, $storage );

		// login param needs to be allowed in GET as well for external auth with redirect url to work
		$loginVal = $requestInfo->getPGParam( self::PARAM_LOGIN );

		if ( $loginVal !== NULL && is_string( $loginVal ) ) {
			static::auth( $loginVal, $user, $conf, $requestInfo, $storage );
		}

		$user->loadFromSession();

		// check for logout
		if ( $user->authenticated && $requestInfo->getGPParam( self::PARAM_LOGOUT ) ) {
			$user->logout();

			static::killSession();
		}

		static::$currentUser = $user;
	}

	public static function auth( $loginVal, User $user, Config $conf, IRequestInfo $requestInfo, IRBStorage $storage ) {
		$authenticatorClass = StringFilter::filterClassName( $loginVal );

		if ( $authenticator = static::getAuthenticator( $authenticatorClass, $conf, $storage ) ) {

			$user->authenticate( $authenticator, $requestInfo );

			return static::createSessionAfterAuth( $user, $authenticatorClass );
		}

		return false;
	}

	public static function createSessionAfterAuth( User $user, $authenticatorClass ) {
		if ( !isset( $user->authException ) ) {
			static::createNewSession();

			if ( $user->record ) {
				static::setSessionData( 'user_primary', $user->record->{Record::FIELDNAME_PRIMARY} );
			}

			static::setSessionData( 'user_auth', $authenticatorClass );

			return true;
		}

		return false;
	}

	public function getRecordActionsForDomainGroup( $recordClass, $record ) {
		if ( !$record instanceof IRecord ) {
			$pk = $recordClass::getPrimaryKeyFields();
			$identity = array();

			foreach ( $pk as $fieldName ) {
				if ( is_array( $record[ $fieldName ] ) ) {
					if ( isset( $record[ $fieldName ][ Record::FIELDNAME_PRIMARY ] ) ) {
						$identity[ $fieldName ] = $record[ $fieldName ][ Record::FIELDNAME_PRIMARY ];
					} else {
						throw new LogicException( 'Cannot construct record from array' );
					}
				} else {
					$identity[ $fieldName ] = $record[ $fieldName ];
				}
			}

			if ( $recordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) && isset( $record[ Record::FIELDNAME_PRIMARY ] ) ) {
				$identity[ Record::FIELDNAME_PRIMARY ] = $record[ Record::FIELDNAME_PRIMARY ];
			}

			$record = $recordClass::get( $this->storage, $identity, Record::TRY_TO_LOAD );
		}

		$domainGroup = $this->getSelectedDomainGroup()->primary;

		if ( $record->exists() && $domainGroupField = $recordClass::getDataTypeFieldName( 'DTSteroidDomainGroup' ) ) {
			$domainGroup = $record->{$domainGroupField};
		}

		$recordClassPermissions = $this->getRecordClassPermissionsForDomainGroup( $recordClass, $domainGroup );

		$mayWrite = $recordClassPermissions && $recordClassPermissions[ 'mayWrite' ] ? $recordClassPermissions[ 'mayWrite' ] : false;
		$mayPublish = $recordClassPermissions && $recordClassPermissions[ RCPermission::ACTION_PERMISSION_PUBLISH ] ? $recordClassPermissions[ RCPermission::ACTION_PERMISSION_PUBLISH ] : false;
		$mayDelete = $recordClassPermissions && $recordClassPermissions[ RCPermission::ACTION_PERMISSION_DELETE ] ? $recordClassPermissions[ RCPermission::ACTION_PERMISSION_DELETE ] : false;
		$mayHide = $recordClassPermissions && $recordClassPermissions[ RCPermission::ACTION_PERMISSION_HIDE ] ? $recordClassPermissions[ RCPermission::ACTION_PERMISSION_HIDE ] : false;
		$mayCreate = $recordClassPermissions && $recordClassPermissions[ RCPermission::ACTION_PERMISSION_CREATE ] ? $recordClassPermissions[ RCPermission::ACTION_PERMISSION_CREATE ] : false;

		$mayWrite = $record->modifyMayWrite( $mayWrite, $this );

		return $recordClass::getAvailableActions( $mayWrite, $mayPublish, $mayHide, $mayDelete, $mayCreate );
	}

	public function getRecordClassPermissionsForDomainGroup( $recordClass, $domainGroup = NULL ) {
		if ( !$domainGroup ) {
			$domainGroup = $this->getSelectedDomainGroup();
		}

		$currentPerms = $this->getPermissionsForDomainGroup( $domainGroup );

		$perms = $this->storage->select( 'RCPermissionEntity', array( 'fields' => '*', 'where' => array( 'permissionEntity:RCPermissionPermissionEntity.permission', '=', $currentPerms, 'AND', 'recordClass', '=', array( $recordClass ) ) ) );

		if ( count( $perms ) < 2 ) {
			return array_shift( $perms );
		}

		$combinedPerms = array_shift( $perms );

		foreach ( $perms as $perm ) {
			$combinedPerms[ 'mayWrite' ] |= (bool)$perm[ 'mayWrite' ];
			$combinedPerms[ RCPermission::ACTION_PERMISSION_PUBLISH ] |= (bool)$perm[ RCPermission::ACTION_PERMISSION_PUBLISH ];
			$combinedPerms[ RCPermission::ACTION_PERMISSION_DELETE ] |= (bool)$perm[ RCPermission::ACTION_PERMISSION_DELETE ];
			$combinedPerms[ RCPermission::ACTION_PERMISSION_HIDE ] |= (bool)$perm[ RCPermission::ACTION_PERMISSION_HIDE ];
			$combinedPerms[ RCPermission::ACTION_PERMISSION_CREATE ] |= (bool)$perm[ RCPermission::ACTION_PERMISSION_CREATE ];
			$combinedPerms[ 'restrictToOwn' ] &= (bool)$perm[ 'restrictToOwn' ];
			$combinedPerms[ 'isDependency' ] &= (bool)$perm[ 'isDependency' ];
		}

		return $combinedPerms;
	}

	public function getPermissionsForDomainGroup( $domainGroup ) {
		return $this->storage->selectRecords(
			'RCPermission', array(
				'fields' => '*',
				'vals' => array( $this->record, $this->getSelectedLanguage(), $domainGroup ),
				'name' => 'User_currentPermissions',
				'join' => array(
					'permission:RCDomainGroupLanguagePermissionUser' => array(
						'where' => array(
							'user', '=', '%1$s', 'AND',
							'language', '=', '%2$s', 'AND',
							'domainGroup', '=', '%3$s'
						)
					)
				)
			)
		);
	}

	public function loadFromSession() {
		if ( static::sessionDataIsSet( 'user_auth' ) ) {
			$this->authenticator = static::getAuthenticator( static::getSessionData( 'user_auth' ), $this->conf, $this->storage );
			$this->authenticator->initWithData( self::getSessionData( 'data' ) );

			if ( !$this->authenticated ) {
				$this->authenticated = true;

				if ( static::sessionDataIsSet( 'user_primary' ) ) {
					$this->record = $this->storage->selectFirstRecord( 'RCUser', array(
						'fields' => array( '*', 'backendPreference' => array( 'fields' => '*' ) ),
						'where' => array( Record::FIELDNAME_PRIMARY, '=', '%1$s' ),
						'vals' => array( intval( static::getSessionData( 'user_primary' ) ) ),
						'name' => 'User_loadFromSession'
					) );

				}
			}
		}
	}

	/**
	 * Only logs out the user object, does not kill the session!
	 */
	public function logout() {
		try {
			if ( $this->authenticator ) {
				$this->authenticator->deauth( static::getSessionData( 'data' ) );
			}

			$this->authenticated = false;
			$this->lastRecord = $this->record;
			$this->record = NULL;
		} catch ( Exception $e ) {
			$this->logoutException = $e;
		}
	}

	public function logoutAndKillSession() {
		$this->logout();
		static::killSession();
	}

	final public static function getAuthenticator( $authenticatorClass, Config $conf, IStorage $storage ) {
		$authenticatorList = $conf->getSection( 'authenticator' );

		if ( array_key_exists( $authenticatorClass, $authenticatorList ) ) {
			$authenticatorFilename = $authenticatorList[ $authenticatorClass ];

			require_once WEBROOT . '/' . $authenticatorFilename;

			$authenticator = new $authenticatorClass();
			$authenticator->setConfig( $conf );
			$authenticator->setStorage( $storage );

			return $authenticator;
		}

		return NULL;
	}

	public static function setCLIUser( $storage, RCLanguage $language = NULL, RCDomainGroup $domainGroup = NULL ) {

		static::$currentUser = new CLIUser( $storage, $language, $domainGroup );
	}

	/**
	 * Get current user
	 *
	 * @return User
	 */
	public static function getCurrent() {
		return static::$currentUser;
	}


	public function __isset( $name ) {
		switch ( $name ) {
			case 'authenticated':
				return isset( $this->authenticated );
			case 'record':
				return isset( $this->record );
			case 'authException':
				return isset( $this->authException );
			case 'logoutException':
				return isset( $this->logoutException );
		}

		throw new InvalidArgumentException();
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'authenticated':
				return $this->authenticated;
			case 'record':
				return $this->record;
			case 'lastRecord':
				return $this->lastRecord;
			case 'authException':
				return $this->authException;
			case 'logoutException':
				return $this->logoutException;
			case 'new':
				return $this->new; // bool | null
		}

		throw new InvalidArgumentException();
	}

	public function authenticate( IUserAuthentication $authenticator, IRequestInfo $requestInfo ) {
		try {
			$authReturn = $authenticator->auth( $requestInfo );

			$this->authenticateWithData( $authReturn );

			return true;
		} catch ( Exception $e ) {
			$this->authException = $e;
		}

		return false;
	}

	public function authenticateWithData( $authReturn ) {
		try {
			static::setSessionData( 'data', $authReturn[ 'data' ] );
			$this->record = $authReturn[ 'record' ];

			$this->authenticated = true;

			$this->new = !empty( $authReturn[ 'new' ] );

			return true;
		} catch ( Exception $e ) {
			$this->authException = $e;
		}

		return false;
	}

	protected function getCSRFBase() {
		if ( !isset( $_SESSION[ 'CSRFBase' ] ) ) {
			$_SESSION[ 'CSRFBase' ] = session_id();
		}

		return $_SESSION[ 'CSRFBase' ];
	}

	protected function getCSRFTokenInput() {
		$id = $this->getCSRFBase();

		$input = '';

		// only use half of the session id, so in case anyone gets behind our algorithm, 
		// they can't esily predict session ids or hijack the session
		// we should still get enough bits of entropy to be secure enough
		for ( $n = 0, $len = strlen( $id ); $n < $len; $n += 2 ) {
			$input .= $id[ $n ];
		}

		return $input;
	}

	public function getCSRFToken() {
		$input = $this->getCSRFTokenInput();

		$rounds = 7;

		$salt = "";
		$salt_chars = array_merge( range( 'A', 'Z' ), range( 'a', 'z' ), range( 0, 9 ) );

		for ( $i = 0; $i < 22; $i++ ) {
			$salt .= $salt_chars[ array_rand( $salt_chars ) ];
		}

		return crypt( $input, sprintf( '$2a$%02d$', $rounds ) . $salt );
	}

	public function validateCSRFToken( $token ) {
		if ( empty( $token ) || crypt( $this->getCSRFTokenInput(), $token ) !== $token ) {
			throw new SecurityException( 'CSRF token mismatch, possible CSRF attack attempt' );
		}
	}

// Session functions from here on - can be "overriden" in subclasses (e.g. for unittesting, different session storage, ...)
	protected static function createNewSession() {
		session_regenerate_id( true ); // automatically copies over old data
	}

	protected static function initSession( IRequestInfo $requestInfo ) {
		session_name( 'sid' );
		session_set_cookie_params( 0, '/', '', $requestInfo->isHTTPS(), true );
		session_start();
	}

	public static function setSessionData( $key, $data ) {
		$_SESSION[ $key ] = $data;
	}

	public static function getSessionData( $key ) {
		return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : NULL;
	}

	public static function sessionDataIsSet( $key ) {
		return isset( $_SESSION[ $key ] );
	}

	public static function unsetSessionData( $key ) {
		unset( $_SESSION[ $key ] );
	}

	protected static function killSession() {
		$_SESSION = array(); // delete all session variables according to php manual

		if ( ini_get( "session.use_cookies" ) ) {
			$params = session_get_cookie_params();
			setcookie( session_name(), '', time() - 42000, $params[ "path" ], $params[ "domain" ], $params[ "secure" ], $params[ "httponly" ] );
		}

		session_destroy(); // delete session data
	}

// ----------- Functions requiring authenticated backend user	
	public function getSelectableDomainGroups() {
		if ( $this->domainGroups === NULL ) {
			// changed this so intermediate results (RCDomainGroupLanguagePermissionUser) aren't instantiated as records
			$domainGroups = $this->storage->fetchAll( 'SELECT t0.* FROM rc_domain_group t0 INNER JOIN rc_domain_group_language_permission_user t1 ON t1.domainGroup_primary = t0.primary INNER JOIN rc_user t2 ON t2.primary = t1.user_primary WHERE t2.primary = ' . $this->record->primary . ' GROUP BY t0.primary' );

			foreach ( $domainGroups as $key => $domainGroup ) {
				$domainGroups[ $key ] = RCDomainGroup::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $domainGroup[ Record::FIELDNAME_PRIMARY ] ), true );
			}

			$this->domainGroups = $domainGroups;
		}

		return $this->domainGroups;
	}

	public function getCurrentPermissions( $domainGroup = NULL ) {
		if ( !$domainGroup ) {
			$domainGroup = $this->getSelectedDomainGroup();
		}

		if ( $this->permissions === NULL ) {
			$this->permissions = $this->storage->selectRecords(
				'RCPermission', array(
					'fields' => '*',
					'vals' => array( $this->record, $this->getSelectedLanguage(), $domainGroup ),
					'name' => 'User_currentPermissions',
					'join' => array(
						'permission:RCDomainGroupLanguagePermissionUser' => array(
							'where' => array(
								'user', '=', '%1$s', 'AND',
								'language', '=', '%2$s', 'AND',
								'domainGroup', '=', '%3$s'
							)
						)
					)
				)
			);
		}

		return $this->permissions;
	}

	public function isDev( $domainGroup ) {
		$permissions = $this->getPermissionsForDomainGroup( $domainGroup );

		foreach ( $permissions as $permission ) {
			if ( $permission->title === self::PERMISSION_TITLE_DEV ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * returns associative array of available record classes in currently selected domainGroup
	 */
	public function getAvailableRecordClasses() {
		if ( $this->availableRecordClasses === NULL ) {
			$this->availableRecordClasses = array();

			$permissionEntities = $this->storage->select( 'RCPermissionEntity', array( 'fields' => '*', 'where' => array( 'permissionEntity:RCPermissionPermissionEntity.permission', '=', $this->getCurrentPermissions() ) ) );

			foreach ( $permissionEntities as $permissionEntity ) {
				$recordClass = $permissionEntity[ 'recordClass' ];

				if ( !isset( $this->availableRecordClasses[ $recordClass ] ) ) {
					$this->availableRecordClasses[ $recordClass ] = array();

					$this->availableRecordClasses[ $recordClass ][ 'mayWrite' ] = (bool)$permissionEntity[ 'mayWrite' ];
					$this->availableRecordClasses[ $recordClass ][ 'restrictToOwn' ] = (bool)$permissionEntity[ 'restrictToOwn' ];
					$this->availableRecordClasses[ $recordClass ][ 'isDependency' ] = (bool)$permissionEntity[ 'isDependency' ];
				} else {
					$this->availableRecordClasses[ $recordClass ][ 'mayWrite' ] |= (bool)$permissionEntity[ 'mayWrite' ];
					$this->availableRecordClasses[ $recordClass ][ 'restrictToOwn' ] &= (bool)$permissionEntity[ 'restrictToOwn' ];
					$this->availableRecordClasses[ $recordClass ][ 'isDependency' ] &= (bool)$permissionEntity[ 'isDependency' ];
				}
			}
		}

		return $this->availableRecordClasses;
	}

	/**
	 * returns associative array of available wizards in currently selected domainGroup
	 */
	public function getAvailableWizards() {
		$wizards = ClassFinder::getAll( ClassFinder::CLASSTYPE_WIZARD, true );

		$availableWizards = array();

		foreach ( $wizards as $wizard => $classInfo ) {
			if ( $wizard::hasPermission( $this ) ) {
				$availableWizards[ ] = $wizard;
			}
		}

		return $availableWizards;
	}

	protected function getSelected( $type ) {
		if ( !array_key_exists( $type, $this->selected ) ) {
			if ( self::sessionDataIsSet( $type ) ) {
				$itemPrimary = (int)self::getSessionData( $type );

				switch ( $type ) {
					case self::SELECTED_LANGUAGE:
						$item = $this->storage->selectFirstRecord( 'RCLanguage', array( 'where' => array( Record::FIELDNAME_PRIMARY, '=', array( $itemPrimary ) ) ) );
						break;
					case self::SELECTED_DOMAIN_GROUP:
						$item = $this->storage->selectFirstRecord( 'RCDomainGroup', array( 'where' => array( Record::FIELDNAME_PRIMARY, '=', array( $itemPrimary ) ) ) );
						break;
					default:
						return NULL;
				}

				$this->setSelected( $type, $item );
			} elseif ( $saved = $this->getBackendPreference()->{$type} ) {
				$this->setSelected( $type, $saved );
			} else {
				$this->selected[ $type ] = NULL;
			}
		}

		return $this->selected[ $type ];
	}

	public function getSelectedLanguage() {
		return $this->getSelected( self::SELECTED_LANGUAGE );
	}

	public function getSelectedDomainGroup() {
		return $this->getSelected( self::SELECTED_DOMAIN_GROUP );
	}

	public function getSelectableLanguages() {
		if ( $this->languages === NULL ) {
			$this->languages = $this->storage->selectRecords( 'RCLanguage', array( 'where' => array( 'language:RCDomainGroupLanguagePermissionUser.user', '=', array( $this->record ), 'AND', 'live', '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) ) ) );
		}

		return $this->languages;
	}


	protected function _setSelected( $type, $item ) {
		$this->selected[ $type ] = $item;

		self::setSessionData( $type, $item->{Record::FIELDNAME_PRIMARY} );

		// these might be dependent on $type
		$this->permsInDomainGroup = NULL;
		$this->availableRecordClasses = NULL;
	}

	protected function setSelected( $type, $item ) {
		$this->_setSelected( $type, $item );

		$backendPreference = $this->getBackendPreference();

		if ( ( isset( $backendPreference->{$type} ) || $backendPreference->exists() ) && $backendPreference->{$type} === $item ) {
			return;
		}

		$backendPreference->{$type} = $item;
		$backendPreference->save();
	}

	public function setSelectedDomainGroup( RCDomainGroup $domainGroup ) {
		$this->setSelected( self::SELECTED_DOMAIN_GROUP, $domainGroup );
	}

	public function setSelectedLanguage( RCLanguage $language ) {
		$this->setSelected( self::SELECTED_LANGUAGE, $language );
	}

	protected function getBackendPreference() {
		if ( $this->backendPreference === NULL ) {

			if ( $this->record->backendPreference === NULL ) { // just create a new record
				$this->record->backendPreference = RCBackendPreferenceUser::get( $this->storage, array( 'language' => self::DEFAULT_LANGUAGE ), false );

				if ( !$this->record->backendPreference->exists() ) {
					$this->record->backendPreference->save();
				}
			}

			$this->backendPreference = $this->record->backendPreference;
		}

		return $this->backendPreference;
	}


	/**
	 * Load user backendPreference into session
	 *
	 * should be called upon backend login
	 */
	public function loadUserPreferences() {
		static $selectableUserPreferences = array( self::SELECTED_DOMAIN_GROUP, self::SELECTED_LANGUAGE );

		$backendPreference = $this->getBackendPreference();

		if ( $backendPreference ) {
			foreach ( $selectableUserPreferences as $selectableUserPreference ) {
				if ( $backendPreference->{$selectableUserPreference} ) {
					$this->_setSelected( $selectableUserPreference, $backendPreference->{$selectableUserPreference} );
				}
			}
		}
	}

	public static function getCLIUserRecord( $storage ) {
		static $record;

		if ( $record === NULL ) {
			$record = $storage->selectFirstRecord( 'RCUser', array( 'fields' => '*', 'where' => array( 'username', '=', array( self::CLI_USERNAME ) ) ) );

			if ( !$record ) {
				$devPermRecord = $storage->selectFirstRecord( 'RCPermission', array( 'where' => array( 'title', '=', array( self::PERMISSION_TITLE_DEV ) ) ) );

				if ( !$devPermRecord ) {
					throw new Exception( 'Must first create dev permission to be able to use cli user' );
				}

				$record = RCUser::get( $storage, array( 'username' => self::CLI_USERNAME ), false );

				// FIXME: problematic in live frontend, need to disable filter first!
				$languages = $storage->selectRecords( 'RCLanguage', array( 'where' => array( 'live', '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) ) ) );
				$domainGroups = $storage->selectRecords( 'RCDomainGroup' );

				$perms = array();

				foreach ( $languages as $language ) {
					foreach ( $domainGroups as $domainGroup ) {
						$perms[ ] = RCDomainGroupLanguagePermissionUser::get( $storage, array( 'permission' => $devPermRecord, 'language' => $language, 'domainGroup' => $domainGroup ), false );
					}
				}

				$record->{'user:RCDomainGroupLanguagePermissionUser'} = $perms;
				$record->save();
			}
		}

		return $record;
	}

	public function getAllowedParentPages( $domainGroup = NULL, $language = NULL ) {
		if ( $domainGroup && !$domainGroup instanceof RCDomainGroup ) {
			throw new InvalidArgumentException( '$domainGroup must be instance of RCDomainGroup' );
		}

		$domainGroup = $domainGroup ? : $this->getSelectedDomainGroup();
		$language = $language ? : $this->getSelectedLanguage();

		$allowedParentPages = array();

		$perms = $this->storage->select( 'RCDomainGroupLanguagePermissionUser', array( 'fields' => array( '*', 'permission:RCPermissionPerPage' ), 'where' => array(
			'domainGroup', '=', array( $domainGroup ),
			'AND', 'language', '=', array( $language ),
			'AND', 'user', '=', array( $this->record )
		) ) );

		foreach ( $perms as $perm ) {
			$permPerPage = $perm[ 'permission:RCPermissionPerPage' ];

			foreach ( $permPerPage as $page ) {
				$allowedParentPages[ ] = RCPage::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $page[ 'page' ] ), true );
			}
		}

		return $allowedParentPages;
	}

	public function getHighestPermissionIndexByDomainGroupLanguage( IRecord $domainGroup, IRecord $language ) {
		$permissionJoins = $this->record->{'user:RCDomainGroupLanguagePermissionUser'};

		$highestPermission = -1;

		foreach ( $permissionJoins as $permissionJoin ) {
			$permissionIndex = array_search( $permissionJoin->permission->title, static::$permissionPriority );

			if ( $permissionIndex !== false && $permissionIndex > $highestPermission ) {
				$highestPermission = $permissionIndex;
			}
		}

		return $highestPermission;
	}

	/* Helper for frontend-data */
	/* calculate country of Austrian user if possible */
	static public function zip2country( $zip ) {
		$zip = intval( $zip );
		if ( $zip >= 1000 && $zip <= 9999 ) {
			$data = array(
				'^1[0-2].*$' => 'Wien',
				'^[2-3].*|^4300|^4303|^4392|^4431|^4432|^4441|^4482|^1300$' => 'Niederösterreich',
				'^4.*$' => 'Oberösterreich',
				'^5.*$' => 'Salzburg',
				'^6[7-9]..$' => 'Vorarlberg',
				'^6[0-6]..$' => 'Tirol',
				'^7.*$' => 'Burgenland',
				'^8.*$' => 'Steiermark',
				'^9.*$' => 'Kärnten',
			);
			foreach ( $data as $pattern => $country ) {
				if ( preg_match( '/' . $pattern . '/', strval( $zip ) ) ) {
					return $country;
				}
			}
		}
		return false;
	}

	public static function getUsersByDomainGroup( RBStorage $storage, RCDomainGroup $domainGroup ) {
		$users = $storage->selectRecords( 'RCUser', array(
			'where' => array(
				'user:RCDomainGroupLanguagePermissionUser.domainGroup', '=', array( $domainGroup ),
				'AND', 'is_backendAllowed', '=', array( 1 )
			)
		) );

		return $users;
	}

	public static function getBackendUsers( RBStorage $storage ) {
		return $storage->selectRecords( 'RCUser', array( 'where' => array( 'is_backendAllowed', '=', array( 1 ) ) ) );
	}
}

class CLIUser extends User {
	public function __construct( IRBStorage $storage, RCLanguage $language = NULL, RCDomainGroup $domainGroup = NULL ) {
		parent::__construct( Config::get( 'localconf' ), $storage );

		$this->record = User::getCLIUserRecord( $this->storage );

		if ( $language !== NULL ) {
			$this->setSelectedLanguage( $language );
		}

		if ( $domainGroup !== NULL ) {
			$this->setSelectedDomainGroup( $domainGroup );
		}
	}
}


class AccessDeniedException extends SteroidException {
}

class ActionDeniedException extends AccessDeniedException {

}

class RecordActionDeniedException extends ActionDeniedException {

}

?>