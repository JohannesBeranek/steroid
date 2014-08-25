<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTForeignReference.php';

/**
 * class for foreign references (e.g. join tables, where the datatype's record is referenced by another record)
 *
 * @package steroid\datatype
 */
class DTForeignReference extends BaseDTForeignReference {
	public static function getFieldDefinition( $requireSelf = false, $constraints = NULL ) {
		return array(
			'dataType' => get_called_class(),
			'nullable' => true,
			'requireSelf' => $requireSelf,
			'constraints' => $constraints ? : array( 'min' => 0 )
		);
	}
}