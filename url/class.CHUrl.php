<?php
/**
 *
 * @package steroid\cli
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once __DIR__ . '/class.RCUrlRewrite.php';

// polyfill for php < 5.5
require_once STROOT . '/util/array_column.php';

/**
 *
 * @package steroid\cli
 *
 */
class CHUrl extends CLIHandler {
	protected $skipped = array();
	protected $urlWithWrongLive = array();
	
	public function performCommand( $called, $command, array $params ) {
		if ( count( $params ) !== 1 ) {
			$this->notifyError( $this->getUsageText( $called, $command, $params ) );
			return EXIT_FAILURE;
		}
		
		switch ($params[0]) {
			case 'fixrewrite':
				$this->commandFixRewrite();
			break;
			default:
				$this->notifyError( $this->getUsageText( $called, $command, $params ) );
				return EXIT_FAILURE;
		}

	
		return EXIT_SUCCESS;
	}
	
	final private function commandFixRewrite() {
		$this->storage->init();
		
		printf("Preface: fixing url primaries.\n");
		
		$rows = $this->storage->fetchAll('SELECT `primary`, id, live FROM rc_url WHERE `primary` != id | ((live & 1) << 24)');
		
		printf("Number of url primaries to fix: %d\n", count($rows));
		
		foreach ($rows as $row) {
			$primary = $row['primary'];
			$newPrimary = intval($row['id']) | ((intval($row['live']) & 1) << 24);
			
			$tx = $this->storage->startTransaction();
			try {
				$this->storage->update('rc_url_rewrite', 'url_primary = ' . $this->storage->escape($primary), array('url_primary' => $newPrimary));
				$this->storage->update('rc_page_url', 'url_primary = ' . $this->storage->escape($primary), array('url_primary' => $newPrimary));
				$this->storage->update('rc_url', '`primary` = ' . $this->storage->escape($primary), array('primary' => $newPrimary));

				$tx->commit();
			} catch(Exception $e) {
				$tx->rollback();
				printf("Failed updating primary %d: %s\n", $primary, $e->getMessage());
				continue;	
			}
			
			printf("Updated primary from %d to %d\n", $primary, $newPrimary );
		}
		

		// find records owning url rewrites
		$foreignReferences = RCUrlRewrite::getForeignReferences();
		
		
		foreach ($foreignReferences as $fieldName => $ref) {
			$recordClass = BaseDTForeignReference::getRecordClassForFieldName( $fieldName );
			$rewriteRecordPrimaries = array();
			
			printf("Checking %s ...\n", $recordClass);
			
			$rewriteField = $recordClass::getDataTypeFieldName('DTUrlRewrite');
			
			if (!isset($rewriteField)) {
				echo "Unable to find rewrite field, skipping.\n";
				continue;
			}
			
			// try to find id path from recordClass
			$idPath = $recordClass::getDataTypeFieldName('DTSteroidID');
			
			if ($idPath === NULL) {
				$fieldDefinitions = $recordClass::getOwnFieldDefinitions();
				
				foreach ($fieldDefinitions as $fieldName => $fieldDef) {
					if ( is_subclass_of( $fieldDef[ 'dataType' ], 'BaseDTRecordReference' ) ) {
						$referencedRecordClass = BaseDTRecordReference::getRecordClassStatically($fieldName, $fieldDef);
						
						if ( $referenceField = $referencedRecordClass::getDataTypeFieldName( 'DTSteroidID' ) ) {
							$idPath = $fieldName . '.' . $referenceField;
							break;
						}
					}
				}
				
				if ($idPath === NULL) {
					echo "Unable to find id path, skipping.\n";
					continue;
				}
			} 
			
			
			$relevantFields = array( $idPath, $rewriteField, $rewriteField . '.*', $rewriteField . '.url.*' );
			
	
			Record::pushIndex();	
							
			// this way we only get records which actually do have a rewrite record set
			$previewRecords = $this->storage->selectRecords( $recordClass, array( 
			'fields' => $relevantFields,
			'where' => array(
				$rewriteField . '.url.live', '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW )
			)), NULL, NULL, NULL, NULL, NULL, 1);
							
			printf("Fixing preview records, currently %d records indexed.\n", Record::getRecordCount());
			
			
			foreach ($previewRecords as $previewRecord) {
				$this->fixRecord( $previewRecord, $rewriteField, $rewriteRecordPrimaries );
			}
			
			Record::popIndex();
			unset($previewRecords);
			
			Record::pushIndex();
			
			// there might be live records with rewrite where the corresponding preview record has no rewrite
			$liveRecords = $this->storage->selectRecords( $recordClass, array( 
			'fields' => $relevantFields,
			'where' => array(
				$rewriteField . '.url.live', '=', array( DTSteroidLive::LIVE_STATUS_LIVE )
			)), NULL, NULL, NULL, NULL, NULL, 1);
			
			
			// get preview records from live records which weren't in first select
			foreach ($liveRecords as $liveRecord) {
				$previewRecord = $liveRecord->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ) );
									
				if (!$previewRecord->exists()) {
					printf("SKIP: previewRecord does not seem to exist for live record, liveRecord values %s.\n", Debug::getStringRepresentation( $liveRecord->getValues() ));
					continue;
				}
									
				if ( $previewRecord !== $liveRecord && ! $previewRecord->getFieldValue( $rewriteField ) ) {
					$this->fixRecord( $previewRecord, $rewriteField, $rewriteRecordPrimaries );
				}
			}
			
			
			Record::popIndex();
				
				
		
			
			
			unset($rewriteField);
			
		}

		echo "\nFinished Part 1, will continue trying to remove url rewrites without owner.\n";
		
		$urlRewritePrimaries = $this->storage->fetchAll('SELECT `primary` FROM rc_url_rewrite');
		
		$deleteCount = 0;
		$failCount = 0;
		
		printf("Checking %d urlRewrites\n", count($urlRewritePrimaries));
		
		foreach ($urlRewritePrimaries as $urlRewritePrimaryRow) {
			$urlRewritePrimary = $urlRewritePrimaryRow['primary'];
						
			$urlRewriteRecord = RCUrlRewrite::get( $this->storage, array( 'primary' => $urlRewritePrimary ), Record::TRY_TO_LOAD );
			$isReferenced = false;
			
			foreach ($foreignReferences as $fieldName => $ref) {
				if ($urlRewriteRecord->getFieldValue($fieldName)) {
					$isReferenced = true;
					break;
				}
			}
			
			if (! $isReferenced) {
				printf("Deleting rewrite %s with primary %d ...", $urlRewriteRecord->rewrite, (int)$urlRewritePrimary);
				
				try {
					$urlRewriteRecord->delete();
					unset($urlRewriteRecord);
					
					$deleteCount ++;
					echo " deleted\n";
				} catch(Exception $e) {
					$failCount ++;
					echo " failed: " . $e->getMessage() . "\n";
				}
				
			}			
		}
		
		printf( "\nFinished Part 2. Deleted: %d  ; Failed: %d\n", $deleteCount, $failCount );
		
		printf( "\nPart 3: try to correct wrong live value on url records\n");
		
		$tableName = RCUrl::getTableName();
		
		foreach ($this->urlWithWrongLive as $primary => $id) {
			try {
				printf("Trying to set live correct on url with primary %d ...", $primary);
				$this->storage->update( $tableName, '`primary` = ' . $this->storage->escape($primary), array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) );
				
				echo " done\n";
			} catch( Exception $e ) {
				echo " failed\n";
			}
		}

		echo "\nFinished.\n";
		
		if ($this->skipped) {
			echo "Skipped the following record ids:\n";
			
			foreach ($this->skipped as $recordClass => $skipped) {
				printf("\t%s: %s\n", $recordClass, implode(', ', $skipped));
			}
		}
	}

	final private function fixRecord( $previewRecord, $rewriteField, &$rewriteRecordPrimaries ) {
		static $urlFields;
		
		if ($urlFields === NULL) {
			$urlFields = RCUrl::getOwnFieldDefinitions();
			
			unset($urlFields['id']);
			unset($urlFields[Record::FIELDNAME_PRIMARY]);
			unset($urlFields['live']);
			
			$urlFields = array_keys($urlFields);
		}

		Record::pushIndex();
		
		$tx = $this->storage->startTransaction();
			
		try {
			$liveRecord = $previewRecord->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) );
			
			if ($liveRecord->exists() && $liveRecord !== $previewRecord) {
				// check if live record has a rewrite record
				$liveRewriteRecord = $liveRecord->getFieldValue( $rewriteField );
				
				if ($liveRewriteRecord) {
					$liveRewriteRecordPrimary = $liveRewriteRecord->getFieldValue( Record::FIELDNAME_PRIMARY );
					
					if (in_array($liveRewriteRecordPrimary, $rewriteRecordPrimaries, true)) {
						printf("Found liveRewriteRecord primary already handled: %d\n", $liveRewriteRecordPrimary);
						$liveRewriteAlreadyHandled = true;
					} else {
						$rewriteRecordPrimaries[] = $liveRewriteRecordPrimary;
						$liveRewriteAlreadyHandled = false;
					}
					
					$liveUrlRecord = $liveRewriteRecord->url;
					
					if ($liveUrlRecord->live !== DTSteroidLive::LIVE_STATUS_LIVE) {
						
						
						if ($liveRewriteAlreadyHandled) {
							printf("Live status not live on url, but should be, id: %d ; liveRewrite of record already handled, will delete connection.\n", $liveUrlRecord->id);
							
							$liveRewriteRecord->readOnly = true;
							$liveRecord->{$rewriteField} = NULL;
							$liveRecord->save();
							$liveRewriteRecord->readOnly = false;
							
							echo "Connection deleted, continuing.\n";
						} else {
							printf("SKIP: Live status not live on url, but should be, id: %d ; liveRewrite of record not yet handled.\n", $liveUrlRecord->id);
							$this->skipped['RCUrl'][$liveUrlRecord->primary] = $liveUrlRecord->id;
							$this->urlWithWrongLive[$liveUrlRecord->primary] = $liveUrlRecord->id;
						}
					} else {
					
						// check if preview has a rewriteRecord
						$previewRewriteRecord = $previewRecord->getFieldValue( $rewriteField );
						
						
						if ($previewRewriteRecord) {
							// preview + live version of rewrite record exist - compare id fields of urls							
							$previewRewriteRecordPrimary = $previewRewriteRecord->getFieldValue( Record::FIELDNAME_PRIMARY );
							
							if (in_array($previewRewriteRecordPrimary, $rewriteRecordPrimaries, true)) {
								printf("Found previewRewriteRecord primary already handled: %d\n", $liveRewriteRecordPrimary);
								$previewRewriteAlreadyHandled = true;
							} else {
								$rewriteRecordPrimaries[] = $previewRewriteRecordPrimary;
								$previewRewriteAlreadyHandled = false;
							}
									
							$previewUrlRecord = $previewRewriteRecord->url;
							
							if ($previewUrlRecord->id !== $liveUrlRecord->id) {
								printf("Url record id field does not match between preview and live: %d != %d\n", $previewUrlRecord->id, $liveUrlRecord->id);
								
								$liveRewriteRecord->load();
								
								$liveUrlRecord->load();
								$liveUrlValues = array();
								
								// save current values
								foreach ($urlFields as $urlField) {
									$liveUrlValues[$urlField] = $liveUrlRecord->getFieldValue( $urlField );
								}
								
								$liveRewriteRecord->url = NULL; // disconnect
								$liveRewriteRecord->readOnly = true; // prevent delete
	
								$liveUrlRecord->delete(); // delete wrong url
								unset($liveUrlRecord);
		
								$liveRewriteRecord->readOnly = false; // unprotect
		
								
								$missingRefArr = array();
								$newLiveUrlRecord = $previewUrlRecord->copy(array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ), $missingRefArr);
								unset($missingRefArr);
								
								// equivalent to 							
								// $newLiveUrlRecord->setValues( $liveUrlValues );
								// but prevents unneccessary dirtying
								foreach ($liveUrlValues as $field => $value) {
									if ($newLiveUrlRecord->getFieldValue($field) !== $value) {
										$newLiveUrlRecord->{$field} = $value;
									}
								}

								// set live record readonly to prevent unecessary save calls
								$liveRecord->readOnly = true;

								
								$liveRewriteRecord->url = $newLiveUrlRecord;
								
								// check if saving rewriterecord would result in duplicate key
								if ($newLiveUrlRecord->fieldHasBeenSet( Record::FIELDNAME_PRIMARY )) {
									$exists = (bool)$this->storage->select( 'RCUrlRewrite', array( 'where' => array(
										'url' => array( $newLiveUrlRecord )
									) ), 0, 0, true );
									
									if ($exists === true) {
										printf( "SKIP: Saving rewrite would probably result in duplicate key for url_primary = %d\n", $newLiveUrlRecord->{Record::FIELDNAME_PRIMARY});
									} else {
										// will also automatically save url
										$liveRewriteRecord->save();				
									}
								} else {
								
									// will also automatically save url
									$liveRewriteRecord->save();
								}
						
								
								printf( "Fixed live record from preview record, live url: %s\n", $liveUrlValues['url'] );
							}
						} else {
							// do not create preview rewrite record!!!
							
/*							
							// no preview record exists - copy live to preview - this should also copy url
							$missingRefArr = array();
							$previewRewriteRecord = $liveRewriteRecord->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ), $missingRefArr );
							unset($missingRefArr);
							
							
							// test for url unique key collision
							$previewUrlRecord = $previewRewriteRecord->url;
						
							
							if (!$previewUrlRecord->exists()) {
								$collidingRecordData = $this->storage->selectFirst( 'RCUrl', array(
									'fields' => array( 'id' ),
									'where' => array(
										'url', '=', array( $previewUrlRecord->url ), 
										'AND', 'domainGroup', '=', array( $previewUrlRecord->domainGroup ), 
										'AND', 'live', '=', array( $previewUrlRecord->live ), 
										'AND', 'id', '!=', array( $previewUrlRecord->id )
									)
								), NULL, NULL, NULL, NULL, NULL, true );
								
								// var_dump($collidingRecordData);
								
								if ($collidingRecordData !== NULL) {
									printf( "Would have collision with unique url on urls with ids: %d - %d, will change rewrite\n", $previewUrlRecord->id, $collidingRecordData['id']);
									
									
									if ( preg_match('/^(.+)(\.[^\.\/]+)$/', $previewUrlRecord->url, $matches) ) {
										$prefix = $matches[1];
										$suffix = $matches[2];	
									} else {
										$prefix = $previewUrlRecord->url;
										$suffix = '';
									}
									
									$count = 0;
									
									do {
										$count++;
										
										$previewUrlRecord->url = $prefix . '-' . $count . $suffix;
										
										$collidingRecordData = $this->storage->selectFirst( 'RCUrl', array(
											'fields' => array( 'id' ),
											'where' => array(
												'url', '=', array( $previewUrlRecord->url ), 
												'AND', 'domainGroup', '=', array( $previewUrlRecord->domainGroup ), 
												'AND', 'live', '=', array( $previewUrlRecord->live ), 
												'AND', 'id', '!=', array( $previewUrlRecord->id )
											)
										), NULL, NULL, NULL, NULL, NULL, true );
									} while( $collidingRecordData !== NULL && $count < 20 );
									
									if ($collidingRecordData === NULL) {
										echo "Fixed the problem by changing url.\n";
									}
								} 
								
								if (isset($collidingRecordData)) {
									echo "SKIP: Gave up on changing url.\n";
								} else {
									$previewRewriteRecord->save();
									$previewRecord->save();
									
									printf( "Copied preview record from live record, preview url: %s\n", $previewRewriteRecord->url->url );
								}
								
							} else {		
								$previewRewriteRecord->save();
								$previewRecord->{$rewriteField} = $previewRewriteRecord; // should already have been set by notify after copy/save, but just to be sure ...
								$previewRecord->save();
							
								printf( "Copied preview record from live record, url already existed, preview url: %s\n", $previewRewriteRecord->url->url );
							}
							
*/
						}
					}
				}
			}
		
			
			$tx->commit();
		} catch(Exception $e) {
			$tx->rollback();
				
			throw $e;
		}
		
		Record::popIndex();
		
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' url command' => array(
				'usage:' => array(
					'php ' . $called . ' fixrewrite' => 'try to find and fix broken url records from rewrites'
				)
			)
		) );
	}
}
