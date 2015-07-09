<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTDateTime.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for datetime values
 */
class DTTime extends BaseDTDateTime {
	const FORMAT_RETURN = 'HH:mm:ss';

	public static function getFieldDefinition( $nullable = false, $currentAsDefault = false, $currentAsMaxLimit = false ) {
		return array(
			'dataType' => get_called_class(),
			'nullable' => $nullable,
			'currentAsDefault' => $currentAsDefault,
			'currentAsMaxLimit' => $currentAsMaxLimit
		);
	}

	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		if ( ctype_digit($data) ) { // probably a timestamp
			parent::setValue( date( "H:i:s", intval($data) ), $loaded );
		} else {
			if (strpos($data, 'T') === 0) {
				$data = ltrim($data, 'T');
			}

			parent::setValue( $data, $loaded, $path, $dirtyTracking );
		}
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		$default = $fieldConf[ 'currentAsDefault' ] ? date( "H:i:s", time() ) : date('H:i:s', 0);

		return $default;
	}
}