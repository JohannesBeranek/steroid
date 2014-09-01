<?php
/**

 */

require_once __DIR__ . '/../util/class.Config.php';

class SteroidInstaller {
	protected static $conf;
	protected static $storage;

	protected static $directories = array(
		'/../../stlocal',
		'/../../cache',
		'/../../upload'
	);

	protected static $templates = array(
		'_localconf.ini.php' => '/../../stlocal/localconf.ini.php',
		'pathdefines.php' => '/../../pathdefines.php',
		'index.php' => '/../../index.php',
		'localroot.php' => '/../../stlocal/localroot.php'
	);

	public static function install() {
		echo "Starting Steroid installer\n";
		
		umask(0002);

		self::createDirectories();
		self::copyTemplates();
		self::checkIntegrity();

		echo "\nConfiguring your local Steroid installation\n\n";

		self::setupLocalconf();
		self::checkDatabase();

		echo "\nInserting required records\n";

		self::createBackendUrl();
		self::createBackendAdmin();

		echo "So far so good! If everything went as expected, all you need to do now is copy all that dojo stuff over to where it should be.\n";
	}

	protected static function createBackendAdmin() {
		echo "Syncing dev permissions\n";
		require_once STROOT . '/user/class.CHUser.php';

		$CHUser = new CHUSer( self::$storage );
		$CHUser->syncDevPerm();

		echo "Do you want to create a new admin user? (y/n): ";

		$confirm = self::getInput();

		if($confirm == 'n'){
			return;
		}

		echo "Creating backend admin user\n";

		require_once STROOT . '/backend/class.CHBackend.php';

		$username = static::askForUsername();

		echo "Please enter the first name for the admin account (defaults to username): ";

		$firstname = self::getInput();

		echo "Please enter the last name for the admin account (defaults to username): ";

		$lastname = self::getInput();

		$password = static::askForPassword();

		$CHBackend = new CHBackend( self::$storage );

		$userRecord = $CHBackend->createAdminUser( $username, $password, $firstname, $lastname );

		echo "Admin user created with primary " . $userRecord->primary . "\n";
	}

	protected static function askForUsername() {
		echo "Please enter a username for the admin account (default admin): ";

		$username = self::getInput();

		if ( empty( $username ) ) {
			$username = 'admin';
		}

		$userRec = self::$storage->selectFirstRecord( 'RCUser', array( 'where' => array( 'username', '=', array( $username ) ) ), false );

		if ( $userRec !== NULL ) {
			echo "A user with that username already exists!\n";
			static::askForUsername();
		}

		return $username;
	}

	protected static function askForPassword() {
		echo "Please enter a password for the admin account: ";

		$password = self::getInput();

		if ( empty( $password ) ) {
			static::askForPassword();
		}

		return $password;
	}

	protected static function createBackendUrl() {
		require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
		require_once STROOT . '/datatype/class.DTSteroidReturnCode.php';

		$domainGroup = self::$storage->selectFirstRecord( 'RCDomainGroup', array( 'where' => array( 'parent', '=', NULL ) ), false );
		$domain = self::$storage->selectFirstRecord( 'RCDomain', array( 'where' => array( 'domainGroup', '=', array( $domainGroup ), 'AND', 'returnCode', '=', array( DTSteroidReturnCode::RETURN_CODE_PRIMARY ) ) ), false );

		$createUrl = true;

		if ( $domainGroup !== NULL ) {
			echo "A root domain group already exists: " . $domainGroup->title;

			if ( $domain !== NULL ) {
				echo ' with primary domain ' . $domain->domain . ". Do you want to create a new one? (y/n): ";
			}

			$confirm = self::getInput();

			if ( $confirm == 'n' ) {
				$createUrl = false;
			}
		}

		if ( $createUrl ) {
			echo "Creating primary domain and backend url\n";
			require_once STROOT . '/backend/class.CHBackend.php';

			$CHBackend = new CHBackend( self::$storage );

			echo "Please enter the primary domain including backend url for this installation (e.g. http://your.doma.in/backend): ";

			$input = self::getInput();

			$CHBackend->createUrl( parse_url( $input ) );

			echo "Done!\n";
		}
	}

	protected static function checkDatabase() {
		echo "Perform database synchronisation? (y/n): \n";
		
		$input = self::getInput();
		
		if($input == 'n'){
			return;
		}
		
		echo "Updating database\n";

		require_once STROOT . '/storage/class.DBInfo.php';
		
		$dateConf = self::$conf->getSection( 'date' );

		date_default_timezone_set( $dateConf[ 'timezone' ] );

		$dbInfo = new DBInfo( self::$storage, array() );

		$dbInfo->execute( true, true, false, false, true, false );
	}

	protected static function setupLocalconf() {
		require_once STROOT . '/base.php';
		
		self::$conf = Config::getDefault();

		self::$storage = getStorage( self::$conf );
		self::$storage->init();
		
		return;

		echo "Setting up local database\n\n";
		echo "Enter host name or leave empty for default ('localhost'): ";

		$input = self::getInput();

		if ( $input == '' ) {
			$input = 'localhost';
		}

		$conf->setKey( 'DB', 'host', $input );
	}

	protected static function createDirectories() {
		echo "\nCreating directories\n";

		foreach ( self::$directories as $dir ) {
			if ( is_dir( __DIR__ . $dir ) && is_writable( __DIR__ . $dir ) ) {
				echo "Directory already exists: " . __DIR__ . $dir . "\n";

				self::ensurePermissions( $dir );
			} else {
				echo "Creating directory " . __DIR__ . $dir . ': ';

				if ( mkdir( __DIR__ . $dir ) ) {
					echo "Success\n";

					self::ensurePermissions( $dir );
				} else {
					echo "Failed\n";
				}
			}
		}
	}

	protected static function ensurePermissions( $path = NULL ) {
		if ( empty( $path ) ) {
			throw new Exception( 'No path specified' );
		}

		if ( chown( __DIR__ . $path, posix_getuid() ) ) {
			echo "Path " . __DIR__ . $path . " is writable\n";
		} else {
			echo "Path " . __DIR__ . $path . " is NOT writable, aborting\n";
			exit;
		}
	}

	protected static function copyTemplates() {
		echo "\nCopying templates\n";

		foreach ( self::$templates as $templateFile => $destination ) {
			if ( file_exists( __DIR__ . '/templates/' . $templateFile ) ) {
				echo "Found template " . $templateFile . " -> ";

				if ( file_exists( __DIR__ . $destination ) ) {
					echo " Already exists in destination path " . $destination . "\n";
					continue;
				}

				echo "Copying to " . $destination . ": ";

				if ( copy( __DIR__ . '/templates/' . $templateFile, __DIR__ . $destination ) ) {
					echo "Success\n";
				} else {
					echo "Failed\n";
				}
			} else {
				echo "Missing template file: " . $templateFile . "\n";
				exit;
			}
		}
	}

	protected static function checkIntegrity() {
		echo "\nChecking integrity: ";

		include( __DIR__ . '/../../pathdefines.php' );
		include( __DIR__ . '/../clihandler/class.CLIHandler.php' );

		if ( class_exists( 'CLIHandler' ) ) {
			echo CLIHandler::RESULT_COLOR_SUCCESS . " Success" . CLIHandler::COLOR_DEFAULT . "\n";
		} else {
			echo "Failed! Please check that the Steroid core files are complete and readable\n";
			exit;
		}
	}
	
	protected static function getInput(){
		$fr = fopen( "php://stdin", "r" );

		$input = fgets( $fr, 128 );
		$input = trim( $input );
		fclose( $fr );
		
		return $input;
	}
}


SteroidInstaller::install();