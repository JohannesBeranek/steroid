<?php
/**
 *
 * @package steroid\cli
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';

require_once STROOT . '/storage/class.DBInfo.php';
require_once STROOT . '/pubdate/class.RCPubDateEntries.php';
require_once STROOT . '/user/class.User.php';

/**
 *
 * @package steroid\cli
 *
 */
class CHPubDateCron extends CLIHandler {

	public function performCommand( $called, $command, array $params ) {
		$this->storage->init();
		User::setCLIUser( $this->storage );

		if ( count( $params ) == 1 && $params[ 0 ] == 'lockstatus' ) {
			echo $this->getLockStatus() ? '1' : '0';
			return EXIT_SUCCESS;
		}

		if ( !$this->tryLock() ) {
			$this->output( "Seems like a sync is already running, aborting ...\n" );
			return EXIT_FAILURE;
		}

		// current date/time
		$date = date( 'Y-m-d H:i:s' );

		// get all records with pubdate in the past
		$records = $this->storage->selectRecords(
			'RCPubDateEntries',
			array(
				'where' => array(
					'pubDate',
					'<=',
					array(
						$date
					)
				)
			)
		);

		// handle each record
		foreach ( $records as $r ) {

			$tx = $this->storage->startTransaction();
			$do = $r->do;
			$rec = $r->elementId;

			try {
				switch ( $do ) {
					case RCPubDateEntries::DO_PUBLISH:
						if ( $rec !== NULL && ( $rec = $rec->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ) ) ) ) {
							$missingReferences = array();

							$liveRec = $rec->copy(
								array(
									'live' => DTSteroidLive::LIVE_STATUS_LIVE
								),
								$missingReferences
							);

							$liveRec->save();
						}

						break;

					case RCPubDateEntries::DO_UNPUBLISH:
						if ( $rec !== NULL && ( $rec = $rec->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) ) ) ) {
							$rec->delete();
						}

						break;
				}

				$tx->commit();

			} catch ( Exception $e ) {
				$this->_createErrorMessages($do, $rec, $e);

				$tx->rollback();
			}

			$r->delete();
		}

		$this->unlock();
	}

	protected function output( $str ) {
		echo $str;
	}

	protected function _createErrorMessages( $do, $rec, $error ) {
		if($rec === NULL || $rec->{$rec::getDataTypeFieldName('DTSteroidDomainGroup')} === NULL){
			return;
		}
		
		try {
			$msg = RCMessageBox::get(
				$this->storage,
				array(
					'creator' => User::getCurrent()->record,
					'domainGroup' => $rec->domainGroup,
					'alert' => true,
					'nlsTitle' => '_messageBox.delayedActionFail.title',
					'nlsMessage' => '_messageBox.delayedActionFail.text',
					'nlsData' => json_encode( array(
						'recordClass' => '#' . get_class( $rec ) . '#',
						'recordTitle' => $rec->getTitle(),
						'domainGroup' => $rec->{$rec::getDataTypeFieldName( 'DTSteroidDomainGroup' )}->getTitle(),
						'action' => $do,
						'_exception' => array_merge( $error->getData(), array( '_exceptionClass' => get_class( $error ) ) )

					) ),
					'nlsRC' => 'generic'
				),
				false
			);

			$this->storage->ensureSaveRecord( $msg );
		} catch ( Exception $e ) {
			Log::write( $e );
			// do something/nothing - anyway
		}
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' pubdatecron command' => array(
				'usage:' => array(
					'php ' . $called . ' pubdatecron' => 'publish and unpublish marked records',
				)
			)
		) );
	}
}
