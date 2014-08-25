<?php
/**
 * @package steroid\user\permission
 */

require_once STROOT . '/datatype/class.BaseDTStaticInlineRecordEdit.php';
require_once STROOT . '/user/class.User.php';
require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/storage/record/class.Record.php';

/**
 *
 * @package steroid\user\permission
 */
class DTStaticInlinePermissionEdit extends BaseDTStaticInlineRecordEdit {

	public static function generatePermissions( RBStorage $storage, $data = NULL ) {
		$permissionEntities = array();
		$fieldPermissions = array();

		foreach ( $data as $join ) {
			$rc = $join[ 'permissionEntity' ][ 'recordClass' ];

			$permissionEntities[ $rc ] = $join[ 'permissionEntity' ];

			if ( isset( $join[ 'fieldPermission' ] ) ) {
				$fieldPermissions[ $rc ] = $join[ 'fieldPermission' ];
			}

			try {
				ClassFinder::find( $rc, true );

				$rc::fillRequiredPermissions( $permissionEntities, false );
			} catch ( ClassNotFoundException $e ) {
				continue; // permissions for currently not existing records. still saving the recordClass permissions in case it comes back?
			}
		}

		self::fillForcedPermissions( $permissionEntities );

		$permissions = array();

		foreach ( $permissionEntities as $rc => $permissionEntity ) {
			$permissionEntity[ 'recordClass' ] = $rc;
			$permissionEntity[ 'isDependency' ] = isset( $permissionEntity[ 'isDependency' ] ) ? $permissionEntity[ 'isDependency' ] : 1;
			$permissionEntity[ 'restrictToOwn' ] = isset( $permissionEntity[ 'restrictToOwn' ] ) ? $permissionEntity[ 'restrictToOwn' ] : 0;

			$actions = array( RCPermission::ACTION_PERMISSION_CREATE, RCPermission::ACTION_PERMISSION_DELETE, RCPermission::ACTION_PERMISSION_HIDE, RCPermission::ACTION_PERMISSION_PUBLISH );

			foreach ( $actions as $action ) { // fill up action permissions in case they aren't set
				if ( !isset( $permissionEntity[ $action ] ) ) {
					$permissionEntity[ $action ] = $permissionEntity[ 'mayWrite' ];
				}
			}

			if ( isset( $permissionEntity[ Record::FIELDNAME_PRIMARY ] ) ) {
				unset( $permissionEntity[ Record::FIELDNAME_PRIMARY ] );
			}

			$permissions[ ] = array(
				'permissionEntity' => RCPermissionEntity::get( $storage, $permissionEntity, Record::TRY_TO_LOAD ),
				'fieldPermission' => isset( $fieldPermissions[ $rc ] ) ? $fieldPermissions[ $rc ] : NULL
			);
		}

		return $permissions;
	}

	public function setValue( $data = NULL, $loaded = false ) {
		if ( !$loaded && is_array( $data ) ) {
			$data = self::generatePermissions( $this->storage, $data );
		}

		parent::setValue( $data, $loaded );
	}

	protected static function fillForcedPermissions( array &$permissions ) {
		$allRecordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );

		foreach ( $allRecordClasses as $className => $file ) {
			$className::fillForcedPermissions( $permissions );
		}
	}

}

?>