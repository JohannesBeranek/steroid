<?php

/**
 * Interface for Unittest
 *
 * @package steroid/unittest
 */

require_once STROOT . '/storage/interface.IStorage.php';

interface IUnitTest {

	/**
	 * Returns list of class names that need to be validated before this one
	 *
	 * @static
	 * @abstract
	 * @return array
	 */
	public static function getDependencies();

	/**
	 * Returns list of tests this class provides
	 *
	 * @static
	 * @abstract
	 * @return array
	 */
	public static function getAvailableTests();

	/**
	 * Executes a given test and returns array containing success, expected result and actual result (must be strings!)
	 *
	 * @static
	 * @abstract
	 *
	 * @param $testName
	 *
	 * @return array
	 */
	public static function executeTest( IStorage $storage, $testName );

	/**
	 * Returns whether this class provides the specified test
	 *
	 * @static
	 * @abstract
	 *
	 * @param $testName
	 *
	 * @return bool
	 */
	public static function hasTest( $testName );

}