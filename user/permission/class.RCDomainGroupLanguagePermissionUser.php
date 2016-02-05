<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTBool.php';

require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/user/permission/class.RCPermission.php';
require_once STROOT . '/user/class.RCUser.php';
require_once STROOT . '/user/class.User.php';

/**
 *
 * @package steroid\permission
 *
 */
class RCDomainGroupLanguagePermissionUser extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_ADMIN;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) ),
			'uniquePermissions' => DTKey::getFieldDefinition( array( 'domainGroup', 'permission', 'user', 'language' ), true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true ),
			'user' => DTRecordReference::getFieldDefinition( 'RCUser', true ),
			'permission' => DTRecordReference::getFieldDefinition( 'RCPermission', true ),
			'domainGroup' => DTRecordReference::getFieldDefinition( 'RCDomainGroup', true ),
			'language' => DTSteroidLanguage::getFieldDefinition(),
			'applyDown' => DTBool::getFieldDefinition()
		);
	}

	public static function getEditableFormFields() {
		return array(
			'user',
			'permission',
			'domainGroup',
			'permission:RCPermissionPerPage',
			'applyDown'
		);
	}

	public static function getDisplayedListFields() {
		return array(
			'user',
			'permission',
			'domainGroup',
			'permission:RCPermissionPerPage'
		);
	}

	public static function getTitleFields() {
		return array(
			'user'
		);
	}

	protected function afterSave( $isUpdate, $isFirst, array $saveResult, array &$savePaths = null ) {
		parent::afterSave($isUpdate, $isFirst, $saveResult, $savePaths);

		if($this->permission->title === User::PERMISSION_TITLE_ADMIN && $this->applyDown && empty( $this->{'permission:RCPermissionPerPage'})){
			//check if domainGroup has children
			$childrenDomainGroups = $this->storage->selectRecords(
				'RCDomainGroup',
				array('where' => array(
					'parent', '=', array($this->domainGroup)
				)));

			foreach ( $childrenDomainGroups as $domainGroup ) {
				$perm = self::get( $this->storage, array(
					'domainGroup' => $domainGroup,
					'user'        => $this->user,
					'language'    => $this->language,
					'permission'  => $this->permission
				), Record::TRY_TO_LOAD );

				//check if child permission exists
				if ( ! $perm->exists() ) {
					$perm->applyDown = $this->applyDown;

					$perm->save();
				}
			}
		}
	}

}
