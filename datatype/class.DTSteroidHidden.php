<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTBool.php';
  
/**
 * @package steroid\datatype
 */
 
class DTSteroidHidden extends BaseDTBool {
	/**
	 * @param bool|null $default Default value
	 * @param bool      $nullable false => NOT NULL
	 */
	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'default' => false,
			'nullable' => false
		);
	}
}