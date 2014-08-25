<?php

require_once STROOT . '/unittest/class.UnitTest.php';
require_once STROOT . '/storage/class.DB.php';

class UTDB extends UnitTest {

	protected static $dependencies = array();

	protected static $tests = array(
		'ConnectionAndQuery'
	);

	public static function executeTest( IStorage $storage, $testName ){

		$storage->init();

		switch($testName){
			case 'ConnectionAndQuery':
				$result = self::connQuery( $storage );
				break;
		}

		return $result;
	}

	protected static function connQuery( IStorage $storage ){

		$expectedResult = 2;

		$res = $storage->fetchFirst('SELECT 1+1 as result');

		return array(
			'expected' => $expectedResult,
			'actual' => $res[ 'result' ],
			'success' => $res['result'] == $expectedResult
		);
	}

}
