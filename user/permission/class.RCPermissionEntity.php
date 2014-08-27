<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTBool.php';

/**
 *
 * @package steroid\permission
 *
 */
class RCPermissionEntity extends Record {

	const ALLOW_CREATE_IN_SELECTION = 1;

	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'unique' => DTKey::getFieldDefinition( array( 'recordClass', 'mayWrite', 'restrictToOwn', 'isDependency', 'mayPublish', 'mayHide', 'mayDelete', 'mayCreate' ), true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'recordClass' => DTString::getFieldDefinition( 127 ),
			'mayWrite' => DTBool::getFieldDefinition(),
			'restrictToOwn' => DTBool::getFieldDefinition(),
			'isDependency' => DTBool::getFieldDefinition(),
			'mayPublish' => DTBool::getFieldDefinition( true ),
			'mayHide' => DTBool::getFieldDefinition( true ),
			'mayDelete' => DTBool::getFieldDefinition( true ),
			'mayCreate' => DTBool::getFieldDefinition( true )
		);
	}

	public static function fillForcedPermissions( array &$permissions ) {
		parent::fillForcedPermissions( $permissions );

		if ( !isset( $permissions[ 'RCFieldPermission' ] ) || !$permissions[ 'RCFieldPermission' ][ 'mayWrite' ] ) {
			$permissions[ 'RCFieldPermission' ] = array(
				'isDependency' => true,
				'mayWrite' => true,
				'restrictToOwn' => false
			);
		}

		if ( !isset( $permissions[ 'RCPermissionPerPage' ] ) || !$permissions[ 'RCPermissionPerPage' ][ 'mayWrite' ] ) {
			$permissions[ 'RCPermissionPerPage' ] = array(
				'isDependency' => true,
				'mayWrite' => true,
				'restrictToOwn' => false
			);
		}

		if ( !isset( $permissions[ 'RCDomain' ] ) || !$permissions[ 'RCDomain' ][ 'mayWrite' ] ) {
			$permissions[ 'RCDomain' ] = array(
				'isDependency' => true,
				'mayWrite' => true,
				'restrictToOwn' => false
			);
		}

		if ( !isset( $permissions[ 'RCUser' ] ) ) {
			$permissions[ 'RCUser' ] = array(
				'isDependency' => true,
				'mayWrite' => false,
				'restrictToOwn' => false
			);
		}
	}

}