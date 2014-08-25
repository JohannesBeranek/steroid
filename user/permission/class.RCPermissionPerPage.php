<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';

/**
 *
 * @package steroid\permission
 *
 */
class RCPermissionPerPage extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'permission', 'page' ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			'permission' => DTRecordReference::getFieldDefinition( 'RCDomainGroupLanguagePermissionUser', true ),
			'page' => DTRecordReference::getFieldDefinition( 'RCPage', true )
		);
	}

	public static function getTitleFields() {
		return array(
			'page'
		);
	}

}

?>