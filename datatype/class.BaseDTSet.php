<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';

/**
 * basic class for set type values
 */
abstract class BaseDTSet extends DataType {
	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		if ( $data ) {
			
			if ( is_string( $data ) ) {
				$data = implode(',', $data);
			} else if (!is_array($data)) {
				throw new Exception('invalid data');
			}
		} else if ( $data !== NULL ) {
			$data = NULL;
		}

		if ($data) {
			// FIXME: sort according to original order
			
			$data = implode(',', $data);
		}
		
		parent::setValue( $data, $loaded );
	}

	// TODO: doesn't parent class already do this all now?
	public function getFormValue() {
		// this makes sure the value is lazily loaded if needed
		$val = $this->record->{$this->fieldName};

		return $val ? explode(',', $val) : NULL;
	}
}
