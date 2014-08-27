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
		$this->storage->init();

		if ( count( $params ) !== 1 ) {
			$this->notifyError( $this->getUsageText( $called, $command, $params ) );
			return EXIT_FAILURE;
		}
		
		switch ($params[0]) {
			case 'fixelement':
			break;
			default:
				$this->notifyError( $this->getUsageText( $called, $command, $params ) );
				return EXIT_FAILURE;
		}

		$allRecordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );		

		foreach ($allRecordClasses as $recordClassInfo) {
			Record::pushIndex();
						
			$recordClass = $recordClassInfo[ClassFinder::CLASSFILE_KEY_CLASSNAME];
			
			printf( "Checking record class %s ...\n", $recordClass );
			
			
			// only work on widgets
			if ($recordClass::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET) {
				$where =  array(
					'element:RCElementInArea.primary', '=', NULL, 'AND',
					'recordPrimary:RCClipboard.primary', '=', NULL
				);
				
				if ($recordClass === 'RCArea') {
					array_push($where, 'AND', 'area:RCPageArea.primary', '=', NULL);
				}
				
				$records = $this->storage->selectRecords(
					$recordClass,
					array(
						'fields' => '*',
						'where' => $where
					)
				);
				
				if ($records) {
					printf( "Found %d widow records, will try to delete them.\n", count($records) );
				
					foreach ($records as $record) {
						$tx = $this->storage->startTransaction();
						
						try {
							$record->delete();
							
							$tx->commit();
						} catch(Exception $e) {
							$tx->rollback();
							
							printf( "Failed deleting: %s\n", $e->getMessage());
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
			ST::PRODUCT_NAME . ' classinfo command' => array(
				'usage:' => array(
					'php ' . $called . ' fixelement' => 'try to find and fix broken element records'
				)
			)
		) );
	}
}
