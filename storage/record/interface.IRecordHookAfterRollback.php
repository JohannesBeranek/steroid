<?php
/**
 * @package steroid\storage\record
 */
 
require_once STROOT . '/storage/interface.IRBStorage.php';
 
/**
 * @package steroid\storage\record
 */
interface IRecordHookAfterRollback {
	public function recordHookAfterRollback( IRBStorage $storage, $newTransactionLevel );
}

?>