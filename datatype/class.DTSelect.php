<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTEnum.php';

/**
 * base class for tinyint values
 */
class DTSelect extends BaseDTEnum {

	public static function getFieldDefinition( array $values = NULL, $nullable = false ) {
		return array(
			'dataType' => get_called_class(),
			'nullable' => $nullable,
			'values' => $values
		);
	}
}