<?php 
/**
 * @package steroid\cache
 */

require_once STROOT . '/cache/interface.ICache.php';
require_once STROOT . '/log/class.Log.php';

/**
 * Class implementing ICache via shmop_* functions
 * 
 * PHP needs to have been compiled with --enable-shmop for this to be used
 * As this might collide with other stuff using shared memory, you should be very cautious with using it
 *
 * @package steroid\cache
 */
class ShmopCache implements ICache {
	const MTIME_LENGTH = 4; // bytes to reserve for mtime - dechex doesn't support more than 32bit according to docs
	const KEYLEN = 4; // bytes to use for key 
	
	protected function convertKey( $key ) {
		return hexdec(substr(md5(ftok(__FILE__, chr(0)) . $key), 0, self::KEYLEN * 2)); 
	}
		
	/**
	 * (non-PHPdoc)
	 * @see ICache::exists()
	 */
	public function exists( $key ) {
		try {
			$shmid = shmop_open( $this->convertKey($key), 'a', 0, 0 );	
		
			if ($shmid !== false) {
				shmop_close($shmid);
				return true;
			}
		} catch( ErrorException $e ) {
			return false;
		}
		
		return false;
	}
	

	
	/**
	 * (non-PHPdoc)
	 * @see ICache::get()
	 */
	public function get( $key ) {
		try {
			$shmid = shmop_open( $this->convertKey($key), 'a', 0, 0 );
		
			if ($shmid !== false) {
				$size = shmop_size($shmid);
				$val = shmop_read($shmid, self::MTIME_LENGTH * 2, $size - self::MTIME_LENGTH * 2);

				shmop_close($shmid);

				return $val;
			}
			
			
		} catch( ErrorException $e ) {
			return false;
		}
		
		return false;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::mtime()
	 */
	public function mtime( $key ) {
		try {
			$shmid = shmop_open( $this->convertKey($key), 'a', 0, 0 );
			
			if ($shmid !== false) {
				$size = shmop_size($shmid);
				$val = shmop_read($shmid, 0, self::MTIME_LENGTH * 2);

				shmop_close($shmid);
				return hexdec($val);
			}
			
			
		} catch( ErrorException $e ) {
			return false;
		}
		
		return false;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::set()
	 */
	public function set( $key, $data ) {
		try {
			
			$data = dechex(time()) . $data;
			
			$size = strlen($data);
		
			$shmid = shmop_open( $this->convertKey($key), 'c', 0664, $size);
		
			if ($shmid !== false) {
				$bytesWritten = shmop_write($shmid, $data, 0);
				
				if ($bytesWritten != $size) {
					return false; // should not happen, otherwise we probably have just written some garbage data
				}
				
				shmop_close($shmid);	
			
				return true;
			}
			
		} catch( ErrorException $e) {
			Log::write( $e );
			return false;
		}
		
		return false;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::delete()
	 */
	public function delete( $key ) {
		try {
			$shmid = shmop_open( $this->convertKey($key), 'w' );
			
			if ($shmid !== false) {
				shmop_delete($shmid);
				
				shmop_close($shmid);
				
				return true;
			}
		} catch( ErrorException $e ) {
			return false;
		}
		
		return false;
	}
	
	// for better performance with send() function
	protected function getWithMTime() {
		try {
			$shmid = shmop_open( $this->convertKey($key), 'a', 0, 0 );
			
			if ($shmid !== false) {
				$size = shmop_size($shmid);
				$val = shmop_read($shmid, 0, $size);
				
				shmop_close($shmid);
				
				$ret = array(
					hexdec(substr($val, 0, self::MTIME_LENGTH * 2)),
					substr($val, self::MTIME_LENGTH * 2)
				);
				
				return $ret;
			}
			
			
		} catch( ErrorException $e ) {
			return false;
		}
		
		return false;
	}

	/**
	 * (non-PHPdoc)
	 * @see ICache::send()
	 */
	public function send( $key, $mimetype, $ifModifiedSince = NULL ) {
		$data = $this->getWithMTime();
		
		if ($data === false) {
			return false;
		}

		Responder::sendString($data[1], $mimetype, $data[0], $ifModifiedSince );
		
		return true;
	}
	
	public function lock( $key ) {
		// TODO
	}
	
	public function unlock( $key ) {
		// TODO
	}
	
	public static function available() {
		return extension_loaded('shmop');
	}
}

?>