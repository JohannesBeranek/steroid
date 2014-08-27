<?php

/**
 * @package steroid/unittest
 */

require_once __DIR__ . '/interface.IUnitTest.php';


abstract class UnitTest implements IUnitTest {

	const UNITTEST_FILEDIR = '/unittest/files';

	/**
	 * Class names of unit tests that must be validated prior to this one
	 *
	 * @var array
	 */
	protected static $dependencies = array();

	/**
	 * List of available tests this class provides
	 *
	 * @var array
	 */
	protected static $tests = array();

	public static function getDependencies(){
		return static::$dependencies;
	}

	public static function getAvailableTests(){
		return static::$tests;
	}

	public static function hasTest( $test ){
		$tests = static::getAvailableTests();
		
		return (in_array($test, $tests));
	}

	public static function fail($message = ''){
		return CHUnitTest::RESULT_COLOR_FAILURE . ($message ?: "FAILED") . CHUnitTest::COLOR_DEFAULT . "\n";
	}

	public static function success( $message = '') {
		return CHUnitTest::RESULT_COLOR_SUCCESS . ($message ?: "SUCCESS") . CHUnitTest::COLOR_DEFAULT . "\n";
	}

	public static function className( $name ) {
		return CHUnitTest::COLOR_CLASSNAME . $name . CHUnitTest::COLOR_DEFAULT;
	}
}
