<?php
/**
 *
 * @package package_name
 */

require_once STROOT . '/storage/interface.IStorage.php';
require_once STROOT . '/user/interface.IUserAuthentication.php';
require_once STROOT . '/user/class.User.php';
require_once STROOT . '/util/class.Password.php';
require_once STROOT . '/datatype/class.DTPassword.php';

require_once STROOT . '/datatype/class.DTString.php';

class ACDefault implements IUserAuthentication {
	const AUTH_TYPE = User::AUTH_TYPE_BE;

	protected $storage;

	public function __construct() {

	}

	public function setConfig( Config $conf ) {

	}

	public function setStorage( IStorage $storage ) {
		$this->storage = $storage;
	}

	public function initWithData( $data ) {

	}

	final public function getReturnValueFromData( $retObject ) {
		$ret = array(
			'record' => NULL // TODO: RCUser
		);

		return $ret;
	}

	public function auth( IRequestInfo $requestInfo ) {
		$username = $requestInfo->getPostParam( 'username' );

		try {
			$password = new Password( $requestInfo->getPostParam( 'password' ) ); // Security: password is wrapped so it doesn't end up in the logs
		} catch ( Exception $e ) {
			// Security: for the improbable possibility that we get an exception when
			// instancing a new Password, we don't want the password to be part of the log chain either,
			// so we throw a new exception here
			throw new Exception();
		}

		$password = DTPassword::hash($password);

		$userRecord = $this->storage->selectFirstRecord('RCUser', array('where' => array('username', '=', array($username), 'AND', 'password', '=', array($password))));

		if(!$userRecord){
			throw new UserAuthException( 'Unable to auth user', 0 );
		}

		$ret = array(
			'record' => $userRecord,
			'data' => array(
				'id' => $userRecord->primary
			)
		);

		return $ret;
	}

	public function deauth( array $data ) {

	}

	public static function addToFieldDefinitions( $recordClass, array $existingFieldDefinitions ) {
		$newFields = array();

		if($recordClass === 'RCUser'){
			$newFields['password'] = DTPassword::getFieldDefinition();
		}

		return $newFields;
	}
}