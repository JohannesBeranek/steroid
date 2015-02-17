<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';

require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/user/permission/class.RCPermission.php';
require_once STROOT . '/user/class.RCUser.php';

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
			'language' => DTSteroidLanguage::getFieldDefinition()
		);
	}

	public static function getEditableFormFields() {
		return array(
			'user',
			'permission',
			'domainGroup',
			'permission:RCPermissionPerPage'
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

}
