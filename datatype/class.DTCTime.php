<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.DTDateTime.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * datatype for a record's creation time
 */
class DTCTime extends DTDateTime {
	public static function getFormRequired() {
		return true;
	}

	/**
	 * Before save
	 *
	 * sets the creation time of a record
	 */
	public function beforeSave( $isUpdate, array $savePaths = NULL ) {
		if (!$isUpdate) {
			$this->setValue($_SERVER['REQUEST_TIME']); // TODO get request time from context
		}
	}
}