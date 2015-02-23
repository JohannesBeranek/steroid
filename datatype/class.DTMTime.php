<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.DTDateTime.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * datatype for the record's modification time
 */
class DTMTime extends DTDateTime {
	public static function getFormRequired() {
		return true;
	}

	/**
	 * before Save
	 *
	 * always sets the modification time to the server's request time
	 */
	public function beforeSave( $isUpdate, array &$savePaths = NULL ) {
		if (!$this->hasBeenSet() && !$this->dirty && ($this->record->isDirty( false ) || !$isUpdate)) {
			$this->setValue( $_SERVER[ 'REQUEST_TIME' ] ); // TODO get request time from context
		}
	}
	
}