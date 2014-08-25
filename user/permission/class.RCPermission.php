<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/user/permission/class.DTStaticInlinePermissionEdit.php';
require_once STROOT . '/user/permission/class.RCPermissionPermissionEntity.php';

/**
 *
 * @package steroid\permission
 *
 */
class RCPermission extends Record {

	const ACTION_PERMISSION_CREATE = 'mayCreate';
	const ACTION_PERMISSION_PUBLISH = 'mayPublish';
	const ACTION_PERMISSION_HIDE = 'mayHide';
	const ACTION_PERMISSION_DELETE = 'mayDelete';

	const BACKEND_TYPE = Record::BACKEND_TYPE_ADMIN;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'title' => DTString::getFieldDefinition( 127 ),
			'description' => DTText::getFieldDefinition( NULL, true ),
			'permission:RCPermissionPermissionEntity' => DTStaticInlinePermissionEdit::getFieldDefinition( 'RCPermissionPermissionEntity', true )
		);
	}

	public static function getEditableFormFields() {
		return array(
			'title',
			'description',
			'permission:RCPermissionPermissionEntity'
		);
	}

	public static function getDisplayedFilterFields() {
		return array();
	}
}

?>