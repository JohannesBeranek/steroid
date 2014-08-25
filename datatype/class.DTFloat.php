<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTFloat.php';

/**
 * class for float values
 */
class DTFloat extends BaseDTFloat {

	/**
	 * @param int|null $default Default value
	 * @param bool     $nullable false => NOT NULL
	 */
	public static function getFieldDefinition( $default = NULL, $nullable = false ) {
		return array(
			'dataType' => get_called_class(),
			'default' => $default,
			'nullable' => $nullable,
			'constraints' => array( 'regExp' => '[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?' )
		);
	}
}