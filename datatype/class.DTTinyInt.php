<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTInteger.php';

/**
 * base class for tinyint values
 */
class DTTinyInt extends BaseDTInteger {
	const BIT_WIDTH = 8;

	/**
	 * @param bool     $unsigned Unsigned
	 * @param bool     $autInc Auto-Increment
	 * @param int|null $default Default value
	 * @param bool     $nullable false => NOT NULL
	 */
	public static function getFieldDefinition( $unsigned = false, $autoInc = false, $default = NULL, $nullable = false, $constraints = NULL ) {
		return array(
			'dataType' => get_called_class(),
			'unsigned' => $unsigned,
			'default' => $default,
			'nullable' => $nullable,
			'autoInc' => $autoInc,
			'bitWidth' => static::BIT_WIDTH,
			'constraints' => $constraints
		);
	}
}