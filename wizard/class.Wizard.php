<?php
/**
 * @package steroid/wizard
 */

require_once STROOT . '/backend/interface.IBackendModule.php';
require_once STROOT . '/user/class.User.php';

abstract class Wizard implements IBackendModule {
	public static function hasPermission( User $user ) {
		// stub
	}

	public static function save( RBStorage $storage, array $postData, User $user ) {
		if ( empty( $postData ) ) {
			throw new InvalidArgumentException( 'Wizard "' . get_called_class() . '" cannot perform action with empty $postData' );
		}
	}

	public static function getDataTypePaths() {
		return array();
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $isSearchField = false ) {

	}

	public static function handleUserAlive( RBStorage $storage, IRequestInfo $requestInfo, $recordID = NULL, $editingParent = NULL ) {
		// stub
	}
}