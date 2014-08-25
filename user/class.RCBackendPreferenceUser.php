<?php
/**
 * @package steroid\user
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTJSON.php';
require_once STROOT . '/datatype/class.DTKey.php';

require_once STROOT . '/user/class.RCUser.php';
require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/language/class.RCLanguage.php';

/**
 * @package steroid\user
 */
class RCBackendPreferenceUser extends Record {

	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'language' => DTString::getFieldDefinition( 2, true, 'en', false ),
			'theme' => DTString::getFieldDefinition( NULL, NULL, NULL, true ),
			'selectedDomainGroup' => DTRecordReference::getFieldDefinition( 'RCDomainGroup' ),
			'selectedLanguage' => DTRecordReference::getFieldDefinition( 'RCLanguage' )
		);
	}

	public static function fillForcedPermissions( array &$permissions ) {
		if ( !isset( $permissions[ get_called_class() ] ) ) {
			$permissions[ get_called_class() ] = array(
				'mayWrite' => 1,
				'isDependency' => 1,
				'restrictToOwn' => 0
			);
		}
	}
}