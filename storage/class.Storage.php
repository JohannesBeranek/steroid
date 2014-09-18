<?php
/**
 *
 * @package steroid
 */

require_once __DIR__ . '/class.DB.php';
require_once __DIR__ . '/interface.IStorage.php';
require_once STROOT . '/util/class.Responder.php';
require_once STROOT . '/file/interface.IFileInfo.php';
require_once STROOT . '/file/class.Filename.php';

require_once STROOT . '/util/class.StringFilter.php';

/**
 * @package steroid
 */
class Storage extends DB implements IStorage {
	/**
	 * Absolute directory used as root for file storage
	 *
	 * Format: '/a/b/c' (leading '/', no trailing '/')
	 *
	 * @var string
	 */
	protected $directory;

	/**
	 * Mask used to segment files into directories for better performance
	 *
	 * @var int
	 */
	protected static $fileDirectorySegmentationMask = 0xFF;


	protected $fileOperations;

	protected $asyncDownloads = false;
	protected $openDownloadHandles = array();

	// TODO: use this for keep-alive connections
//	protected $openDownloadConnections = array();

	const MAX_DOWNLOADS_AT_ONCE = 8;

	const FILE_OPERATION_UPLOAD = 'Upload'; // reverse: unlink ; upload is slightly modified copy
	const FILE_OPERATION_UNLINK = 'Unlink'; // reverse: -      ; rm
	const FILE_OPERATION_DOWNLOAD = 'Download'; // reverse: unlink ; copy
	const FILE_OPERATION_CREATE = 'Create';

	const MOVE_OPERATION_UPLOAD = 'upload';
	const MOVE_OPERATION_MOVE = 'move';
	const MOVE_OPERATION_DOWNLOAD = 'download';
	const MOVE_OPERATION_CREATE = 'create';

	const DEFAULT_DIRECTORY = 'upload';

	public function __construct( $host, $user, $password, $database, $directory = NULL, $engine = NULL, $charset = NULL, $collation = NULL, $persistent = true ) {
		parent::__construct( $host, $user, $password, $database, $engine, $charset, $collation, $persistent );

		if ( $directory === NULL ) {
			$directory = self::DEFAULT_DIRECTORY;
		}

		$this->directory = rtrim( WEBROOT . '/' . ltrim( $directory, ' /' ), ' /' );

		$this->fileOperations = array();
	}

	public function init() {
		parent::init();

		umask( 0002 );
	}

	public function getStorageDirectory() {
		return $this->directory;
	}

	/**
	 * @param int $filenum
	 */
	protected function ensureDirectoryForFile( $filenum ) {
		$subDirectory = (string)( $filenum & self::$fileDirectorySegmentationMask );
		$directory = $this->directory . '/' . $subDirectory;

		if ( !is_dir( $directory ) && !mkdir( $directory, 0775, true ) ) { // there seems to be no way to do this in an atomic way with php
			throw new RuntimeException( 'Unable to create directory "' . $directory . '"' );
		}

		return $subDirectory;
	}


	/**
	 * Move/Rename a file
	 *
	 * Automatically does locking on the given directory
	 * $from and $targetBasename are presumed to be correctly escaped already
	 * $targetDirectory is assumed to have been checked beforehand or generated by the system
	 *
	 * @param string $from filename including path to rename from
	 * @param string $targetDirectory target directory, with leading '/' and without trailing '/'
	 * @param string $targetBasename preferred target filename without directory (also known as basename)
	 * @param string $type self::MOVE_OPERATION_*
	 *
	 * @return string actual basename (may differ from prefered basename if that was already taken)
	 *
	 * @throws RuntimeException in case move wasn't successful (e.g. insufficient permissions)
	 */
	protected function realMoveFile( $from, $targetDirectory, $targetBasename, $type ) {
		$fn = pathinfo( $targetBasename, PATHINFO_FILENAME );

		// TODO: defer till we know source file exists (also makes it possible to respect 'filename=...' in http header)

		// Find filename
		$ext = pathinfo( $targetBasename, PATHINFO_EXTENSION );

		if ( $ext !== '' ) {
			$ext = preg_replace( '/[^a-z0-9\.].*/', '', strtolower( $ext ) );

			if ( $ext !== '' ) {
				$ext = '.' . $ext;
			}
		}

		$ct = 0;
		$dstBaseName = $fn . $ext;
		$dstFileName = $targetDirectory . '/' . $dstBaseName;
		$e = NULL;

		do {
			while ( $e || file_exists( $dstFileName ) ) {
				$ct++;
				$dstBaseName = $fn . '_' . $ct . $ext;
				$dstFileName = $targetDirectory . '/' . $dstBaseName;
				$e = NULL;
			}

			try {
				// From the official documentation of open(2):
				// If O_CREAT and O_EXCL are set, open() shall fail if the file exists. The check for the existence of the 
				// file and the creation of the file if it does not exist shall be atomic with respect to other threads executing
				// open() naming the same filename in the same directory with O_EXCL and O_CREAT set. 
				$fdest = fopen( $dstFileName, "x" ); // "LOCK"
			} catch ( Exception $e ) {
				if ( !is_writable( $targetDirectory ) ) {
					throw new Exception( 'Target directory not writable: ' . $targetDirectory );
				}
			}
		} while ( empty( $fdest ) );
		// Fin filename end

		$opException = NULL;

		try {
			switch ( $type ) {
				case self::MOVE_OPERATION_CREATE:
					$bytesWritten = fwrite( $fdest, $from );

					if ( $bytesWritten === false ) {
						throw new Exception( 'fwrite failed' );
					} elseif ( strlen( $from ) !== $bytesWritten ) {
						throw new Exception( 'fwrite finished prematurely' );
					}
					
					$success = true;
					break;
				case self::MOVE_OPERATION_DOWNLOAD:
					if ( $this->asyncDownloads && count( $this->openDownloadHandles ) >= self::MAX_DOWNLOADS_AT_ONCE ) {
						$this->freeDownloadSlot();
					}

					try {
						// TODO: use keep-alive
						// TODO: make this async (stream_socket_client) and continue downloading open handles till open completes
						$fsrc = fopen( $from, 'r' ); // need to do this sync even in async mode so we see if 'file' exists
					} catch ( Exception $e ) {
						Log::write( $e );
						Log::write( "HTTP Response header:", $http_response_header );
					}


					if ( isset( $fsrc ) && $fsrc ) {
						if ( substr( $from, 0, 4 ) === 'http' ) {
							$httpHeader = $http_response_header;

							// TODO: check return code (do we need to handle redirects?)
							// TODO: support filename= in header
						}

						// TODO: use this for keep-alive connections
//						$host = parse_url( $from, PHP_URL_HOST );							
//						if (isset($this->openDownloadConnections[$host])) {
//							$this->openDownloadConnections[$host]['refCount']++;
//						} else {
//							$this->openDownloadConnections[$host] = array( 'handle' => $fsrc, 'refCount' => 1 );
//						}

						stream_set_timeout( $fsrc, 60 );

						// TODO: make this async (stream_socket_client) and continue downloading open handles till open completes

						if ( $this->asyncDownloads ) {
							stream_set_blocking( $fsrc, 0 );

							$downloadHandle = array( 'src' => $fsrc, 'dest' => $fdest, 'destFileName' => $dstFileName );

							if ( isset( $httpHeader ) ) {
								$downloadHandle[ 'httpHeader' ] = $httpHeader;
							}

							$this->openDownloadHandles[ ] = $downloadHandle;

							unset( $fdest ); // make sure dest handle is not closed prematurely

							$success = true;
						} else {
							// TODO: handle errors + connection close by other side	
							$success = $this->streamDownload( $fsrc, $fdest );

							fclose( $fsrc );
							unset( $fsrc );

							fclose( $fdest );

							if ( isset( $httpHeader ) && $this->failsContentLength( $httpHeader, $fdest ) ) {
								throw new Exception( 'Incomplete download - downloaded size did not match content length.' );
							}

							unset( $fdest );
						}

					} else {
						$success = false;
						Log::write( 'Failed opening src file "' . $from . '"' );
						Log::write( $http_response_header );
					}
					break;
				case self::MOVE_OPERATION_MOVE:
					$success = rename( $from, $dstFileName );
					break;
				case self::MOVE_OPERATION_UPLOAD:
					$success = move_uploaded_file( $from, $dstFileName );

					// HHVM Workaround
					if ($success === false && is_readable( $from )) {
						$success = rename( $from, $dstFileName );
					}

					break;
			}
		} catch ( Exception $e ) { // needed to make sure we unlock again
			if ( isset( $fsrc ) && $fsrc && is_resource( $fsrc ) ) {
				if ( $type == self::MOVE_OPERATION_DOWNLOAD && $this->asyncDownloads ) {
					$this->freeDownloadSocket( $fsrc );
				} else {
					fclose( $fsrc );
				}
			}


			$success = false;
			$opException = $e;
		}

		if ( isset( $fdest ) && $fdest && is_resource( $fdest ) ) {
			fclose( $fdest );
		}


		if ( $opException !== NULL ) {
			throw $opException;
		} else if ( !$success ) {
			if ( $type === self::MOVE_OPERATION_CREATE ) {
				throw new RuntimeException( 'Unable to create file' );
			} else {
				throw new RuntimeException( 'Unable to move file "' . $from . '" trying to perform operation ' . $type );
			}
		}

		return $dstBaseName;
	}

	// processes async downloads till at least one slot is freed up
	protected function freeDownloadSlot() {
		if ( count( $this->openDownloadHandles ) == 0 ) {
			return;
		}


		$done = false;

		$sourceStreams = array();

		foreach ( $this->openDownloadHandles as $handle ) {
			$sourceStreams[ ] = $handle[ 'src' ];
		}

		$writeStreams = $exceptStreams = null;

		while ( !$done ) {
			// TODO: read timeout value
			$n = stream_select( $sourceStreams, $writeStreams, $exceptStreams, 10 );

			// TODO: auto reconnect
			if ( !$n ) {
				throw new Exception( 'Timed out waiting for stream to become ready' );
			}

			foreach ( $sourceStreams as $stream ) {
				foreach ( $this->openDownloadHandles as $k => $v ) {
					if ( $v[ 'src' ] == $stream ) {
						$handle = $v;
						break;
					}
				}

				$fsrc = $handle[ 'src' ];
				$fdest = $handle[ 'dest' ];

				$this->streamDownload( $fsrc, $fdest );

				if ( feof( $fsrc ) ) {
					stream_set_blocking( $fsrc, 1 );

					$this->streamDownload( $fsrc, $fdest ); // there might still be something left

					$this->freeDownloadSocket( $fsrc );
					unset( $handle[ 'src' ] );

					fclose( $fdest );

					unset( $handle[ 'dest' ] );

					unset( $this->openDownloadHandles[ $k ] );

					if ( isset( $handle[ 'httpHeader' ] ) && $this->failsContentLength( $handle[ 'httpHeader' ], $fdest ) ) {
						throw new Exception( 'Incomplete download - downloaded size did not match content length.' );
					}

					$done = true;
					// don't break here so we visit each slot at least once
				}
			}
		}
	}

	protected function streamDownload( $fsrc, $fdest ) {
		// TODO: handle errors + connection close by other side
		// TODO: read content-length header, use .part files for downloading (or save in-memory)
		return stream_copy_to_stream( $fsrc, $fdest );
	}

// TODO: use this for keep alive conenctions 

	protected function freeDownloadSocket( $socket ) {
//		foreach ($this->openDownloadConnections as $k => $conn) {
//			if ($conn['handle'] == $socket) {
//				$this->openDownloadConnections[$k]['refCount'] --;

//				if ($this->openDownloadConnections[$k]['refCount'] == 0) {
		fclose( $socket );
//					unset($this->openDownloadConnections[$k]);
//				}
//				break;
//			}
//		}		
	}

	public function finishDownload( $filename ) {
		foreach ( $this->openDownloadHandles as $k => $handle ) {
			if ( $handle[ 'destFileName' ] === $filename ) {
				$fsrc = $handle[ 'src' ];
				$fdest = $handle[ 'dest' ];

				stream_set_blocking( $fsrc, 1 );
				$this->streamDownload( $fsrc, $fdest );

				$this->freeDownloadSocket( $fsrc );
				unset( $handle[ 'src' ] );

				if ( $fdest && is_resource( $fdest ) ) {
					fclose( $fdest );
				}

				unset( $handle[ 'dest' ] );

				unset( $this->openDownloadHandles[ $k ] );

				if ( isset( $handle[ 'httpHeader' ] ) && $this->failsContentLength( $handle[ 'httpHeader' ], $fdest ) ) {
					throw new Exception( 'Incomplete download - downloaded size did not match content length.' );
				}

				break;
			}
		}
	}

	protected function failsContentLength( array $httpHeader, $fdest ) {
		if ( !$fdest || !is_resource( $fdest ) || !$httpHeader ) {
			return false;
		}

		foreach ( $httpHeader as $header ) {
			if ( strcasecmp( $header, 'Content-Length:' ) === 0 ) {
				$contentLengthSize = preg_replace( '/[^0-9]/', '', $header );

				if ( $contentLengthSize !== NULL && $contentLengthSize !== '' ) {
					break;
				}
			}
		}

		if ( !isset( $contentLengthSize ) || $contentLengthSize === '' ) {
			return false;
		}

		$contentLengthSize = (int)$contentLengthSize;

		$stats = fstat( $fdest );

		$actualSize = (int)$stats[ 'size' ];

		return ( $contentLengthSize > $actualSize );
	}

	public function setAsyncDownloads( $val ) {
		if ( $val == $this->asyncDownloads ) {
			return;
		}

		if ( !$val ) {
			$this->finishDownloads();
		}

		$this->asyncDownloads = $val;
	}

	public function finishDownloads() {
		if ( !$this->openDownloadHandles ) {
			return;
		}

		while ( count( $this->openDownloadHandles ) > 0 ) {
			$this->freeDownloadSlot();
		}
	}

// Upload
	protected function preExecuteUploadFile( IFileInfo $fileRecord ) {
		$tmpFileName = StringFilter::filterFilenameWithPath( $fileRecord->getTempFilename() );
		$filenum = time(); // TODO: replace with requestInfo servertime

		$subDir = $this->ensureDirectoryForFile( $filenum );
		$dir = $this->directory . '/' . $subDir;

		$uploadedFilename = StringFilter::filterFilename( $fileRecord->getUploadedFilename() );

		$dstBaseName = $this->realMoveFile( $tmpFileName, $dir, $uploadedFilename, self::MOVE_OPERATION_UPLOAD );

		$storedFilename = $subDir . '/' . $dstBaseName;
		$fileRecord->setStoredFilename( $storedFilename );
		$fileRecord->setFullFilename( $this->directory . '/' . $storedFilename );

		// TODO: extend in RBStorage
	}

	protected function executeUploadFile( IFileInfo $fileRecord ) {
		// nothing to do here
	}

	protected function _unlinkFile( IFileInfo $fileRecord ) {
		// \0 poisoning filter + protection against '..' in filename
		$storedFilename = Filename::resolvePath( $fileRecord->getStoredFilename() );

		if ( $storedFilename === '' || $storedFilename === NULL ) {
			throw new Exception( 'Unable to get stored filename for uploaded file' );
		}

		$filename = $this->directory . '/' . $storedFilename;

		$success = unlink( $filename );

		if ( !$success ) {
			throw new RuntimeException( 'Unable to unlink file "' . $filename . '"' );
		}
	}

	protected function undoUploadFile( IFileInfo $fileRecord ) {
		$this->_unlinkFile( $fileRecord );
	}


	public function uploadFile( IFileInfo $fileRecord ) { // FIXME: throw exception when file is null
		$this->scheduleFileOperation( $fileRecord, self::FILE_OPERATION_UPLOAD );
	}

// Create
	protected function preExecuteCreateFile( IFileInfo $fileRecord ) {
		$filenum = time(); // TODO: replace with requestInfo servertime

		$subDir = $this->ensureDirectoryForFile( $filenum );
		$dir = $this->directory . '/' . $subDir;

		$uploadedFilename = $fileRecord->getUploadedFilename();

		if ( $uploadedFilename === NULL ) { // autogenerate filename
			$uploadedFilename = '0' . Filename::extensionFromMime( $fileRecord->getMimeType(), true );
		}

		$uploadedFilename = StringFilter::filterFilename( $uploadedFilename );

		$dstBaseName = $this->realMoveFile( $fileRecord->getData(), $dir, $uploadedFilename, self::MOVE_OPERATION_CREATE );

		$fileRecord->setStoredFilename( $subDir . '/' . $dstBaseName );
	}

	protected function executeCreateFile( IFileInfo $fileRecord ) {

	}

	protected function undoCreateFile( IFileInfo $fileRecord ) {
		$completeFilename = $fileRecord->getFullFilename();

		if ( $completeFilename === '' || $completeFilename === NULL ) {
			throw new Exception( 'Could not get filename from fileRecord.' );
		}

		if ( substr( $completeFilename, 0, strlen( WEBROOT ) ) !== WEBROOT ) {
			throw new Exception( 'File to unlink not in webroot, aborting.' );
		}

		$success = unlink( $completeFilename );

		if ( !$success ) {
			throw new RuntimeException( 'Unable to unlink file "' . $completeFilename . '"' );
		}
	}

	public function createFile( IFileInfo $fileRecord ) {
		$this->scheduleFileOperation( $fileRecord, self::FILE_OPERATION_CREATE );
	}

// Unlink	
	protected function preExecuteUnlinkFile( IFileInfo $fileRecord ) {
		// don't delete pre
	}

	protected function executeUnlinkFile( IFileInfo $fileRecord ) {
		$this->_unlinkFile( $fileRecord );
	}

	protected function undoUnlinkFile( IFileInfo $fileRecord ) {
		// nothing to do here, as we didn't do anything in the first place ;)
	}

	public function unlinkFile( IFileInfo $fileRecord ) {
		$this->scheduleFileOperation( $fileRecord, self::FILE_OPERATION_UNLINK );
	}

// Download
	protected function preExecuteDownloadFile( IFileInfo $fileRecord ) {
		$tmpFileName = StringFilter::filterFilenameWithPath( $fileRecord->getTempFilename() );
		$filenum = time(); // TODO: replace with requestInfo servertime

		$subDir = $this->ensureDirectoryForFile( $filenum );
		$dir = $this->directory . '/' . $subDir;

		$uploadedFilename = StringFilter::filterFilename( $fileRecord->getUploadedFilename() );

		$dstBaseName = $this->realMoveFile( $tmpFileName, $dir, $uploadedFilename, self::MOVE_OPERATION_DOWNLOAD );

		$storedFilename = $subDir . '/' . $dstBaseName;

		$fileRecord->setStoredFilename( $storedFilename );
		$fileRecord->setFullFilename( $this->directory . '/' . $storedFilename );

		// TODO: extend in RBStorage
	}

	protected function executeDownloadFile( IFileInfo $fileRecord ) {
		// nothing to do here
	}

	protected function undoDownloadFile( IFileInfo $fileRecord ) {
		$completeFilename = $fileRecord->getFullFilename();

		if ( $completeFilename === '' || $completeFilename === NULL ) {
			throw new Exception( 'Could not get filename from fileRecord.' );
		}

		if ( substr( $completeFilename, 0, strlen( WEBROOT ) ) !== WEBROOT ) {
			throw new Exception( 'File to unlink not in webroot, aborting.' );
		}

		foreach ( $this->openDownloadHandles as $k => $handle ) {
			if ( $handle[ 'destFileName' ] === $completeFilename ) {
				try {
					if ( !empty( $handle[ 'dest' ] ) && is_resource( $handle[ 'dest' ] ) ) {
						fclose( $handle[ 'dest' ] );
					}
				} catch ( Exception $e ) {
					$this->safeLog( $e );
				}

				try {
					if ( !empty( $handle[ 'src' ] ) && is_resource( $handle[ 'src' ] ) ) $this->freeDownloadSocket( $handle[ 'src' ] );
				} catch ( Exception $e ) {
					$this->safeLog( $e );
				}

				unset( $this->openDownloadHandles[ $k ] );

				break;
			}
		}

		$success = unlink( $completeFilename );

		if ( !$success ) {
			throw new RuntimeException( 'Unable to unlink file "' . $completeFilename . '"' );
		}
	}

	public function downloadFile( IFileInfo $fileRecord ) {
		$this->scheduleFileOperation( $fileRecord, self::FILE_OPERATION_DOWNLOAD );
	}


	public function sendFile( IFileInfo $fileRecord, $forceDownload = false ) {
		// TODO: support range queries etc
		Responder::sendFile( $fileRecord->getFullFilename(), NULL, NULL, NULL, $forceDownload );
	}


	protected function preExecuteFileOperation( IFileInfo $fileRecord, $type ) {
		// TODO: everything which gets done regardless of transaction

		switch ( $type ) {
			case self::FILE_OPERATION_UPLOAD:
				$this->preExecuteUploadFile( $fileRecord );
				break;
			case self::FILE_OPERATION_UNLINK:
				$this->preExecuteUnlinkFile( $fileRecord );
				break;
			case self::FILE_OPERATION_DOWNLOAD:
				$this->preExecuteDownloadFile( $fileRecord );
				break;
			case self::FILE_OPERATION_CREATE:
				$this->preExecuteCreateFile( $fileRecord );
				break;
		}
	}

	/**
	 * Internal method to schedule file operation (or execute instantly, if no transaction is open)
	 *
	 * @param IFileInfo $fileRecord
	 * @param string    $type
	 */
	protected function scheduleFileOperation( IFileInfo $fileRecord, $type ) {
		$this->preExecuteFileOperation( $fileRecord, $type );

		if ( $this->transactionLevel == 0 ) {
			$this->executeFileOperation( $fileRecord, $type );
		} else {
			if ( !array_key_exists( $this->transactionLevel, $this->fileOperations ) ) {
				$this->fileOperations[ $this->transactionLevel ] = array();
			}

			$this->fileOperations[ $this->transactionLevel ][ ] = array( 'fileRecord' => $fileRecord, 'type' => $type );
		}
	}

// Storage transaction methods
	protected function executeFileOperation( IFileInfo $fileRecord, $type ) {
		// TODO: everything which gets done on commit

		switch ( $type ) {
			case self::FILE_OPERATION_UPLOAD:
				$this->executeUploadFile( $fileRecord );
				break;
			case self::FILE_OPERATION_UNLINK:
				$this->executeUnlinkFile( $fileRecord );
				break;
			case self::FILE_OPERATION_DOWNLOAD:
				$this->executeDownloadFile( $fileRecord );
				break;
		}
	}

	public function commit( Transaction $transaction ) {
		$currentTransactionLevel = $this->transactionLevel;

		parent::commit( $transaction );

		if ( array_key_exists( $currentTransactionLevel, $this->fileOperations ) ) {
			if ( $currentTransactionLevel == 1 ) { // last open transaction? execute operations
				foreach ( $this->fileOperations[ $currentTransactionLevel ] as $fileOperation ) {
					$this->executeFileOperation( $fileOperation[ 'fileRecord' ], $fileOperation[ 'type' ] );
				}
			} else { // still got a transaction open? let operations fall down to the next level
				if ( array_key_exists( $this->transactionLevel, $this->fileOperations ) ) {
					$this->fileOperations[ $this->transactionLevel ] = array_merge( $this->fileOperations[ $this->transactionLevel ], $this->fileOperations[ $currentTransactionLevel ] );
				} else {
					$this->fileOperations[ $this->transactionLevel ] = $this->fileOperations[ $currentTransactionLevel ];
				}
			}

			unset( $this->fileOperations[ $currentTransactionLevel ] );
		}
	}

	protected function undoFileOperation( IFileInfo $fileRecord, $type ) {
		switch ( $type ) {
			case self::FILE_OPERATION_UPLOAD:
				$this->undoUploadFile( $fileRecord );
				break;
			case self::FILE_OPERATION_UNLINK:
				$this->undoUnlinkFile( $fileRecord );
				break;
			case self::FILE_OPERATION_DOWNLOAD:
				$this->undoDownloadFile( $fileRecord );
				break;
		}
	}

	public function rollback( Transaction $transaction ) {
		$currentTransactionLevel = $this->transactionLevel;

		parent::rollback( $transaction );

		if ( array_key_exists( $currentTransactionLevel, $this->fileOperations ) ) {
			// rollback should always execute, no matter which transactionlevel we currently have!

			$exceptionStack = array();

			foreach ( $this->fileOperations[ $currentTransactionLevel ] as $fileOperation ) {
				$this->undoFileOperation( $fileOperation[ 'fileRecord' ], $fileOperation[ 'type' ] );
				// don't catch exceptions here, as we might get strange results then!
			}

			unset( $this->fileOperations[ $currentTransactionLevel ] );
		}
	}


	public function _cleanup() {
		parent::_cleanup();

		try {
			while ( $fileOperations = array_pop( $this->fileOperations ) ) {
				foreach ( $fileOperations as $fileOperation ) {
					try {
						$this->undoFileOperation( $fileOperation[ 'fileRecord' ], $fileOperation[ 'type' ] );
					} catch ( Exception $e ) {
						$this->safeLog( $e );
					}
				}
			}
		} catch ( Exception $e ) {
			$this->safeLog( $e );
		}
	}
}


?>