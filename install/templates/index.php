<?php


/**
 * @var string
 */
const WEBROOT = __DIR__;

/**
 * @var string
 */
const STDIRNAME = 'steroid';

/**
 * @var string
 */
const LOCALDIRNAME = 'stlocal';
 

require_once __DIR__ . '/' . STDIRNAME . '/base.php';

$returnCode = run( isset( $argv ) ? $argv : NULL );

if ( is_string( $returnCode ) ) {
	// make it possible to launch script which needs to be run in global scope (e.g. apc.php)
	include $returnCode;
} else {
	return ( $returnCode );
}

