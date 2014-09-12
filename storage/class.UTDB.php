<?php

require_once STROOT . '/storage/class.DB.php';
require_once STROOT . '/storage/class.DBKeyDefinition.php';

class UTDB extends PHPUnit_Framework_TestCase {
	static $dependencies = array('UTConfig');

	static $testColumnName = 'testColumn';
	static $testString = 'testString';
	static $testColumnType = 'VARCHAR(255)';

	protected static $DB = NULL;

	protected function setUp() {
		if ( static::$DB !== NULL ) {
			return;
		}

		static::$DB = testCommons::getTestingStorage(testCommons::STORAGE_TYPE_DB);

		$this->dropAllTables();
	}

	public function testCreateTable(){
		$this->createTestTable();

		$tables = $this->getTables();

		$this->assertCount(1, $tables);

		$this->assertEquals( testCommons::TESTTABLE, $tables[0]['TABLE_NAME']);
	}

	public function testDropTable(){
		$this->deleteTestTable();

		$tables = $this->getTables();

		$this->assertCount( 0, $tables );
	}

	public function testTransaction(){
		$this->createTestTable();

		$tx = static::$DB->startTransaction();

		$this->insertTestRow();

		$data = $this->getTestRow();

		$this->assertEquals(static::$testString, $data[static::$testColumnName]);

		$tx->rollback();

		$data = $this->getTestRow();

		$this->assertEmpty($data);

		$this->deleteTestTable();
	}


	// HELPER METHODS
	public function dropAllTables(){
		$tables = $this->getTables();

		foreach($tables as $table){
			static::$DB->dropTable($table[ 'TABLE_NAME' ]);
		}
	}

	public function createTestTable( ){
		$colDef = new columnDefinition();
		$keyDef = new keyDefinition();

		static::$DB->createTable(false, testCommons::TESTTABLE, array(static::$testColumnName => $colDef), array('PRIMARY' => $keyDef));
	}

	protected function getTables(){
		$tables = static::$DB->fetchAll( 'SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA LIKE ' . static::$DB->escape( testCommons::DATABASE ) );

		return $tables;
	}

	protected function deleteTestTable(){
		static::$DB->dropTable( testCommons::TESTTABLE );
	}

	protected function insertTestRow(){
		static::$DB->insert( testCommons::TESTTABLE, array( static::$testColumnName => static::$testString ) );
	}

	protected function getTestRow(){
		return static::$DB->fetchFirst( 'SELECT * FROM ' . static::$DB->escapeObjectName( testCommons::TESTTABLE ) . ' WHERE ' . static::$DB->escapeObjectName( static::$testColumnName ) . ' = ' . static::$DB->escape( static::$testString ) );
	}
}


// HELPER CLASSES

class columnDefinition {
	public function getCreate(){
		return UTDB::$testColumnType;
	}
}

class keyDefinition {
	public function getValue() {
		return array(
			DBKeyDefinition::TABLEKEY_COLNAME => UTDB::$testColumnName
		);
	}
}