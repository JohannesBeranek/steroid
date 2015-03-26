<?php
/**
 * @package steroid\permission
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/user/permission/class.DTStaticInlinePermissionEdit.php';
require_once STROOT . '/user/permission/class.RCPermissionPermissionEntity.php';

/**
 *
 * @package steroid\permission
 *
 */
class RCPermission extends Record {

	const ACTION_PERMISSION_CREATE = 'mayCreate';
	const ACTION_PERMISSION_PUBLISH = 'mayPublish';
	const ACTION_PERMISSION_HIDE = 'mayHide';
	const ACTION_PERMISSION_DELETE = 'mayDelete';

	const BACKEND_TYPE = Record::BACKEND_TYPE_ADMIN;

	const ACTION_DUPLICATE = 'duplicateRecord';

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'title' => DTString::getFieldDefinition( 127 ),
			'description' => DTText::getFieldDefinition( NULL, true ),
			'permission:RCPermissionPermissionEntity' => DTStaticInlinePermissionEdit::getFieldDefinition( 'RCPermissionPermissionEntity', true )
		);
	}

	public static function getEditableFormFields() {
		return array(
			'title',
			'description',
			'permission:RCPermissionPermissionEntity'
		);
	}

	public static function getDisplayedFilterFields() {
		return array();
	}

	public static function getAvailableActions( $mayWrite = false, $mayPublish = false, $mayHide = false, $mayDelete = false, $mayCreate = false ) {
		$actions = parent::getAvailableActions( $mayWrite, $mayPublish, $mayHide, $mayDelete, $mayCreate );

		if ( $mayCreate ) {
			$actions[ ] = self::ACTION_DUPLICATE;
		}

		return $actions;
	}

	protected static function duplicate( RBStorage $storage, RCPermission $rec ){
		$newJoins = array();
		$newPermission = RCPermission::get($storage, array('title' => $rec->title . ' (duplicate)', 'description' => $rec->description), false);

		$newPermission->save();

		$permissionEntityJoins = $rec->{'permission:RCPermissionPermissionEntity'};

		foreach($permissionEntityJoins as $join){
			$newJoin = array(
				'permission' => $newPermission,
				'permissionEntity' => $join->permissionEntity
			);

			if($join->fieldPermission){
				$newJoin['fieldPermission'] = RCFieldPermission::get( $storage, array( 'readOnlyFields' => $join->fieldPermission->readOnlyFields ), false );
			}

			RCPermissionPermissionEntity::get($storage, $newJoin, false)->save();
		}
	}

	public static function handleBackendAction( RBStorage $storage, $action, $requestInfo ) {
		switch ( $action ) {
			case self::ACTION_DUPLICATE:
				$primary = $requestInfo->getPostParam( 'recordID' );

				$rec = RCPermission::get( $storage, array( Record::FIELDNAME_PRIMARY => $primary ), Record::TRY_TO_LOAD );

				if ( ! $rec->exists() ) {
					throw new RecordDoesNotExistException();
				}

				self::duplicate($storage, $rec);

				return $rec;
				break;
			default:
				throw new Exception( 'Unknown action: ' . $action );
				break;
		}
	}
}

?>