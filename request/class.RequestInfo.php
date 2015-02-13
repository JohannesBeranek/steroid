<?php

require_once STROOT . '/request/interface.IRequestInfo.php';
require_once STROOT . '/file/class.Filename.php';

require_once STROOT . '/domain/class.RCDomain.php';
require_once STROOT . '/domaingroup/class.RCDomainGroup.php';

class RequestInfo implements IRequestInfo {
	const PROXY_SAFE_REMOTE_ADDR = 'proxy_safe_remote_addr';
	const PROXY_SAFE_HTTP_HOST = 'proxy_safe_http_host';
	const PROXY_SAFE_IS_HTTPS = 'proxy_safe_is_https';
	const FULL_URL = 'full_url';
	const PROXY_SAFE_FULL_URL = 'proxy_safe_full_url';
	
	/** @var float */
	protected $requestTime; // float since PHP 5.4.0
	/** @var bool */
	protected $isHttps;
	/** @var string */
	protected $requestMethod;
	/** @var string */
	protected $requestURI;
	/** @var string */
	protected $protocol;
	/** @var string */
	protected $httpHost;
	/** @var int */
	protected $port;
	/** @var string */
	protected $pagePath;
	/** @var string */
	protected $queryString;
	/** @var array */
	protected $queryParams;
	/** @var array */
	protected $postParams;
	/** @var array */
	protected $fileInfos;
	
	/** @var array */
	protected $fileInfoObjects;
	
	/** @var array */
	protected $serverInfo;
	
	/** @var array */
	protected $cookies;
	
	
	protected $domainRecord;
	
	
	protected static $currentRequestInfo;
	
	public static function requestInfoFromContext( array $context = NULL ) {
		if ($context === NULL) {
			$context = array( '_POST' => $_POST, '_GET' => $_GET, '_FILES' => $_FILES, '_COOKIE' => $_COOKIE, '_ENV' => $_ENV, '_SERVER' => $_SERVER );
		}
		
		return new RequestInfo(
			$context['_SERVER']['REQUEST_TIME'],
			!empty($context['_SERVER']['HTTPS']) && $context['_SERVER']['HTTPS'] != 'off',
			$context['_SERVER']['REQUEST_METHOD'],
			$context['_SERVER']['REQUEST_URI'],
			$context['_SERVER']['SERVER_PROTOCOL'],
			!empty($context['_SERVER']['HTTP_X_FORWARDED_HOST']) ? $context['_SERVER']['HTTP_X_FORWARDED_HOST'] : $context['_SERVER']['HTTP_HOST'],
			$context['_SERVER']['SCRIPT_NAME'],
			$context['_SERVER']['QUERY_STRING'],
			$context['_GET'],
			$context['_POST'],
			$context['_FILES'],
			$context['_SERVER'],
			$context['_COOKIE']
		);
	}
	
	/**
	 * 
	 * @param float $requestTime Float since php 5.4
	 * @param bool $https
	 * @param string $requestMethod
	 * @param string $requestURI
	 * @param string $protocol
	 * @param string $httpHost
	 * @param string $pagePath
	 * @param string $queryString
	 */
	
	public function __construct( $requestTime, $https, $requestMethod, $requestURI, $protocol, $httpHost, $pagePath = NULL, $queryString = NULL, array $queryParams = NULL, array $postParams = NULL, array $fileInfos = NULL, array $serverInfo = NULL, array $cookies = NULL ) {
		$this->requestTime = floatval($requestTime);
		$this->isHttps = (bool)$https;
		
		$this->requestMethod = $requestMethod;
		$this->requestURI = $requestURI;
		$this->protocol = $protocol;
		
		$httpHostParts = explode(':', $httpHost);
		
		if ($httpHostParts) {
			$httpHost = reset($httpHostParts);
			$port = next($httpHostParts);	// might return null!
		} 
		
		if (!isset($port)) {
			$port = $this->isHttps ? 443 : 80; // default port
		}
		
		
		$this->httpHost = $httpHost;
		$this->port = $port;
		
		// optional components, will be extracted if not provided
		
		// security related filtering + resolving of '/..'
		$this->pagePath = Filename::resolvePath( $pagePath !== NULL ? $pagePath : ('/' . trim(array_shift(explode('?', $this->requestURI)), '/')));
		
		
		$this->queryString = $queryString !== NULL ? $queryString : (($queryString = strpbrk($this->requestURI, "?")) === FALSE ? '' : substr($queryString, 1));
		
		if (is_array($queryParams)) {
			$this->queryParams = $queryParams;
		} else {
			$this->queryParams = array();
			parse_str($this->queryString, $this->queryParams);
		}
		
		$this->fileInfoObjects = array();
		
		$this->postParams = $postParams ? $postParams : array();
		$this->fileInfos = $fileInfos ? $this->rebuildFilesArray($fileInfos) : array();
		
		if ($fileInfos) {
			$this->postParams = array_merge_recursive( $this->postParams, $this->fileInfos );
		}
		
		$this->serverInfo = $serverInfo ? $serverInfo : array();
		
		$this->cookies = $cookies ? $cookies : array();
		
		self::$currentRequestInfo = $this;
	}

	public function rewrite( $newUrl ) {
		$this->requestURI = $newUrl;
		
		$pagePathParts = explode('?', $this->requestURI);
		
		$this->pagePath = ('/' . trim(array_shift($pagePathParts), '/'));
		$this->queryString = (($queryString = strpbrk($this->requestURI, "?")) === FALSE ? '' : substr($queryString, 1));
		
		$this->queryParams = array();
		parse_str($this->queryString, $this->queryParams);
	}
	
	/**
	 * Get current instance of requestInfo
	 * 
	 * @return IRequestInfo
	 */
	public static function getCurrent() { return self::$currentRequestInfo; }
	
	public function getRequestTime() { return $this->requestTime; }
	
	/**
	 * are we serving via https?
	 * 
	 * use getServerInfo( PROXY_SAFE_IS_HTTPS ) for proxy safe version!
	 * 
	 * @return bool
	 */
	public function isHTTPS() { return $this->isHttps; }
	public function getRequestMethod() { return $this->requestMethod; }
	public function getRequestURI() { return $this->requestURI; }
	public function getProtocol() { return $this->protocol; }
	public function getHTTPHost() { return $this->httpHost; }
	public function getPagePath() { return $this->pagePath; }
	public function getQueryString() { return $this->queryString; }
		
	public function getQueryParam( $name, $unsetValue = NULL ) {
		return isset($this->queryParams[$name]) ? $this->queryParams[$name] : $unsetValue;
	}
	
	public function getPostParam( $name, $unsetValue = NULL ) {
		return isset($this->postParams[$name]) ? $this->postParams[$name] : $unsetValue;
	}

	public function addQueryParams( array $params ) {
		$this->queryParams = array_merge( $this->queryParams, $params );
	}

	public function getPost() {
		return $this->postParams;
	}
	
	public function getQueryParams() {
		return $this->queryParams;
	}
	
	public function getGPParam( $name, $unsetValue = NULL ) {
		return isset($this->queryParams[$name]) ? $this->queryParams[$name] : ( isset($this->postParams[$name]) ? $this->postParams[$name] : $unsetValue );
	}
	
	public function getPGParam( $name, $unsetValue = NULL ) {
		return isset($this->postParams[$name]) ? $this->postParams[$name]  : (  isset($this->queryParams[$name]) ? $this->queryParams[$name] : $unsetValue );
	}
	
	public function getFileInfo( $name, $unsetValue = NULL ) {
		return isset($this->fileInfos[$name]) ? $this->fileInfos[$name] : $unsetValue;
	}
	
	public function getFileInfoForFile( $tmpName ) {
		if (array_key_exists($tmpName, $this->fileInfoObjects)) {
			return $this->fileInfoObjects[$tmpName];
		}
		
		return null;
	}
	
	public function getServerInfo( $name, $unsetValue = NULL ) {
		switch ($name) {
			case self::PROXY_SAFE_REMOTE_ADDR:
				$ret = isset($this->serverInfo['HTTP_X_FORWARDED_FOR']) ? $this->serverInfo['HTTP_X_FORWARDED_FOR'] : (isset($this->serverInfo['REMOTE_ADDR']) ? $this->serverInfo['REMOTE_ADDR'] : $unsetValue);
			break;
			case self::PROXY_SAFE_HTTP_HOST:
				$ret = isset($this->serverInfo['HTTP_X_FORWARDED_HOST']) ? $this->serverInfo['HTTP_X_FORWARDED_HOST'] : (isset($this->serverInfo['HTTP_HOST']) ? $this->serverInfo['HTTP_HOST'] : $unsetValue);	
			break;
			case self::PROXY_SAFE_IS_HTTPS:
				$ret = !empty($this->serverInfo['HTTP_X_FORWARDED_PROTO']) && $this->serverInfo['HTTP_X_FORWARDED_PROTO'] === 'https' ? true : $this->isHttps;
			break;
			case self::FULL_URL:
				if (!isset($this->serverInfo['HTTP_HOST']) || !isset($this->serverInfo['REQUEST_URI'])) {
					$ret = NULL;
				} else {
				
				$ret = 
					((!empty($this->serverInfo['HTTPS']) && $this->serverInfo['HTTPS'] !== 'off') ? 'https' : 'http') .
					'://' . $this->serverInfo['HTTP_HOST'] . $this->serverInfo['REQUEST_URI'];
				}	
			break;
			case self::PROXY_SAFE_FULL_URL:
				$ret = ($this->getServerInfo(self::PROXY_SAFE_IS_HTTPS) ? 'https' : 'http') . '://' . $this->getServerInfo(self::PROXY_SAFE_HTTP_HOST) . $this->serverInfo['REQUEST_URI'];
			break;
			default:
				$ret = isset($this->serverInfo[$name]) ? $this->serverInfo[$name] : $unsetValue;
		}
		
		return $ret;
	}
	
	public function getCookie( $name ) {
		return isset($this->cookies[$name]) ? $this->cookies[$name] : NULL;
	}
	
	public function setCookie( $name, $value = NULL, $expire = 0, $path = NULL, $domain = NULL, $secure = NULL, $httponly = NULL ) {
		if ( $path === NULL ) {
			$path = '/';
		}
		
		if ($domain === NULL) {
			$domain = $this->httpHost;
		}
		
		if ($secure === NULL) {
			$secure = false;
		}
		
		if ($httponly === NULL) {
			$httponly = true; // different from php default, but more secure and works in 99.9% of the real use cases
		}
		
		setcookie( $name, $value , $expire, $path, $domain, $secure, $httponly );
		
		if (strpos($this->httpHost, $domain) !== false && strpos($this->pagePath, $path) === 0) {
			if ($expire > $this->requestTime || $expire === 0) {
				$this->cookies[$name] = $value;
			} else {
				unset($this->cookies[$name]);
			}
		}
	}
	
	public function deleteCookie( $name, $path = NULL, $domain = NULL, $secure = NULL, $httponly = NULL ) {
		$this->setCookie( $name, '', 1, $path, $domain, $secure, $httponly );
	}
	
	/**
	 * get domain record
	 * 
	 * @return null|RCDomain
	 */
	public function getDomainRecord() {
		return $this->domainRecord;
	}
	
	public function setDomainRecord( RCDomain $domainRecord ) {
		$this->domainRecord = $domainRecord;
	}
	
	/**
	 * get domain group record
	 * 
	 * @return null|RCDomainGroup
	 */
	public function getDomainGroupRecord() {
		return $this->domainRecord->domainGroup;
	}
	
	// Util functions, which should only be used here
	protected function rebuildFilesArray( $files ) {
		if (!is_array($files)) return $files;
	
		if (array_key_exists('name', $files) && array_key_exists('tmp_name', $files) && array_key_exists('size', $files) && array_key_exists('error', $files)) {
			if (is_array($files['name'])) {
				$newArr = array();
	
				foreach ($files['name'] as $k => $v) {
					$newArr[$k] = array();
	
					foreach ($files as $ak => $av) {
						$newArr[$k][$ak] = $files[$ak][$k];
					}
				}
	
				return $this->rebuildFilesArray($newArr);
			} else { // proper structure already
				$this->fileInfoObjects[$files['tmp_name']] = $files;
				
				return $files;
			}
		}
	
		foreach ($files as $k => $v) {
			$files[$k] = $this->rebuildFilesArray($v);
		}
	
		return $files;
	}
}


?>
