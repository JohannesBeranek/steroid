<?php

require_once STROOT . '/util/class.Config.php';

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
			static::$conf = Config::loadNamed( STROOT . '/unittest/testconf.ini.php', 'testconf' );
		}

		return static::$conf;
	}

	public static function getTestingStorage( $type ){
		if(!in_array($type, static::$storageTypes)){
			throw new Exception('Invalid storage type');
		}

		require_once STROOT . '/storage/class.' . $type . '.php';

		$conf = testCommons::getTestingLocalconf();

		$dbConfig = $conf->getSection( 'DB' );
		$filestoreConfig = $conf->getSection( 'filestore' );

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
}