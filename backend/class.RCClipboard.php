<?php
/**
 *
 * @package steroid\log
 */

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTDynamicRecordReferenceClass.php';
require_once STROOT . '/datatype/class.DTDynamicContentReferenceInstance.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';
require_once STROOT . '/storage/record/class.Record.php';

/**
 *
 * @package steroid\log
 *
 */
class RCClipboard extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	const MAY_FILTER_BY_ME = false;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'un' => DTKey::getFieldDefinition( array( 'recordClass', 'recordPrimary', 'creator' ), true, true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'ctime' => DTCTime::getFieldDefinition(),
			'recordClass' => DTDynamicRecordReferenceClass::getFieldDefinition( 'recordPrimary', true ),
			'recordPrimary' => DTDynamicContentReferenceInstance::getFieldDefinition( 'recordClass', true, false, array( self::BACKEND_TYPE_WIDGET, self::BACKEND_TYPE_CONTENT ) ),
			'creator' => DTSteroidCreator::getFieldDefinition()
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

