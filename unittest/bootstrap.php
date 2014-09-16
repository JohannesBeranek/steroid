<?php

require_once __DIR__ . '/../../pathdefines.php';
require_once WEBROOT . '/stlocal/localroot.php';
require_once __DIR__ . '/class.testCommons.php';
require_once STROOT . '/util/class.SteroidException.php';

if ( function_exists( 'mb_internal_encoding' ) ) {
	mb_internal_encoding( 'UTF-8' );
}