<?php
/**
 *
 * @package steroid\file
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';

// filesize formatting
require_once STROOT . '/util/class.StringFilter.php';

/**
 *
 * @package steroid\file
 *
 */
class CHFile extends CLIHandler {	
	public function performCommand( $called, $command, array $params ) {
		if ( count( $params ) !== 1 ) {
			$this->notifyError( $this->getUsageText( $called, $command, $params ) );
			return EXIT_FAILURE;
		}
		
		switch ($params[0]) {
			case 'linkdup':
				$this->commandLinkDup();
			break;
			default:
				$this->notifyError( $this->getUsageText( $called, $command, $params ) );
				return EXIT_FAILURE;
		}

		return EXIT_SUCCESS;
	}
	
	private final function commandLinkDup() {
		
		// get used storage dir, so we can work directly on file system 
		$storageDir = $this->storage->getStorageDirectory();
		
		// create recursive iterator, so we can walk all files
		// unfortunately we can't pass flags to only get pathname here as RecursiveIteratorIterator doesn't work anymore then
		$storageDirIterator = new RecursiveDirectoryIterator( $storageDir, FilesystemIterator::FOLLOW_SYMLINKS | RecursiveDirectoryIterator::SKIP_DOTS ); 
		
		// filter storageDirIterator
		$filteredStorageDirIterator = new RecursiveCallbackFilterIterator( $storageDirIterator, function( $current, $key, $iterator ) {
			$currentFilename = $current->getFilename();
			
			// skip hidden files and directories
			if ( ( $currentFilename[0] === '.' ) || ( ( $currentFilename === "lost+found" ) && $current->isDir() ) ) {
				return false;
			}
			
			return true;
		});
		
		// used to store file sizes so we have a rough filter for files first
		$fileSizes = array();
		$numFiles = 0;
		$numFilesUnduped = 0;
		$startTime = microtime(true);
				
		$spaceSaved = 0;
		
		// report
		printf("Starting iteration through %s\n", $storageDir);
		
		// iterate through directories and compare files as we go
		foreach ( new RecursiveIteratorIterator( $filteredStorageDirIterator, RecursiveIteratorIterator::CHILD_FIRST ) as $splFileObject ) {
			// skip directories
			if ( $splFileObject->isDir() ) {
				continue;
			}
			
			try {
				// SPLFileObject does not expose a method to read an 
				// arbitrary number of bytes, so we need to get
				// the full Pathname to use a regular filehandle
				$fullFileName = $splFileObject->getPathname();
				
				if ( ( $fileHandle = fopen($fullFileName, 'rb') ) === false ) {
					// in case we can't open the file, we report and continue
					$this->notifyError( "Unable to read file $fullFileName\n" );
					
					continue;
				}
				
				
				// according to php manual, this function does not fail
				$stats = fstat($fileHandle);
				
				$fileSize = $stats['size'];
				
				$fileIno = $stats['ino'];
				$fileDev = $stats['dev'];
							
				// !isset? -> no files with that size yet
				if (!isset($fileSizes[$fileSize])) {
					$fileSizes[$fileSize] = array();
				} 		
				
				// read first 32k of data to use as base for hash comparison
				// by limiting to fixed length at start of file we stay time-constant in theory
				if ( ( $data = fread($fileHandle, 32768) ) === false ) {
					// in case of error, we report and continue
					
					fclose( $fileHandle );
					
					$this->notifyError( "Unable to read 32k from $fullFileName\n" );
				}
				
				// crc is less compute intensive than e.g. md5 and should be sufficient for our case
				// according to php manual, this function does not fail
				$crc = crc32($data);
				
				
				if (isset($fileSizes[$fileSize][$crc])) {
					// as we've already read 32k, we need to first 
					// rewind the pointer to make sure to read everything
					// it would also be possible to prepend $data, but we
					// would then risk to not have mmap
					rewind( $fileHandle );
					
					// hopefully php will mmap this
					$fileContents = stream_get_contents($fileHandle);
					fclose($fileHandle);
					
					foreach ($fileSizes[$fileSize][$crc] as $fullFileNameOther ) {
						// first check that this isn't already linking to the same 
						// if so, we can completely skip this file
						$statsOther = stat( $fullFileNameOther );
						
						if ( ( $fileDev === $statsOther['dev'] ) && ( $fileIno === $statsOther['ino'] ) ) {
							continue 2;
						}
						
						// exact comparison - if match found: hardlink (and count for report)
						// file_get_contents & stream_get_contents should use mmap 
						// and thus be more efficient than fread 
						// unfortunately we can't really save anything into variables without 
						// possibly loosing optimizations, which makes error handling bad
						if ($fileContents === file_get_contents($fullFileNameOther)) {
							// create link on different filename and then rename it
							// to original filename - this way we achieve atomic operation
		
							$linkName = $fullFileNameOther . '_LINK';		
							
							// check for file with $linkName already existing
							if ( file_exists( $linkName ) ) {
								
								// if so, it was probably left from a previous iteration
								// but we can't just delete, so we report and skip
								$this->notifyError( "$linkName already exists\n" );
								
								continue 2;
							}				
							
						
							try {
								// hard link file to already existing one so we can remove doubled contents
								if ( link( $fullFileNameOther, $linkName ) === false ) {
									
									$this->notifyError( "Unable to create link $linkName pointing to $fullFileNameOther\n" );
									
									// in case of error creating link, we can just 
									// continue to next file after reporting
									continue 2;
								}
							} catch( Exception $linkException ) {
								// may encounter exception in case of permission problem
								
								$this->notifyError( "Exception trying to link $linkName pointing to $fullFileNameOther\n" );
								$this->notifyError( $linkException->getMessage() );
								
								continue 2;
							}
																					
							// attempt to rename link to original filename ;
							// unfortunately there's no way to make sure that
							// this is the original file we've been dealing
							// with / make the operation atomic
							if ( rename( $linkName, $fullFileName ) === false ) {
								$this->notifyError( "Unable to rename link from $linkName to $fullFileName\n" );
								
								// if we err on rename, we need to remove link again before proceeding
								if ( unlink( $linkName ) === false ) {
									
									// something went really wrong if we're unable to remove 
									// the link we just created again
									throw new Exception( "Unable to remove the link at $linkName after creating it" );
								}
								
								// in case of error creating link, we can just 
								// continue to next file after reporting
								continue 2;
							}
							
							$numFilesUnduped++;
							
							$spaceSaved += $fileSize;
							
							
							// jump to next file
							continue 2;
						}
					}
					
					// if no match found, add to array
					$fileSizes[$fileSize][$crc][] = $fullFileName;
				} else {
					fclose($fileHandle);
					
					$fileSizes[$fileSize][$crc] = array( $fullFileName );
				}

			} catch(Exception $e) {
				if (isset($fileHandle) && is_resource($fileHandle)) {
					fclose($fileHandle);
				}
				
				throw $e;
			}
			
			// count number of files, so we can display something in the meantime
			$numFiles ++;
			
			// progress report
			printf("\rFiles scanned: %d - Files unduped: %d - Space saved: %s", $numFiles, $numFilesUnduped, StringFilter::formatFileSize($spaceSaved) );
		}
		
		print("\n");
		
		
		echo "Finished unduping.\n";		
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' file command' => array(
				'usage:' => array(
					'php ' . $called . ' file [command]' => array(
						'linkdup' => 'convert duplicate files in upload dir into hardlinks to free space'
						// TODO: command to remove unlisted files - make sure no upload is running in the meantime!
					)
				)
			)
		) );
	}
}