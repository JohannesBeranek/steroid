<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';

/**
 * basic class for integer type values
 */
abstract class BaseDTEnum extends DataType {
	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		if ( $data && is_array( $data ) ) {
			if ( count( $data ) > 1 || ( !$this->config[ 'nullable' ] && count( $data ) < 1 ) ) {
				throw new InvalidArgumentException( '$data of ENUM field must be one value' );
			}

			$data = $data[ 0 ];
		}

		if ( $data === NULL || !in_array( (string)$data, $this->config[ 'values' ], true ) ) {
			if ( ( $data === '' || $data === NULL ) && $this->config[ 'nullable' ] ) {
				$data = NULL;
			} else {
				throw new InvalidValueForFieldException( 'invalid value for field ' . $this->fieldName );
			}
		}

		parent::setValue( $data === NULL ? NULL : (string)$data, $loaded, $path, $dirtyTracking );
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		return $fieldConf[ 'nullable' ] ? NULL : $fieldConf[ 'values' ][ 0 ];
	}
}
