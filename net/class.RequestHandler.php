<?php
/**
 * @package steroid\net
 */
 
/**
 * Basic handler for multiple parallel requests
 * 
 * Handles only https/http (1.0, no keep-alive) at the moment 
 *
 * @package steroid\net
 */
class RequestHandler {
	protected $maxSockets;
	protected $socketConnectTimeout;
	protected $autoReconnect;
	protected $streamSelectTimeout;
	protected $reconnectWait;
	
	const DEFAULT_MAX_SOCKETS = 4;
	const DEFAULT_SOCKET_TIMEOUT_FALLBACK = 30;
	const DEFAULT_AUTO_RECONNECT = 1;
	const DEFAULT_STREAM_SELECT_TIMEOUT = 20;
	const DEFAULT_RECONNECT_WAIT = 10000;
	
	protected static $defaultSocketTimeout;
	
	final public function __construct( $maxSockets = NULL, $socketTimeout = NULL, $autoReconnect = NULL, $streamSelectTimeout = NULL, $reconnectWait = NULL ) {
		$this->maxSockets = intval($maxSockets);
		
		if ($this->maxSockets <= 0) {
			$this->maxSockets = self::DEFAULT_MAX_SOCKETS;
		}
		
		if (self::$defaultSocketTimeout === NULL) {
			self::$defaultSocketTimeout = ini_get("default_socket_timeout");
		
			if (!self::$defaultSocketTimeout || intval(self::$defaultSocketTimeout) <= 0) {
				self::$defaultSocketTimeout =  self::DEFAULT_SOCKET_TIMEOUT_FALLBACK;;
			}
		}
		
		$this->socketConnectTimeout = intval($socketTimeout);
		
		if ($this->socketConnectTimeout <= 0) {
			$this->socketConnectTimeout = self::$defaultSocketTimeout;
		}
		
		if ($autoReconnect === NULL) {
			$this->autoReconnect = self::DEFAULT_AUTO_RECONNECT;
		} else {
			$this->autoReconnect = $autoReconnect;		
		}
		
		if ($streamSelectTimeout === NULL) {
			$this->streamSelectTimeout = self::DEFAULT_STREAM_SELECT_TIMEOUT;
		} else {
			$this->streamSelectTimeout = $streamSelectTimeout;
		}
		
		if ($reconnectWait === NULL) {
			$this->reconnectWait = self::DEFAULT_RECONNECT_WAIT;
		} else {
			$this->reconnectWait = $reconnectWait;
		}
	}
	
	
	// used to make multiple requests in parallel
	// TODO: use keep-alive
	// TODO: cleanup?
	final public function multiRequest( array $requests ) {
		if (!$requests) {
			return array();			
		}
		
		static $useCurl;
		
		if ($useCurl === NULL) {
			$useCurl = function_exists('curl_init') && function_exists('curl_setopt') && function_exists('curl_multi_init') 
			&& function_exists('curl_multi_add_handle') && function_exists('curl_multi_exec') && function_exists('curl_multi_remove_handle')
			&& function_exists('curl_multi_close');
		}
		
	
		return $useCurl ? $this->multiRequest_curl( $requests ) : $this->multiRequest_streams( $requests );

	}
	
	
	final public function multiRequest_curl( array $requests ) {
		$curlHandles = array();
		$ret = array();
		$curlMultiHandle = curl_multi_init();
		
		foreach ($requests as $request) {
			$curlHandle = curl_init();
			
			$url = is_array($request) ? $request['url'] : $request;
			
			curl_setopt($curlHandle, CURLOPT_URL, $url);
			curl_setopt($curlHandle, CURLOPT_AUTOREFERER, true);
			curl_setopt($curlHandle, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curlHandle, CURLOPT_HEADER, false);
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $this->socketConnectTimeout);
			curl_setopt($curlHandle, CURLOPT_MAXREDIRS, 5);
			
			
			curl_multi_add_handle( $curlMultiHandle, $curlHandle );
			
			$curlHandles[] = $curlHandle;	
		}
		
		
		// code from hhvm docs
		/*
		$active = null;
		// execute the handles
		
		do {
			$mrc = curl_multi_exec($curlMultiHandle, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		
		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($curlMultiHandle) != -1) {
				do {
					$mrc = curl_multi_exec($curlMultiHandle, $active);
				} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			}
		}
		 */

		$infos = array();
		 
		do {
			$status = curl_multi_exec($curlMultiHandle, $active);
		} while ($status === CURLM_CALL_MULTI_PERFORM || $active);
		 
		 
		// cycle handles to close them and get contents
		foreach ($curlHandles as $i => $curlHandle) {
			$ret[] = curl_multi_getcontent($curlHandle);
		
			curl_multi_remove_handle($curlMultiHandle, $curlHandle);
			curl_close($curlHandle);
		}
		
		
		curl_multi_close($curlMultiHandle);
		
		return $ret;
	}
	
	final public function multiRequest_streams( array $requests ) {
		foreach ($requests as &$request) {
			if (!is_array($request)) {
				$request = array( 'url' => $request );
			}
			
			if (!isset($request['urlParts'])) {								 				
				$request['urlParts'] = parse_url($url);
			}
		}
		
		$reconnectTries = array();
		$sockets = array();
		$ret = array();
		$out = array();
		
		$readableSockets = array();
		$writableSockets = array();
		
		$nextQuery = 0;
		$countQueries = count($requests);
		
		while($nextQuery < $countQueries) {
			while(($nextQuery < $countQueries) && (count($sockets) < $this->maxSockets)) {
				$request =& $requests[$nextQuery];

				$url = $request['url'];
				$urlParts = $request['urlParts'];
				
				// needed to support https
				$scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] : 'http';
				
				if ($scheme !== 'https') {
					$scheme = 'http';
				}
				// TODO: add support for custom header
				// TODO: add support for post data
				
				if (empty($urlParts['host'])) {
					throw new Exception('Unable to parse host from url:"' . $url . '"');
				}
				
				$port = isset($urlParts['port']) ? (int)$urlParts['port'] : ( $scheme === 'https' ? 443 : 80 );
				$transport = 'tcp';
				
				// we don't use tls/ssl wrappers, as those can't connect async	
				$connectTarget = $transport . '://' . $urlParts['host'] . ':' . $port;
				
				$flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
				$contextOptions = array();
				
				if ($scheme === 'https') {
					$host = $urlParts['host'];
					// $host = 'www.yahoo.com'; // DEBUG ONLY
					
					$contextOptions['ssl'] = array( 
						'verify_peer' => false, // should be true for security, but php implementation of ssl is so buggy ...
						'verify_host' => false, // should be true for security, but php implementation of ssl is so buggy ...
					//	'ciphers' => 'HIGH:!SSLv2:!SSLv3',
						'disable_compression' => true,       // prevent TLS compression attack (CRIME attack vector)
					);
					
					if (PHP_VERSION_ID < 50600) {					
						$contextOptions['ssl']['CN_match'] = $host;
						$contextOptions['ssl']['SNI_server_name'] = $host;
					} else {
						$contextOptions['ssl']['peer_name'] = $host;
					}
				} 
				
				$connectContext = stream_context_create($contextOptions);

				
				$socket = stream_socket_client( $connectTarget, $errno, $errstr, $this->socketConnectTimeout, $flags, $connectContext );
				
				stream_set_read_buffer( $socket, 0 );
	
				if ($socket === FALSE || $errno !== 0) {
					throw new Exception('Dead socket #' . $errno . ': ' . $errstr);
				}
				
				stream_set_blocking($socket, false);

				$queryUrl = $urlParts['path'];
				
				if (!empty($urlParts['query'])) {
					$queryUrl .= '?' . $urlParts['query'];
				}
		
				if (!isset($request['num'])) {
					$request['num'] = $nextQuery;	
				}
						
				$sockets[] = array( // TODO: use keep-alive (HTTP/1.1)
					'send' => "GET " . $queryUrl . " HTTP/1.0\r\nHost: " . $urlParts['host'] . "\r\nConnection: Close\r\n\r\n",
					'num' => $request['num'],
					'socket' => $socket,
					'errno' => &$errno,
					'errstr' => &$errstr,
					'crypto' => $scheme === 'https',
					'cryptoDone' => false
				);
				
				$writableSockets[] = $socket;
				
				$out[$request['num']] = '';
				
				$nextQuery++;
				
			}
			
			$done = false;
			
			while (!$done) {
				if (!$readableSockets && !$writableSockets) {
					throw new Exception( 'Unexpected out of sockets.' );
				}
				
				$readOn = $readableSockets;
				$writeOn = $writableSockets;
				
				$e = NULL;
				
				// wait MAX $this->streamSelectTimeout seconds till something happens
				$n = stream_select( $readOn, $writeOn, $e, $this->streamSelectTimeout );
				
				if ($n !== false && $n > 0) {
					foreach( $writeOn as $socket ) {
						foreach ($sockets as $d) { // get socket description
							if ($d['socket'] == $socket) {
								$socketDesc = $d;
								break;
							}
						}
						
						if ( $socketDesc['crypto'] === true && $socketDesc['cryptoDone'] === false ) {
							// TODO: support other crypto than TLS as well
							$enableCryptoReturnValue = stream_socket_enable_crypto( $socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT );
							
							if ($enableCryptoReturnValue === true) {
								// handshake done, can continue with writing data
								$socketDesc['cryptoDone'] = true;
							} else if ($enableCryptoReturnValue === false) {
								throw new Exception( "Enabling crypto failed."   );
							} else {
								// handshake not finished yet, need to do another loop
								continue;
							}
						}
						
						fwrite( $socket, $socketDesc['send'] );
						unset($socketDesc['send']);
						
						fflush( $socket );
						
						$k = array_search( $socket, $writableSockets );
						unset($writableSockets[$k]);
						
						$readableSockets[] = $socket;
					}
					
					foreach( $readOn as $socket ) {
						foreach ($sockets as $key => $d) { // get socket description
							if ($d['socket'] === $socket) {
								$k = $key;
								$socketDesc = $d;
								break;
							}
						}
						
						$read = fread( $socket, 8192 );
						
						if (strlen($read) === 0) {
							// should not be needed, but somehow with async https there 
							// is a remainder most of the time which we will read with 
							// this call
							stream_set_blocking($socket, true);
							$out[$socketDesc['num']] .= stream_get_contents($socket);
							
							fclose($socket);
	
							unset($readableSockets[ array_search( $socket, $readableSockets ) ]);
							
							// FIXME: substr makes a copy of string
							$out[$socketDesc['num']] = substr($out[$socketDesc['num']], strpos($out[$socketDesc['num']], "\r\n\r\n") + 4); // strip header
							$ret[$socketDesc['num']] = $out[$socketDesc['num']];
							unset($out[$socketDesc['num']]);

						
							unset($sockets[$k]);
						
								
							if ($nextQuery < $countQueries || count($sockets) === 0) { // time to open new connection OR finish up
								$done = true;
								// dont break here - we want to handle all sockets
							} 
						} else { // TODO: skip header while reading would be more efficient than substr call
							$out[$socketDesc['num']] .= $read;
						} 
						
					}
				} else {
					// $n === 0 in case timeout reached, or $n === false in case of error
					
					if ($this->autoReconnect) {
						$openSockets = array_merge($readableSockets, $writableSockets);
						
						$readableSockets = array();
						$writableSockets = array();
						
							
						foreach ($openSockets as $socket) {
							foreach ($sockets as $key => $d) { // get socket description
								if ($d['socket'] === $socket) {
									$k = $key;
									$socketDesc = $d;
									break;
								}
							}
							
							if (!isset( $reconnectTries[$socketDesc['num']] )) {
								$reconnectTries[$socketDesc['num']] = 1;
							} else {
								$reconnectTries[$socketDesc['num']] ++;
							}
							
							if ($reconnectTries[$socketDesc['num']] > $this->autoReconnect) {
								throw new Exception( 'Reached maximum numbers of reconnects.' );
							}
							
							// close connection
							if (is_resource($socket)) {
								fclose($socket);
							}
							
							unset($sockets[$k]);
							
							// put back on stack
							$requests[] = $requests[ $socketDesc['num'] ];
							
							usleep($this->reconnectWait); // sleep, so we don't reconnect right away
						}
							
						$countQueries = count($requests);
						break;
						
					} else {
						throw new Exception( 'Reconnect disabled; Timed out waiting ; remaining Sockets (r/w): '. count($readableSockets) . '/' . count($writableSockets) . '; return value: ' . ($n === 0 ? '0' : 'FALSE'));
					}
				}
				
				
			}
		}
		
		return $ret;
	}
}
