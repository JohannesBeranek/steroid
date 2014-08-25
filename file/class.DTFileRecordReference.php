<?php
/**
 * @package steroid\file
 */

require_once STROOT . '/file/class.BaseDTFileRecordReference.php';
require_once STROOT . '/file/class.RCFile.php';

/**
 * @package steroid\file
 */
class DTFileRecordReference extends BaseDTFileRecordReference {
	public static function getFieldDefinition( $requireForeign = false, $allowedCategories = NULL, $allowedTypes = NULL ) {
		return array(
			'dataType' => __CLASS__,
			'recordClass' => 'RCFile',
			'nullable' => !$requireForeign,
			'requireForeign' => $requireForeign,
			'requireSelf' => false,
			'default' => NULL,
			'allowedCategories' => $allowedCategories, // TODO: use this (regex which should match mimetype)
			'allowedTypes' => $allowedTypes,
			'constraints' => array( 'min' => $requireForeign ? 1 : 0, 'max' => 1 )
		);
	}
}