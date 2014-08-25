<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTBool.php';
  
/**
 * @package steroid\datatype
 */
 
class DTBool extends BaseDTBool {
	/**
	 * @param bool|null $default Default value
	 * @param bool      $nullable false => NOT NULL
	 */
	public static function getFieldDefinition( $default = false, $nullable = false, $readOnly = false ) {
		return array(
			'dataType' => get_called_class(),
			'default' => $default,
			'nullable' => $nullable,
			'readOnly' => $readOnly
		);
	}
}
?>