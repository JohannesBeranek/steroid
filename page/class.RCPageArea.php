<?php
/**
 * @package steroid\page
 */
 
require_once STROOT . '/storage/record/class.Record.php';
 
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTTinyInt.php';
require_once STROOT . '/datatype/class.DTBool.php';
require_once STROOT . '/datatype/class.DTString.php';

require_once STROOT . '/area/class.RCArea.php';
require_once STROOT . '/area/class.DTAreaReference.php';
require_once STROOT . '/page/class.RCPage.php';

 
/**
 * @package steroid\page
 */
class RCPageArea extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'un' => DTKey::getFieldDefinition( array( 'page', 'area', Record::FIELDNAME_SORTING ), true ),
			'area' => DTKey::getFieldDefinition( array( 'area' ), false )
		);
	}
	
	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'page' => DTRecordReference::getFieldDefinition( 'RCPage', true ),
			Record::FIELDNAME_SORTING => DTSteroidSorting::getFieldDefinition(),
			'area' => DTAreaReference::getFieldDefinition( 'RCArea', true ),
			'columns' => DTTinyInt::getFieldDefinition( true, false, NULL, false ),
			'fixed' => DTBool::getFieldDefinition(),
			'key' => DTString::getFieldDefinition( 127, false, NULL, false )
		);
	}
} 