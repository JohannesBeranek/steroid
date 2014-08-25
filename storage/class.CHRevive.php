<?php
/**
 *
 * @package steroid\storage
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/page/class.DTSteroidPage.php';
require_once STROOT . '/datatype/class.BaseDTRecordReference.php';

/**
 *
 * @package steroid\storage
 *
 */
class CHRevive extends CLIHandler {

	public function performCommand( $called, $command, array $params ) {
		$this->storage->init();


		$addParam = array_shift( $params );
		
		switch ($addParam) {
			case 'all':				
				$exclude = array();
				
				foreach ($params as $param) {
					$exclude[] = $param;
					
				}
				
				$recordClassInfos = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );
				
				foreach ($recordClassInfos as $recordClassInfo) {
					$recordClass = $recordClassInfo[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];
					
					if (in_array($recordClass, $exclude, true)) {
						printf("\nExcluding recordClass %s by arguments passed\n", $recordClass);
					} else {
						switch ($recordClass::BACKEND_TYPE) {
							case Record::BACKEND_TYPE_CONTENT:
							// case Record::BACKEND_TYPE_WIDGET:
								$this->revive( $recordClass );
							break;
							default:
								printf("\nExcluding recordClass %s by backend type\n", $recordClass);
						}
					}
				}
			break;
			default:
				if ( $params ) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;
				}
				
				$recordClass = $addParam;

				$files = ClassFinder::find( $recordClass, true );
		
				if ( count( $files ) !== 1 ) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;
				}
		
				$recordClassInfo = current( $files );
				
				$recordClass = $recordClassInfo[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];
				$this->revive( $recordClass );
		}

		
		

				

		return EXIT_SUCCESS;
	}
	
	public function revive( $recordClass ) {
		$idFieldName = $recordClass::getDataTypeFieldName( 'DTSteroidID' );

		if ( $idFieldName !== NULL ) {

			$liveFieldName = $recordClass::getDataTypeFieldName( 'DTSteroidLive' );

			if ( $liveFieldName !== NULL ) {
				echo $recordClass . " has live field, looking for live records without preview version ...\n";

				$liveRecordPrimaries = $this->storage->fetchAll( 'SELECT t0.' . $this->storage->escapeObjectName( Record::FIELDNAME_PRIMARY ) . ' FROM ' . $this->storage->escapeObjectName( $recordClass::getTableName() ) . ' t0 WHERE t0.' . $this->storage->escapeObjectName( $liveFieldName ) . ' = ' . $this->storage->escape( DTSteroidLive::LIVE_STATUS_LIVE ) . ' AND NOT EXISTS ( SELECT * FROM ' . $this->storage->escapeObjectName( $recordClass::getTableName() ) . ' t1 WHERE t0.' . $this->storage->escapeObjectName( $idFieldName ) . ' = t1.' . $this->storage->escapeObjectName( $idFieldName ) . ' AND t1.' . $this->storage->escapeObjectName( $liveFieldName ) . ' = ' . $this->storage->escape( DTSteroidLive::LIVE_STATUS_PREVIEW ) . ')' );

				if ( count( $liveRecordPrimaries ) > 0 ) {
					printf( "\tFound %d live records without preview version, trying to recover preview versions from live records ...\n", count( $liveRecordPrimaries ) );

					foreach ( $liveRecordPrimaries as $values ) {
						$primary = $values[ Record::FIELDNAME_PRIMARY ];

						Record::pushIndex();

						$tx = $this->storage->startTransaction();

						try {
							$liveRecord = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $primary ), true );
							// $liveRecord->load();


							// save index state so we can restore it afterwards
							// keeps memory usage lower as well as making it possible to retry save on same record
							Record::pushIndex();


							$missingReferences = array();
							$previewRecord = $liveRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ), $missingReferences );

							if ( $missingReferences ) {
								printf( "\tMissing %d references to restore from live record with primary %d\n", count( $missingReferences ), $primary );
							}


							// track fields so we can try to fix an error when it pops up
							$fields = array();

							Record::startFieldTracking( $fields );

							Record::$currentSavePath = NULL;

							if ($previewRecord->exists()) {
								printf ("\tpreviewRecord already exists, no need to save, previewRecord primary: %d\n", $previewRecord->getFieldValue( Record::FIELDNAME_PRIMARY ));
							} else {
								$previewRecord->save();
							}

							Record::endFieldTracking();
							Record::popIndex();

							$tx->commit();


							printf( "\tRecord restored successfully, primary of preview record is %d\n", $previewRecord->getFieldValue( Record::FIELDNAME_PRIMARY ) );
						} catch ( RequiredFieldNotSetException $requiredFieldNotSetException ) { // 'Record of type ... must be set on ....' - TODO: make this more specialized exception
							$tx->rollback();

							printf( "\tFailed saving restored record, trying to fix the problem ...\n" );

							// these two can be called safely even if counterparts haven't been called before
							Record::endFieldTracking();
							Record::popIndex();

							Record::pushIndex();

							try {
								if ( !Record::$currentSavePath ) {
									throw new Exception();
								}

								$dependencyLiveRecords = $previewRecord->collect( Record::$currentSavePath, true );


								foreach ( $dependencyLiveRecords as $dependencyLiveRecord ) {
									if ( $dependencyLiveRecord === NULL ) {
										continue;
									}

									$missingReferences = array();
									$dependencyPreviewRecord = $dependencyLiveRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ), $missingReferences );
								}

								$missingReferences = array();
								$previewRecord = $liveRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ), $missingReferences );


								$previewRecord->save();

								// TODO

								printf( "\tProblem fixed.\n" );
							} catch ( Exception $e ) {
								printf( "\tUnable to fix the problem: %s\n", Debug::getStringRepresentation( $e ) );
							}

							Record::popIndex();

						} catch ( Exception $e ) {
							$tx->rollback();

							printf( "\tFailed restoring from live record with primary %d\n", $primary );

							echo Debug::getStringRepresentation( $e ) . "\n";
						}
						
						Record::popIndex();
					}

				} else {
					echo "\tNo live records without preview version found.\n";
				}
			}
		}
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' classinfo command' => array(
				'usage:' => array(
					'php ' . $called . ' revive [recordClass]' => 'try to find and fix broken records in specified recordClass'
				)
			)
		) );
	}
}
