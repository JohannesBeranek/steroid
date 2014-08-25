<?php
/**
 * @package steroid\url
 */


require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTSteroidReturnCode.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';
require_once STROOT . '/url/class.DTSteroidUrl.php';

/**
 * @package steroid\url
 */
class RCUrl extends Record {

	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getTitleFields() {
		return array( 'url' );
	}

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'id', 'live' ) ),
			'uniqueUrl' => DTKey::getFieldDefinition( array( 'url', 'domainGroup', 'live' ), true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'url' => DTSteroidUrl::getFieldDefinition(),
			'urlHandler' => DTRecordReference::getFieldDefinition( 'RCUrlHandler', true ),
			'returnCode' => DTSteroidReturnCode::getFieldDefinition(),
			'domainGroup' => DTSteroidDomainGroup::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'url:RCUrlRewrite' => DTForeignReference::getFieldDefinition( true, array( 'min' => 0, 'max' => 1 ) )
		);
	}

	protected function requireReferences() {
		return true;
	}

	protected static function addPermissionsForReferencesNotInFormFields() {
		return array(
			'url:RCUrlRewrite'
		);
	}

	public static function getEditableFormFields() {
		return array_diff( parent::getEditableFormFields(), array( 'urlHandler', 'domainGroup', 'url:RCUrlRewrite' ) );
	}

	public static function fillRequiredPermissions( array &$permissions, $titleOnly = false ) {
		parent::fillRequiredPermissions( $permissions, $titleOnly );

		if ( !isset( $permissions[ 'RCUrlHandler' ] ) ) {
			$permissions[ 'RCUrlHandler' ] = array(
				'mayWrite' => false,
				'isDependency' => true,
				'restrictToOwn' => false
			);
		}
	}
}