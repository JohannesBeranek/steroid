<?php
/**
 * @package steroid\storage\record
 */
 
require_once STROOT . '/storage/interface.IRBStorage.php';
 
/**
 * @package steroid\storage\record
 */
interface IRecordHookAfterCommit {
	public function recordHookAfterCommit( IRBStorage $storage, $newTransactionLevel );
}

?>