<?php

require_once STROOT . '/storage/interface.IStorage.php';
require_once STROOT . '/url/class.RCUrl.php';
require_once STROOT . '/request/interface.IRequestInfo.php';

interface IURLHandler {
	const RETURN_CODE_HANDLED = 0;
	const RETURN_CODE_REEVALUATE_PAGEPATH = 1;
	
	/** 
	 * @param RCUrl $url
	 * @param IRBStorage $storage
	 *
	 * @return int
	 */
	public function handleURL( IRequestInfo $requestInfo, RCUrl $url, IRBStorage $storage );
}

class URLHandlerException extends Exception {}

class BadRequestException extends Exception {}

?>