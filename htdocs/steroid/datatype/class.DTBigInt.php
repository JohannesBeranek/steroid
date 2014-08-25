<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.DTInt.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * basic class for bigint values
 */
class DTBigInt extends DTInt {
	const BIT_WIDTH = 64;
}