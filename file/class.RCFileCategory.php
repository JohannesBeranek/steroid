<?php
/**
 * @package steroid\file
 */
 
require_once STROOT . '/storage/record/class.Record.php';
 
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTInt.php';
 
/**
 * @package steroid\file
 */
class RCFileCategory extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_DEV;
	
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'title' => DTKey::getFieldDefinition( array( 'title' ), true )
		);
	}
	
	protected static function getFieldDefinitions() {
		return array(
			'primary' => DTInt::getFieldDefinition( true, true ),
			'title' => DTString::getFieldDefinition( NULL, false, NULL, false )
		);
	}
	
	// TODO: editable form fields (fileTypeFileCategory)
}
