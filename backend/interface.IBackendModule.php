<?php
/**
 * @package steroid\backend
 */

interface IBackendModule {
	public static function getDefaultValues( IStorage $storage, array $fieldsToSelect, array $extraParams = NULL );

	public static function getFormFields( IRBStorage $storage, array $fields = NULL );

	public static function getConditionalFieldConf();

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $isSearchField = false );

	public static function getAvailableActions( $mayWrite = false, $mayPublish = false, $mayHide = false, $mayDelete = false, $mayCreate = false );

	public static function getCustomJSConfig();

	public static function getFieldSets( RBStorage $storage );
}