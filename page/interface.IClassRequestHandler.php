<?

require_once STROOT . '/request/interface.IRequestInfo.php';

require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/page/class.RCPage.php';

interface IClassRequestHandler {
	public function handleClassRequest( IRBStorage $storage, RCPage $page, $command, IRequestInfo $requestInfo );
}


?>