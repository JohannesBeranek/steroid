<?php

require_once STROOT . '/datatype/class.DTParentReference.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTKey.php';

require_once STROOT . '/storage/record/class.Record.php';

class UTDTParentReference extends PHPUnit_Framework_TestCase {

	static $dependencies = array();

	public function testSetValue_Record(){
		$record = static::getTestRecord();

		$record->parent = static::getTestRecord(2);

		$this->assertInstanceOf('RCTest', $record->parent);
		$this->assertEquals(2, $record->parent->{Record::FIELDNAME_PRIMARY});
	}

	public function testSetValue_String() {
		$record = static::getTestRecord();

		$record->parent = '2';

		$this->assertInstanceOf( 'RCTest', $record->parent );
		$this->assertEquals( 2, $record->parent->{Record::FIELDNAME_PRIMARY} );
	}

	public function testSetValue_Int() {
		$record = static::getTestRecord();

		$record->parent = 2;

		$this->assertInstanceOf( 'RCTest', $record->parent );
		$this->assertEquals( 2, $record->parent->{Record::FIELDNAME_PRIMARY} );
	}

	public function testSetValue_Array() {
		$record = static::getTestRecord();

		$record->parent = array(Record::FIELDNAME_PRIMARY => 2);

		$this->assertInstanceOf( 'RCTest', $record->parent );
		$this->assertEquals( 2, $record->parent->{Record::FIELDNAME_PRIMARY} );
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_Record() {
		$record = static::getTestRecord();

		$record->parent = $record;
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_String() {
		$record = static::getTestRecord();

		$record->parent = '1';
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_Int() {
		$record = static::getTestRecord();

		$record->parent = 1;
	}

	/**
	 * @expectedException ParentOfItselfException
	 */
	public function testRecordParentOfItselfException_Array() {
		$record = static::getTestRecord();

		$record->parent = array(Record::FIELDNAME_PRIMARY => 1);
	}


	// HELPER FUNCTIONS

	protected static function getTestRecord( $primary = 1 ){
		$storage = testCommons::getTestingStorage( testCommons::STORAGE_TYPE_RBSTORAGE );

		return RCTest::get( $storage, array( 'primary' => $primary ), false );
	}
}



// HELPER CLASSES

class RCTest extends Record {
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'primary' ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'parent' => DTParentReference::getFieldDefinition()
		);
	}
}