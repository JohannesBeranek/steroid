<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTString.php'; 
 
/**
 * @package steroid\datatype
 */
class DTDynamicRecordReferenceClass extends BaseDTString {
	public static function getFieldDefinition( $instanceFieldName, $required = false ) {
		return array(
			'dataType' => get_called_class(),
			'instanceFieldName' => $instanceFieldName,
			'maxLen' => 127,
			'isFixed' => false,
			'default' => NULL,
			'nullable' => !$required
		);
	}
}