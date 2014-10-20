<?php

require_once STROOT . '/util/class.ClassFinder.php';

class UTClassFinder extends PHPUnit_Framework_TestCase {

	static $dependencies = array();

	protected static $expectedMulti = array(
		array(
			'fullPath' => '',
			'fileName' => 'class.RTTest.php',
			'className' => 'RTTest'
		),
		array(
			'fullPath' => '',
			'fileName' => 'class.RTTestJoin.php',
			'className' => 'RTTestJoin'
		),
		array(
			'fullPath' => '',
			'fileName' => 'class.RTTestForeign.php',
			'className' => 'RTTestForeign'
		)

	);

	protected static $expectedDatatype = array(
		array(
			'fullPath' => '',
			'fileName' => 'class.DTSteroidPrimary.php',
			'className' => 'DTSteroidPrimary'
		)
	);

	public static function setUpBeforeClass(){
		self::$expectedMulti[0]['fullPath'] = STROOT . "/storage/record/UT/class.RTTest.php";
		self::$expectedMulti[ 1 ][ 'fullPath' ] = STROOT . "/storage/record/UT/class.RTTestJoin.php";
		self::$expectedMulti[ 2 ][ 'fullPath' ] = STROOT . "/storage/record/UT/class.RTTestForeign.php";

		self::$expectedDatatype[ 0 ][ 'fullPath' ] = STROOT . "/datatype/class.DTSteroidPrimary.php";
	}

	public function testSingleClass(){
		$files = ClassFinder::find('RTTest');

		$this->assertEquals(array(self::$expectedMulti[0]), $files);
	}

	public function testMultiType(){
		$files = ClassFinder::find(array('RTTest', 'DTSteroidPrimary'));

		$this->assertEquals(array(
			self::$expectedMulti[0],
			self::$expectedDatatype[0]
		), $files);
	}

	public function testMultipleClassesOfSameType(){
		$files = ClassFinder::find(array('RTTest', 'RTTestJoin', 'RTTestForeign'));

		$this->assertEquals(static::$expectedMulti, $files);
	}
}