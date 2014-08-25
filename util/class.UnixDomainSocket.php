<?php
class UnixDomainSocket {	
	public static function generateFilename( $string ) {
		return '/tmp/stunix_' . md5($string);
	}
		
	protected $path;
	protected $socket;
		
	protected $errno;
	protected $errstr;
	
	protected $currentBlocking;
	protected $isServer;
		
	protected $currentConnection;
	protected $permissions;
		
	public function __construct( $name, $isServer = NULL, $permissions = NULL ) {
		$this->path = self::generateFilename($name);
		$this->isServer = $isServer === NULL ? NULL : (bool)$isServer;
		$this->permissions = $permissions;
	}
	
	public function open() {
		if (!$this->socket) { // we don't support persistent sockets for the moment
			$fileExists = file_exists($this->path);
		
			if ($this->isServer === NULL) { // auto detect if we should play server
				$this->isServer = !$fileExists;
			} elseif (($fileExists && $this->isServer) || (!$fileExists && !$this->isServer)) {
				throw new UnixDomainSocketFailedConnectingException( $this->errstr, $this->errno );
			}
		
			if ($this->isServer) {
				$this->socket = stream_socket_server( 'unix://' . $this->path, $this->errno, $this->errstr );
				
				if ($this->permissions !== NULL) {
					chmod($this->path, $this->permissions);
				}

			} else { // client
				try {
					$this->socket = stream_socket_client( 'unix://' . $this->path, $this->errno, $this->errstr, 1 );
				} catch(Exception $e) {
					throw new UnixDomainSocketFailedConnectingException( $this->errstr, $this->errno, $e );
				}
				$this->currentConnection =& $this->socket;
			}
			
			if ($this->socket === false) {
				// specialized exception so it can be caught individually
				throw new UnixDomainSocketFailedConnectingException( $this->errstr, $this->errno );
			}
		}
	}
	
	public function close() {
		if ($this->socket) {
			fclose($this->socket);
			
			if ($this->isServer) {
				unlink($this->path);
			}
			
			$this->socket = NULL;
		}
	}
	
	public function cleanRemaining() {
		if (!$this->socket && $this->isServer && file_exists($this->path)) {
			unlink($this->path);
		}
	}
	
	public function write( $data ) {
		$this->open();
		
		fwrite( $this->currentConnection, $data );	
	}
	
	public function read( $block = false ) {
		if ($block !== $this->currentBlocking) {
			stream_set_blocking($this->currentConnection, $block);
			$this->currentBlocking = $block;
		}
		
		return stream_get_contents( $this->currentConnection );
	}
	
	public function accept( $timeout = -1 ) { // -1 = disable timeout, 0 = return instantly
		$this->open();
		
		if ($this->isServer) {
			try {
				$this->currentConnection = stream_socket_accept( $this->socket, $timeout );
			} catch(Exception $e) {
				throw new UnixDomainSocketAcceptInterruptedException( $e->getMessage(), $e->getCode(), $e );
			}	
		}
		
		return true; // so we can use this function in loop
	}
	
	public function finish() {
		if ($this->currentConnection && $this->isServer) {
			fclose($this->currentConnection);
			$this->currentConnection = NULL;
		}
	}
	
	public function __destruct() {
		$this->close();
	}
}

class UnixDomainSocketFailedConnectingException extends Exception {}
class UnixDomainSocketAcceptInterruptedException extends Exception {}