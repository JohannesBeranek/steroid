<?php
/**
 * @package steroid\file
 */

require_once STROOT . '/util/class.StringFilter.php';

/**
 * @package steroid\file
 */
final class Filename {

	
	
	
	
	
	
	final public static function getPathInsideWebrootWithLocalDir( $filename, $localDir = NULL ) {
		if ( $localDir === NULL || substr( $filename, 0, 1 ) === '/' ) {
			$complete = self::getPathInsideWebroot( $filename );
		} else {
			$complete = self::getPathInsideWebroot( rtrim( $localDir, '/' ) . '/' . $filename );
		}

		return $complete;
	}

	/**
	 * Get filename ensured to be in WEBROOT based upon given filename
	 *
	 * This function might possibly return an invalid file-/directory name, but the returned file-/directory name is
	 * ensured to be within WEBROOT
	 *
	 * This function does not check for symlinks, but also not permit '..' which makes it safe even with
	 * symlinks pointing outside WEBROOT.
	 *
	 * @param string $filename
	 *
	 * @return string
	 */
	final public static function getPathInsideWebroot( $filename ) {

		$filename = StringFilter::filterFilenameWithPath( $filename );

		$path = self::resolvePath( pathinfo( $filename, PATHINFO_DIRNAME ) );

		$fn = rtrim( $path, '/' );

		$wr = rtrim( WEBROOT, '/' ) . '/';

		if ( substr( $fn, 0, strlen( $wr ) ) != $wr ) {
			$fn = $wr . ltrim( $fn, '/' );
		}

		$basename = pathinfo( $filename, PATHINFO_BASENAME );

		if ( $basename ) {
			$fn .= '/' . $basename;
		}

		return $fn;
	}

	final public static function unwind( $path ) {
		$visited = array();

		$path = self::resolvePath( $path );

		while( $path !== '' && is_link($path)) {
			$path = readlink( $path );

			if ($path === 0) {
				return FALSE;
			} else {
				$path = self::resolvePath( $path );
			}
		}

		return $path;
	}

	final public static function resolvePath( $path ) {
		if ( $path === '' || $path === NULL ) {
			return '';
		}

		$pathParts = array();

		$path = StringFilter::filterPath( $path );

		if ( !$path ) {
			return '';
		}

		$filePathParts = explode( '/', $path );

		foreach ( $filePathParts as $filePathPart ) {
			if ( $filePathPart != '.' ) { // don't touch '', as otherwise we'd trim off leading '/'
				if ( $filePathPart == '..' ) {
					array_pop( $pathParts ); // no-op in case $pathParts is empty
				} else {
					$pathParts[ ] = StringFilter::filterFilename( $filePathPart );
				}
			}
		}

		return implode( '/', $pathParts );
	}

	final public static function getPathWithoutWebroot( $filename ) {
		if ( substr( $filename, 0, strlen( WEBROOT ) ) == WEBROOT ) {
			return substr( $filename, strlen( WEBROOT ) );
		}

		return $filename;
	}

	final public static function extensionFromMime( $contentType, $includeDot = NULL ) {
		if ( $contentType === NULL ) {
			return '';
		}

		static $map = array(
			'application/pdf' => 'pdf',
			'application/zip' => 'zip',
			'image/gif' => 'gif',
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'text/css' => 'css',
			'text/html' => 'html',
			'text/javascript' => 'js',
			'text/plain' => 'txt',
			'text/xml' => 'xml',
		);

		if ( isset( $map[ $contentType ] ) ) {
			$ret = $map[ $contentType ];
		} else { // fallback
			$pieces = explode( '/', $contentType );
			$ret = array_pop( $pieces );
		}

		if ( $includeDot ) {
			$ret = '.' . $ret;
		}

		return $ret;
	}
}