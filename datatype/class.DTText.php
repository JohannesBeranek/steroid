<?php
/**
 * @package steroid\datatype
 */
require_once STROOT . '/datatype/class.BaseDTText.php';
require_once STROOT . '/datatype/class.BaseDTString.php';

/**
 * base class for Text type values
 *
 * @package steroid\datatype
 */
class DTText extends BaseDTText {
	/**
	 *
	 * @param int  $maxLen
	 * @param bool $nullable
	 *
	 */
	public static function getFieldDefinition( $maxLen = NULL, $nullable = false, $searchType = BaseDTString::SEARCH_TYPE_BOTH ) {
		if ( $maxLen === NULL || $maxLen > 65535 ) {
			$maxLen = 65535;
		}

		return array(
			'dataType' => get_called_class(),
			'maxLen' => (int)$maxLen,
			'isFixed' => false,
			'default' => NULL,
			'nullable' => (bool)$nullable,
			'searchType' => $searchType
		);
	}

}