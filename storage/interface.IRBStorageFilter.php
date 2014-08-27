<?php
/**
*
* @package steroid\storage
*/

require_once __DIR__ . '/record/interface.IRecord.php';

/**
 *
 * @package steroid\storage
 */
interface IRBStorageFilter {
	public function injectSelectFilter( $recordClass, &$conf, &$additionalJoinConf );
	public function checkSaveFilter( IRecord $record );
	public function checkUpdateFilter( IRecord $record );
	public function checkInsertFilter( IRecord $record );
	public function checkDeleteFilter( IRecord $record );
	public function modifySelectCacheName( &$name );
}

?>