<?php

/**
 * @package steroid\user
 */

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTTinyInt.php';
require_once STROOT . '/datatype/class.DTBigInt.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTDecision.php';

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/user/class.RCBackendPreferenceUser.php';

require_once STROOT . '/file/class.DTImageRecordReference.php';

/**
 * @package steroid\user
 */
class RCUser extends Record {

	const BACKEND_TYPE = Record::BACKEND_TYPE_ADMIN;

//	const LIST_ONLY = true; // editing of user records from backend not allowed

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'username' => DTKey::getFieldDefinition( array( 'username' ), true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'username' => DTString::getFieldDefinition( 127 ),
			'firstname' => DTString::getFieldDefinition( 127 ), // TODO: if possible load firstname / lastname / zip / gender from crm on demand
			'lastname' => DTString::getFieldDefinition( 127 ),
			'zip' => DTString::getFieldDefinition( 5, false, NULL, true ),
			'gender' => DTDecision::getFieldDefinition( array( 'm', 'f' ) ),
			'image' => DTImageRecordReference::getFieldDefinition(), // optional
			//	'email' => DTString::getFieldDefinition( 127 ),
			//	'facebookID' => DTString::getFieldDefinition( 255, false, NULL, true ),
			//	'twitterID' => DTBigInt::getFieldDefinition( true, false, NULL, true ),
			//	'sessionID' => DTString::getFieldDefinition( 255, false, NULL, true ),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'is_backendAllowed' => DTBool::getFieldDefinition(),
			'backendPreference' => DTRecordReference::getFieldDefinition( 'RCBackendPreferenceUser' )
		);
	}

	protected static function getTitleFields() {
		return array( 'firstname', 'lastname' );
	}

	public static function getEditableFormFields() {
		return array(
			'username',
			'firstname',
			'lastname',
			'zip',
			'gender',
			'image'
		);
	}

	protected static function getDisplayedFilterFields() {
		return array();
	}

	public static function getDisplayedListFields() {
		return array(
			'firstname',
			'lastname',
			'username',
			'zip',
			'gender',
			'image',
			'is_backendAllowed',
			'disableComments'
		);
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass ) {
		parent::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass );

		if ( $requestingRecordClass == 'RCDomainGroupLanguagePermissionUser' ) {
			if ( !isset( $queryStruct[ 'where' ] ) ) {
				$queryStruct[ 'where' ] = array();
			} else {
				$queryStruct[ 'where' ][ ] = 'AND';
			}

			array_push( $queryStruct[ 'where' ], 'is_backendAllowed', '=', array( 1 ) );
		}
	}

	// TODO: getTitle to get name from CRM
}

?>