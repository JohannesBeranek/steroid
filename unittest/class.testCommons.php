<?php

require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/storage/class.DBInfo.php';

class testCommons {
	const DATABASE = 'steroid_testing';
	const TESTTABLE = 'steroid_testing';

	const STORAGE_TYPE_DB = 'DB';
	const STORAGE_TYPE_STORAGE = 'Storage';
	const STORAGE_TYPE_RBSTORAGE = 'RBStorage';

	protected static $conf = NULL;
	protected static $storageTypes = array(
		self::STORAGE_TYPE_DB,
		self::STORAGE_TYPE_STORAGE,
		self::STORAGE_TYPE_RBSTORAGE
	);

	public static function getTestingLocalconf(){
		if(static::$conf === NULL){
			static::$conf = Config::loadNamed( STROOT . '/unittest/testconf.ini.php', 'localconf' );
		}

		return static::$conf;
	}

	public static function getRealLocalconf() {
		throw new Exception('WHAT?!?!? No, I wont do this.');

		if ( static::$conf === NULL ) {
			static::$conf = Config::loadNamed( LOCALROOT . '/localconf.ini.php', 'localconf' );
		}

		return static::$conf;
	}

	public static function getTestingStorage( $type ){
		if(!in_array($type, static::$storageTypes)){
			throw new Exception('Invalid storage type');
		}

		$conf = self::getTestingLocalconf();

		return self::getStorage($conf, $type);
	}

	public static function getRealStorage($type){
		$conf = self::getRealLocalconf();

		return self::getStorage( $conf, $type );
	}

	protected static function getStorage($config, $type){
		require_once STROOT . '/storage/class.' . $type . '.php';

		$dbConfig = $config->getSection( 'DB' );
		$filestoreConfig = $config->getSection( 'filestore' );

		$DB = new $type(
			$dbConfig[ 'host' ], $dbConfig[ 'username' ], $dbConfig[ 'password' ], $dbConfig[ 'database' ],
			( $filestoreConfig !== NULL && isset( $filestoreConfig[ 'path' ] ) ) ? $filestoreConfig[ 'path' ] : NULL,
			isset( $dbConfig[ 'default_engine' ] ) ? $dbConfig[ 'default_engine' ] : NULL,
			isset( $dbConfig[ 'default_charset' ] ) ? $dbConfig[ 'default_charset' ] : NULL,
			isset( $dbConfig[ 'default_collation' ] ) ? $dbConfig[ 'default_collation' ] : NULL
		);

		$DB->init();

		return $DB;
	}

	public static function updateTestRecordTables(RBStorage $storage, $recordClasses = array()){
		if(empty($recordClasses)){
			$recordClasses = ClassFinder::getAll('RT', true);
		}

		$dbInfo = new DBInfo( $storage, $recordClasses );

		return $dbInfo->execute( true, true, true, false, false, true );
	}
}