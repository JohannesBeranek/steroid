<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';

require_once STROOT . '/user/permission/class.RCPermission.php';
require_once STROOT . '/user/permission/class.RCPermissionEntity.php';

/**
 *
 * @package steroid\permission
 *
 */
class RCPermissionPermissionEntity extends Record {

	const BACKEND_TYPE = Record::BACKEND_TYPE_ADMIN;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'permission', 'permissionEntity' ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			'permission' => DTRecordReference::getFieldDefinition( 'RCPermission', true ),
			'permissionEntity' => DTRecordReference::getFieldDefinition( 'RCPermissionEntity', true ),
			'fieldPermission' => DTRecordReference::getFieldDefinition( 'RCFieldPermission', false, true )
		);
	}

	public function getFormValues( array $fields ) {
		$fields[ ] = 'fieldPermission';

		return parent::getFormValues( $fields );
	}

	public function beforeDelete( array &$basket = NULL ){
		if($this->deleted || $this->isDeleting){
			return;
		}

		parent::beforeDelete($basket);
	}
}
