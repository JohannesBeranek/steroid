<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTEnum.php';

/**
 * base class for tinyint values
 */
class DTSteroidReturnCode extends BaseDTEnum {
	// enum works on strings
	const RETURN_CODE_PRIMARY = "200";
	const RETURN_CODE_ALIAS = "418";

	public static function getFieldDefinition() {
		return array(
			'dataType' => get_called_class(),
			'nullable' => false,
			'values' => array( static::RETURN_CODE_PRIMARY, static::RETURN_CODE_ALIAS )
		);
	}
}