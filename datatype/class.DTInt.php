<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DTMediumInt.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for int values
 */
class DTInt extends DTMediumInt {
	const BIT_WIDTH = 32;
}