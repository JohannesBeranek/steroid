<?php

require_once STROOT . '/util/class.SteroidException.php';

/**
*
* @package steroid
*/

/**
 * Used to convert catchable errors / warnings etc. into exceptions
 *
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @throws ErrorException
 */
function exception_error_handler($errno, $errstr, $errfile, $errline ) {
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

set_error_handler("exception_error_handler");
