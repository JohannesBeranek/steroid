<?php
/**
 *
 * @package steroid\storage
 */

require_once __DIR__ . '/interface.IStorage.php';

/**
 *
 * @package steroid\storage
 *
 */
interface IRBStorage extends IStorage {
	function select( $mainRecordClass, $queryStruct = NULL, $start = NULL, $count = NULL, $getTotal = NULL, array $vals = NULL, $name = NULL, $noAutoSelect = false );

	function selectFirst( $mainRecordClass, $queryStruct = NULL, $start = NULL, $getTotal = NULL );

	function selectRecords( $mainRecordClass, $queryStruct = NULL, $start = NULL, $count = NULL, $getTotal = NULL );

	/**
	 * Select record from storage
	 *
	 *
	 * @return IRecord
	 */
	function selectFirstRecord( $mainRecordClass, $queryStruct = NULL, $start = NULL, $getTotal = NULL );

	function getFoundRecords();

	function registerFilter( IRBStorageFilter $filter, $identifier = NULL );

	function unregisterFilter( $identifier );

	// TODO: add missing methods (save, delete, ...)
}


require_once __DIR__ . '/interface.IRBStorageFilter.php';

?>