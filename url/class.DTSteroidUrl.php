<?php
/**
 * @package steroid\datatype
 */
require_once STROOT . '/datatype/class.BaseDTString.php';
require_once STROOT . '/storage/record/interface.IRecord.php';


/**
 * @package steroid\datatype
 */
class DTSteroidUrl extends BaseDTString {
	/**
	 *
	 * @param string $default
	 * @param bool   $nullable
	 *
	 * @throws InvalidArgumentException
	 */
	public static function getFieldDefinition( $default = NULL, $nullable = false ) {
		if ( !is_string( $default ) && !is_null( $default ) ) {
			throw new InvalidArgumentException( '$default has to be of type string.' );
		}

		return array(
			'dataType' => get_called_class(),
			'maxLen' => 255,
			'isFixed' => false,
			'default' => $default,
			'nullable' => (bool)$nullable
		);
	}
}