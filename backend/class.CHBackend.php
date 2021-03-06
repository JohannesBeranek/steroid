<?php
/**
*
* @package steroid\backend
*/

require_once STROOT . '/clihandler/class.CLIHandler.php';

require_once STROOT . '/urlhandler/class.RCUrlHandler.php';
require_once STROOT . '/domain/class.RCDomain.php';
require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/url/class.RCUrl.php';
require_once STROOT . '/language/class.RCLanguage.php';

require_once STROOT . '/user/class.CHUser.php';
require_once STROOT . '/datatype/class.DTPassword.php';

/**
 * 
 * @package steroid\backend
 *
 */
class CHBackend extends CLIHandler {


	public function performCommand( $called, $command, array $params ) {
		if (count($params) < 1) {
			$this->notifyError( $this->getUsageText($called, $command, $params));
			return EXIT_FAILURE;
		}
		
		$this->storage->init();

	
		switch ( $params[0] ) {
			case 'create':
				if (count($params) != 2) {
					$this->notifyError( 'create expects exactly one additional parameter (url)' );
					return EXIT_FAILURE;
				}

				$urlParts = parse_url( $params[ 1 ] );

				$this->createUrl($urlParts);

				echo "Backend should now be accessible via given url.\n";
			break;
			case 'admin':
				if(count($params) < 3){
					$this->notifyError( 'admin expects at least two parameters (username, password)' );
					return EXIT_FAILURE;
				}

				if(!RCUser::getDataTypeFieldName('DTPassword')){
					throw new Exception('RCUser does not have required field "password". Are you using ACDefault as authenticator?');
				}

				$username = $params[1];
				$password = $params[2];
				$firstname = isset($params[3]) ? $params[3] : NULL;
				$lastname = isset( $params[ 4 ] ) ? $params[ 4 ] : NULL;

				$userRecord = $this->createAdminUser($username, $password, $firstname, $lastname);

				echo 'Admin user created. Primary: ' . $userRecord->primary . "\n";
				break;
		}


		return EXIT_SUCCESS;
	}

	public function createAdminUser($username = NULL, $password = NULL, $firstname = NULL, $lastname = NULL){
		if(empty($username) || empty($password) ){
			throw new Exception('username and password must be set');
		}

		$userRecord = $this->storage->selectFirstRecord('RCUser', array('where' => array('username', '=', array($username))), false);

		if($userRecord !== NULL){
			throw new Exception('A user with that username already exists');
		}

		$userRecord = RCUser::get( $this->storage, array( 'username' => $username ), false );

		$userRecord->password = DTPassword::hash($password);
		$userRecord->is_backendAllowed = 1;
		$userRecord->firstname = $firstname ?: $username;
		$userRecord->lastname = $lastname ?: $username;

		$userRecord->save();

		$CHUser = new CHUser($this->storage);

		$CHUser->makeDev($userRecord->primary);

		return $userRecord;
	}

	public function createUrl($urlParts){
		$tx = $this->storage->startTransaction();

		try {
			if ( !$urlParts || !isset( $urlParts[ 'host' ] ) || !isset( $urlParts[ 'path' ] ) || !isset( $urlParts[ 'scheme' ] ) ) {
				$this->notifyError( 'given url must at least have scheme, host and path parts' );
				return EXIT_FAILURE;
			}

			if ( !$urlParts[ 'scheme' ] || ( $urlParts[ 'scheme' ] != 'http' && $urlParts[ 'scheme' ] != 'https' ) ) {
				$urlParts[ 'scheme' ] = 'http';
			}

			$urlParts[ 'path' ] = rtrim( $urlParts[ 'path' ], '/' );

			$backendUrlHandler = $this->storage->selectFirstRecord( 'RCUrlHandler', array( 'where' => array( 'className', '=', array( 'UHBackend' ) ) ) );

			if ( !$backendUrlHandler ) {
				$this->notifyError( 'backend url handler not found in db, you should sync url handlers' );
				return EXIT_FAILURE;
			}

			$domain = $this->storage->selectFirstRecord( 'RCDomain', array( 'where' => array( 'domain', '=', array( $urlParts[ 'host' ] ) ) ) );

			if ( !$domain ) {
				$domainGroup = RCDomainGroup::get( $this->storage, array( 'title' => $urlParts[ 'host' ] ), false );


				$domain = RCDomain::get( $this->storage, array( 'domain' => $urlParts[ 'host' ], 'domainGroup' => $domainGroup ), false );
				$domain->save();

				$url = RCUrl::get( $this->storage, array( 'url' => $urlParts[ 'path' ], 'urlHandler' => $backendUrlHandler, 'returnCode' => 200, 'domainGroup' => $domainGroup ), false );

				$url->save();

				$missingReferences = array();

				$liveUrl = $url->copy( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ), $missingReferences );
				$liveUrl->save();
			} else {
				// must exist
				$domainGroup = $domain->domainGroup;

				// check if url exists - we don't care for different returnCode
				$url = $this->storage->selectFirstRecord( 'RCUrl', array( 'where' => array( 'domainGroup', '=', array( $domainGroup ), 'AND', 'live', '=', array( 0 ), 'AND', 'url', '=', array( $urlParts[ 'path' ] ) ) ) );

				$urlValues = array( 'url' => $urlParts[ 'path' ], 'urlHandler' => $backendUrlHandler, 'returnCode' => 200, 'domainGroup' => $domainGroup );

				if ( !$url ) {
					$url = RCUrl::get( $this->storage, $urlValues, false );
					$url->save();
				} else if ( $url->urlHandler !== $backendUrlHandler ) { // check if url has backend as handler
					throw new Exception( 'Url is already taken by handler with classname "' . $url->urlHandler->className . '"' );
				}


				$liveUrl = $url->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) );

				if ( !$liveUrl->exists() ) {
					$liveUrl->setValues( $urlValues );
					$liveUrl->save();
				} else if ( $liveUrl->urlHandler !== $backendUrlHandler ) { // we don't care for different returnCode
					throw new Exception( 'Live Url is already taken by handler with classname "' . $liveUrl->urlHandler->className . '"' );
				}
			}

			// check if we have at least one language record
			$languageRecord = $this->storage->selectFirstRecord( 'RCLanguage', array( 'where' => array( 'live', '=', array( 0 ) ) ) );

			if ( !$languageRecord ) { // create default language record - can be altered later on
				$languageRecord = RCLanguage::get( $this->storage, array( 'title' => 'English', 'iso639' => 'en' ), false );
				$languageRecord->save();
			}

			$tx->commit();
		} catch ( Exception $e ) {
			$tx->rollback();
			throw $e;
		}
	}
	
	public function getUsageText($called, $command, array $params) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' backend command:' => array(
				'usage:' => array(
					'php ' . $called . ' backend create URL' => 'ensure backend is accessible via given url (given webserver is configured correctly)',
					'php ' . $called . ' backend admin username password [firstname lastname]' => 'create a backend user with all permissions (only to be used with ACDefault authenticator)'
				)
			)
		));
	}
}



?>
