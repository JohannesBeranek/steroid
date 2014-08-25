<?php
/**
 *
 * @package steroid\log
 */

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTText.php';
require_once STROOT . '/datatype/class.DTMediumText.php';

/**
 *
 * @package steroid\log
 *
 */
class RCLog extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'ctime' => DTCTime::getFieldDefinition(),
			'hash' => DTString::getFieldDefinition( 32, true, NULL, false ),
			'context' => DTText::getFieldDefinition(),
			'formatted' => DTMediumText::getFieldDefinition(),
			'requestID' => DTString::getFieldDefinition( 8, true, NULL, false )
		);
	}

	public static function getTitleField() {
		return array( 'formatted' );
	}

	protected static function getEditableFormFields() {
		return array_keys( static::getOwnFieldDefinitions() );
	}

	public static function fillForcedPermissions( array &$permissions ) {
		$permissions[ get_called_class() ] = array(
			'mayWrite' => 1,
			'isDependency' => 0,
			'restrictToOwn' => 0
		);
	}
}