<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTEnum.php';

/**
 * base class for tinyint values
 */
class DTDecision extends BaseDTEnum {

	public static function getFieldDefinition( array $values = NULL ) {
		return array(
			'dataType' => get_called_class(),
			'nullable' => false,
			'values' => $values
		);
	}
}