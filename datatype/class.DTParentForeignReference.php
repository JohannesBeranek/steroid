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
class DTParentForeignReference extends BaseDTForeignReference {
	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'nullable' => true,
			'requireSelf' => true
		);
	}

}