<?php


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