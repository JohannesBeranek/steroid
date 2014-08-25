<?php 
/**
 * @package steroid\cache
 */


/**
 * @package steroid\cache
 */
interface ICache {
	/**
	 * 
	 * @param string $key
	 */
	function exists( $key );	
	
	/**
	 * @param string $key
	 */
	function get( $key );

	/**
	 * @param string $key
	 * @param mixed $data
	 */
	function set( $key, $data );
	
	/**
	 * @param string $key
	 */
	function delete( $key );
	
	/**
	 * @param string $key
	 */
	function mtime( $key );
	
	/**
	 * @param string $key
	 * @param string|null $ifModifiedSince
	 */
	function send( $key, $mimetype, $ifModifiedSince = NULL );
	
	static function available();
	
	function lock( $key );
	function unlock( $key );
}

?>