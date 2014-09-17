<?php

require_once __DIR__ . '/class.BaseDTText.php';

class DTJSON extends BaseDTText {
	protected $value;

	public static function getFieldDefinition( $nullable = false ) {
		return array(
			'dataType' => get_called_class(),
			'maxLen' => 65535,
			'isFixed' => false,
			'default' => NULL,
			'nullable' => (bool)$nullable
		);
	}

	public function cleanup() {
		parent::cleanup();
		
		$this->value = NULL;
	}

	public function getValue() {
		return $this->value;
	}

	protected function _setValue( $data, $loaded, $skipRaw = false, $skipReal = false ) {
		if ( $data === NULL || is_array( $data ) ) {
			if ( !$skipReal ) {
				$this->value = $data;
			}

			if ( !$skipRaw ) {
				parent::setValue( $data === NULL ? NULL : json_encode( $data ), $loaded );
			}
		} elseif ( is_string( $data ) ) {
			if ( !$skipReal ) {
				$decoded = json_decode( $data, true );

				if ( !$this->config[ 'nullable' ] && $decoded === NULL && !( $data === 'null' || $data === NULL ) ) {
					throw new InvalidArgumentException( 'JSON string could not be decoded: ' . $data );
				}

				$this->value = $decoded;
			}

			if ( !$skipRaw ) {
				parent::setValue( $data, $loaded );
			}
		} else {
			throw new InvalidArgumentException( 'Unable to handle given type for record reference.' );
		}
	}

	/**
	 * Set value
	 *
	 * takes the following as $data:
	 *
	 * - IRecord: a record instance
	 * - array: an associative array of fieldNames => values
	 * - string/int: the primary value of the foreign record
	 *
	 *
	 * @param null   $data
	 * @param bool   $loaded
	 * @param string $fieldName
	 *
	 * @throws InvalidArgumentException
	 */
	public function setValue( $data = NULL, $loaded = false ) {
		$this->_setValue( $data, $loaded );
	}

	public function setRawValue( $data = NULL, $loaded = false ) {
		$this->_setValue( $data, $loaded, false, true );

		$this->lastRawValue = isset( $this->values[ $this->colName ] ) ? $this->values[ $this->colName ] : NULL;
	}

	public function setRealValue( $data = NULL, $loaded = false ) {
		$this->_setValue( $data, $loaded, true, false );
	}
}