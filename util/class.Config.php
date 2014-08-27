<?php
/**
*
* @package package_name
*/

class Config {
	protected $conf;
	protected $currentFilename;
	
	protected static $configs = array();
	
	public function __construct() {
		
	}
	
	public function load( $filename ) {
		$this->currentFilename = $filename;
		$this->refresh();
	}
	
	public function refresh() {
		$this->conf = parse_ini_file( $this->currentFilename, true );
	}
	
	public function getSection( $section ) {
		return array_key_exists($section, $this->conf) ? $this->conf[$section] : NULL;
	}
	
	// TODO: isset
	public function setKey( $section, $key, $value ) {
		$this->conf[$section][$key] = $value;
	}
	
	// TODO: isset
	public function getKey( $section, $key ) {
		return isset($this->conf[$section][$key]) ? $this->conf[$section][$key] : NULL;
	}
	
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
		if (!array_key_exists($identifier, self::$configs)) {
			return NULL;
		}
		
		return self::$configs[$identifier];
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