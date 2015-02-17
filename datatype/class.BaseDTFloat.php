<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';

/**
 * basic class for float type values
 */
abstract class BaseDTFloat extends DataType {	
	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
		if (is_string($data)) $data = str_replace(',', '.', $data); // allow for german comma in values
		if ($data === NULL || is_float($data) || is_numeric($data)) {
			parent::setValue( $data === NULL ? NULL : (float)$data, $loaded, $path, $dirtyTracking );
		}
	}
}
