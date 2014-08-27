<?php

require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/lib/jsmin/jsmin.php';
require_once STROOT . '/lib/json/JSON.php';

class NLS {
	public static function getTranslation( $nlsMessage = NULL, $recordClass = NULL, $language ) {
		if ( empty( $nlsMessage ) || empty( $recordClass ) ) {
			throw new InvalidArgumentException( '$nlsMessage and $recordClass must be set' );
		}

		$nlsPath = self::getNLSPathForRecordClass( $recordClass, $language );

		$nlsObj = self::getNLSFileContents( $nlsPath );

		if ( !$nlsObj ) {
			$nlsObj = self::getNLSFileContents( $baseLangNLSPath );

			if ( !$nlsObj ) {
				throw new LogicException( 'Cannot find required NLS file for recordClass "' . $recordClass . '"' );
			}
		}

		try {
			$message = self::getKey( $recordClass, $nlsMessage, $nlsObj );
		} catch ( Exception $e ) {
			$nlsPath = self::getNLSPathForRecordClass( $recordClass );

			$nlsObj = self::getNLSFileContents( $nlsPath );

			if ( !$nlsObj ) {
				throw new LogicException( 'Cannot find required NLS file for recordClass "' . $recordClass . '"' );
			}

			$message = self::getKey( $recordClass, $nlsMessage, $nlsObj );
		}

		return $message;
	}

	protected static function getNLSPathForRecordClass( $recordClass, $language = NULL ) {
		$classPath = NULL;
		$nlsPath = '';

		if ( $recordClass !== 'generic' && $recordClass !== 'error' ) {
			$classPath = ClassFinder::getClassLocation( $recordClass );
		}

		if ( $classPath && !ST::pathIsCore( $classPath ) ) {
			$baseLangNLSPath = WEBROOT . '/' . $classPath . '/res/static/js/nls/' . $recordClass . '.js';
			$nlsPath = WEBROOT . '/' . $classPath . '/res/static/js/nls/' . ( $language == 'en' ? '' : ( $language . '/' ) ) . $recordClass . '.js';
		} else {
			switch ( $recordClass ) {
				case 'error':
					$fileName = 'Errors';
					break;
				default:
					$fileName = 'RecordClasses';
			}

			$baseLangNLSPath = STROOT . '/res/static/js/dev/steroid/backend/nls/' . $fileName . '.js';
			$nlsPath = STROOT . '/res/static/js/dev/steroid/backend/nls/' . ( $language == 'en' ? '' : ( $language . '/' ) ) . $fileName . '.js';
		}

		return ( $language && $nlsPath ) ? $nlsPath : $baseLangNLSPath;
	}

	public static function replaceObjectNames( $text, $language ) {
		if ( empty( $text ) || empty( $language ) ) {
			throw new InvalidArgumentException( '$text and $language must be set' );
		}

		$text = preg_replace_callback( '#(\#RC[a-zA-Z]*\#)#', function ( $matches ) use ( $language ) {
			$className = str_replace( '#', '', $matches[ 0 ] );

			$nlsPath = self::getNLSPathForRecordClass( $className, $language );

			$nlsObj = self::getNLSFileContents( $nlsPath );

			return $nlsObj[ $className . '_name' ];
		}, $text );

		return $text;
	}

	public static function fillPlaceholders( $text, $dataStr ) {
		if ( empty( $text ) || empty( $dataStr ) ) {
			throw new InvalidArgumentException( '$text and $data must be set' );
		}

		$json = new Services_JSON( SERVICES_JSON_LOOSE_TYPE );

		$data = $json->decode( $dataStr );

		if ( !$data ) {
			throw new Exception( 'Failed decoding NLS message data: "' . $dataStr . '"' );
		}

		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				continue;
			}

			$text = str_replace( ( '$' . $key ), $value, $text );
		}

		return $text;
	}

	protected static function getNLSFileContents( $path ) {
		$nlsObj = file_get_contents( $path );

		if ( !$nlsObj ) {
			return NULL;
		}

		$nlsObj = JSMin::minify( str_replace( array( 'define(', ');', '(', ')' ), '', $nlsObj ) );

		$json = new Services_JSON( SERVICES_JSON_LOOSE_TYPE );

		$nlsObj = $json->decode( $nlsObj );

		if ( !$nlsObj ) {
			return NULL;
		}

		if ( isset( $nlsObj[ 'root' ] ) ) {
			$nlsObj = $nlsObj[ 'root' ];
		}

		return $nlsObj;
	}

	protected static function getKey( $recordClass, $path, $nlsObj ) {
		$temp = & $nlsObj;

		if ( $recordClass !== 'error' ) {
			$path = $recordClass . '.' . $path;
		}

		$nlsMessage = explode( '.', $path );

		foreach ( $nlsMessage as $key ) {
			if ( !isset( $temp[ $key ] ) ) {
				throw new LogicException( 'Key "' . $key . '" does not exist in path "' . implode( '.', $nlsMessage ) . '"' );
			}

			$temp = & $temp[ $key ];
		}

		$message = $temp;
		unset( $temp );

		return $message;
	}
}