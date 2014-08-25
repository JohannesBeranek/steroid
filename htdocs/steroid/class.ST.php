<?php
/**
 * Base class for STCLI and STWeb
 *
 * @package package_name
 */


/**
 * Steroid root
 *
 * @var string
 */
require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/log/class.Log.php';
require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/cache/class.Cache.php';

/**
 * Base class for STCLI and STWeb
 *
 * @package steroid
 */
class ST {
	/** @var IRBStorage */
	protected $storage;

	/** @var Config */
	protected $config;

	/**
	 * Product string
	 *
	 * @var string
	 */
	const PRODUCT_NAME = 'Steroid';

	const MODE_PRODUCTION = 'production';
	const MODE_DEVELOPMENT = 'development';

	protected static $mode;

	public static function getMode() {
		return self::$mode;
	}

	// FIXME: does not resolve '..' or symlinks correctly
	public static function pathIsCore( $path ) {
		return $path === STDIRNAME || strncmp( ltrim( $path, '/' ), STDIRNAME . '/', strlen( STDIRNAME ) + 1 ) === 0;
	}

	public function __construct( Config $conf, IRBStorage $storage ) {
		$this->config = $conf;
		$this->storage = $storage;

		self::$mode = ( $modeConf = $this->config->getSection( 'mode' ) ) && ( $installationMode = $modeConf[ 'installation' ] ) ? $installationMode : self::MODE_PRODUCTION;

		Cache::init( $storage );
	}

	public function __invoke() {

	}
}


class InvalidClassConstantException extends Exception {
}

class NotImplementedException extends Exception {
}

class SecurityException extends Exception {
}
