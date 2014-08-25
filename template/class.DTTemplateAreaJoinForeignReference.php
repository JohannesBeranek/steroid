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
class DTTemplateAreaJoinForeignReference extends BaseDTAreaJoinForeignReference {

	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {
		$owningRecordPerms = $permissions[ $owningRecordClass ];

		if ( $owningRecordPerms[ 'mayWrite' ] ) {
			parent::fillRequiredPermissions( $permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly );

			$owningRecordPerms = $permissions[$owningRecordClass];

			if ( !isset( $permissions[ 'RCTemplateArea' ] ) || $owningRecordPerms[ 'mayWrite' ] > $permissions[ 'RCTemplateArea' ][ 'mayWrite' ] ) {
				$permissions[ 'RCTemplateArea' ] = array(
					'mayWrite' => $owningRecordPerms[ 'mayWrite' ]
				);

				RCTemplateArea::fillRequiredPermissions( $permissions, $titleOnly );
			}
		}
	}
}
