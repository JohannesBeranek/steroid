<?php
if (!extension_loaded("curl") && !function_exists("curl_init")) {



/* HTTP Retriever
 * Version v1.1.10
 * Copyright 2004-2007, Steve Blinch
 * http://code.blitzaffe.com
 * 
 * Modified 2012 to fit steroid needs
 * ============================================================================
 *
 *
 * LICENSE
 *
 * This script is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *	
 * You should have received a copy of the GNU General Public License along
 * with this script; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

// define user agent ID's
define('UA_EXPLORER', 0);
define('UA_MOZILLA', 1);
define('UA_FIREFOX', 2);
define('UA_OPERA', 3);

// define progress message severity levels
define('HRP_DEBUG', 0);
define('HRP_INFO', 1);
define('HRP_ERROR', 2);

class HTTPRetriever {
	public $error;
	public $result_code;
	
	public $response_headers = array();
	
	// Constructor
	public function HTTPRetriever() {
		// default HTTP headers to send with all requests
		$this->headers = array(
			"Referer"=>"",
			"User-Agent"=>"HTTPRetriever/1.0",
			"Connection"=>"close"
		);
		
		// HTTP version (has no effect if using CURL)
		$this->version = "1.1";
		
		// Normally, CURL is only used for HTTPS requests; setting this to
		// TRUE will force CURL for HTTP requests as well.  Not recommended.
		$this->force_curl = false;	
		
		// If you don't want to use CURL at all, set this to TRUE.
		$this->disable_curl = false;
		
		// If HTTPS request return an error message about SSL certificates in
		// $this->error and you don't care about security, set this to TRUE
		$this->insecure_ssl = false;
		
		// Set the maximum time to wait for a connection
		$this->connect_timeout = 15;
		
		// Set the maximum time to allow a transfer to run, or 0 to disable.
		$this->max_time = 0;
		
		// Set the maximum time for a socket read/write operation, or 0 to disable.
		$this->stream_timeout = 0;
		
		// If you're making an HTTPS request to a host whose SSL certificate
		// doesn't match its domain name, AND YOU FULLY UNDERSTAND THE
		// SECURITY IMPLICATIONS OF IGNORING THIS PROBLEM, set this to TRUE.
		$this->ignore_ssl_hostname = false;
		
		// If TRUE, the get() and post() methods will close the connection
		// and return immediately after receiving the HTTP result code
		$this->result_close = false;
		

		
		// Set these to perform basic HTTP authentication
		$this->auth_username = '';
		$this->auth_password = '';

		// Optionally set this to a valid callback method to have HTTPRetriever
		// provide page preprocessing capabilities to your script.  If set, this
		// method should accept two arguments: an object representing an instance
		// of HTTPRetriever, and a string containing the page contents
		$this->page_preprocessor = null;
		
		// Optionally set this to a valid callback method to have HTTPRetriever
		// provide progress messages.  Your callback must accept 2 parameters:
		// an integer representing the severity (0=debug, 1=information, 2=error),
		// and a string representing the progress message
		$this->progress_callback = null;
		
		// Optionally set this to a valid callback method to have HTTPRetriever
		// provide bytes-transferred messages.  Your callbcak must accept 2
		// parameters: an integer representing the number of bytes transferred,
		// and an integer representing the total number of bytes expected (or
		// -1 if unknown).
		$this->transfer_callback = null;
		
		// Set this to TRUE if you HTTPRetriever to transparently follow HTTP
		// redirects (code 301, 302, 303, and 307).  Optionally set this to a
		// numeric value to limit the maximum number of redirects to the specified
		// value.  (Redirection loops are detected automatically.)
		// Note that non-GET/HEAD requests will NOT be redirected except on code
		// 303, as per HTTP standards.
		$this->follow_redirects = false;
	}
	
	// Send an HTTP GET request to $url; if $ipaddress is specified, the
	// connection will be made to the selected IP instead of resolving the 
	// hostname in $url.
	//
	// If $cookies is set, it should be an array in one of two formats.
	//
	// Either: $cookies[ 'cookiename' ] = array (
	//		'/path/'=>array(
	//			'expires'=>time(),
	//			'domain'=>'yourdomain.com',
	//			'value'=>'cookievalue'
	//		)
	// );
	//
	// Or, a more simplified format:
	//	$cookies[ 'cookiename' ] = 'value';
	//
	// The former format will automatically check to make sure that the path, domain,
	// and expiration values match the HTTP request, and will only send the cookie if
	// they do match.  The latter will force the cookie to be set for the HTTP request
	// unconditionally.
	// 
	public function get($url,$ipaddress = false,$cookies = false) {
		$this->method = "GET";
		$this->post_data = "";
		$this->connect_ip = $ipaddress;
		return $this->_execute_request($url,$cookies);
	}
	
	// Send an HTTP POST request to $url containing the POST data $data.  See ::get()
	// for a description of the remaining arguments.
	public function post($url,$data="",$ipaddress = false,$cookies = false) {
		$this->method = "POST";
		$this->post_data = $data;
		$this->connect_ip = $ipaddress;
		return $this->_execute_request($url,$cookies);
	}
	
	// Send an HTTP HEAD request to $url.  See ::get() for a description of the arguments.	
	public function head($url,$ipaddress = false,$cookies = false) {
		$this->method = "HEAD";
		$this->post_data = "";
		$this->connect_ip = $ipaddress;
		return $this->_execute_request($url,$cookies);
	}
		
	// send an alternate (non-GET/POST) HTTP request to $url
	public function custom($method,$url,$data="",$ipaddress = false,$cookies = false) {
		$this->method = $method;
		$this->post_data = $data;
		$this->connect_ip = $ipaddress;
		return $this->_execute_request($url,$cookies);
	}	
	
	public function array_to_query($arrayname,$arraycontents) {
		$output = "";
		foreach ($arraycontents as $key=>$value) {
			if (is_array($value)) {
				$output .= $this->array_to_query(sprintf('%s[%s]',$arrayname,urlencode($key)),$value);
			} else {
				$output .= sprintf('%s[%s]=%s&',$arrayname,urlencode($key),urlencode($value));
			}
		}
		return $output;
	}
	
	// builds a query string from the associative array array $data;
	// returns a string that can be passed to $this->post()
	public function make_query_string($data) {
		$output = "";
		if (is_array($data)) {
			foreach ($data as $name=>$value) {
				if (is_array($value)) {
					$output .= $this->array_to_query(urlencode($name),$value);
				} elseif (is_scalar($value)) {
					$output .= urlencode($name)."=".urlencode($value)."&";
				} else {
					$output .= urlencode($name)."=".urlencode(serialize($value)).'&';
				}
			}
		}
		return substr($output,0,strlen($output)-1);
	}

	
	// this is pretty limited... but really, if you're going to spoof you UA, you'll probably
	// want to use a Windows OS for the spoof anyway
	//
	// if you want to set the user agent to a custom string, just assign your string to
	// $this->headers["User-Agent"] directly
	public function set_user_agent($agenttype,$agentversion,$windowsversion) {
		$useragents = array(
			"Mozilla/4.0 (compatible; MSIE %agent%; Windows NT %os%)", // IE
			"Mozilla/5.0 (Windows; U; Windows NT %os%; en-US; rv:%agent%) Gecko/20040514", // Moz
			"Mozilla/5.0 (Windows; U; Windows NT %os%; en-US; rv:1.7) Gecko/20040803 Firefox/%agent%", // FFox
			"Mozilla/4.0 (compatible; MSIE 6.0; Windows NT %os%) Opera %agent%  [en]", // Opera
		);
		$agent = $useragents[$agenttype];
		$this->headers["User-Agent"] = str_replace(array("%agent%","%os%"),array($agentversion,$windowsversion),$agent);
	}
	
	
	
	
	public function parent_path($path) {
		if (substr($path,0,1)=='/') $path = substr($path,1);
		if (substr($path,-1)=='/') $path = substr($path,0,strlen($path)-1);
		$path = explode('/',$path);
		array_pop($path);
		return count($path) ? ('/' . implode('/',$path)) : '';
	}
	
	// $cookies should be an array in one of two formats.
	//
	// Either: $cookies[ 'cookiename' ] = array (
	//		'/path/'=>array(
	//			'expires'=>time(),
	//			'domain'=>'yourdomain.com',
	//			'value'=>'cookievalue'
	//		)
	// );
	//
	// Or, a more simplified format:
	//	$cookies[ 'cookiename' ] = 'value';
	//
	// The former format will automatically check to make sure that the path, domain,
	// and expiration values match the HTTP request, and will only send the cookie if
	// they do match.  The latter will force the cookie to be set for the HTTP request
	// unconditionally.
	// 	
	public function response_to_request_cookies($cookies,$urlinfo) {
		
		// check for simplified cookie format (name=value)
		$cookiekeys = array_keys($cookies);
		if (!count($cookiekeys)) return;
		
		$testkey = array_pop($cookiekeys);
		if (!is_array($cookies[ $testkey ])) {
			foreach ($cookies as $k=>$v) $this->request_cookies[$k] = $v;
			return;
		}
		
		// must not be simplified format, so parse as complex format:
		foreach ($cookies as $name=>$paths) {
			foreach ($paths as $path=>$values) {
				// make sure the cookie isn't expired
				if ( isset($values['expires']) && ($values['expires']<time()) ) continue;
				
				$cookiehost = $values['domain'];
				$requesthost = $urlinfo['host'];
				// make sure the cookie is valid for this host
				$domain_match = (
					($requesthost==$cookiehost) ||
					(substr($requesthost,-(strlen($cookiehost)+1))=='.'.$cookiehost)
				);				
				
				// make sure the cookie is valid for this path
				$cookiepath = $path; if (substr($cookiepath,-1)!='/') $cookiepath .= '/';
				$requestpath = $urlinfo['path']; if (substr($requestpath,-1)!='/') $requestpath .= '/';
				if (substr($requestpath,0,strlen($cookiepath))!=$cookiepath) continue;
				
				$this->request_cookies[$name] = $values['value'];
			}
		}
	}					
	
	// Execute the request for a particular URL, and transparently follow
	// HTTP redirects if enabled.  If $cookies is specified, it is assumed
	// to be an array received from $this->response_cookies and will be
	// processed to determine which cookies are valid for this host/URL.
	protected function _execute_request($url,$cookies = false) {
		// valid codes for which we transparently follow a redirect
		$redirect_codes = array(301,302,303,307);
		// valid methods for which we transparently follow a redirect
		$redirect_methods = array('GET','HEAD');

		$request_result = false;
		
		$this->followed_redirect = false;
		$this->response_cookies = array();
		$this->cookie_headers = '';

		$previous_redirects = array();
		do {
			// send the request
			$request_result = $this->_send_request($url,$cookies);
			$lasturl = $url;
			$url = false;

			// see if a redirect code was received
			if ($this->follow_redirects && in_array($this->result_code,$redirect_codes)) {
				
				// only redirect on a code 303 or if the method was GET/HEAD
				if ( ($this->result_code==303) || in_array($this->method,$redirect_methods) ) {
					
					// parse the information from the OLD URL so that we can handle
					// relative links
					$oldurlinfo = parse_url($lasturl);
					
					$url = $this->response_headers['Location'];
					
					// parse the information in the new URL, and fill in any blanks
					// using values from the old URL
					$urlinfo = parse_url($url);
					foreach ($oldurlinfo as $k=>$v) {
						if (!$urlinfo[$k]) $urlinfo[$k] = $v;
					}
					
					// create an absolute path
					if (substr($urlinfo['path'],0,1)!='/') {
						$baseurl = $oldurlinfo['path'];
						if (substr($baseurl,-1)!='/') $baseurl = $this->parent_path($url) . '/';
						$urlinfo['path'] = $baseurl . $urlinfo['path'];
					}
					
					// rebuild the URL
					$url = $this->rebuild_url($urlinfo);

					$this->method = "GET";
					$this->post_data = "";
					
					$this->progress(HRP_INFO,'Redirected to '.$url);
				}
			}
			
			if ( $url && strlen($url) ) {
				
				if (isset($previous_redirects[$url])) {
					$this->error = "Infinite redirection loop";
					$request_result = false;
					break;
				}
				if ( is_numeric($this->follow_redirects) && (count($previous_redirects)>$this->follow_redirects) ) {
					$this->error = "Exceeded redirection limit";
					$request_result = false;
					break;
				}

				$previous_redirects[$url] = true;
			}

		} while ($url && strlen($url));

		// clear headers that shouldn't persist across multiple requests
		$per_request_headers = array('Host','Content-Length');
		foreach ($per_request_headers as $k=>$v) unset($this->headers[$v]);
		
		if (count($previous_redirects)>1) $this->followed_redirect = array_keys($previous_redirects);
		
		return $request_result;
	}
	
	// private - sends an HTTP request to $url
	protected function _send_request($url,$cookies = false) {
		$this->progress(HRP_INFO,"Initiating {$this->method} request for $url");

		$time_request_start = $this->getmicrotime();
		
		$urldata = parse_url($url);
		$this->urldata = &$urldata;
		$http_host = $urldata['host'] . (isset($urldata['port']) ? ':'.$urldata['port'] : '');
		
		if (!isset($urldata["port"]) || !$urldata["port"]) $urldata["port"] = ($urldata["scheme"]=="https") ? 443 : 80;
		if (!isset($urldata["path"]) || !$urldata["path"]) $urldata["path"] = '/';
		
		if (!empty($urldata['user'])) $this->auth_username = $urldata['user'];
		if (!empty($urldata['pass'])) $this->auth_password = $urldata['pass'];
		
		//echo "Sending HTTP/{$this->version} {$this->method} request for ".$urldata["host"].":".$urldata["port"]." page ".$urldata["path"]."<br>";
		
		if ($this->version>"1.0") $this->headers["Host"] = $http_host;
		if ($this->method=="POST") {
			$this->headers["Content-Length"] = $this->post_data === NULL ? 0 : strlen($this->post_data);
			if (!isset($this->headers["Content-Type"])) $this->headers["Content-Type"] = "application/x-www-form-urlencoded";
		}
		
		if ( !empty($this->auth_username) || !empty($this->auth_password) ) {
			$this->headers['Authorization'] = 'Basic '.base64_encode($this->auth_username.':'.$this->auth_password);
		} else {
			unset($this->headers['Authorization']);
		}
		
		if (is_array($cookies)) {
			$this->response_to_request_cookies($cookies,$urldata);
		}
		
		if (!empty($urldata["query"])) $urldata["path"] .= "?".$urldata["query"];
		$request = $this->method." ".$urldata["path"]." HTTP/".$this->version."\r\n";
		$request .= $this->build_headers();
		
		if (isset($this->post_data)) {
			$request .= $this->post_data;
		}
		
		$this->response = "";
		
		// clear headers that shouldn't persist across multiple requests
		// (we can do this here as we've already built the request, including headers, above)
		$per_request_headers = array('Host','Content-Length');
		foreach ($per_request_headers as $k=>$v) unset($this->headers[$v]);
		
		// Native SSL support requires the OpenSSL extension, and was introduced in PHP 4.3.0
		$php_ssl_support = extension_loaded("openssl") && version_compare(phpversion(),"4.3.0")>=0;
		
		// if this is a plain HTTP request, or if it's an HTTPS request and OpenSSL support is available,
		// natively perform the HTTP request
		if ( ( ($urldata["scheme"]=="http") || ($php_ssl_support && ($urldata["scheme"]=="https")) ) && (!$this->force_curl) ) {
			$curl_mode = false;

			$hostname = $this->connect_ip ? $this->connect_ip : $urldata['host'];
			if ($urldata["scheme"]=="https") $hostname = 'ssl://'.$hostname;
			
			$time_connect_start = $this->getmicrotime();

			$this->progress(HRP_INFO,'Opening socket connection to '.$hostname.' port '.$urldata['port']);

			$this->expected_bytes = -1;
			$this->received_bytes = 0;
			
			$fp = @fsockopen ($hostname,$urldata["port"],$errno,$errstr,$this->connect_timeout);
			$time_connected = $this->getmicrotime();
			$connect_time = $time_connected - $time_connect_start;
			if ($fp) {
				if ($this->stream_timeout) stream_set_timeout($fp,$this->stream_timeout);
				$this->progress(HRP_INFO,"Connected; sending request");
				
				$this->progress(HRP_DEBUG,$request);
				fputs ($fp, $request);
				$this->raw_request = $request;
				
				if ($this->stream_timeout) {
					$meta = socket_get_status($fp);
					if ($meta['timed_out']) {
						$this->error = "Exceeded socket write timeout of ".$this->stream_timeout." seconds";
						$this->progress(HRP_ERROR,$this->error);
						return false;
					}
				}
				
				$this->progress(HRP_INFO,"Request sent; awaiting reply");
				
				$headers_received = false;
				$data_length = false;
				$chunked = false;
				$iterations = 0;
				while (!feof($fp)) {
					if ($data_length>0) {
						$line = fread($fp,$data_length);
						$this->progress(HRP_DEBUG,"[DL] Got a line: [{$line}] " . gettype($line));

						if ($line!==false) $data_length -= strlen($line);
					} else {
						$line = @fgets($fp,10240);
						$this->progress(HRP_DEBUG,"[NDL] Got a line: [{$line}] " . gettype($line));
						
						if ( ($chunked) && ($line!==false) ) {
							$line = trim($line);
							if (!strlen($line)) continue;
							
							list($data_length,) = explode(';',$line,2);
							$data_length = (int) hexdec(trim($data_length));
							
							if ($data_length==0) {
								$this->progress(HRP_DEBUG,"Done");
								// end of chunked data
								break;
							}
							$this->progress(HRP_DEBUG,"Chunk length $data_length (0x$line)");
							continue;
						}
					}
					
					if ($line===false) {
						$meta = socket_get_status($fp);
						if ($meta['timed_out']) {
							if ($this->stream_timeout) {
								$this->error = "Exceeded socket read timeout of ".$this->stream_timeout." seconds";
							} else {
								$this->error = "Exceeded default socket read timeout";
							}
							$this->progress(HRP_ERROR,$this->error);
							return false;
						} else {
							$this->progress(HRP_ERROR,'No data but not timed out');
						}
						continue;
					}					

					// check time limits if requested
					if ($this->max_time>0) {
						if ($this->getmicrotime() - $time_request_start > $this->max_time) {
							$this->error = "Exceeded maximum transfer time of ".$this->max_time." seconds";
							$this->progress(HRP_ERROR,$this->error);
							return false;
							break;
						}
					}

					$this->response .= $line;
					
					$iterations++;
					if ($headers_received) {
						if ($time_connected>0) {
							$time_firstdata = $this->getmicrotime();
							$process_time = $time_firstdata - $time_connected;
							$time_connected = 0;
						}
						$this->received_bytes += strlen($line);
						if ($iterations % 20 == 0) {
							$this->update_transfer_counters();
						}
					}

					
					// some dumbass webservers don't respect Connection: close and just
					// leave the connection open, so we have to be diligent about
					// calculating the content length so we can disconnect at the end of
					// the response
					if ( (!$headers_received) && (trim($line)=="") ) {
						$headers_received = true;
						$this->progress(HRP_DEBUG,"Got headers: {$this->response}");

						if (preg_match('/^Content-Length: ([0-9]+)/im',$this->response,$matches)) {
							$data_length = (int) $matches[1];
							$this->progress(HRP_DEBUG,"Content length is $data_length");
							$this->expected_bytes = $data_length;
							$this->update_transfer_counters();
						} else {
							$this->progress(HRP_DEBUG,"No data length specified");
						}
						if (preg_match("/^Transfer-Encoding: chunked/im",$this->response,$matches)) {
							$chunked = true;
							$this->progress(HRP_DEBUG,"Chunked transfer encoding requested");
						} else {
							$this->progress(HRP_DEBUG,"CTE not requested");
						}
						
						if (preg_match_all("/^Set-Cookie: ((.*?)\=(.*?)(?:;\s*(.*))?)$/im",$this->response,$cookielist,PREG_SET_ORDER)) {
							foreach ($cookielist as $k=>$cookie) $this->cookie_headers .= $cookie[0]."\n";
							
							// get the path for which cookies will be valid if no path is specified
							$cookiepath = preg_replace('/\/{2,}/','',$urldata['path']);
							if (substr($cookiepath,-1)!='/') {
								$cookiepath = explode('/',$cookiepath);
								array_pop($cookiepath);
								$cookiepath = implode('/',$cookiepath) . '/';
							}
							// process each cookie
							foreach ($cookielist as $k=>$cookiedata) {
								list(,$rawcookie,$name,$value,$attributedata) = $cookiedata;
								$attributedata = explode(';',trim($attributedata));
								$attributes = array();

								$cookie = array(
									'value'=>$value,
									'raw'=>trim($rawcookie),
								);
								foreach ($attributedata as $k=>$attribute) {
									list($attrname,$attrvalue) = explode('=',trim($attribute));
									$cookie[$attrname] = $attrvalue;
								}

								if (!isset($cookie['domain']) || !$cookie['domain']) $cookie['domain'] = $urldata['host'];
								if (!isset($cookie['path']) || !$cookie['path']) $cookie['path'] = $cookiepath;
								if (isset($cookie['expires']) && $cookie['expires']) $cookie['expires'] = strtotime($cookie['expires']);
								
								if (!$this->validate_response_cookie($cookie,$urldata['host'])) continue;
								
								// do not store expired cookies; if one exists, unset it
								if ( isset($cookie['expires']) && ($cookie['expires']<time()) ) {
									unset($this->response_cookies[ $name ][ $cookie['path'] ]);
									continue;
								}
								
								$this->response_cookies[ $name ][ $cookie['path'] ] = $cookie;
							}
						}
					}
					
					if ($this->result_close) {
						if (preg_match_all("/HTTP\/([0-9\.]+) ([0-9]+) (.*?)[\r\n]/",$this->response,$matches)) {
							$resultcodes = $matches[2];
							foreach ($resultcodes as $k=>$code) {
								if ($code!=100) {
									$this->progress(HRP_INFO,'HTTP result code received; closing connection');

									$this->result_code = $code;
									$this->result_text = $matches[3][$k];
									fclose($fp);
					
									return ($this->result_code==200);
								}
							}
						}
					}
				}
				if (feof($fp)) $this->progress(HRP_DEBUG,'EOF on socket');
				@fclose ($fp);
				
				$this->update_transfer_counters();
				
				if (is_array($this->response_cookies)) {
					// make sure paths are sorted in the order in which they should be applied
					// when setting response cookies
					foreach ($this->response_cookies as $name=>$paths) {
						ksort($this->response_cookies[$name]);
					}
				}
				$this->progress(HRP_INFO,'Request complete');
			} else {
				$this->error = strtoupper($urldata["scheme"])." connection to ".$hostname." port ".$urldata["port"]." failed";
				$this->progress(HRP_ERROR,$this->error);
				return false;
			}

		// perform an HTTP/HTTPS request using CURL
		} elseif ( !$this->disable_curl && ( ($urldata["scheme"]=="https") || ($this->force_curl) ) ) {
			$this->progress(HRP_INFO,'Passing HTTP request for $url to CURL');
			$curl_mode = true;
			if (!$this->_curl_request($url)) return false;
			
		// unknown protocol
		} else {
			$this->error = "Unsupported protocol: ".$urldata["scheme"];
			$this->progress(HRP_ERROR,$this->error);
			return false;
		}
		
		$this->raw_response = $this->response;

		$totallength = strlen($this->response);
		
		do {
			$headerlength = strpos($this->response,"\r\n\r\n");

			$response_headers = explode("\r\n",substr($this->response,0,$headerlength));
			$http_status = trim(array_shift($response_headers));
			foreach ($response_headers as $line) {
				list($k,$v) = explode(":",$line,2);
				$this->response_headers[trim($k)] = trim($v);
			}
			$this->response = substr($this->response,$headerlength+4);
		
			if (!preg_match("/^HTTP\/([0-9\.]+) ([0-9]+) (.*?)$/",$http_status,$matches)) {
				$matches = array("",$this->version,0,"HTTP request error");
			}
			list (,$response_version,$this->result_code,$this->result_text) = $matches;

			// skip HTTP result code 100 (Continue) responses
		} while (($this->result_code==100) && ($headerlength));
		
		// record some statistics, roughly compatible with CURL's curl_getinfo()
		if (!$curl_mode) {
			$total_time = $this->getmicrotime() - $time_request_start;
			$transfer_time = $total_time - $connect_time;
			$this->stats = array(
				"total_time"=>$total_time,
				"connect_time"=>$connect_time,	// time between connection request and connection established
				"process_time"=> isset($process_time) ? $process_time : 0,	// time between HTTP request and first data (non-headers) received
				"url"=>$url,
				"content_type"=> isset($this->response_headers["Content-Type"]) ? $this->response_headers["Content-Type"] : NULL,
				"http_code"=>$this->result_code,
				"header_size"=>$headerlength,
				"request_size"=>$totallength,
				"filetime"=> isset( $this->response_headers[ "Date" ]) ? strtotime($this->response_headers["Date"]) : 0,
				"pretransfer_time"=>$connect_time,
				"size_download"=>$totallength,
				"speed_download"=>$transfer_time > 0 ? round($totallength / $transfer_time) : 0,
				"download_content_length"=>$totallength,
				"upload_content_length"=>0,
				"starttransfer_time"=>$connect_time,
			);
		}
		
		
		$ok = ($this->result_code==200);
		if ($ok) {
			// if a page preprocessor is defined, call it to process the page contents
			if (is_callable($this->page_preprocessor)) $this->response = call_user_func($this->page_preprocessor,$this,$this->response);
			
		}

		return $ok;
	}
	
	public function validate_response_cookie($cookie,$actual_hostname) {
		// make sure the cookie can't be set for a TLD, eg: '.com'		
		$cookiehost = $cookie['domain'];
		$p = strrpos($cookiehost,'.');
		if ($p===false) return false;
		
		$tld = strtolower(substr($cookiehost,$p+1));
		$special_domains = array("com", "edu", "net", "org", "gov", "mil", "int");
		$periods_required = in_array($tld,$special_domains) ? 1 : 2;
		
		$periods = substr_count($cookiehost,'.');
		if ($periods<$periods_required) return false;
		
		if (substr($actual_hostname,0,1)!='.') $actual_hostname = '.'.$actual_hostname;
		if (substr($cookiehost,0,1)!='.') $cookiehost = '.'.$cookiehost;
		$domain_match = (
			($actual_hostname==$cookiehost) ||
			(substr($actual_hostname,-strlen($cookiehost))==$cookiehost)
		);
		
		return $domain_match;

	}
	
	public function build_headers() {
		$headers = "";
		foreach ($this->headers as $name=>$value) {
			$value = trim($value);
			if (empty($value)) continue;
			$headers .= "{$name}: {$value}\r\n";
		}

		if (isset($this->request_cookies) && is_array($this->request_cookies)) {
			$cookielist = array();
			foreach ($this->request_cookies as $name=>$value) {
				$cookielist[] = "{$name}={$value}";
			}
			if (count($cookielist)) $headers .= "Cookie: ".implode('; ',$cookielist)."\r\n";
		}
		
		
		$headers .= "\r\n";
		
		return $headers;
	}
	
	// opposite of parse_url()
	public function rebuild_url($urlinfo) {
		$url = $urlinfo['scheme'].'://';
		
		if ($urlinfo['user'] || $urlinfo['pass']) {
			$url .= $urlinfo['user'];
			if ($urlinfo['pass']) {
				if ($urlinfo['user']) $url .= ':';
				$url .= $urlinfo['pass'];
			}
			$url .= '@';
		}
		
		$url .= $urlinfo['host'];
		if ($urlinfo['port']) $url .= ':'.$urlinfo['port'];
		
		$url .= $urlinfo['path'];
		
		if ($urlinfo['query']) $url .= '?'.$urlinfo['query'];
		if ($urlinfo['fragment']) $url .= '#'.$urlinfo['fragment'];
		
		return $url;
	}
	
	protected function _replace_hostname(&$url,$new_hostname) {
		$parts = parse_url($url);
		$old_hostname = $parts['host'];
		
		$parts['host'] = $new_hostname;
		
		$url = $this->rebuild_url($parts);
				
		return $old_hostname;
	}
	
	protected function _curl_request($url) {
		$this->error = false;

		// if a direct connection IP address was specified,	replace the hostname
		// in the URL with the IP address, and set the Host: header to the
		// original hostname
		if ($this->connect_ip) {
			$old_hostname = $this->_replace_hostname($url,$this->connect_ip);
			$this->headers["Host"] = $old_hostname;
		}
		

		unset($this->headers["Content-Length"]);
		$headers = explode("\n",$this->build_headers());
		
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_URL, $url); 
		curl_setopt($ch,CURLOPT_USERAGENT, $this->headers["User-Agent"]); 
		curl_setopt($ch,CURLOPT_HEADER, 1); 
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1); 
//		curl_setopt($ch,CURLOPT_FOLLOWLOCATION, 1); // native method doesn't support this yet, so it's disabled for consistency
		curl_setopt($ch,CURLOPT_TIMEOUT, 10);
		if ($this->curl_proxy) {
			curl_setopt($ch,CURLOPT_PROXY,$this->curl_proxy);
		}
		curl_setopt($ch,CURLOPT_HTTPHEADER, $headers);
		
		if ($this->method=="POST") {
			curl_setopt($ch,CURLOPT_POST,1);
			curl_setopt($ch,CURLOPT_POSTFIELDS,$this->post_data);
		}
		if ($this->insecure_ssl) {
			curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		}
		if ($this->ignore_ssl_hostname) {
			curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,1);
		}
		
		$this->response = curl_exec ($ch);
		if (curl_errno($ch)!=0) {
			$this->error = "CURL error #".curl_errno($ch).": ".curl_error($ch);
		}
		
		$this->stats = curl_getinfo($ch);
		curl_close($ch);
		
		return ($this->error === false);
	}
	
	public function progress($level,$msg) {
		if (is_callable($this->progress_callback)) call_user_func($this->progress_callback,$level,$msg);
	}
	
	// Gets any available HTTPRetriever error message (including both internal
	// errors and HTTP errors)
	public function get_error() {
		return $this->error ? $this->error : 'HTTP ' . $this->result_code.': '.$this->result_text;
	}
	
	public function get_content_type() {	
		if ((!isset($this->response_headers['Content-Type']) || !$ctype = $this->response_headers['Content-Type']) && isset($this->response_headers['Content-type'])) {
			$ctype = $this->response_headers['Content-type'];
		}
		
		if (!isset($ctype)) {
			return NULL;
		}
		
		list($ctype,) = explode(';',$ctype);
		
		return strtolower($ctype);
	}
	
	public function update_transfer_counters() {
		if (is_callable($this->transfer_callback)) call_user_func($this->transfer_callback,$this->received_bytes,$this->expected_bytes);
	}

	public function set_transfer_display($enabled = true) {
		if ($enabled) {
			$this->transfer_callback = array(&$this,'default_transfer_callback');
		} else {
			unset($this->transfer_callback);
		}
	}
	
	public function set_progress_display($enabled = true) {
		if ($enabled) {
			$this->progress_callback = array(&$this,'default_progress_callback');
		} else {
			unset($this->progress_callback);
		}
	}
	
	public function default_progress_callback($severity,$message) {
		$severities = array(
			HRP_DEBUG=>'debug',
			HRP_INFO=>'info',
			HRP_ERROR=>'error',
		);
		
		echo date('Y-m-d H:i:sa').' ['.$severities[$severity].'] '.$message."\n";
		flush();
	}

	public function default_transfer_callback($transferred,$expected) {
		$msg = "Transferred " . round($transferred/1024,1);
		if ($expected>=0) $msg .= "/" . round($expected/1024,1);
		$msg .=	"KB";
		if ($expected>0) $msg .= " (".round($transferred*100/$expected,1)."%)";
		echo date('Y-m-d H:i:sa')." $msg\n";
		flush();
	}	
	
	public function getmicrotime() { 
		list($usec, $sec) = explode(" ",microtime()); 
		return ((float)$usec + (float)$sec); 
	}	
}

/* CURL Extension Emulation Library (Native PHP)
 * Copyright 2004-2007, Steve Blinch
 * http://code.blitzaffe.com
 * 
 * Modified 2012 to fit steroid needs
 * ============================================================================
 *
 * LICENSE
 *
 * This script is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *	
 * You should have received a copy of the GNU General Public License along
 * with this script; if not, write to the Free Software Foundation, Inc.,
 * 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
 

define('CURLNAT_VERSION', "1.0.0");

define('CURLOPT_RETURNTRANSFER', 19913);

define('CURLOPT_NOTHING', 0);
define('CURLOPT_FILE', 10001);
define('CURLOPT_URL', 10002);
define('CURLOPT_PORT', 3);
define('CURLOPT_PROXY', 10004);
define('CURLOPT_USERPWD', 10005);
define('CURLOPT_PROXYUSERPWD', 10006);
define('CURLOPT_RANGE', 10007);
define('CURLOPT_INFILE', 10009);
define('CURLOPT_ERRORBUFFER', 10010);
define('CURLOPT_WRITEFUNCTION', 20011);
define('CURLOPT_READFUNCTION', 20012);
define('CURLOPT_TIMEOUT', 13);
define('CURLOPT_INFILESIZE', 14);
define('CURLOPT_POSTFIELDS', 10015);
define('CURLOPT_REFERER', 10016);
define('CURLOPT_FTPPORT', 10017);
define('CURLOPT_USERAGENT', 10018);
define('CURLOPT_LOW_SPEED_LIMIT', 19);
define('CURLOPT_LOW_SPEED_TIME', 20);
define('CURLOPT_RESUME_FROM', 21);
define('CURLOPT_COOKIE', 10022);
define('CURLOPT_HTTPHEADER', 10023);
define('CURLOPT_HTTPPOST', 10024);
define('CURLOPT_SSLCERT', 10025);
define('CURLOPT_SSLCERTPASSWD', 10026);
define('CURLOPT_SSLKEYPASSWD', 10026);
define('CURLOPT_CRLF', 27);
define('CURLOPT_QUOTE', 10028);
define('CURLOPT_WRITEHEADER', 10029);
define('CURLOPT_COOKIEFILE', 10031);
define('CURLOPT_SSLVERSION', 32);
define('CURLOPT_TIMECONDITION', 33);
define('CURLOPT_TIMEVALUE', 34);
define('CURLOPT_HTTPREQUEST', 10035);
define('CURLOPT_CUSTOMREQUEST', 10036);
define('CURLOPT_STDERR', 10037);
define('CURLOPT_POSTQUOTE', 10039);
define('CURLOPT_WRITEINFO', 10040);
define('CURLOPT_VERBOSE', 41);
define('CURLOPT_HEADER', 42);
define('CURLOPT_NOPROGRESS', 43);
define('CURLOPT_NOBODY', 44);
define('CURLOPT_FAILONERROR', 45);
define('CURLOPT_UPLOAD', 46);
define('CURLOPT_POST', 47);
define('CURLOPT_FTPLISTONLY', 48);
define('CURLOPT_FTPAPPEND', 50);
define('CURLOPT_NETRC', 51);
define('CURLOPT_FOLLOWLOCATION', 52);
define('CURLOPT_FTPASCII', 53);
define('CURLOPT_TRANSFERTEXT', 53);
define('CURLOPT_PUT', 54);
define('CURLOPT_MUTE', 55);
define('CURLOPT_PROGRESSFUNCTION', 20056);
define('CURLOPT_PROGRESSDATA', 10057);
define('CURLOPT_AUTOREFERER', 58);
define('CURLOPT_PROXYPORT', 59);
define('CURLOPT_POSTFIELDSIZE', 60);
define('CURLOPT_HTTPPROXYTUNNEL', 61);
define('CURLOPT_INTERFACE', 10062);
define('CURLOPT_KRB4LEVEL', 10063);
define('CURLOPT_SSL_VERIFYPEER', 64);
define('CURLOPT_CAINFO', 10065);
define('CURLOPT_PASSWDFUNCTION', 20066);
define('CURLOPT_PASSWDDATA', 10067);
define('CURLOPT_MAXREDIRS', 68);
define('CURLOPT_FILETIME', 10069);
define('CURLOPT_TELNETOPTIONS', 10070);
define('CURLOPT_MAXCONNECTS', 71);
define('CURLOPT_CLOSEPOLICY', 72);
define('CURLOPT_CLOSEFUNCTION', 20073);
define('CURLOPT_FRESH_CONNECT', 74);
define('CURLOPT_FORBID_REUSE', 75);
define('CURLOPT_RANDOM_FILE', 10076);
define('CURLOPT_EGDSOCKET', 10077);
define('CURLOPT_CONNECTTIMEOUT', 78);
define('CURLOPT_HEADERFUNCTION', 20079);
define('CURLOPT_HTTPGET', 80);
define('CURLOPT_SSL_VERIFYHOST', 81);
define('CURLOPT_COOKIEJAR', 10082);
define('CURLOPT_SSL_CIPHER_LIST', 10083);
define('CURLOPT_HTTP_VERSION', 84);
define('CURLOPT_FTP_USE_EPSV', 85);
define('CURLOPT_SSLCERTTYPE', 10086);
define('CURLOPT_SSLKEY', 10087);
define('CURLOPT_SSLKEYTYPE', 10088);
define('CURLOPT_SSLENGINE', 10089);
define('CURLOPT_SSLENGINE_DEFAULT', 90);
define('CURLOPT_DNS_USE_GLOBAL_CACHE', 91);
define('CURLOPT_DNS_CACHE_TIMEOUT', 92);
define('CURLOPT_PREQUOTE', 10093); 

define('CURLINFO_EFFECTIVE_URL', 1);
define('CURLINFO_HTTP_CODE', 2);
define('CURLINFO_FILETIME', 14);
define('CURLINFO_TOTAL_TIME', 3);
define('CURLINFO_NAMELOOKUP_TIME', 4);
define('CURLINFO_CONNECT_TIME', 5);
define('CURLINFO_PRETRANSFER_TIME', 6);
define('CURLINFO_STARTTRANSFER_TIME', 17);
define('CURLINFO_REDIRECT_TIME', 19);
define('CURLINFO_REDIRECT_COUNT', 20);
define('CURLINFO_SIZE_UPLOAD', 7);
define('CURLINFO_SIZE_DOWNLOAD', 8);
define('CURLINFO_SPEED_DOWNLOAD', 9);
define('CURLINFO_SPEED_UPLOAD', 10);
define('CURLINFO_HEADER_SIZE', 11);
define('CURLINFO_REQUEST_SIZE', 12);
define('CURLINFO_SSL_VERIFYRESULT', 13);
define('CURLINFO_CONTENT_LENGTH_DOWNLOAD', 15);
define('CURLINFO_CONTENT_LENGTH_UPLOAD', 16);
define('CURLINFO_CONTENT_TYPE', 18);

define('CURLE_UNSUPPORTED_PROTOCOL', 1);
define('CURLE_FAILED_INIT', 2);

define('TIMECOND_ISUNMODSINCE', 1);
define('TIMECOND_IFMODSINCE', 2);


function _curlopt_name($curlopt) {
	foreach (get_defined_constants() as $k=>$v) {
		if ( (substr($k,0,8)=="CURLOPT_") && ($v==$curlopt)) return $k;
	}
	return false;
}

// Initialize a CURL emulation session
function curl_init() {
	if (!isset($GLOBALS["_CURLNAT_OPT"])) {
		$GLOBALS["_CURLNAT_OPT"] = array(
			'index' => 0
		);	
	}
	
	$i = $GLOBALS["_CURLNAT_OPT"]["index"]++;
	$GLOBALS["_CURLNAT_OPT"][$i] = array();
	
	$newCurl = new HTTPRetriever();
	
	$GLOBALS["_CURLNAT_OPT"][$i]["http"] = &$newCurl;
	$GLOBALS["_CURLNAT_OPT"][$i]["include_body"] = true;
	return $i;
}


// Set an option for a CURL emulation transfer 
function curl_setopt($ch,$option,$value) {
	
	$opt = &$GLOBALS["_CURLNAT_OPT"][$ch];
	if (empty($opt["args"])) $opt["args"] = array();
	
	$args = &$opt["args"];
	if (empty($opt["settings"])) $opt["settings"] = array();
	
	$settings = &$opt["settings"];
	$http = &$opt["http"];
	
	switch($option) {
		case CURLOPT_URL:
			$opt["url"] = $value;
			break;
		case CURLOPT_CUSTOMREQUEST:
			$opt["method"] = $value;
			break;
		case CURLOPT_REFERER:
			$http->headers["Referer"] = $value;
			break;
		case CURLOPT_NOBODY:
			$opt["include_body"] = $value==0;
			break;
		case CURLOPT_FAILONERROR:
			$opt["fail_on_error"] = $value>0;
			break;
		case CURLOPT_USERAGENT:
			$http->headers["User-Agent"] = $value;
			break;
		case CURLOPT_HEADER:
			$opt["include_headers"] = $value>0;
			break;
		case CURLOPT_RETURNTRANSFER:
			$opt["return_transfer"] = $value>0;
			break;
		case CURLOPT_TIMEOUT:
			$opt["max-time"] = (int) $value;
			break;
		case CURLOPT_CONNECTTIMEOUT:
			$opt["connect-timeout"] = (int) $value;
			break;
		case CURLOPT_HTTPHEADER:
			reset($value);
			foreach ($value as $k=>$header) {
				list($headername,$headervalue) = explode(":",$header);
				$http->headers[$headername] = ltrim($headervalue);
			}
			break;
		case CURLOPT_POST:
			$opt["post"] = $value>0;
			break;
		case CURLOPT_POSTFIELDS:
			$opt["postdata"] = $value;
			break;
		case CURLOPT_MUTE:
			// we're already mute, no?
			break;
		case CURLOPT_FILE:
			if (is_resource($value)) {
				$opt["output_handle"] = $value;
			} else {
				trigger_error("CURLOPT_FILE must specify a valid file resource",E_USER_WARNING);
			}
			break;
		case CURLOPT_WRITEHEADER:
			if (is_resource($value)) {
				$opt["header_handle"] = $value;
			} else {
				trigger_error("CURLOPT_WRITEHEADER must specify a valid file resource",E_USER_WARNING);
			}
			break;
		case CURLOPT_STDERR:
			// not implemented for now - not really relevant
			break;
		case CURLOPT_FORBID_REUSE:
			// not needed
			break;

		case CURLOPT_SSL_VERIFYPEER:
		case CURLOPT_SSL_VERIFYHOST:
			// these are automatically disabled using ssl:// anyway
			break;
			
		case CURLOPT_USERPWD:
			list($curl_user,$curl_pass) = explode(':',$value,2);
			$http->auth_username = $curl_user;
			$http->auth_password = $curl_pass;
			break;

		// Important stuff not implemented (as it's not yet supported by HTTPRetriever)
		case CURLOPT_PUT:
		case CURLOPT_INFILE:
		case CURLOPT_FOLLOWLOCATION:
		case CURLOPT_PROXYUSERPWD:
		case CURLOPT_COOKIE:
		case CURLOPT_COOKIEFILE:
		case CURLOPT_PROXY:
		case CURLOPT_RANGE:
		case CURLOPT_RESUME_FROM:

		// Things that cannot (reasonably) be implemented here
		case CURLOPT_LOW_SPEED_LIMIT:
		case CURLOPT_LOW_SPEED_TIME:
		case CURLOPT_KRB4LEVEL:
		case CURLOPT_SSLCERT:
		case CURLOPT_SSLCERTPASSWD:
		case CURLOPT_SSLVERSION:
		case CURLOPT_INTERFACE:
		case CURLOPT_CAINFO:
		case CURLOPT_TIMECONDITION:
		case CURLOPT_TIMEVALUE:
	
		// FTP stuff not implemented
		case CURLOPT_QUOTE:
		case CURLOPT_POSTQUOTE:
		case CURLOPT_UPLOAD:
		case CURLOPT_FTPLISTONLY:
		case CURLOPT_FTPAPPEND:
		case CURLOPT_FTPPORT:
		
		// Other stuff not implemented
		case CURLOPT_VERBOSE:
		case CURLOPT_NETRC:
		default:
			trigger_error("CURL emulation does not implement CURL option "._curlopt_name($option),E_USER_WARNING);
			break;
	}
}


function curl_setopt_array( $ch, $opts ) {
	foreach ( $opts as $key => $value ) {
		curl_setopt( $ch, $key, $value );
	}
}

// Perform a CURL emulation session
function curl_exec($ch) {
	$opt = &$GLOBALS["_CURLNAT_OPT"][$ch];
	$url = $opt["url"];

	$http = &$opt["http"];
	$http->disable_curl = true; // avoid problems with recursion, since we *ARE* CURL
	
	// set time limits if requested
	if (!empty($opt["max-time"])) {
		$http->connect_timeout = $opt["max-time"];
		$http->max_time = $opt["max-time"];
	}
	
	if (!empty($opt["connect-timeout"])) {
		$http->connect_timeout = $opt["connect-timeout"];
	}
	
	if (!empty($opt["post"]) || !empty($opt["postdata"])) {
		$res = $http->post($url,isset($opt["postdata"]) ? $opt["postdata"] : NULL);

		// TODO: not sure if this is 100% correct
		unset($opt['postdata']);
	} elseif (!empty($opt["method"])) {
		$res = $http->custom($opt["method"],$url, isset($opt["postdata"]) ? $opt["postdata"] : NULL);

		// TODO: not sure if this is 100% correct
		unset($opt['postdata']);
	} else {
		$res = $http->get($url);
	}
		
	// check for errors
	$opt["errno"] = (!$res && $http->error) ? 1 : 0;
	if ($opt["errno"]) $opt["error"] = $http->error;
	
	// die if CURLOPT_FAILONERROR is set and the HTTP result code is greater than 300
	if ($http->result_code > 300 && !empty($opt["fail_on_error"])) {
		throw new Exception("HTTP Result Code: " . $http->result_code);
	}
	
	$opt["stats"] = $http->stats;


	$headers = "";
	foreach ($http->response_headers as $k=>$v) {
		$headers .= "$k: $v\r\n";
	}

	// if a file handle was provided for header output, extract the headers
	// and write them to the handle
	if (isset($opt["header_handle"])) {
		fwrite($opt["header_handle"],$headers);
	}
	
	$output = (!empty($opt["include_headers"]) ? $headers."\r\n" : "") . ($opt["include_body"] ? $http->response : "");
	
	// if a file handle was provided for output, write the output to it
	if (isset($opt["output_handle"])) {
		fwrite($opt["output_handle"],$output);
		
	// if the caller requested that the response be returned, return it
	} elseif (!empty($opt["return_transfer"])) {
		return $output;
		
	// otherwise, just echo the output to stdout
	} else {
		echo $output;
	}
	return true;
}

function curl_close($ch) {
	$opt = &$GLOBALS["_CURLNAT_OPT"][$ch];
	
	if ($opt["settings"]) {
		$settings = &$opt["settings"];
		// if the user used CURLOPT_INFILE to specify a file to upload, remove the
		// temporary file created for the CURL binary
		if ($settings["upload-file"]["value"] && file_exists($settings["upload-file"]["value"])) unlink($settings["upload-file"]["value"]);
	}

	unset($GLOBALS["_CURLNAT_OPT"][$ch]);
}

function curl_errno($ch) {
	return (int) $GLOBALS["_CURLNAT_OPT"][$ch]["errno"];
}

function curl_error($ch) {
	return $GLOBALS["_CURLNAT_OPT"][$ch]["error"];
}

function curl_getinfo($ch,$opt=NULL) {
	if ($opt) {
		$curlinfo_tags = array(
			CURLINFO_EFFECTIVE_URL=>"url",
			CURLINFO_CONTENT_TYPE=>"content_type",
			CURLINFO_HTTP_CODE=>"http_code",
			CURLINFO_HEADER_SIZE=>"header_size",
			CURLINFO_REQUEST_SIZE=>"request_size",
			CURLINFO_FILETIME=>"filetime",
			CURLINFO_SSL_VERIFYRESULT=>"ssl_verify_result",
			CURLINFO_REDIRECT_COUNT=>"redirect_count",
			CURLINFO_TOTAL_TIME=>"total_time",
			CURLINFO_NAMELOOKUP_TIME=>"namelookup_time",
			CURLINFO_CONNECT_TIME=>"connect_time",
			CURLINFO_PRETRANSFER_TIME=>"pretransfer_time",
			CURLINFO_SIZE_UPLOAD=>"size_upload",
			CURLINFO_SIZE_DOWNLOAD=>"size_download",
			CURLINFO_SPEED_DOWNLOAD=>"speed_download",
			CURLINFO_SPEED_UPLOAD=>"speed_upload",
			CURLINFO_CONTENT_LENGTH_DOWNLOAD=>"download_content_length",
			CURLINFO_CONTENT_LENGTH_UPLOAD=>"upload_content_length",
			CURLINFO_STARTTRANSFER_TIME=>"starttransfer_time",
			CURLINFO_REDIRECT_TIME=>"redirect_time"
		);
		
		$key = $curlinfo_tags[$opt];
		return $GLOBALS["_CURLNAT_OPT"][$ch]["stats"][$key];
	} else {
		return $GLOBALS["_CURLNAT_OPT"][$ch]["stats"];
	}
}

function curl_version() {
	return "libcurlemu/".CURLNAT_VERSION."-nat";
}

}
?>
