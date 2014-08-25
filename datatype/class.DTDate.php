<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTDateTime.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for datetime values
 */
class DTDate extends BaseDTDateTime {
	const FORMAT_RETURN = 'yyyy-MM-dd';

	public static function getFieldDefinition( $nullable = false, $currentAsDefault = false, $currentAsMaxLimit = false ) {
		return array(
			'dataType' => get_called_class(),
			'nullable' => $nullable,
			'currentAsDefault' => $currentAsDefault,
			'currentAsMaxLimit' => $currentAsMaxLimit
		);
	}

	public function setValue( $data = NULL, $loaded = false ) {
		parent::setValue( ctype_digit($data) ? date( "Y-m-d", intval($data) ) : $data, $loaded );
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		$default = $fieldConf[ 'currentAsDefault' ] ? date( "Y-m-d", time() ) : date('Y-m-d', 0);

		return $default;
	}
}