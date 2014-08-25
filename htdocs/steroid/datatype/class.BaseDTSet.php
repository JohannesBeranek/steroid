<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';

/**
 * basic class for integer type values
 */
abstract class BaseDTSet extends DataType {
	public function setValue( $data = NULL, $loaded = false ) {
		if ( $data && is_array( $data ) ) {
			$data = implode(',', $data);
		}

		parent::setValue( $data, $loaded );
	}

	public function getFormValue() {
		// this makes sure the value is lazily loaded if needed
		$val = $this->record->{$this->fieldName};

		return $val ? explode(',', $val) : NULL;
	}
}
