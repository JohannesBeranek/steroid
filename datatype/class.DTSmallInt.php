<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DTTinyInt.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * base class for smallint values
 */
class DTSmallInt extends DTTinyInt {
	const BIT_WIDTH = 16;
}