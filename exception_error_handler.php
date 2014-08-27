<?php
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

class SteroidException extends Exception implements JsonSerializable {
	protected $data;
		
	public function __construct( $message = "", array $data = NULL, $code = 0, Exception $previous = NULL ) {
		$this->data = $data === NULL ? array() : $data;
		
		parent::__construct( $message, $code, $previous );
	}
	
	public function jsonSerialize() {
		return array(
			'data' => $this->data
		);
	}
	
	public function getData() {
		return $this->data;
	}
}
