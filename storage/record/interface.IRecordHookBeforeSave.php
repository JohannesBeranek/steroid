<?php
/**
 * @package steroid\storage\record
 */

require_once __DIR__ . '/interface.IRecord.php';
require_once STROOT . '/storage/interface.IRBStorage.php';
 
/**
 * @package steroid\storage\record
 */
interface IRecordHookBeforeSave {
	public function recordHookBeforeSave( IRBStorage $storage, IRecord $record, $isUpdate );
}

?>