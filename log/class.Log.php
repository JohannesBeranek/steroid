<?php
/**
 *
 * @package steroid\log
 */

require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/request/interface.IRequestInfo.php';
require_once STROOT . '/log/class.RCLog.php';
require_once STROOT . '/util/class.Debug.php';
require_once STROOT . '/util/class.UnixDomainSocket.php';

/**
 * @package steroid\log
 */
class Log {
	const DLOG_QUERY_PARAM = 'dlog';

	protected static $storage;
	protected static $requestInfo;

	protected static $uds = array();

	public static function init( IRBStorage $storage, IRequestInfo $requestInfo = NULL ) {
		static::$storage = $storage;
		static::$requestInfo = $requestInfo;
	}

	public static function setRequestInfo( IRequestInfo $requestInfo ) {
		static::$requestInfo = $requestInfo;
	}

	/**
	 * conditional unix domain socket log, extracts udsName from request
	 *
	 * You may pass multiple params to log them at once
	 */
	public static function socketLog( $obj ) {
		if ( static::$requestInfo && ( $udsName = static::$requestInfo->getQueryParam( self::DLOG_QUERY_PARAM ) ) ) {
			$args = func_get_args();

			array_unshift($args, $udsName);

			call_user_func_array( array( __CLASS__, 'namedSocketLog' ), $args );
		}
	}

	/**
	 * conditional unix domain socket log, takes udsName as parameter
	 *
	 * You may pass multiple params to log them at once
	 * This function should be preferred for non-temporary logging due to caching of socket existence
	 */
	public static function namedSocketLog( $udsName, $obj ) {
		$args = func_get_args();
		array_shift($args); // kick first arg

		if ( !isset(self::$uds[$udsName]) ) {
			self::$uds[$udsName] = new UnixDomainSocket( $udsName, false );
		}

		$uds = self::$uds[$udsName];

		$logString = '';

		foreach ( $args as $argument ) {
			$logPart = Debug::getStringRepresentation( $argument );

			if ( $logPart !== '' ) {
				$logString .= ( $logString === '' ? '' : ' ' ) . $logPart;
			}
		}

		try {
			$uds->write( $logString . "\n" );
		} catch ( UnixDomainSocketFailedConnectingException $e ) {
			// in case there is no socket, we don't throw an error
			Log::write( $e );
		}
	}

	/**
	 * Write 1-n things to log
	 *
	 * You may pass multiple params to log them at once
	 */
	public static function write( $obj ) {
		$args = func_get_args();

		$strings = array();

		foreach ( $args as $argument ) {
			$strings[] = Debug::getStringRepresentation( $argument );
		}

		$exceptionString = implode(', ', $strings);
		$logString = $exceptionString;

		// logging post information and session data might be a security problem in certain circumstances, so we restrict to SERVER and GET data
		// $contextInformation = Debug::getAvailableContextInformation();
		$contextInformation = print_r( array( 'GET' => $_GET, 'SERVER' => $_SERVER ), true );

		$internalLogString = $contextInformation;
		$internalLogString .= "\n------------- DEBUG: " . self::getUniqueID() . " ------------\n";
		$internalLogString .= $logString;

		static::logInternalString( $internalLogString );

		try {
			static::$storage->init();

			if ( $obj instanceof Exception ) {
				$hash = md5( var_export( $obj->getTraceAsString(), true ) );
			} else {
				$hash = md5( $exceptionString );
			}

			// TODO: in case of an exception we should also save the type of exception in an extra column, so we can filter/sort by that
			$logEntry = RCLog::get( static::$storage, array( 'hash' => $hash, 'formatted' => $logString, 'context' => $contextInformation, 'requestID' => self::getUniqueID() ), false );
			
			// log storage should be a separate one, but if not, this helps ensuring the log entry is saved in case of transactions
			static::$storage->ensureSaveRecord( $logEntry );

			$logEntry->removeFromIndex(); // make it possible to free mem
		} catch ( Exception $ex ) {
			error_log( 'FAILED LOGGING' );
			static::logInternalString( $ex->getFile() . $ex->getLine() . $ex->getMessage() );
		}
	}

	public static function getUniqueID() {
		static $uniqueID;
		
		if ($uniqueID === NULL) {
			if ( PHP_SAPI === 'cli' ) {
				$uniqueID = sprintf("%08x", rand());
			} else {
				$uniqueID = sprintf("%08x", abs(crc32($_SERVER['REMOTE_ADDR'] . $_SERVER['REQUEST_TIME_FLOAT'] . $_SERVER['REMOTE_PORT'])));
			} 
		}
		
		return $uniqueID;
	}

	/**
	 * Log exception with php error_log
	 *
	 * This must be passed a valid Exception object!
	 *
	 * @param Exception $e
	 */
	protected static function logInternal( Exception $e ) {
		// use php std error logging - this must not fail!
		static::logInternalString( Debug::getStringRepresentation( $e ) );
	}

	protected static function logInternalString( $str ) { // TODO: cut info more intelligently
		if ( strlen( $str ) > 4096 ) {
			$strparts = str_split( $str, 4096 );

			foreach ( $strparts as $strpart ) {
				error_log( $strpart );
			}
		} else {
			error_log( $str );
		}
	}

}
