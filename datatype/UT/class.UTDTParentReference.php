<?php

require_once STROOT . '/datatype/class.DTParentReference.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTKey.php';

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/storage/record/class.RTTest.php';

class UTDTParentReference extends PHPUnit_Framework_TestCase {

	static $dependencies = array('UTDBInfo');
	protected static $testClass = 'RTTest';

	public function testSetValue_Int_notExists() {
		$record = static::getTestRecord(NULL, false);

		$record->parent = 2;

		$this->assertInstanceOf( static::$testClass, $record->parent );
		$this->assertEquals( 2, $record->parent->{Record::FIELDNAME_PRIMARY} );
	}

	public function testSetValue_Record(){
		$record = static::getTestRecord(1);

		$record->parent = static::getTestRecord(2);

		$this->assertInstanceOf(static::$testClass, $record->parent);
		$this->assertEquals(2, $record->parent->{Record::FIELDNAME_PRIMARY});
	}

	public function testSetValue_String() {
		$record = static::getTestRecord(1);

		$record->parent = '2';

		$this->assertInstanceOf( static::$testClass, $record->parent );
		$this->assertEquals( 2, $record->parent->{Record::FIELDNAME_PRIMARY} );
	}

	public function testSetValue_Int() {
		$record = static::getTestRecord(1);

		$record->parent = 2;

		$this->assertInstanceOf( static::$testClass, $record->parent );
		$this->assertEquals( 2, $record->parent->{Record::FIELDNAME_PRIMARY} );
	}

	public function testSetValue_Array() {
		$record = static::getTestRecord(1);

		$record->parent = array(Record::FIELDNAME_PRIMARY => 2);

		$this->assertInstanceOf( static::$testClass, $record->parent );
		$this->assertEquals( 2, $record->parent->{Record::FIELDNAME_PRIMARY} );
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_Record() {
		$record = static::getTestRecord(1);

		$record->parent = $record;
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_String() {
		$record = static::getTestRecord(1);

		$record->parent = '1';
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_Int() {
		$record = static::getTestRecord(1);

		$record->parent = 1;
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_Array() {
		$record = static::getTestRecord(1);

		$record->parent = array(Record::FIELDNAME_PRIMARY => 1);
	}


	// HELPER FUNCTIONS

	protected static function getTestRecord( $primary = NULL, $andSave = true ){
		$storage = testCommons::getTestingStorage( testCommons::STORAGE_TYPE_RBSTORAGE );

		$values = array();

		if($primary !== NULL){
			$values['primary'] = $primary;
		}

		$testClass = static::$testClass;

		$rec = $testClass::get( $storage, $values, false );

		if($andSave){
			$rec->save();
		}

		return $rec;
	}
}