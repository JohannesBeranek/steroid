<?php

require_once STROOT . '/log/class.Log.php';

class FIFO {
	const MODE_READ = 'r';
	const MODE_WRITE = 'w';
	
	
	public static function generateFilename( $string ) {
		return '/tmp/stfifo_' . md5($string);
		
	}
	
	protected $permissions;
	
	protected $path;
	protected $cleanup;
	
	
	protected $isCreated;
	
	protected $fh;
	protected $currentMode;
	
	public function __construct( $name, $cleanup, $permissions = 0644 ) {
		$this->path = self::generateFilename( $name );
		$this->cleanup = $cleanup;
		$this->permissions = $permissions;
			
		if ($cleanup) {
			register_shutdown_function(array($this, 'cleanup'));
		}
			
		$this->recreate();
	}
	
	public function recreate() {
		if (!$this->isCreated) {
			if (! file_exists($this->path)) { // TODO: this is non-atomic, should be made atomic if possible
	 			if (! posix_mkfifo( $this->path, $this->permissions )) {
	 				throw new Exception('Unable to creat fifo at ' . $this->path);
	 			}
				
				chmod($this->path, $this->permissions); // make sure permissions are set correctly regardless of umask
				
				$this->isCreated = true;
			} else if (filetype($this->path) !== 'fifo') {
				throw new Exception($this->path . ' exists, but is not a fifo');
			}
		}
	}
	
	public function open( $mode ) {
		$this->recreate();
		
		if ($mode !== self::MODE_READ && $mode !== self::MODE_WRITE) {
			throw new Exception('Invalid mode: ' . $mode);
		}
		
		if ($this->fh && $mode !== $this->currentMode) {
			$this->close();
		} 

		if (!$this->fh) {
			$this->fh = fopen( $this->path, $mode );
			$this->currentMode = $mode;
		}
		
	}
	
	public function read() {
		$this->open( self::MODE_READ );
		
		return stream_get_contents( $this->fh );
	}
	
	public function write( $data ) {
		$this->open( self::MODE_WRITE );
		
		fwrite($this->fh, $data );
	}
	
	public function remove() {
		$this->close();
		
		if ($this->isCreated) {
			unlink( $this->path );
		
			$this->isCreated = false;
		}
	}
	
	public function close() {
		if ($this->currentMode !== NULL) {
			fclose($this->fh);
				
			$this->currentMode = NULL;
		}
	}
	
	// shutdown + __destruct handler
	public function cleanup() {
		try {
			$this->remove();
		} catch (Exception $e) {
			Log::write($e);
		}
	}
	
	public function __destruct() {
		if ($this->cleanup) {
			$this->cleanup();
		}
	}
}

?>