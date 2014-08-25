<?php 
/**
 * @package steroid\cache
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTMediumText.php';
require_once STROOT . '/datatype/class.DTMTime.php';

/**
 * @package steroid\cache
 */
class RCCache extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys(){
		return array(
			'primary' => DTKey::getFieldDefinition(array( 'key' ))
		);
	}

	protected static function getFieldDefinitions(){
		return array(
			'key' => DTString::getFieldDefinition( 127 ),
			'data' => DTMediumText::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition()
		);
	}
}

?>