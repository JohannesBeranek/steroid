<?php

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTInt.php';

require_once __DIR__ . '/class.RCFileCategory.php';

class RCFileType extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getTitleFields(){
		return array('mimeCategory', 'mimeType');
	}

	protected static function getKeys(){
		return array(
			'primary' => DTKey::getFieldDefinition(array(Record::FIELDNAME_PRIMARY)),
			'mime' => DTKey::getFieldDefinition(array('mimeCategory', 'mimeType'), true)
		);
	}

	protected static function getFieldDefinitions(){
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'mimeCategory' => DTString::getFieldDefinition(127),
			'mimeType' => DTString::getFieldDefinition(127)
		);
	}
}

?>
