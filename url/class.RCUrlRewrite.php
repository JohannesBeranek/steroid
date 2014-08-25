<?php
/**
 * @package steroid\url
 */


require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';

require_once STROOT . '/url/class.RCUrl.php';

/**
 * @package steroid\url
 */
class RCUrlRewrite extends Record {

	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getTitleFields(){
		return array('url');
	}

	protected static function getKeys(){
		return array(
			'primary' => DTKey::getFieldDefinition(array( self::FIELDNAME_PRIMARY )),
			'uniqueUrl' => DTKey::getFieldDefinition(array( 'url' ), true)
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true ),
			'rewrite' => DTString::getFieldDefinition( 255 ),
			'url' => DTRecordReference::getFieldDefinition( 'RCUrl', true ),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition()
		);
	}
	
	protected function requireReferences() {
		return true;
	}
}