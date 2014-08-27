<?php

abstract class EmailProvider {
	const PRIORITY = 0;

	public static function send( $to, $from, $subject, $message, $messageHTML = NULL, $files = NULL, array $options = NULL ){
		throw new Exception('Class "' . get_called_class() . '" does not implement method send');
	}
}