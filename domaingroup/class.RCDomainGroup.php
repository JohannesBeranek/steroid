<?php

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTInt.php';

require_once STROOT . '/datatype/class.DTParentReference.php';
require_once STROOT . '/file/class.DTFileRecordReference.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';

require_once STROOT . '/page/class.RCPage.php';

require_once STROOT . '/user/class.User.php';

class RCDomainGroup extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_CONFIG;

	const LIST_MODE_START_HIERARCHIC = false;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'title' => DTString::getFieldDefinition( 127 ),
			'parent' => DTParentReference::getFieldDefinition(),
			'favicon' => DTFileRecordReference::getFieldDefinition( false, array( 'image' ), array( 'image/png', 'image/ico', 'image/x-icon' ) ),
			'notFoundPage' => DTRecordReference::getFieldDefinition( 'RCPage' )
		);
	}

	protected function afterSave( $isUpdate, $isFirst, array $saveResult, array &$savePaths = NULL ) {
		if ( !$isUpdate && $isFirst ) {
			if ( $this->parent ) {
				$this->copyPermissionsFromParent();
			} elseif ( $user = User::getCurrent() ) { // root domainGroup, so only copy the current user's permissions
				$this->copyPermissionsForUser( $user );
			}
		}

		// needs to be called after adding perms as foreign reference, so they get saved as well
		parent::afterSave( $isUpdate, $isFirst, $saveResult, $savePaths );
	}

	protected function copyPermissionsForUser( $user ) {
		$currentPermissions = $this->storage->selectRecords( 'RCDomainGroupLanguagePermissionUser', array( 'fields' => array( 'domainGroup', 'language', 'permission', 'user' ), 'where' => array( 'domainGroup', '=', array( $user->getSelectedDomainGroup() ), 'AND', 'user', '=', array( $user->record ) ) ) );

		$hasPerms = $this->{'domainGroup:RCDomainGroupLanguagePermissionUser'};

		foreach ( $currentPermissions as $perm ) {
			$newPerm = RCDomainGroupLanguagePermissionUser::get( $this->storage, array(
				'domainGroup' => $this,
				'language' => $perm->language,
				'permission' => $perm->permission,
				'user' => $user->record
			), false );

			$hasPerms[ ] = $newPerm;
			// calling $newPerm->save() here leads to infinite recursion, so we let foreign ref save
		}

		$this->{'domainGroup:RCDomainGroupLanguagePermissionUser'} = $hasPerms;
	}

	protected function copyPermissionsFromParent() {
		$userGroups = $this->storage->selectRecords( 'RCPermission', array( 'where' => array( 'title', '=', array( User::PERMISSION_TITLE_MASTER, User::PERMISSION_TITLE_DEV, User::PERMISSION_TITLE_ADMIN ) ) ) );

		$parentPermissions = $this->storage->selectRecords( 'RCDomainGroupLanguagePermissionUser', array( 'fields' => array( 'domainGroup', 'language', 'permission', 'user' ), 'where' => array( 'domainGroup', '=', array( $this->parent ), 'AND', 'permission', '=', $userGroups ) ) );

		$hasPerms = $this->{'domainGroup:RCDomainGroupLanguagePermissionUser'};

		foreach ( $parentPermissions as $perm ) {
			$newPerm = RCDomainGroupLanguagePermissionUser::get( $this->storage, array(
				'domainGroup' => $this,
				'language' => $perm->language,
				'permission' => $perm->permission,
				'user' => $perm->user
			), false );

			$hasPerms[ ] = $newPerm;
			// calling $newPerm->save() here leads to infinite recursion, so we let foreign ref save
		}

		$this->{'domainGroup:RCDomainGroupLanguagePermissionUser'} = $hasPerms;
	}

	public function getPrimaryDomain() {
		$domains = $this->{'domainGroup:RCDomain'};

		foreach ( $domains as $domain ) {
			if ( $domain->returnCode == 200 ) {
				return $domain;
			}
		}

		// fallback
		return array_shift( $domains );
	}

	public function getTitle() {
		return $this->title;
	}

	protected static function getTitleFields() {
		return array( 'title' );
	}

	public static function getDisplayedFilterFields() {
		return array( 'domainGroup:RCDomain' );
	}

	public static function getCustomJSConfig() {
		return array(
			'detailPane'
		);
	}

	public static function fillRequiredPermissions( array &$permissions, $titleOnly = false ) {
		parent::fillRequiredPermissions( $permissions, $titleOnly );

		if ( !isset( $permissions[ 'RCDomain' ] ) ) {
			$permissions[ 'RCDomain' ] = array(
				'mayWrite' => false,
				'isDependency' => true,
				'restrictToOwn' => false
			);
		}
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $isSearchField = false ) {
		parent::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $isSearchField );

		if ( ( is_subclass_of( $mainRecordClass, 'IRecord' ) || $mainRecordClass === NULL ) && $recordClass === get_called_class() && $requestFieldName === NULL && $requestingRecordClass === NULL ) {
			$user = User::getCurrent();

			$queryStruct[ 'where' ] = array(
				self::FIELDNAME_PRIMARY,
				'=',
				array( $user->getSelectedDomainGroup() )
			);
		}
	}
}
