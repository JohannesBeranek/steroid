<?php



class UTDBInfo extends PHPUnit_Framework_TestCase {

	static $dependencies = array('UTRBStorage');

	protected $storage;

	public function testCreateTestRecordTable() {
		$this->storage = testCommons::getTestingStorage( testCommons::STORAGE_TYPE_RBSTORAGE );

		$testClass = ClassFinder::find( array( 'RTTest' ), true );

		$summary = testCommons::updateTestRecordTables($this->storage, array('RTTest' => array_shift( $testClass )));

		$this->assertEquals(1, $summary['isEqual']);
	}
}