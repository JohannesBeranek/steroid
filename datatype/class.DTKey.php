<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.DataType.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for key definitions
 */
class DTKey extends DataType {

	public static function getFieldDefinition( array $fieldNames, $unique = false, $index = true ) {
		return array(
			'fieldNames' => $fieldNames,
			'unique' => $unique,
			'index' => $index
		);
	}

}