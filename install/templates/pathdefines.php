<?php
/**
 *
 * const is used instead of define(), as define is invoked on runtime and thus
 * apc will not cache classes and functions (opcode cache would still work) ;
 * using const instead fixes this problem, but as PHP can't interpret concatenated
 * constant expressions as constant value, STROOT and LOCALROOT need to be defined
 * in their respective directories, as __DIR__ is a compile time constant.
 *
 * @package steroid
 */


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

require_once __DIR__ . '/' . STDIRNAME . '/stroot.php';

// defines LOCALROOT
require_once WEBROOT . '/' . LOCALDIRNAME . '/localroot.php';