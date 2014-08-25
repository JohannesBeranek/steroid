<?php
/**
 *
 * @package steroid\cli
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
require_once STROOT . '/log/class.RCLog.php';

require_once STROOT . '/util/class.UnixDomainSocket.php';

/**
 *
 * @package steroid\cli
 *
 * Regarding while(true) loops: we don't have pcntl on osx, and input can't be set to unbuffered without a shell call, so this is as good as it gets
 */
class CHEcholog extends CLIHandler {

	public function performCommand( $called, $command, array $params ) {
		if ( count( $params ) >= 1 ) {
			foreach ( $params as $k => &$param ) {
				switch ( $param ) {
					case '-c':
					case '--context':
						unset( $params[ $k ] );
						$printContext = true;
						break;
					case '-s':
					case '--search':
						$search = $params[ $k + 1 ];
						unset($params[$k]);
						unset($params[$k + 1]);
						break;
					case '-h':
					case '--no-highlight':
						$noHighlight = true;
						unset($params[$k]);
						break;
				}
			}

			$params = array_values( $params ); // re-index
		}

		if ( count( $params ) > 1 ) {
			$this->notifyError( $this->getUsageText( $called, $command, $params ) );
			return EXIT_FAILURE;
		}

		$this->storage->init();

		$lastCount = 1;

		$done = false;

		if ( function_exists( 'pcntl_signal' ) ) {
			$signalHandler = function ( $signo ) use ( &$done ) {
				$done = true;
			};

			pcntl_signal( SIGTERM, $signalHandler );
			pcntl_signal( SIGHUP, $signalHandler );
			pcntl_signal( SIGABRT, $signalHandler );
			pcntl_signal( SIGINT, $signalHandler );
		}

		if ( isset( $params[ 0 ] ) ) {
			if ( is_numeric( $params[ 0 ] ) ) { // count of last messages to print
				$lastCount = intval( $params[ 0 ] );
			} else {
				echo "opening uds ...\n";

				$socket = new UnixDomainSocket( $params[ 0 ], true, 0666 );
				$socket->cleanRemaining(); // make sure old leftovers get deleted

				echo "ready ...\n";

				try {
					while ( $socket->accept() && !$done ) {
						if ( function_exists( 'pcntl_signal_dispatch' ) ) {
							pcntl_signal_dispatch();
						}

						echo 'got conn' . "\n";
						echo $socket->read( true ); // blocking read	

						$socket->finish();
					}
				} catch ( UnixDomainSocketAcceptInterruptedException $e ) {
				}

				echo "\nTerminated.\n";

				return;
			}
		}

		// only get last $lastCount entries at start
		$lastLogs = $this->storage->select( 'RCLog', array( 'fields' => array( 'primary' ), 'orderBy' => array( 'primary' => DB::ORDER_BY_DESC ) ), 0, $lastCount + 1 );

		$lastUid = !empty($lastLogs) ? $lastLogs[ count( $lastLogs ) - 1 ][ 'primary' ] : 0;
		
		$qs = array(
			'name' => 'CHEcholog_select',
			'fields' => array(
				'primary',
				'ctime',
				'formatted',
				'requestID'
			),
			'where' => array(
				'primary',
				'>',
				'%1$s'
			),
			'orderBy' => array(
				'primary' => RBStorage::ORDER_BY_ASC
			)
		);

		if ( !empty( $printContext ) ) {
			$qs[ 'fields' ][ ] = 'context';
			$qs['name'] .= '_context';
		}

		if (isset($search)) {
			array_push($qs['where'], 'AND', 'formatted', 'LIKE', array( '%%' . $this->storage->escapeLike($search) . '%%' ));
			$qs['name'] .= '_search';
		}

		while ( !$done ) {
			if ( function_exists( 'pcntl_signal_dispatch' ) ) {
				pcntl_signal_dispatch();

				if ( $done ) {
					echo "\nTerminated.\n";

					return;
				}
			}

			$logs = $this->storage->select( 'RCLog', $qs, NULL, NULL, NULL, array( $lastUid ) );

			foreach ( $logs as $log ) {
				printf("################### %s - %s ##################################\n\n", $log[ 'requestID' ], $log[ 'ctime' ]);

				if ( !empty( $printContext ) ) {
					echo $log[ 'context' ] . "\n------------- DEBUG ------------\n";
				}

				if (isset($noHighlight)) {
					echo $log[ 'formatted' ] . "\n";
				} else {
					echo preg_replace("/(?|(\[[^\]]*\][^\(]*)(\(.*)|(\[[^\]]*\])(.*))/", "\033[0;35m$1\033[0;0m$2", $log['formatted']) . "\n";
				}

				$lastUid = (int)$log[ 'primary' ];
			}

			sleep( 1 );
		}
	}

	public function getUsageText( $called, $command, array $params ) {

		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' ' . $command . ' command' => array(
				'usage:' => array(
					'php ' . $called . ' ' . $command . '[-c|--context] [-s|--search sword] [-h|--no-highlight] [COUNT]' => 'tail log (optionally printing context, optionally filtering by search sword, optionally disabling highlighting)',
					'php ' . $called . ' ' . $command . ' [keyword]' => 'tail keyword logging (use Log::dlog in code + dlog=keyword in url ; keyword may be anything but a number)'
				)
			)
		) );

	}
}