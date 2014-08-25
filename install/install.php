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
		'pathdefines.php' => '/../../pathdefines.php',
		'index.php' => '/../../index.php',
		'localroot.php' => '/../../stlocal/localroot.php'
	);

	public static function install(){
		echo "Starting Steroid installer\n";

		self::createDirectories();
		self::copyTemplates();
		self::checkIntegrity();

		echo "\nConfiguring your local Steroid installation\n\n";

		self::setupLocalconf();
		self::checkDatabase();
		self::createBackendUrl();

		//TODO:

		//admin user
		//dojo/dijit?
	}

	protected static function createBackendUrl(){
		echo "\nInserting required records\n";
		require_once STROOT . '/backend/class.CHBackend.php';

		$CHBackend = new CHBackend(self::$storage);

		echo "Please enter the primary domain for this installation: ";

		$fr = fopen( "php://stdin", "r" );

		$input = fgets( $fr, 128 );
		$input = trim( $input );
		fclose( $fr );

		$CHBackend->createUrl($input);

		echo "Done!\n";
	}

	protected static function checkDatabase(){
		echo "Updating database\n";

		require_once STROOT . '/storage/class.DBInfo.php';

		date_default_timezone_set(self::$conf->getSection('date')['timezone']);

		$dbInfo = new DBInfo( self::$storage, array() );

		$dbInfo->execute( true, true, false, false, true, false );
	}

	protected static function setupLocalconf(){
		require_once STROOT . '/base.php';

		self::$conf = getConfig();

		self::$storage = getStorage( self::$conf );
		self::$storage->init();
		//TODO
		return;


		self::$conf = $config->load( __DIR__ . '/templates/_localconf.ini.php' );

		echo "Setting up local database\n\n";
		echo "Enter host name or leave empty for default ('localhost'): ";

		$fr = fopen( "php://stdin", "r" );

		$input = fgets( $fr, 128 );
		$input = trim( $input );
		fclose( $fr );

		if($input == ''){
			$input = 'localhost';
		}

		$conf->setKey('DB', 'host', $input);
	}

	protected static function createDirectories(){
		echo "\nCreating directories\n";

		foreach(self::$directories as $dir){
			if( is_dir( __DIR__ . $dir ) && is_writable( __DIR__ . $dir )){
				echo "Directory already exists: " . __DIR__ . $dir . "\n";

				self::ensurePermissions($dir);
			} else {
				echo "Creating directory " . __DIR__ . $dir . ': ';

				if(mkdir( __DIR__ . $dir)){
					echo "Success\n";

					self::ensurePermissions( $dir );
				} else {
					echo "Failed\n";
				}
			}
		}
	}

	protected static function ensurePermissions($path = NULL){
		if(empty($path)){
			throw new Exception('No path specified');
		}

		if(chmod( __DIR__ . $path, 0755 )){
			echo "Path " . __DIR__ . $path . " is writable\n";
		} else {
			echo "Path " . __DIR__ . $path . " is NOT writable, aborting\n";
			exit;
		}
	}

	protected static function copyTemplates(){
		echo "\nCopying templates\n";

		foreach(self::$templates as $templateFile => $destination){
			if ( file_exists( __DIR__ . '/templates/' . $templateFile ) ) {
				echo "Found template " . $templateFile . " -> ";

				if(file_exists(__DIR__ . $destination)){
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

	protected static function checkIntegrity(){
		echo "\nChecking integrity: ";

		include( __DIR__ . '/../../pathdefines.php' );
		include( __DIR__ . '/../clihandler/class.CLIHandler.php');

		if(class_exists('CLIHandler')){
			echo CLIHandler::RESULT_COLOR_SUCCESS . " Success" . CLIHandler::COLOR_DEFAULT . "\n";
		} else {
			echo "Failed! Please check that the Steroid core files are complete and readable\n";
			exit;
		}
	}
}


SteroidInstaller::install();