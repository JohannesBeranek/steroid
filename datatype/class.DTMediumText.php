<?php
/**
 * @package steroid\datatype
 */
require_once STROOT . '/datatype/class.BaseDTText.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * base class for Text type values
 * 
 * @package steroid\datatype
 */
class DTMediumText extends BaseDTText {
	/**
	 * 
	 * @param int $maxLen
	 * @param bool $nullable
	 * 
	 */
	public static function getFieldDefinition( $maxLen = NULL, $nullable = false ) {
		if ($maxLen === NULL) {
			$maxLen = 16777215;
		}
		
		return array(
			'dataType' => get_called_class(),
			'maxLen' => max(16777215, $maxLen),
			'isFixed' => false,
			'default' => NULL,
			'nullable' => (bool)$nullable
		);
	}

}