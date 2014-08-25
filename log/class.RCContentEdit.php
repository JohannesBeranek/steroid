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

require_once STROOT . '/datatype/class.DTDynamicRecordReferenceClass.php';
require_once STROOT . '/datatype/class.DTDynamicContentReferenceInstance.php';


/**
 *
 * @package steroid\log
 *
 */
class RCContentEdit extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	const MAY_FILTER_BY_ME = false;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'recordClass', 'recordPrimary' ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			'ctime' => DTCTime::getFieldDefinition( false, true ),
			'recordClass' => DTDynamicRecordReferenceClass::getFieldDefinition( 'recordPrimary', true ),
			'recordPrimary' => DTDynamicContentReferenceInstance::getFieldDefinition( 'recordClass', true, false, array( self::BACKEND_TYPE_CONTENT, self::BACKEND_TYPE_DEV, self::BACKEND_TYPE_ADMIN, self::BACKEND_TYPE_CONFIG, self::BACKEND_TYPE_EXT_CONTENT, self::BACKEND_TYPE_UTIL ) ),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'lastAliveMessage' => DTDateTime::getFieldDefinition()
		);
	}

	public static function fillForcedPermissions( array &$permissions ) {
		$permissions[ get_called_class() ] = array(
			'mayWrite' => 1,
			'isDependency' => 0,
			'restrictToOwn' => 0
		);
	}
}


?>