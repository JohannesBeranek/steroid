<?php

class UTConfig extends PHPUnit_Framework_TestCase {

	static $dependencies = array();

	public function testGetKey() {
		$conf = testCommons::getTestingLocalconf();

		$database = $conf->getKey( 'DB', 'database' );

		$this->assertEquals( testCommons::DATABASE, $database );
	}
}