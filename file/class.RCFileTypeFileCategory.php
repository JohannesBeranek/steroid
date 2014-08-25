<?php
/**
 * @package steroid\file
 */
 
require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';

require_once __DIR__ . '/class.RCFileType.php';
require_once __DIR__ . '/class.RCFileCategory.php';
 
/**
 * @package steroid\file
 */

class RCFileTypeFileCategory extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'fileType', 'fileCategory' ) )
		);
	}
	
	protected static function getFieldDefinitions() {
		return array(
			'fileType' => DTRecordReference::getFieldDefinition ( 'RCFileType', true ),
			'fileCategory' => DTRecordReference::getFieldDefinition( 'RCFileCategory', true )
		);
	}
}
