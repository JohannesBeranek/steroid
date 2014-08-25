<?php

require_once STROOT . '/storage/interface.IRBStorage.php';

class Cache {
	protected static $storage;
	
	public static function init(IRBStorage $storage) {
		self::$storage = $storage;
	}
	
	/**
	 * returns instance of cache best matching given type
	 * 
	 * @return ICache
	 */
	public static function getBestMatch( $type ) {
		static $order = array( 'db', 'apc', 'shm', 'file' );
		
		$type = strtolower($type);
		
		if (!in_array($type, $order)) { // try to be as graceful as possible
			$type = 'file';
		}
		
		$options = $order;
		$cache = NULL;
		
		array_unshift( $options, $type );
		$options = array_unique($options);
		
		while ($cache === NULL && ($currentOption = array_shift($options))) {
			switch ($currentOption) {
				case 'db':
					require_once STROOT . '/cache/class.DBCache.php';
					if (DBCache::available()) $cache = new DBCache( self::$storage );
				break;
				case 'apc':
					require_once STROOT . '/cache/class.ApcCache.php';
					if (APCCache::available()) $cache = new ApcCache();
				break;
				case 'shm':
					require_once STROOT . '/cache/class.ShmopCache.php';
					if (ShmopCache::available()) $cache = new ShmopCache();
				break;
				case 'file':
				default:
					require_once STROOT . '/cache/class.FileCache.php';
					if (FileCache::available()) $cache = new FileCache();
				break;	
			}
		}
		
		if (!$cache) {
			throw new RuntimeException('No cache available');
			
		}
		
		return $cache;
	}
	
}

?>