<?php
/**
 * @package steroid\util
 */

require_once STROOT . '/url/class.UrlUtil.php';

require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/util/class.Match.php';

require_once STROOT . '/request/class.RequestInfo.php';

/**
 * @package steroid\util
 */
class Responder {
	/**
	 * Defines more specific content types for mimetype starting with 'text/'
	 *
	 * @var array
	 */
	protected static $textContentTypes = array(
		'css' => 'text/css',
		'js' => 'text/javascript',
		'html' => 'text/html'
	);

	// $maxAge in seconds
	public static function sendHSTSHeader( $maxAge = 31536000 ) {
		if ( RequestInfo::getCurrent()->getServerInfo( RequestInfo::PROXY_SAFE_IS_HTTPS ) && ( $domains = Config::key('web', 'domainsHSTS') ) && Match::multiFN( $_SERVER['HTTP_HOST'], $domains ) ) {
			header( "Strict-Transport-Security: max-age=" . $maxAge );
		}
	}

	public static function sendLocationHeader( $location ) {
		self::sendHSTSHeader();

		// TODO: escaping!
		header( 'Location: ' . $location );
	}

	public static function sendContentTypeHeader( $mimetype = NULL, $forceDownload = false, $charset = '' ) {
		if ( $forceDownload !== false ) {
			$ext = pathinfo( $forceDownload, PATHINFO_EXTENSION );

			$fn = pathinfo( $forceDownload, PATHINFO_FILENAME );

			if ( $ext !== NULL && $ext !== '' ) {
				if ( function_exists( 'mb_strtolower' ) ) {
					$ext = mb_strtolower( $ext, "UTF-8" );
				} else {
					$ext = strtolower( $ext );
				}

				$ext = '.' . $ext;
			} else {
				$ext = '';
			}

			$simpleFilename = UrlUtil::generateUrlPartFromString( $fn ) . $ext;

			// these 2 headers are needed to make it work in IE < 9 (otherwise IE will just say file could not be found when trying to download)
			header( 'Pragma: public' );
			header( 'Cache-Control: max-age=0' );

			header( 'Content-disposition: attachment; filename="' . $simpleFilename . '"; filename*=utf-8\'\'' . rawurlencode( $forceDownload ) );
			header( 'Content-Type: application/octet-stream' );
		} else if ( $mimetype !== NULL ) {
			header( 'Content-Type: ' . $mimetype . ( $charset ? ( '; charset=' . $charset ) : '' ));
		}

	
	}

	public static function sendContentLanguageHeader( $iso639 ) {
		if ( $iso639 ) {
			header( 'Content-Language: ' . $iso639 );
		}
	}

	public static function sendContentEncodingHeader( $encoding = 'gzip' ) {
		if ( $encoding !== NULL ) {
			header( 'Content-Encoding: ' . $encoding );
		}
	}

	protected static function sendContentHeader( $mimetype, $size, $mtime, $forceDownload = false, $encoding = NULL ) {
		self::sendContentEncodingHeader( $encoding );

		self::sendContentTypeHeader( $mimetype, $forceDownload );

		header( 'Content-Length: ' . $size );
		header( 'Last-Modified: ' . gmdate( "D, d M Y H:i:s", $mtime ) . " GMT" ); // date must not be localized according to RFC1123
	}


	public static function sendString( $data, $mimetype, $mtime = NULL, $ifModifiedSince = NULL, $forceDownload = false, $encoding = NULL ) {
		if ( !is_string( $data ) ) {
			throw new InvalidArgumentException( '$data must be string.' );
		}

		if ( $mtime === NULL ) {
			$mtime = time();
		} else if ( $ifModifiedSince !== NULL ) {
			$compareTime = strtotime( $ifModifiedSince );

			if ( $compareTime >= $mtime ) {
				header_remove( 'Content-Encoding' );
				header_remove( 'Content-Length' );

				self::sendHSTSHeader();
				
				self::sendReturnCodeHeader( 304 );

				return;
			}
		}

		// TODO: Support ETag here?

		self::sendHSTSHeader();

		$size = strlen( $data );
		
		self::sendContentHeader( $mimetype, $size, $mtime, $forceDownload, $encoding );

		echo $data;
	}


	/**
	 * Send return code header - use this to be compatible to mod_php as well as fastcgi
	 */
	public static function sendReturnCodeHeader( $code ) {
		switch($code) {
            case 100: $text = 'Continue'; break;
            case 101: $text = 'Switching Protocols'; break;
            case 200: $text = 'OK'; break;
            case 201: $text = 'Created'; break;
            case 202: $text = 'Accepted'; break;
            case 203: $text = 'Non-Authoritative Information'; break;
            case 204: $text = 'No Content'; break;
            case 205: $text = 'Reset Content'; break;
            case 206: $text = 'Partial Content'; break;
            case 300: $text = 'Multiple Choices'; break;
            case 301: $text = 'Moved Permanently'; break;
            case 302: $text = 'Moved Temporarily'; break;
            case 303: $text = 'See Other'; break;
            case 304: $text = 'Not Modified'; break;
            case 305: $text = 'Use Proxy'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 402: $text = 'Payment Required'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 405: $text = 'Method Not Allowed'; break;
            case 406: $text = 'Not Acceptable'; break;
            case 407: $text = 'Proxy Authentication Required'; break;
            case 408: $text = 'Request Time-out'; break;
            case 409: $text = 'Conflict'; break;
            case 410: $text = 'Gone'; break;
            case 411: $text = 'Length Required'; break;
            case 412: $text = 'Precondition Failed'; break;
            case 413: $text = 'Request Entity Too Large'; break;
            case 414: $text = 'Request-URI Too Large'; break;
            case 415: $text = 'Unsupported Media Type'; break;
            case 500: $text = 'Internal Server Error'; break;
            case 501: $text = 'Not Implemented'; break;
            case 502: $text = 'Bad Gateway'; break;
            case 503: $text = 'Service Unavailable'; break;
            case 504: $text = 'Gateway Time-out'; break;
            case 505: $text = 'HTTP Version not supported'; break;
            default:
            	return false;
		}

		if (strpos(php_sapi_name(), 'cgi') !== FALSE) {
			$prefix = 'Status:';
		} else {
			$prefix = $_SERVER['SERVER_PROTOCOL'];
		}

		// TODO: php5.4 has http_response_code method
		header( $prefix . ' '  . intval($code) . ' ' . $text );

		return true;
	}

	/**
	 * Sends file to user and then exits
	 *
	 * Supports HTTP_IF_MODIFIED_SINCE as optional parameter. ETag is not supported, as it only
	 * increases load and can't be reliable implemented in a way it does not hurt multi server
	 * environments without reading in the whole file.
	 *
	 * Also uses XSendfile if the function apache_get_modules exists and
	 * mod_xsendfile is present in apache_get_modules()
	 *
	 * In case of fail (e.g. file does not exist), this function throws an exception
	 *
	 * @throws InvalidArgumentException
	 *
	 * @param string      $filename Absolute filename from filesystem root
	 * @param string|null $ifModifiedSince HTTP_IF_MODIFIED_SINCE
	 * @param string|null $range HTTP_RANGE
	 * @param string|null $mimetype
	 *
	 * @return void
	 */
	public static function sendFile( $filename, $ifModifiedSince = NULL, $range = NULL, $mimetype = NULL, $forceDownload = false ) {
		// checking with ctype_print poses a problem with complex filenames
		// file exists / is readable?
		if ( !file_exists( $filename ) || !is_readable( $filename ) ) {
			throw new InvalidArgumentException( 'File "' . $filename . '" does not exist or is not readable by php user.' );
		}

		self::sendHSTSHeader();

		// disable output buffering if it is enabled
		while ( ob_get_level() ) ob_end_clean();

		$filemtime = filemtime( $filename );

		if ( $ifModifiedSince !== NULL ) {
			$compareTime = strtotime( $ifModifiedSince );

			if ( $compareTime >= $filemtime ) {
				header_remove( 'Content-Length' );

				self::sendContentTypeHeader( $mimetype, $forceDownload );
	
				self::sendReturnCodeHeader( 304 );
				return;
			}
		}


		// check if content encoding header was sent
		$headers = headers_list();

		$isEncoded = false;

		// conditional check fixes problem with HHVM headers_list() returning NULL instead of empty array, when no headers are present
		if ($headers !== NULL) {
			foreach ( $headers as $header ) {
				$header = strtolower( $header );
				if ( strpos( $header, 'content-encoding' ) === 0 ) {
					$isEncoded = true;
					$range = NULL; // we don't support range requests for encoded content
					break;
				}
			}
		}


		// XSendFile doesn't send a content-type header
		$finfo = new finfo();

		if ( $mimetype === NULL ) {
			$mimetype = $finfo->file( $filename, FILEINFO_MIME_TYPE );

			// overwriting mimetype also avoids text/x-c++ for js files 
			if ( strpos( $mimetype, 'text/' ) === 0 && strpos( $filename, '.' ) !== false ) {
				$extension = pathinfo( $filename, PATHINFO_EXTENSION );

				if ( array_key_exists( $extension, self::$textContentTypes ) ) {
					$mimetype = self::$textContentTypes[ $extension ];
				}
			}
		}

		// xsendfile doesn't support content-encoding
		if ( !$isEncoded && function_exists( 'apache_get_modules' ) && in_array( 'mod_xsendfile', apache_get_modules() ) ) {
			self::sendContentTypeHeader( $mimetype, $forceDownload );

			header( 'X-SendFile: ' . $filename ); // mod_xsendfile supposedly supports ranges
		} else {
			clearstatcache(); // filesize might be cached
			$filesize = filesize( $filename );

			self::sendContentHeader( $mimetype, $filesize, $filemtime, $forceDownload );
			header( 'Accept-Ranges: bytes' );


			if ( $range !== NULL ) { // accept range for download
				list( $param, $range ) = explode( '=', $range );

				if ( strtolower( trim( $param ) ) != 'bytes' ) { // Bad request - range unit is not 'bytes'
					throw new InvalidRangeException( 'Invalid range unit' );
				}

				$range = explode( ',', $range );
				$range = explode( '-', $range[ 0 ] ); // We only deal with the first requested range

				if ( count( $range ) != 2 ) { // Bad request - 'bytes' parameter is not valid
					throw new InvalidRangeException( 'Invalid byte parameter for range' );
				}

				if ( $range[ 0 ] === '' ) { // First number missing, return last $range[1] bytes
					$end = $filesize - 1;
					$start = $end - intval( $range[ 0 ] );
				} else if ( $range[ 1 ] === '' ) { // Second number missing, return from byte $range[0] to end
					$start = intval( $range[ 0 ] );
					$end = $filesize - 1;
				} else { // Both numbers present, return specific range
					$start = intval( $range[ 0 ] );
					$end = intval( $range[ 1 ] );
				}

				if ( $start >= $end ) { // Bad request - zero or negative range
					throw new InvalidRangeException( 'Zero or negative range given' );
				}

				if ( $end < $filesize && ( $start || ( $end && $end == ( $filesize - 1 ) ) ) ) {
					if ( !$fp = fopen( $filename, 'r' ) ) {
						throw new InvalidArgumentException( 'Unable to open file "' . $filename . '"' );
					}


					$length = $end - $start + 1;

					if ( $start ) {
						fseek( $fp, $start );
					}

					header( 'Partial Content', true, 206 );
					header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $filesize );


					while ( $length ) {
						$read = ( $length > 8192 ) ? 8192 : $length;
						$length -= $read;
						print( fread( $fp, $read ) );
					}

					fclose( $fp );
				} else { // Invalid range/whole file specified, return whole file
					$range = NULL;
				}
			}

			if ( $range === NULL ) {
				readfile( $filename );
			}
		}

		// don't output anything anymore after calling sendFile, as xsendfile runs async!
		// this is also needed due to mod_xsendfile reportedly failing if script execution continues afterwards
	}
}

class InvalidRangeException extends Exception {
}
