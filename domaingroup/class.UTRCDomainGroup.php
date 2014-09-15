<?php

require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/user/class.User.php';

class UTRCDomainGroup extends PHPUnit_Framework_TestCase {

	static $dependencies = array('UTDBInfo');

	// not really a test, just used for code coverage at the moment
	public function testCreateDomainGroup(){
		ClassFinder::$ignoreLocal = true;

		$this->storage = testCommons::getTestingStorage( testCommons::STORAGE_TYPE_RBSTORAGE );

		$testClass = ClassFinder::find( array( 'RCDomainGroup' ), true );

		testCommons::updateTestRecordTables($this->storage, array( 'RCDomainGroup' => array_shift( $testClass ) ));

		$rec = RCDomainGroup::get($this->storage, array('title' => 'testDomainGroup'), false);

		$rec->save();

		$this->assertEquals(1, $rec->exists());
	}
}