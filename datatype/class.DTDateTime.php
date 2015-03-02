<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTDateTime.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for datetime values
 */
class DTDateTime extends BaseDTDateTime {
	const FORMAT_SAVE = "Y-m-d H:i:s";
	const FORMAT_RETURN = 'yyyy-MM-dd HH:mm:ss'; // TODO: what is this used for?

	public static function getFieldDefinition( $nullable = false, $currentAsDefault = false ) {
		
		return array(
			'dataType' => get_called_class(),
			'nullable' => $nullable,
			'currentAsDefault' => $currentAsDefault
		);
	}

	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		if ($data === '') {
			$data = NULL;
		} elseif ( ctype_digit( $data ) || is_int( $data ) || is_float( $data ) ) {
			$data = date( self::FORMAT_SAVE, intval( $data ) );
		}
		
		parent::setValue( $data, $loaded, $path, $dirtyTracking );
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		$default = $fieldConf[ 'currentAsDefault' ] ? date( self::FORMAT_SAVE, time() ) : ($fieldConf['nullable'] ? NULL : date( self::FORMAT_SAVE, 0 ));

		return $default;
	}

	/**
	 * Formats given DateTime (needs to be in FORMAT_SAVE!) to given format
	 *
	 * ->format($record->dateTime, 'U'); to get unix timestamp
	 */
	public static function format( $data, $format ) {
		if ( $data !== NULL && $data !== '' && $format !== self::FORMAT_SAVE ) {
			if ( $dt = DateTime::createFromFormat( self::FORMAT_SAVE, $data ) ) { // TODO: DateTimeZone?
				$data = $dt->format( $format );
			}
		}

		return $data;
	}

	public static function valueFromTimestamp( $data ) {
		if ( $data !== NULL && $data !== '' ) {
			$data = date( self::FORMAT_SAVE, $data );
		}

		return $data;
	}
}