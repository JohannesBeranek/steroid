<?php 
/**
 * @package steroid\cache
 */

require_once STROOT . '/cache/interface.ICache.php';
require_once STROOT . '/storage/interface.IDB.php';
require_once STROOT . '/cache/class.RCCache.php';
require_once STROOT . '/log/class.Log.php';

/**
 * Cache build upon DB - 
 * 
 * Does not use Record classes to avoid overhead as well as loops
 * 
 *
 * @package steroid\cache
 */
class DBCache implements ICache {
	protected $db;
	
	public function __construct( IDB $db ) {
		$this->db = $db;
		$this->db->init(); // make sure storage is init
	}	
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::exists()
	 */
	public function exists( $key ) {
		return (bool)$this->db->fetchFirst('SELECT 1 FROM `' . RCCache::getTableName() . '` WHERE `key` = ' . $this->db->escape( $key ) . ' LIMIT 1' );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::get()
	 */
	public function get( $key ) {
		$ret = $this->db->fetchFirst('SELECT `data` FROM `' . RCCache::getTableName() . '` WHERE `key` = ' . $this->db->escape( $key ) . ' LIMIT 1' );
		
		if (!$ret) return false;
		return $ret['data'];
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::set()
	 */
	public function set( $key, $data ) {
		$mtime = date('Y-m-d H:i:s');
		$this->db->insert( RCCache::getTableName(), array('key' => $key, 'data' => $data, 'mtime' => $mtime), array('data' => $data, 'mtime' => $mtime));		
	
		return true;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::delete()
	 */
	public function delete( $key ) {
		return (bool)$this->db->fetchAll('DELETE FROM `' . RCCache::getTableName() . '` WHERE `key` = ' . $this->db->escape($key));
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::mtime()
	 */
	public function mtime( $key ) {
		$ret = $this->db->fetchFirst('SELECT `mtime` FROM `' . RCCache::getTableName() . '` WHERE `key` = ' . $this->db->escape($key) . ' LIMIT 1' );
		
		if (!$ret) return false;
		return strtotime($ret['mtime']);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ICache::send()
	 */
	public function send( $key, $mimetype, $ifModifiedSince = NULL ) {
		try {
			$res = $this->db->fetchFirst('SELECT `data`, `mtime` FROM `' . RCCache::getTableName() . '` WHERE `key` = ' . $this->db->escape( $key ) . ' LIMIT 1');
				
			if (!$res) return false;
				
			Responder::sendString( $res['data'], $mimetype, strtotime($res['mtime']), $ifModifiedSince);	
		} catch( Exception $e ) {
			Log::write( $e );
			
			return false;
		}
		
		return true;
	}
	
	public function lock( $key ) {
		// TODO
	}
	
	public function unlock( $key ) {
		// TODO
	}
	
	public static function available() {
		return true; 
	}
}

?>
