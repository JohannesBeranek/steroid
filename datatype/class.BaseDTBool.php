<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';
  
/**
 * @package steroid\datatype
 */
 
class BaseDTBool extends DataType {

	public function setValue( $data = NULL, $loaded = false ) {
		parent::setValue( $data === NULL ? NULL : ((int)$data == 0 ? 0 : 1), $loaded );
	}
}
?>