<?php
/**
* @package steroid\util
*/

require_once __DIR__ . '/class.File.php';

/**
* @package steroid\util
*/
class Config {
	protected $conf;
	protected $currentFilename;
	protected $fileContent;
	
	protected static $configs = array();
	
	public function load( $filename ) {
		$this->currentFilename = $filename;
		$this->refresh();
	}
	
	/**
	 * Write current file content with possibly updated values
	 */
	public function write( $filename ) {
		File::putContents( $filename, $this->fileContent );
	}
	
	public function refresh() {
		// need to keep file contents to be able to write config later on
		$this->fileContent = File::getContents( $this->currentFilename );		
		$this->conf = parse_ini_string( $this->fileContent, true );
	}
	
	public function getSection( $section ) {
		return array_key_exists( $section, $this->conf ) ? $this->conf[ $section ] : NULL;
	}
	
	final private function escapeValueForIni( $value ) {
		if (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		} else if (is_numeric($value)) {
			$value = (string)$value;
		} else if (is_string($value)) {
			$value = '"' . $value . '"';
		} else {
			throw new Exception( 'Unable to escape value for ini file writing: ' . var_export($value) );
		}
		
		return $value;
	}
	
	final private function getKeyLineString( $key, $value ) {
		$keyString = '';
		
		if ( is_array( $value ) ) {
			$keyParts = array();
			
			foreach ($value as $val) {
				$keyParts[] = $key . "[] = " . $this->escapeValueForIni( $val );			
			}
			
			$keyString = implode( "\n", $keyParts );
		} else {
			$key . " = " . $this->escapeValueForIni( $value );
		}
		
		return $keyString;
	}
	
	public function setKey( $section, $key, $value ) {
		$this->conf[ $section ][ $key ] = $value;
		
		
		$sectionString = '[' . $section . ']';
		$sectionPos = strpos( $this->fileContent, $sectionString );
		
		if ( $sectionPos === false ) {
			$this->fileContent .= "\n\n" . $sectionString . "\n" . $this->getKeyLineString( $key, $value );
		} else {
			// commented section
			if ( $sectionPos > 0 && $this->fileContent[$sectionPos - 1] === ';') {
				// remove ';'
				$this->fileContent = substr( $this->fileContent, 0, $sectionPos - 1 ) . substr( $this->fileContent, $sectionPos + strlen( $sectionString ) );
			}
			
			// find next section 
			$nextSectionPosition = strpos( $this->fileContent, "\n[", $sectionPos + strlen( $sectionString ) );
			
			if ($nextSectionPosition === false) {				
				$workOn =& $this->fileContent;				
			} else {
				$startPos = $sectionPos + strlen( $sectionString );
				$sectionContent = substr( $this->fileContent, $startPos, $nextSectionPosition - $startPos );
				
				$workOn =& $sectionContent;
			}

			// try to find key
			$keyExists = preg_match( "/^" . preg_quote($key, "/") . "(\[[^\]]*\])\s*=.*$/", $this->fileContent, $matches, PREG_OFFSET_CAPTURE, $sectionPos + strlen( $sectionString ) );
			
			
			// TODO
		}
	}
	
	public function getKey( $section, $key ) {
		return isset( $this->conf[ $section ][ $key ] ) ? $this->conf[ $section ][ $key ] : NULL;
	}
	
	public function issetKey( $section, $key ) {
		return isset( $this->conf[ $section ][ $key ] );
	}
	
	
	
	
	
// STATICS
	public static function loadNamed( $filename, $identifier ) {
		$config = new Config();
		$config->load( $filename );
		self::$configs[ $identifier ] = $config;
		
		return $config;
	}

	public static function getDefault(){
		return self::loadNamed( LOCALROOT . '/localconf.ini.php', 'localconf' );
	}
	
	/**
	 * get config by identifier
	 * 
	 * @return Config
	 */
	public static function get( $identifier ) {
		if (!array_key_exists( $identifier, self::$configs )) {
			return NULL;
		}
		
		return self::$configs[ $identifier ];
	}
	
	public static function section( $section ) {
		$confSection = NULL;

		foreach (self::$configs as $config) {
			if (($conf = $config->getSection( $section )) !== NULL ) {
				if ($confSection === NULL) {
					$confSection = $conf;
				} else {
					$confSection = array_merge( $confSection, $conf );
				}
			}
		}
		
		return $confSection;
	}

	public static function key( $section, $key ) {
		if ( ( $section = self::section($section) ) !== NULL ) {
			return isset($section[$key]) ? $section[$key] : NULL;
		}

		return NULL;
	}
}