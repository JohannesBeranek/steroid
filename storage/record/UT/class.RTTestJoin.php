<?php

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTKey.php';

class RTTestJoin extends Record {
	protected static function getKeys() {
		return array(
			'uni' => DTKey::getFieldDefinition(array('testRec', 'foreignRec'), true)
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			'testRec' => DTRecordReference::getFieldDefinition('RTTest', true),
			'foreignRec' => DTRecordReference::getFieldDefinition('RTTestForeign', true)
		);
	}
}