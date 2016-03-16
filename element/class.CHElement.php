<?php
/**
 *
 * @package steroid\cli
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/datatype/class.DTSteroidLive.php';

require_once STROOT . '/backend/class.RCClipboard.php';

/**
 *
 * @package steroid\cli
 *
 */
class CHElement extends CLIHandler {
	
	public function performCommand( $called, $command, array $params ) {
		switch ($params[0]) {
			case 'fixelement':
				return $this->fixElement();
			break;
			case 'getpages':
				//TODO: check recordClassName is set and valid
				$recordClassName = $params[1];

				ClassFinder::find(array($recordClassName), true);

				if($recordClassName::BACKEND_TYPE !== Record::BACKEND_TYPE_WIDGET){
					echo 'recordClassName must be of BACKEND_TYPE_WIDGET';
					return EXIT_FAILURE;
				}

				return $this->getPages($recordClassName);
				break;
			default:
				$this->notifyError( $this->getUsageText( $called, $command, $params ) );
				return EXIT_FAILURE;
		}
	}

	protected function getPages($recordClassName = NULL){
		$this->storage->init();

		$elements = $this->storage->selectRecords($recordClassName, array('where' => array($recordClassName::getDataTypeFieldName('DTSteroidLive'), '=', array(DTSteroidLive::LIVE_STATUS_PREVIEW))));

		foreach($elements as $element){
			$containingPages = $element->getContainingPages();

			foreach($containingPages as $page){
				printf('%s -> Page "%s" -> Widget title "%s"' . "\n", $page->domainGroup->getTitle(), $page->getTitle(), $element->getTitle() );
			}
		}

		return EXIT_SUCCESS;
	}

	protected function fixElement(){
		$allRecordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );

		$this->storage->init();

		foreach ( $allRecordClasses as $recordClassInfo ) {
			Record::pushIndex();

			$recordClass = $recordClassInfo[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];

			printf( "Checking record class %s ...\n", $recordClass );


			// only work on widgets
			if ( $recordClass::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET ) {
				$where = array(
					'element:RCElementInArea.primary',
					'=',
					null,
					'AND',
					'recordPrimary:RCClipboard.primary',
					'=',
					null
				);

				if ( $recordClass === 'RCArea' ) {
					array_push( $where, 'AND', 'area:RCPageArea.primary', '=', null );
				}

				$records = $this->storage->selectRecords(
					$recordClass,
					array(
						'fields' => '*',
						'where'  => $where
					)
				);

				if ( $records ) {
					printf( "Found %d widow records, will try to delete them.\n", count( $records ) );

					foreach ( $records as $record ) {
						$tx = $this->storage->startTransaction();

						try {
							$record->delete();

							$tx->commit();
						} catch ( Exception $e ) {
							$tx->rollback();

							printf( "Failed deleting: %s\n", $e->getMessage() );
						}
					}
				}
			}

			Record::popIndex();
		}

		return EXIT_SUCCESS;
	}


	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' ' . $command . ' command' => array(
				'usage:' => array(
					'php ' . $called . ' fixelement' => 'try to find and fix broken element records',
					'php ' . $called . ' getpages recordClassName' => 'outputs a list of pages containing elements of recordClassName'
				)
			)
		) );
	}
}
