<?php

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTKey.php';

class RTTestForeign extends Record {
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'primary' ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false )
		);
	}
}