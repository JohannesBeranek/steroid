<?php 

require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/storage/interface.IStorage.php';
require_once STROOT . '/request/interface.IRequestInfo.php';

interface IUserAuthentication {
	/**
	 * Try to authenticate user
	 * 
	 * Calling this function may lead to a redirect in case of OAuth.
	 *
	 * @param IRequestInfo $requestInfo
	 */
	public function auth( IRequestInfo $requestInfo );

	public function deauth( array $data );
	
	/**
	 * Call before anything else, right after constructor
	 * 
	 * @param Config $conf
	 */
	public function setConfig( Config $conf );
	
	public function setStorage( IStorage $storage );
	
	public function initWithData( $data );
	
	public static function addToFieldDefinitions( $recordClass, array $existingFieldDefinitions );
}

class UserAuthException extends Exception{}
class UserRegisterTakenException extends Exception{}