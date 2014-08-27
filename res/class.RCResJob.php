<?php
/**
 * @package steroid\res
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTText.php';

/**
 * @package steroid\res
 */
class RCResJob extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'hash' ) )
		);	
	}
	
	protected static function getFieldDefinitions() {
		return array(
			'hash' => DTString::getFieldDefinition( 32, true, NULL, false ),
			'files' => DTText::getFieldDefinition(),
			'type' => DTString::getFieldDefinition(127)
		);
	}

	public static function fillForcedPermissions( array &$permissions ) {
		$permissions[__CLASS__] = array(
			'mayWrite' => 1,
			'isDependency' => 0,
			'restrictToOwn' => 0
		);
	}
}

?>