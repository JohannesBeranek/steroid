<?php
/**
 * @package steroid\area
 */

require_once STROOT . '/area/class.BaseDTAreaJoinForeignReference.php';

/**
 * Class for DT in RCPage/RCTemplate/... references by join records also referencing RCArea in a field called 'area'
 *
 * @package steroid\area
 */
class DTPageAreaJoinForeignReference extends BaseDTAreaJoinForeignReference {
	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {

		$owningRecordPerms = $permissions[ $owningRecordClass ];

		if ( $owningRecordPerms[ 'mayWrite' ] ) {
			parent::fillRequiredPermissions( $permissions, $fieldName, $fieldDef, $permissions, $owningRecordClass, $titleOnly );

			if ( !isset( $permissions[ 'RCPageArea' ] ) || $owningRecordPerms[ 'mayWrite' ] > $permissions[ 'RCPageArea' ][ 'mayWrite' ] ) {
				$permissions[ 'RCPageArea' ] = array(
					'mayWrite' => $owningRecordPerms[ 'mayWrite' ]
				);

				RCPageArea::fillRequiredPermissions( $permissions, $titleOnly );
			}

			if ( !isset( $permissions[ 'RCTemplateArea' ] ) ) {
				$permissions[ 'RCTemplateArea' ] = array(
					'mayWrite' => 0
				);

				RCTemplateArea::fillRequiredPermissions( $permissions, $titleOnly );
			}
		}
	}
}
