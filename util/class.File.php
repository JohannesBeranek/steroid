<?php
/**
* @package steroid\util
*/



/**
* @package steroid\util
*/
class File {
	/**
	 * Atomic version of file_get_contents using (reader-)shared advisory locking
	 */
	final public static function getContents( $filename ) {
		return self::wrapLock($filename, 'rb', function( $fd, $filename ) {
			$contents = stream_get_contents( $fd );
			
			if ( $contents === false ) {
				throw new Exception( 'Unable to read anything from file "' . $filename . '"' );
			}
			
			return $contents;
		});
	}
	
	/**
	 * Calls file_put_contents using exclusive locking to provide atomic 
	 * operation when used in conjunction with getContents
	 */
	final public static function putContents( $filename, $contents ) {
		return file_put_contents( $filename, $contents, LOCK_EX );
	}
	
	/**
	 * Uses shared locking on file handle around parse_ini_file function
	 * to prevent writing while parsing
	 */
	final public static function parseIni( $filename, $parseSections ) {
		return self::wrapLock($filename, 'rb', function( $fd, $filename ) {
			$contents = parse_ini_file( $fd, $parseSections );
			
			if ( $contents === false ) {
				throw new Exception( 'Unable to parse ini from file "' . $filename . '"' );
			}
			
			return $contents;
		});
	}
	
	final private static function wrapLock( $filename, $mode, $fn ) {
		$fd = fopen( $filename, $mode );
		
		if ( $fd === false ) {
			throw new Exception( 'Unable to open file "' . $filename . '"' );
		}
		
		if ( flock( $fd, LOCK_SH ) === false ) {
			fclose( $filename );
			throw new Exception( 'Unable to lock file "' . $filename . '" in shared mode' );
		}
		
		try {
			$ret = $fn( $fd, $filename );
		} catch ( Exception $e ) {
			// unlocking happens automatically on fclose
			fclose( $filename );
			throw $e;
		}
		
		// unlocking happens automatically on fclose
		fclose( $fd );
		
		return $ret;
	}
}
