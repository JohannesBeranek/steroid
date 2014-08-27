<?php
/**
 * @package steroid\datatype
 */
require_once STROOT . '/datatype/class.BaseDTString.php';
require_once STROOT . '/storage/record/interface.IRecord.php';
require_once STROOT . '/util/class.Password.php';

require_once STROOT . '/util/class.Config.php';

/**
 * base class for string type values
 *
 * @package steroid\datatype
 */
class DTPassword extends BaseDTString {
	/**
	 *
	 * @param int    $maxLen
	 * @param bool   $isFixed
	 * @param string $default
	 * @param bool   $nullable
	 *
	 * @throws InvalidArgumentException
	 */
	public static function getFieldDefinition( $maxLen = NULL, $isFixed = false, $default = NULL, $nullable = false, $constraints = NULL, $searchType = BaseDTString::SEARCH_TYPE_BOTH ) {
		if ( !is_int( $maxLen ) && !is_null( $maxLen ) ) {
			throw new InvalidArgumentException( '$maxLen has to be of type int (or null).' );
		}

		if ( !is_string( $default ) && !is_null( $default ) ) {
			throw new InvalidArgumentException( '$default has to be of type string.' );
		}

		if ( $maxLen === NULL ) {
			$maxLen = 127;
		}

		return array(
			'dataType' => get_called_class(),
			'maxLen' => $maxLen,
			'isFixed' => (bool)$isFixed,
			'default' => $default,
			'nullable' => (bool)$nullable,
			'constraints' => $constraints,
			'searchType' => $searchType
		);
	}

	public static function hash($value){
		if($value instanceof Password){
			$value = $value->getValue();
		}

		$conf = Config::getDefault();

		$security = $conf->getSection('security');

		if(empty($security) || !isset($security['passwordSalt'])){
			throw new Exception('Cannot cook without salt!');
		}

		return crypt($value, $security['passwordSalt']);
	}

}