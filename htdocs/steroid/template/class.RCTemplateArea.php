<?php
/**
 * @package steroid\template
 */
 
require_once STROOT . '/storage/record/class.Record.php';
 
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTTinyInt.php';
require_once STROOT . '/datatype/class.DTBool.php';
require_once STROOT . '/datatype/class.DTString.php';

require_once STROOT . '/area/class.RCArea.php';
require_once STROOT . '/area/class.DTAreaReference.php';
require_once STROOT . '/template/class.RCTemplate.php';

 
/**
 * @package steroid\template
 */
class RCTemplateArea extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) ),
			'un' => DTKey::getFieldDefinition( array( 'template', 'area', self::FIELDNAME_SORTING ), true )
		);
	}
	
	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'template' => DTRecordReference::getFieldDefinition( 'RCTemplate', true ),
			self::FIELDNAME_SORTING => DTSteroidSorting::getFieldDefinition(),
			'area' => DTAreaReference::getFieldDefinition(),
			'columns' => DTTinyInt::getFieldDefinition( true, false, NULL, false ),
			'fixed' => DTBool::getFieldDefinition(),
			'key' => DTString::getFieldDefinition( 127, false, NULL, false )
		);
	}
} 

?>