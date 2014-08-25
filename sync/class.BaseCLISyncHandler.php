<?php
/**
 * @package steroid\clihandler
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';


require_once STROOT . '/user/class.User.php';
require_once STROOT . '/datatype/class.DTDateTime.php';

require_once STROOT . '/backend/class.RCMessageBox.php';

/**
 * @package steroid\clihandler
 */
abstract class BaseCLISyncHandler extends CLIHandler {
	protected $dryRun;
	protected $onlyInitial;
	protected $silent;
	protected $force;

	protected $complete;

	const NOTIFY_ON_ERRORCOUNT = 3; // notify users on N errors (or more) on the same url
	const MAX_BACKOFF = 259200; // 3 days in seconds ; maximum time between 2 syncs of same url in case of exponential back off
	const MAX_BACKOFF_POW = 7; // max times to back off - this is just to avoid astronomical numbers/overflow in mysql

	public function performCommand( $called, $command, array $params ) {
		$this->storage->init();

		if ( count( $params ) == 1 && $params[ 0 ] == 'lockstatus' ) {
			echo $this->getLockStatus() ? '1' : '0';
			return EXIT_SUCCESS;
		}

		$syncUrls = array();

		foreach ( $params as $param ) {
			switch ( $param ) {
				case '-d':
				case '--dry-run':
					$this->dryRun = true;
					break;
				case '-i':
				case '--initial-sync':
					$this->onlyInitial = true;
					break;
				case '-s':
				case '--silent':
					$this->silent = true;
					break;
				case '-f':
				case '--force':
					$this->force = true;
					break;
				case '-c':
				case '--complete':
					$this->complete = true;
					break;
				default:
					if ( substr( $param, 0, 4 ) == 'http' ) {
						$syncUrls[ ] = $param;
					} else {
						throw new InvalidArgumentException( 'Invalid Argument: ' . $param );
					}
			}
		}

		if ( !$this->dryRun ) {
			if ( !$this->tryLock() ) {
				$this->output( "Seems like a sync is already running, aborting ...\n" );
				return EXIT_FAILURE;
			}
		}

		$urlRecordClass = $this->getUrlRecordClass();

		// TODO: cache parsed query!
		$queryStruct = array( 'fields' => '*' );

		$urlFieldName = $this->getUrlFieldName();

		if ( $syncUrls ) {
			$queryStruct[ 'where' ] = array( $urlFieldName, '=', $syncUrls );
		} else {
			$queryStruct[ 'where' ] = array( 'autoSync', '=', array( 1 ) );
		}

		// TODO: exponential back off on sync error, then implement notify on error
		$defaultSyncLimit = $this->getSyncLimit();
		$now = time();

		if ( !$this->force ) {
			if ( $this->onlyInitial ) {
				array_push( $queryStruct[ 'where' ], 'AND', 'lastSync', '=', NULL );
			} else {
				$nowDT = DTDateTime::valueFromTimestamp( $now );
				$defaultSyncDT = DTDateTime::valueFromTimestamp( $now - $defaultSyncLimit );

				array_push( $queryStruct[ 'where' ], 'AND',
					'(', 'lastSync', '=', NULL, 'OR',
					'(',
					'(',
					'(', '(', 'autoSyncInterval', '=', NULL, 'OR', 'autoSyncInterval', '<=', array( $defaultSyncLimit ), ')', 'AND', 'lastSync', '<', array( $defaultSyncDT ), ')', 'OR',
					'(', '(', 'autoSyncInterval', '!=', NULL, 'AND', 'autoSyncInterval', '>', array( $defaultSyncLimit ), ')', 'AND', '(', 'lastSync', '+', new RBStorageInterval( 'autoSyncInterval', RBStorageInterval::SECOND ), ')', '<', array( $nowDT ), ')',
// version with exponential backup 
					')',
					')',
					')', 'AND', 'lastSyncTry', '<', 'NOW', '(', ')', '-', new RBStorageInterval(array( DB::MATH_LEAST, '(', array( $defaultSyncLimit ), '*', DB::MATH_POW, '(', array( 2 ), DB::MATH_LEAST, '(', 'lastErrorCount', array( self::MAX_BACKOFF_POW ), ')', ')', array( self::MAX_BACKOFF ), ')'), RBStorageInterval::SECOND) // make sure in case of error we don't sync more often than defaultSyncLimit, including exponential back off
//							')', 'AND', 'lastSyncTry', '<', array( $defaultSyncDT ),					
				);

			}
		}


		if ( !$this->dryRun ) {
			$tx = $this->storage->startTransaction();

			User::setCLIUser( $this->storage );
		}

		$urlRecords = $this->storage->selectRecords( $urlRecordClass, $queryStruct );

		foreach ( $urlRecords as $urlRecord ) {
			$failed = false;

			if ( $this->dryRun ) {
				$this->output( 'Would sync: ' . $urlRecord->{$urlFieldName} . "\n" );
			} else {
				$this->output( 'Syncing: ' . $urlRecord->{$urlFieldName} . "\n" );

				try {
					$this->sync( $urlRecord );

				} catch ( Exception $e ) {
					Log::write( $e );

					$failed = true;
				}

				try {
					if ( $failed ) {
						$urlRecord->lastErrorCount++;

						if ( $urlRecord->lastErrorCount >= self::NOTIFY_ON_ERRORCOUNT ) {
							if ( $user = $urlRecord->creator ) {
								$message = RCMessageBox::get( $this->storage, array( 'domainGroup' => $urlRecord->domainGroup, 'creator' => $user, 'alert' => true ), false );

								$message->nlsMessage = '_messageBox.syncFail.text';
								$message->nlsTitle = '_messageBox.syncFail.title';
								$message->nlsRC = 'generic';
								$message->nlsData = json_encode(array(
									'url' => $urlRecord->url,
									'syncFails' => $urlRecord->lastErrorCount,
									'rc' => '#' . get_class($urlRecord) . '#',
									'domainGroup' => $urlRecord->domainGroup->getTitle()
								));

								$this->storage->ensureSaveRecord( $message );
							} else {
								Log::write( 'Missing creator for urlRecord ' . $urlRecord->getTitle() );
							}

						}
					} else {
						$urlRecord->lastErrorCount = 0;
					}

					$urlRecord->save();

				} catch ( Exception $e ) {
					Log::write( $e );
				}
			}
		}

		if ( isset( $tx ) ) {
			$tx->commit();
		}

		$this->unlock();

		return EXIT_SUCCESS;
	}

	protected function getUrlFieldName() {
		return 'url';
	}

	protected function output( $str ) {
		if ( !$this->silent ) {
			echo $str;
		}
	}


	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' ' . $command => array(
				'usage:' => array(
					$called . ' ' . $command => 'sync everything',
					$called . ' ' . $command . ' lockstatus' => 'print lockstatus (1 = free, 0 = locked) and exit'
				),
				'options:' => array(
					'-f, --force' => 'force syncing of every url, no matter their last sync time',
					'-d, --dry-run' => "don't actually do anything, just list which urls would be synced",
					'-i, --initial-sync' => "only consider urls which have not been successful synced yet",
					'-s, --silent' => 'less output - useful for cronjob',
					'-c, --complete' => 'sync completely - flag is passed to synchandler and should never be needed if everything works correctly!'
				)
			)
		) );
	}

	abstract protected function getUrlRecordClass();

	abstract protected function getSyncLimit();

	abstract protected function sync( $urlRecord );
}

?>