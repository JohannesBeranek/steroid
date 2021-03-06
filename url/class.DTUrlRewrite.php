<?php
/**
 * @package steroid\url
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';

require_once __DIR__ . '/class.RCUrlRewrite.php';

// for profiveRewrite function
require_once __DIR__ . '/class.RCUrl.php';
require_once STROOT . '/page/class.RCPage.php';
require_once STROOT . '/storage/interface.IRBStorage.php';

/**
 * @package steroid\url
 */
class DTUrlRewrite extends BaseDTRecordReference {
	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'recordClass' => 'RCUrlRewrite',
			'nullable' => true,
			'requireForeign' => false,
			'requireSelf' => true,
			'default' => NULL,
			'constraints' => array( 'min' => 0, 'max' => 1 )
		);
	}
	
	private static function getNewUrl( IRBStorage $storage, RCPage $page, $prefix = NULL, $suffix = NULL, $liveState = NULL, $acceptUrl = NULL ) {
		$ct = 0;
		$urlLast = ( $prefix === NULL ? '' : $prefix ) . ( $suffix === NULL ? '' : $suffix );
		
		// trim + adding slash is needed so root page url works the same as others
		$urlBase = rtrim( $page->getUrlForPage( $page, false ), '/' ) . '/'; 
		
		$domainGroup = $page->domainGroup;
		
		if ($domainGroup === NULL) {
			throw new Exception();
		}

		do {
			if ( $ct > 0 ) {
				if ( $prefix !== NULL ) {
					$urlLast = $prefix . '-' . $ct . ( $suffix === NULL ? '' : $suffix );
				} else {
					$urlLast = $ct . '-' . $suffix;
				}
			}

			$newUrl = $urlBase . $urlLast;

			// TODO: cache query
			// TODO: also use already existing records (maybe via record index?)
			$urlRow = $storage->selectFirst( 'RCUrl', array(
				'fields' => array('primary'),
				'where' => array(
					'url', '=', array( $newUrl ),
					'AND', 'domainGroup', '=', array( $domainGroup ), // RCDomainGroup has no live field
					'AND', 'live', '=', array( $liveState ) ) ) );


		
			
			$ct++;
		} while ( $urlRow && $acceptUrl !== $newUrl );
		
		return $newUrl;
	}

	private static function createNewUrlRecord( IRBStorage $storage, RCPage $page, $urlHandlerRecord, $prefix = NULL, $suffix = NULL, $liveState = NULL ) {
		if ($prefix === NULL && $suffix === NULL) {
			throw new InvalidArgumentException( 'Must provide either $prefix or $suffix, or both');
		}
		
		if ($liveState === NULL) {
			$liveState = $page->live;
		}
		
		$newUrl = self::getNewUrl( $storage, $page, $prefix, $suffix, $liveState );
		
		$urlRecord = RCUrl::get( $storage, array( 
			'url' => $newUrl, 
			'domainGroup' => $page->domainGroup, 
			'live' => $liveState, 
			'urlHandler' => $urlHandlerRecord,
			'returnCode' => DTSteroidReturnCode::RETURN_CODE_PRIMARY 
		), false );

		return $urlRecord;
	}

	private static function provideRewriteUrl( $rewriteField, IRBStorage $storage, RCPage $page, array $params = NULL, IRecord $rewriteOwningRecord = NULL, $rewriteTitlePrefix = NULL, $rewriteTitleSuffix = NULL, &$rewriteUrlRecordReturn = NULL ) {
		// get attached url record
		if (isset($rewriteUrlRecordReturn->url) || $rewriteUrlRecordReturn->exists()) {
			$urlRecord = $rewriteUrlRecordReturn->getFieldValue('url');
			
			if ($urlRecord !== NULL) {
				$compareUrl = self::getNewUrl($storage, $page, $rewriteTitlePrefix, $rewriteTitleSuffix, $page->live, $urlRecord->url);

				if($compareUrl !== $urlRecord->url){
					$tx = $storage->startTransaction();

					try{
						$urlRecord->url = $compareUrl;
						$urlRecord->save();

						$tx->commit();
					} catch(Exception $e){
						$tx->rollback();
						throw($e);
					}
				}

				$url = $urlRecord->url;
			}
		}
		
		if (!isset($url)) {
			// try to fetch rewriteUrlRecord for other live status 
			// to check if it has an url record attached
			
			
			$liveStatus = $page->live;
			$otherLiveStatus = DTSteroidLive::getOtherLiveStatus( $liveStatus );
			
			// need to disable filter for that
			$frontendFilter = $storage->getFilter( UHPage::FILTER_IDENTIFIER );
			
			if ( $frontendFilter !== NULL ) {
				$storage->unregisterFilter( UHPage::FILTER_IDENTIFIER );
			}
			
			try {
				// try to get a live record from owning Record
				$otherRewriteOwningRecord = $rewriteOwningRecord->getFamilyMember( array( 'live' => $otherLiveStatus ) );
				
				// check if we actually got a different record
				if ( $otherRewriteOwningRecord !== $rewriteOwningRecord && $otherRewriteOwningRecord->exists() ) {
					$otherRewriteUrlRecord = $otherRewriteOwningRecord->getFieldValue( $rewriteField );
					
					// ... and if the record with the other live status has a rewrite record set to it
					if ( $otherRewriteUrlRecord !== NULL ) {
						$otherUrlRecord = $otherRewriteUrlRecord->getFieldValue('url');
						
						if ( $otherUrlRecord !== NULL ) {
							// finally, if we can get an url record from that, 
							// we can copy it for our original live state
							// and thus fix up our original record	
							
							$missingReferences = array();
	
							$urlRecord = $otherUrlRecord->copy( array( 'live' => $liveStatus ), $missingReferences );
	
							// just to be safe
							if ($urlRecord === $otherUrlRecord) {
								throw new Exception();
							}
							
							if ($urlRecord->exists()) {
								$foreignRewrites = $urlRecord->{'url:RCUrlRewrite'};
								
								if ($foreignRewrites) {
									$foreignRewrite = reset($foreignRewrites);
									
									if (count($foreignRewrites) > 1) {
										// should not happen
										throw new Exception();
									} else if ($rewriteUrlRecordReturn === $foreignRewrite) {
										// should not happen
										throw new Exception();
									} else {
										// other rewrite record is already connected to url
										// this should not happen
										
										// if it still happens, we try to take over the existing rewrite
										if($foreignRewrite->getReferenceCount() === 0){
											$foreignRewrite->url = NULL;
											$foreignRewrite->delete();
										} else {
											// if the existing rewrite is still in use (referenced), fail.
											throw new Exception('UrlRewrite with primary ' . $foreignRewrite->{Record::FIELDNAME_PRIMARY} . ' already exists and is in use');
										}
									}
								} else {
									$pageUrlEntries = $urlRecord->{'url:RCPageUrl'};

									if ($pageUrlEntries) {
										throw new Exception();
									}
								}
							}
	
							//  make sure we don't get problems if url is already taken in current live status
							$urlRecord->url = self::getNewUrl($storage, $page, $rewriteTitlePrefix, $rewriteTitleSuffix, $liveStatus);
							
							
							$rewriteUrlRecordReturn->url = $urlRecord;
						

							// put save into transaction as multiple records will be affected
							$tx = $storage->startTransaction();
							
							try {
								$rewriteUrlRecordReturn->save();
								
								$tx->commit();
							} catch( Exception $e ) {
								$tx->rollback();
								throw($e);
							}
							
							// if everything went fine, it's time to set the url to return it later on
							$url = $urlRecord->getFieldValue('url');
						} 
						
						// else: other record also doesn't have an url record
						// so we need to create one from scratch
			
						// -> $url is not set, so urlRecord will be created
						
					} 
	
					// else: other record doesn't have a rewrite record set to it
					// so we need to create out url record from scratch
					
					// -> $url is not set, so urlRecord will be created
					
				} 
	
				// else: there might not be a record in a different live state for this record class
				// so create url record from scratch
				
				// -> $url is not set, so urlRecord will be created
				
				if ( !isset($url) ) {
					$tx = $storage->startTransaction();
					
					try {
						static $urlHandlerRecord;
						
						if ($urlHandlerRecord === NULL) {
							$urlHandlerRecord =  $storage->selectFirstRecord( 'RCUrlHandler', array( 'where' => array( 'className', '=', array( 'UHUrlRewrite' ) ) ) );
							
							if ($urlHandlerRecord === NULL) {
								throw new Exception('Unable to find UHUrlRewrite urlHandler');
							}
						}
		
						$urlRecord = self::createNewUrlRecord( $storage, $page, $urlHandlerRecord, $rewriteTitlePrefix, $rewriteTitleSuffix, $liveStatus );
					
						$rewriteUrlRecordReturn->url = $urlRecord;
					
						$urlRecord->save();
	
						$tx->commit();
					} catch( Exception $e ) {
						$tx->rollback();
						throw $e;
					}
					
					$url = $urlRecord->url;
				}
			} catch(Exception $e) {
				// enable filter again
				if ( isset($frontendFilter) ) {
					$storage->registerFilter( $frontendFilter, UHPage::FILTER_IDENTIFIER );
					unset( $frontendFilter );
				}
			
				throw $e;
			}
			
			// enable filter again
			if ( isset($frontendFilter) ) {
				$storage->registerFilter( $frontendFilter, UHPage::FILTER_IDENTIFIER );
				unset( $frontendFilter );
			}
			
		
		}

		return $url;
	}

	// FIXME: prevent different records from owning the same RCUrlRewrite record
	public static function provideRewrite( IRBStorage $storage, RCPage $page, array $params = NULL, IRecord $rewriteOwningRecord = NULL, $rewriteTitlePrefix = NULL, $rewriteTitleSuffix = NULL, &$rewriteUrlRecordReturn = NULL ) {
		
				
		if ( ( $rewriteTitlePrefix !== NULL || $rewriteTitleSuffix !== NULL ) && $rewriteOwningRecord !== NULL ) {		
	
			// get field of owning record which references RCUrlRewrite
			$rewriteField = $rewriteOwningRecord->getDataTypeFieldName( 'DTUrlRewrite' );
		
			// check if rewriteRecord already exists
			$rewriteFieldValue = $rewriteOwningRecord->getFieldValue( $rewriteField );
			
			
			if ( $rewriteFieldValue === NULL ) {
				// need to create rewriteRecord 
			
				$tx = $storage->startTransaction();
				
				try {
				
					$originalUrl = $page->getUrlForPage( $page, $params, false, true );
					
					$rewriteUrlRecordReturn = RCUrlRewrite::get( $storage, array( 'rewrite' => $originalUrl ), false );
					
					$rewriteOwningRecord->{$rewriteField} = $rewriteUrlRecordReturn;
					
					$url = self::provideRewriteUrl( $rewriteField, $storage, $page, $params, $rewriteOwningRecord, $rewriteTitlePrefix, $rewriteTitleSuffix, $rewriteUrlRecordReturn );

					// will trigger chain save for rewriteRecord and urlRecord
					$rewriteOwningRecord->save();
				
					$tx->commit();
				} catch(Exception $e) {
					$tx->rollback();
					
					throw $e;
				}
				
			} else {
				// check if original url changed
				$originalUrl = $page->getUrlForPage($page, $params, false, true);

				if($rewriteFieldValue->rewrite !== $originalUrl){
					$tx = $storage->startTransaction();

					try{
						$rewriteFieldValue->rewrite = $originalUrl;

						$rewriteFieldValue->save();

						$tx->commit();
					} catch(Exception $e) {
						$tx->rollback();

						throw $e;
					}
				}

				// rewriteRecord exists 
				$rewriteUrlRecordReturn = $rewriteFieldValue;
				
				$url = self::provideRewriteUrl( $rewriteField, $storage, $page, $params, $rewriteOwningRecord, $rewriteTitlePrefix, $rewriteTitleSuffix, $rewriteUrlRecordReturn );	
			}

		} else {
			$url = $page->getUrlForPage( $page, $params, false, true );
		}

  
		return $url;
	}


	protected function mayCopyReferenced() {
		return 0;
	}
}
