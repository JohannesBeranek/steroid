<?php

require_once __DIR__ . '/class.RCResJob.php';

require_once STROOT . '/lib/jsmin/jsmin.php';
require_once STROOT . '/lib/cssmin/cssmin.php';

// less
require_once STROOT . '/lib/less/lessc.inc.php';

// scss
require_once STROOT . '/lib/scss/scss.inc.php';

// stylus
require_once STROOT . '/lib/stylus/src/Stylus/Stylus.php';

require_once STROOT . '/cache/interface.ICache.php';
require_once STROOT . '/request/interface.IRequestInfo.php';
require_once STROOT . '/storage/interface.IRBStorage.php';


class Res {
	protected static $mimetypes = array( 
			'js' => 'text/javascript',
			'css' => 'text/css',
			'scss' => 'text/css',
			'less' => 'text/css',
			'styl' => 'text/css'
		);
		
		
	public static function createJob( $files, $type, IRBStorage $storage ) {
		// generate hash		
		$mtimes = array();

		foreach ($files as &$filename) {
			$filename = Filename::getPathInsideWebroot($filename);			
			$mtimes[] = filemtime($filename);
		}
		
		$hash = md5( implode(',', $files ) . '-' . implode(',', $mtimes) ); // assume no ',' in filenames
						
		$jobRecord = $storage->selectFirstRecord( 'RCResJob', array( 'fields' => '*', 'where' => array( 'hash', '=', array( $hash ) ) ) );
	
		if ( $jobRecord === NULL ) {			
			$jobRecord = RCResJob::get( $storage, array( 'hash' => $hash, 'files' => json_encode( $files ), 'type' => $type ), false );
			$jobRecord->save(); 
		} 
		
		return $hash;
	}
	
	public static function serveJob( IRBStorage $storage, $hash, ICache $cache, IRequestInfo $requestInfo ) {
		$jobRecord = $storage->selectFirstRecord( 'RCResJob', array( 'fields' => '*', 'where' => array( 'hash', '=', array( $hash ) ) ) );
		
		if (!$jobRecord) {
			throw new Exception('Res Job for hash ' . $hash . ' not found.');
		}
		
		$files = json_decode($jobRecord->files, true);
		$type = $jobRecord->type;
		
		$mimetype = self::$mimetypes[$type];
		
		// filename, used as key for caching
		$fn = $hash . '.' . $type;
		$fnGZ = $fn . '.gz';
		
		// no need to check for mtime, as file mtimes are part of the hash
		$generate = !($cache->exists( $fn ));
		
		$acceptGzip = false;
		$acceptHeader = $requestInfo->getServerInfo('HTTP_ACCEPT_ENCODING');
		
		// TODO: support sdch
		if ($acceptHeader && strpos($acceptHeader, 'gzip') !== false) {
			$acceptGzip = true;
			$sendFilename = $fnGZ;
			header( 'Content-Encoding: gzip' );
		} else {
			$sendFilename = $fn;
		}
		
		$ifModifiedSince = $requestInfo->getServerInfo('HTTP_IF_MODIFIED_SINCE');
		
		// TODO: http range header?
		
				// this construct avoids the race condition which could otherwise happen when key is deleted in the meantime	
		// in the worst case - file is deleted right after we create it - we're doing everything all over again	
		while ($generate || !$cache->send( $sendFilename, $mimetype, $ifModifiedSince )) {
			$cache->lock( $fn );
		
			// need to check anew, as we might have run into lock right after some other process locked, thus entry would be there upon getting here
			// alternative would be to enlarge critical section, but that would lead to worse performance in the mean case
			if (!$cache->exists( $fn ) || (isset($maxMTime) && $cache->mtime( $fn ) < $maxMTime ) || !$cache->send( $sendFilename, $mimetype, $ifModifiedSince ) ) {
				
				// preprocessing
				$newFiles = array();
				
				// filter duplicates/symlinks pointing to already included files
				foreach ($files as $filename) {
					$rp = realpath($filename);
					
					if (!isset($newFiles[$rp])) {
						$newFiles[$rp] = $rp;
					}
				}
				
				
				$handledFiles = array();
				$source = '';
				
				
				foreach ($files as $filename) {
					self::parseRes($filename, $handledFiles, $source);
				}
				
				// inline imports and remove imports for already included files
				$offset = 0;
				

				
				$resOutputUncompressed = null;
				
				switch($type) {
					case 'css': // TODO: media query
					
						$cssmin = new CSSmin();
						$resOutputUncompressed = $cssmin->run($source);
						
					break;
					case 'js':
						$resOutputUncompressed = JSMin::minify($source);
					break;
				}
	
				// save compressed & uncompressed version
				$cache->set( $fn, $resOutputUncompressed );
				
				$resOutputGZ = gzencode( $resOutputUncompressed, 9 );
				$cache->set( $fnGZ, $resOutputGZ );
			}
			
			$generate = false;
			
			$cache->unlock( $fn );
		}
			
	}

	public static function serveFile( $file, $requestInfo ) {
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		

		if (!isset(self::$mimetypes[$ext])) {
			throw new SecurityException( 'Filename with invalid extension passed to res handler: "' . $file . '"' );
		}
			
		$filename = Filename::getPathInsideWebroot( $file );
		$handledFiles = array();
		
		$source = '';
		
		$mtime = self::parseRes( $filename, $handledFiles, $source, false );
			
		$ifModifiedSince = $requestInfo->getServerInfo('HTTP_IF_MODIFIED_SINCE');

		Responder::sendString( $source, self::$mimetypes[$ext], $mtime, $ifModifiedSince );
	}

	final protected static function parseRes( $filename, array &$handledFiles, &$source, $inline = true ) {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);

		$mtime = self::handleImports( $filename, $handledFiles, $source, $inline, $ext !== 'js' );

		// TODO: cache compiled, use mtime
		switch ($ext) {
			case 'scss':
				static $use_cli;
				
				if ($use_cli === NULL) {
					$use_cli_conf = Config::key( 'web', 'scss_cli' );
					switch ($use_cli_conf) {
						case 'sassc':
							$use_cli = `which sassc` ? 'sassc' : false;
						break;
						default:
							$use_cli = ($use_cli_conf && `which sass`) ? 'sass --scss --stdin --no-cache -E utf-8 /dev/stdout' : false;
					}
				}

				if ( $use_cli ) {
					$cmd = $use_cli;
					$descriptorspec = array( 0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w') );
					
					$process = proc_open( $cmd , $descriptorspec, $pipes );
					if (is_resource($process)) {
						fwrite($pipes[0], $source);
						fclose($pipes[0]);
						
						$css = stream_get_contents( $pipes[1] );
						fclose($pipes[1]);
						
						$stderr = stream_get_contents( $pipes[2] );
						fclose($pipes[2]);
						
						$return_value = proc_close($process);
						
						if ($return_value !== 0) {
							throw new Exception("sass failed processing source with return code " . $return_value . "; stderr: " . $stderr);
						}
						
						$source = $css;
					} else {
						throw new Exception("Unable to exec sass");
					}
				} else {
					$scssc = new scssc();
					$source = $scssc->compile($source);
				}
			break;
			case 'less':
				$lessc = new lessc();
				$source = $lessc->compile($source);
			break;
			case 'styl':
				$stylus = new Stylus();
				$stylus->setImportDir( dirname( $filename ) );
				$source = $stylus->parseString( $source );
			break;
		}

		return $mtime;
	}

	final protected static function handleImports( $filename, array &$handledFiles, &$source, $inline = true, $parseImports = true ) {
		$handledFiles[$filename] = $filename;

		// TODO: handle file not found
		$filemtime = filemtime( $filename );

		$newestFileMTime = $filemtime;

		// TODO: cache?
		$newcontents = file_get_contents( $filename );

		if($parseImports){
			$offset     = 0;
			$lastOffset = 0;

			while ( ( $offset = strpos( $newcontents, '@import', $lastOffset ) ) !== false ) {
				$length    = strcspn( $newcontents, ";\n", $offset );
				$statement = substr( $newcontents, $offset, $length );

				$source .= substr( $newcontents, $lastOffset, $offset - $lastOffset );

				$lastOffset = $offset + $length + 1;

				if ( ! preg_match( '/@import (["\'])([^\1]+)\1/', $statement, $matches ) ) {
					throw new Exception( 'Invalid import statement found: ' . $statement );
				}


				// skip imports without extension, as those should be passed on to scss/less/etc
				$ext = pathinfo( $matches[ 2 ], PATHINFO_EXTENSION );

				if ( $ext === '' ) {
					$source .= $statement;
					continue;
				}


				$fn = Filename::getPathInsideWebrootWithLocalDir( $matches[ 2 ], dirname( $filename ) );

				if ( $inline ) {
					$rp = realpath( $fn );

					if ( ! isset( $handledFiles[ $rp ] ) ) {
						$inlinedFileMTime = self::handleImports( $rp, $handledFiles, $source );

						if ( $inlinedFileMTime > $newestFileMTime ) {
							$newestFileMTime = $inlinedFileMTime;
						}
					}
				} else {
					// TODO: do we need to call parseCSS on imports?

					$source .= '@import "/res?file=' . Filename::getPathWithoutWebroot( $fn ) . '";';
				}
			}

			if ( $lastOffset === 0 ) {
				$source .= $newcontents; // save substr call and thus copying potentially very large string
			} else {
				$source .= substr( $newcontents, $lastOffset );
			}
		} else {
			$source .= $newcontents;
		}

		return $newestFileMTime;
	}
}
