<?php
/**
 * @package steroid\storage\record
 */
 
require_once STROOT . '/storage/interface.IRBStorage.php';
 
/**
 * @package steroid\storage\record
 */
interface IRecordHookAfterStartTransaction {
	public function recordHookAfterStartTransaction( IRBStorage $storage, $newTransactionLevel );
}

?>