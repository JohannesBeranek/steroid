<?php

require_once STROOT . '/util/class.Config.php';

class testCommons {
	const DATABASE = 'steroid_testing';
	const TESTTABLE = 'steroid_testing';

	protected static $conf = NULL;

	public static function getTestingLocalconf(){
		if(static::$conf === NULL){
			static::$conf = Config::loadNamed( STROOT . '/unittest/testconf.ini.php', 'testconf' );
		}

		return static::$conf;
	}
}