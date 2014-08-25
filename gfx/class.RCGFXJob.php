<?php
/**
 * @package steroid\gfx
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTText.php';

require_once STROOT . '/datatype/class.DTCTime.php';

/**
 * @package steroid\gfx
 */
class RCGFXJob extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	
	
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition(array( 'hash' ))
		);	
	}
	
	protected static function getFieldDefinitions() {
		return array(
			'hash' => DTString::getFieldDefinition( 32, true, NULL, false ),
			'params' => DTText::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'width' => DTInt::getFieldDefinition( true, false, NULL, true ),
			'height' => DTInt::getFieldDefinition( true, false, NULL, true )
		);
	}

	public static function fillForcedPermissions( array &$permissions ) {
		$permissions[get_called_class()] = array(
			'mayWrite' => 1,
			'isDependency' => 0,
			'restrictToOwn' => 0
		);
	}
}

?>