<?php
/**
 * Entry point
 *
 * Depending on php_sapi_name() either STCLI or STWeb is instanced and run.
 *
 *
 * @package steroid
 */

require_once __DIR__ . '/pathdefines.php';

require_once WEBROOT . '/' . STDIRNAME . '/base.php';

$returnCode = run( isset( $argv ) ? $argv : NULL );

if ( is_string( $returnCode ) ) {
	// make it possible to launch script which needs to be run in global scope (e.g. apc.php)
	include $returnCode;
} else {
	return ( $returnCode );
}

