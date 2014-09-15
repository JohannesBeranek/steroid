<?php
/**
 * Entry point
 *
 * Depending on php_sapi_name() either STCLI or STWeb is instanced and run.
 *
 * const is used instead of define(), as define is invoked on runtime and thus
 * apc will not cache classes and functions (opcode cache would still work) ;
 * using const instead fixes this problem, but as PHP can't interpret concatenated
 * constant expressions as constant value, STROOT and LOCALROOT need to be defined
 * in their respective directories, as __DIR__ is a compile time constant.
 *
 *
 *
 * @package steroid
 */


if ( !isset( $_SERVER[ 'REQUEST_TIME_FLOAT' ] ) ) { // php < 5.4
	$_SERVER[ 'REQUEST_TIME_FLOAT' ] = microtime( true );
}

if ( function_exists( 'mb_internal_encoding' ) ) {
	mb_internal_encoding( 'UTF-8' );
}

require_once STROOT . '/util/class.SteroidException.php';
require_once STROOT . '/exception_error_handler.php';

require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/log/class.Log.php';

require_once STROOT . '/storage/class.RBStorage.php';

function getConfig() {
	static $conf;

	if ( $conf === NULL )
		$conf = Config::loadNamed( LOCALROOT . '/localconf.ini.php', 'localconf' );

	return $conf;
}

function getStorage( Config $conf ) {
	$dbConfig = $conf->getSection( 'DB' );
	$filestoreConfig = $conf->getSection( 'filestore' );

	$storage = new RBStorage(
		$dbConfig[ 'host' ], $dbConfig[ 'username' ], $dbConfig[ 'password' ], $dbConfig[ 'database' ],
		( $filestoreConfig !== NULL && isset( $filestoreConfig[ 'path' ] ) ) ? $filestoreConfig[ 'path' ] : NULL,
		isset( $dbConfig[ 'default_engine' ] ) ? $dbConfig[ 'default_engine' ] : NULL,
		isset( $dbConfig[ 'default_charset' ] ) ? $dbConfig[ 'default_charset' ] : NULL,
		isset( $dbConfig[ 'default_collation' ] ) ? $dbConfig[ 'default_collation' ] : NULL
	);

	return $storage;
}

/**
 * ST base class - also defines STROOT var
 */
require_once WEBROOT . '/' . STDIRNAME . '/class.ST.php';

/**
 * Everything wrapped in a function so we don't pollute global space
 */
function run( $argv ) {
	$conf = getConfig();

	// configure timezone to avoid php notices
	// this is especially important using HHVM due to HHVM bug
	if ( $timezone = $conf->getKey( 'date', 'timezone' ) ) {
		date_default_timezone_set( $timezone );
	}

	$storage = getStorage( $conf );

	// separate storage connection for log so we can have live logging even during transactions
	$logStorage = getStorage( $conf );

	Log::init( $logStorage );
	set_exception_handler( array( 'Log', 'write' ) );

	if ( PHP_SAPI === 'cli' ) {
		require_once STROOT . '/class.STCLI.php';
		$ST = new STCLI( $conf, $storage, $argv );
	} else {
		// fixes for HHVM 
		if (defined('HHVM_VERSION')) {
			// fix for HHVM running via mod_proxy_fcgi on apache not setting correct SCRIPT_NAME
			$requestUri = $_SERVER['REQUEST_URI'];
			$scriptName = current(explode('?', $requestUri, 2));
			
			$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $scriptName;
		}

		// HHVM fixes end


		$matches = array();

		if ( preg_match( '/^\/([^\/\.]+\.php)(\/.*)?$/', $_SERVER[ 'SCRIPT_NAME' ], $matches ) ) {
			require_once STROOT . '/file/class.Filename.php';

			$fn = Filename::getPathInsideWebrootWithLocalDir( $matches[ 1 ], LOCALROOT . '/res/exposed_scripts' );

			if ( is_readable( $fn ) ) {
				if ( !empty( $matches[ 2 ] ) ) {
					$matches[ 2 ] = trim( $matches[ 2 ], '/' );
				}

				// translate REST like paths to $_GET params transparently
				if ( !empty( $matches[ 2 ] ) && ( $params = explode( '/', $matches[ 2 ] ) ) ) {
					for ( $i = 0, $ilen = count( $params ); $i < $ilen; $i++ ) {
						$param = $params[ $i ];

						if ( $param === '' ) continue; // skip empty params

						if ( ( $i + 1 ) < $ilen ) {
							$value = $params[ $i + 1 ];
						} else {
							$value = NULL;
						}

						$_GET[ $param ] = $value;
					}
				}

				return $fn; // run script in global scope
			}
		}

		require_once STROOT . '/class.STWeb.php';
		$ST = new STWeb( $conf, $storage );
	}

	return $ST();
}