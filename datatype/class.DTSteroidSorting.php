<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTInteger.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for int values
 */
class DTSteroidSorting extends BaseDTInteger {
	const BIT_WIDTH = 32;

	/**
	 * @param bool     $unsigned Unsigned
	 * @param bool     $autInc Auto-Increment
	 * @param int|null $default Default value
	 * @param bool     $nullable false => NOT NULL
	 */
	public static function getFieldDefinition() {
		return array(
			'dataType' => get_called_class(),
			'unsigned' => true,
			'default' => NULL,
			'nullable' => false,
			'autoInc' => false,
			'bitWidth' => static::BIT_WIDTH
		);
	}
}