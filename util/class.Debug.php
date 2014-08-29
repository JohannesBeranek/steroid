<?php
/**
 *
 * @package steroid\util
 */

/**
 * @package steroid\util
 */
class Debug {
	public static function getAvailableContextInformation() {
		$info = '$GLOBALS: ' . print_r( $GLOBALS, true );

		return $info;
	}

	public static function getStringRepresentation( $obj ) {
		$args = func_get_args();

		$strings = array();
		
		foreach ($args as $arg) {
			$strings[] = self::_getStringRepresentation($arg);
		}

		return implode(', ', $strings);
	}

	private static function _getStringRepresentation( $farg, array $visited = NULL ) {
		if ( $farg === NULL ) {
			$str = "NULL";
		} else if ( is_string( $farg ) ) {
			$str = '"' . str_replace( '"', '\\"', $farg ) . '"';
		} else if ( is_bool( $farg ) ) {
			$str = $farg ? 'true' : 'false';
		} else if ( is_int( $farg ) ) {
			$str = $farg;
		} else if ( is_float( $farg ) ) {
			$str = str_replace( ",", ".", (string)$farg ); // prevent printing comma as decimal separator due to locale setting
		} else if ( is_array( $farg ) ) {
			if ( $visited === NULL ) {
				$visited = array( $farg );
			} else {
				if ( in_array( $farg, $visited, true ) ) {
					return '*RECURSION*';
				}

				$visited[ ] = $farg;
			}

			$parts = array();

			foreach ( $farg as $k => $v ) {
				$parts[ ] = self::_getStringRepresentation( $k ) . ' => ' . self::_getStringRepresentation( $v, $visited );
			}

			$str = 'array' . ( $parts ? ( '( ' . implode( ', ', $parts ) . ' )' ) : '()' );
		} else if ( is_object( $farg ) ) {
			if ( get_class($farg) === 'Imagick' ) { // filter Imagick objects, as they just debug as binary garbage killing the log (because of NUL byte)
				$str = 'Imagick';
			} else if ( $farg instanceof Exception ) {
				$str = self::exceptionToString( $farg );
			} else if ( method_exists( $farg, '__toString' ) ) {
				try {
					$str = $farg->__toString();
				} catch ( Exception $e ) {
					$str = 'Exception when calling __toString on ' . get_class( $farg );
				}
			} else {
				$str = get_class( $farg );
			}
		} else {
			$str = "UNKNOWN";
		}

		return $str;
	}

	public static function exceptionToString( Exception $e ) {
		$s = array();

		$exceptions = array( $e );

		while ( $e = $e->getPrevious() ) {
			// array_unshift($exceptions, $e);
			$exceptions[ ] = $e;
		}

		foreach ( $exceptions as $e ) {
			if ( $s ) {
				$s[ ] = '---------------------- NEXT EXCEPTION --------------------' . "\n";
			}

			$s[ ] = get_class( $e ) . "\n";

			$trace = array_reverse( $e->getTrace() );

			foreach ( $trace as $part ) {
				$argparts = array();

				if ( array_key_exists( 'args', $part ) ) {
					foreach ( $part[ 'args' ] as $farg ) {
						$argparts[ ] = self::_getStringRepresentation( $farg );
					}
				}


				if ( array_key_exists( 'file', $part ) ) {
					$fn = $part[ 'file' ];

					if ( substr( $fn, 0, strlen( WEBROOT ) ) == WEBROOT ) {
						$fn = substr( $fn, strlen( WEBROOT ) );
					}
				} else {
					$fn = 'UNKNOWN';
				}

				if ( array_key_exists( 'line', $part ) ) {
					$line = $part[ 'line' ];
				} else {
					$line = 'UNKNOWN';
				}
				
				$ex = "\t[" . $fn . '|' . $line . '] ';

				if (isset($part['class'])) {
					$ex .= $part[ 'class' ] . $part[ 'type' ];
				} 
				
				$ex .= $part[ 'function' ];
				
				if ($argparts) {
					$ex .= '( ' . implode( ', ', $argparts ) . ' )';	
				} else {
					$ex .= '()';
				}
				
				$s[ ] =  "\t" . $ex . ";\n";
			}

			$s[ ] = "\t[" . $e->getFile() . '|' . $e->getLine() . '] #' . $e->getCode() . ': ' . $e->getMessage() . "\n";

		}

		$ret = implode( "", $s );

		return $ret;
	}
}

// helper class to prevent stuff to be put into debug output
class DebugProtection {
	protected $value;

	public function __construct( $value ) {
		$this->value = $value;
	}

	public function getValue() {
		return $this->value;
	}
}