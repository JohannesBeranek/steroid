<?php 
/**
 * @package steroid\cache
 */

require_once STROOT . '/cache/interface.ICache.php';
require_once STROOT . '/log/class.Log.php';

/**
 * @package steroid\cache
 */
class ApcCache implements ICache {
	const MTIME_PREFIX = '__mtime__';	
	
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::exists()
	 */
	public function exists( $key ) {
		return apc_exists( WEBROOT . $key );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::get()
	 */
	public function get( $key ) {
		return apc_fetch( WEBROOT . $key );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::set()
	 */
	public function set( $key, $data ) {
		$ret = apc_store( WEBROOT . $key, $data );
		
		if ($ret) {
			apc_store( self::MTIME_PREFIX . WEBROOT . $key, time() );
		}
		
		return $ret;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::delete()
	 */
	public function delete( $key ) {
		$ret = apc_delete( WEBROOT . $key );
		
		if ($ret) {
			apc_delete(  self::MTIME_PREFIX . WEBROOT . $key );
		}
		
		return $ret;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::mtime()
	 */
	public function mtime( $key ) {
		return apc_fetch( self::MTIME_PREFIX . WEBROOT . $key );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::send()
	 */
	public function send( $key, $mimetype, $ifModifiedSince = NULL ) {
		if (($data = $this->get($key)) === false || ($mtime = $this->mtime($key)) === false) {
			return false;
		}

		Responder::sendString($data, $mimetype, $mtime, $ifModifiedSince );
		
		return true;
	}
	
	public function lock( $key ) {
		// TODO
		
		return NULL;
	}
	
	public function unlock( $handle ) {
		// TODO
	}
	
	public static function available() {
		return extension_loaded('apc');
	}
}

?>