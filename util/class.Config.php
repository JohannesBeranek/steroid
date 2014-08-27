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
		File::putContents( $filename, $contents );
	}
	
	public function refresh() {
		// need to keep file contents to be able to write config later on
		$this->fileContent = File::getContents( $this->currentFilename );		
		$this->conf = parse_ini_string( $this->fileContent, true );
	}
	
	public function getSection( $section ) {
		return array_key_exists( $section, $this->conf ) ? $this->conf[ $section ] : NULL;
	}
	
	public function setKey( $section, $key, $value ) {		
		$this->conf[ $section ][ $key ] = $value;
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