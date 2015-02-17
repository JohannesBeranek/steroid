<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTSet.php';

/**
 * base class for tinyint values
 */
class DTMultiSelect extends BaseDTSet {

	public static function getFieldDefinition( array $values = NULL, $nullable = false ) {
		// array_unique with keeping order
		$values = array_unique($values);
		ksort($values);
		
		return array(
			'dataType' => get_called_class(),
			'nullable' => $nullable,
			'values' => $values 
		);
	}
}