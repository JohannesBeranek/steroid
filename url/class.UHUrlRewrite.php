<?php
/**
* @package steroid\url
*/

require_once STROOT . '/urlhandler/interface.IURLHandler.php';


/**
* @package steroid\url
*/
class UHUrlRewrite implements IURLHandler {
	public function handleURL( IRequestInfo $requestInfo, RCUrl $url, IRBStorage $storage ) {
		$urlRewriteRecords = $url->{'url:RCUrlRewrite'};
		
		if (empty($urlRewriteRecords) || count($urlRewriteRecords) !== 1) {
			throw new Exception("Unable to find RCUrlRewrite for url record");
		}
		
		$urlRewriteRecord = reset($urlRewriteRecords);
		
		$requestInfo->rewrite( $urlRewriteRecord->rewrite );
				
		return self::RETURN_CODE_REEVALUATE_PAGEPATH;
	}
}

