<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';

require_once STROOT . '/user/permission/class.RCPermission.php';
require_once STROOT . '/user/permission/class.RCPermissionEntity.php';

/**
 *
 * @package steroid\permission
 *
 */
class RCFieldPermission extends Record {

	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true ),
			'readOnlyFields' => DTText::getFieldDefinition(NULL, true)
		);
	}

	public static function getTitleFields(){
		return array('readOnlyFields');
	}
}
