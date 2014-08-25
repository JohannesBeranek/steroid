<?php
/**
*
* @package steroid\db
*/
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/url/class.DTUrlRecordReference.php'; 

require_once STROOT . '/page/class.RCPage.php';
require_once STROOT . '/url/class.RCUrl.php';

/**
 * 
 * @package steroid\db
 *
 */
class RCPageUrl extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition(array( 'page', 'url' )),
			'url' => DTKey::getFieldDefinition(array('url'), true)
		);
	}
	
	protected static function getFieldDefinitions() {
		return array(
			'page' => DTRecordReference::getFieldDefinition( 'RCPage', true ),
			'url' => DTUrlRecordReference::getFieldDefinition()
		);
	}
}