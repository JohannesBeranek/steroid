<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DTSmallInt.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for mediumint values
 */
class DTMediumInt extends DTSmallInt {
	const BIT_WIDTH = 24;
}