<?php 
/**
 * 
 * @package steroid\cache
 *
 */

require_once STROOT . '/cache/interface.ICache.php';
require_once STROOT . '/log/class.Log.php';
require_once STROOT . '/util/class.Responder.php';
require_once STROOT . '/util/class.StringFilter.php';

require_once __DIR__ . '/interface.IIncludeCache.php';


/**
 *
 * @package steroid\cache
 *
 */
class FileCache implements ICache, IIncludeCache {
	const CACHE_DIR = '/cache';
	
	protected $locks = array();
	
	public function __construct() {
		register_shutdown_function(array( $this, '_cleanup' ));
	}
	
	public function convertKey( $key ) {
		return WEBROOT . self::CACHE_DIR . '/' . StringFilter::filterFilename($key);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::exists()
	 */
	public function exists( $key ) {
		$fn = $this->convertKey($key);
		return file_exists( $fn ) && (filesize( $fn ) > 0);
		
		// TODO: support 0 byte size files (need to memorize which files have been created anew by locking)
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::get()
	 */
	public function get( $key ) {
		try {
			$val = file_get_contents( $this->convertKey($key) );
		} catch( ErrorException $e ) {
			return false;
		}
		
		return $val;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::set()
	 */
	public function set( $key, $data ) {
		// TODO: try to create dir if needed
		file_put_contents( $this->convertKey($key), $data );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::delete()
	 */
	public function delete( $key ) {
		try {
			unlink( $this->convertKey($key) );
		} catch (ErrorException $e) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::mtime()
	 */
	public function mtime( $key ) {
		return filemtime( $this->convertKey($key) );
	}
	
	public function send( $key, $mimetype, $ifModifiedSince = NULL ) {
		try {
			Responder::sendFile( $this->convertKey($key), $ifModifiedSince, NULL, $mimetype );
		} catch (Exception $e) {
			Log::write( $e );
			
			return false;	
		}
		
		return true;
	}
	
	public function getUrlForKey( $key ) {
		return self::CACHE_DIR . '/' . $key;
	}
	
	public function lock( $key ) {
		if (!empty($this->locks[$key])) return;
		
		// TODO: might need to create dir
		$handle = fopen( $this->convertKey($key), 'c+' );
		flock( $handle, LOCK_EX );
		
		$this->locks[$key] = $handle;
	}
	
	public function unlock( $key ) {
		if (empty($this->locks[$key])) return;
		
		$handle = $this->locks[$key];
		
		flock($handle, LOCK_UN);
		fclose($handle);
		
		// TODO: delete 0 byte size file without triggering race condition, and only if file was not written to
		
		unset($this->locks[$key]);
	}
	
	public static function available() {
		// TODO: try to create dir
		$cacheDir = WEBROOT . self::CACHE_DIR . '/';
		return is_dir($cacheDir) && is_writable($cacheDir);
	}
	
	public function __destruct() {
		$this->_cleanup();
	}
	
	// registered as shutdown function so we cleanup no matter what happens (destructors may not always get called!)
	// needs to be public to be callable on shutdown
	public function _cleanup() {
		foreach ($this->locks as $k => $v) {
			try {
				$this->unlock($k);
			} catch(Exception $e) {
				error_log('Unable to release lock for key ' . $k);
			}			
		}
	}
	
	/* IIncludeCache */
	final public function doInclude( $key ) {
		return ( include $this->convertKey($key) );
	}
	
	final public function doIncludeOnce( $key ) {
		return ( include_once $this->convertKey($key) );
	}
	
	final public function doRequire( $key ) {
		return ( require $this->convertKey($key) );
	}
	
	final public function doRequireOnce( $key ) {
		return ( require_once $this->convertKey($key) );
	}
	
}
