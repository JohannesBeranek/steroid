<?php

require_once STROOT . '/storage/interface.IDBKeyDefinition.php';

class DBKeyDefinition implements IDBKeyDefinition {
	const TABLEKEY_TABLE = 'Table';
	const TABLEKEY_NON_UNIQUE = 'Non_unique';
	const TABLEKEY_PRIMARY = 'Primary';
	const TABLEKEY_KEYNAME = 'Key_name';
	const TABLEKEY_COLNAME = 'Column_name';

	protected $keySchema = array();

	public function setDefinitionFromRecord( $recordClassName = NULL, $keyName, array $keyDef ) {
		if ( empty($keyDef) || empty($recordClassName)) {
			throw new InvalidArgumentException( "\$keyDef and \$recordClassName must be set" );
		}

		$colNames = array();

		foreach($keyDef['fieldNames'] as $fieldName){
			if(!$recordClassName::fieldDefinitionExists($fieldName)){
				throw new InvalidArgumentException('Field "' . $fieldName . '" specified in key "' . $keyName . '" does not exist');
			}
			
			$colNames[] = $recordClassName::getColumnName($fieldName);
		}
		
		$this->keySchema = array(
			self::TABLEKEY_KEYNAME => $keyName,
			self::TABLEKEY_NON_UNIQUE => (!empty( $keyDef[ 'unique' ]) || strtoupper($keyName) == 'PRIMARY' ? 0 : 1), // MySQL stores as "non_unique" so we have to invert it
			self::TABLEKEY_COLNAME => $colNames
		);
	}

	public function createDefinitionFromTable( array $keySchema ) {
		if ( empty( $keySchema ) ) {
			throw new InvalidArgumentException( '$keySchema must be set' );
		}
		
		$temp = array();

		foreach($keySchema as $col){
			$temp[$col[self::TABLEKEY_KEYNAME]][self::TABLEKEY_KEYNAME] = $col[self::TABLEKEY_KEYNAME];
			$temp[$col[self::TABLEKEY_KEYNAME]][self::TABLEKEY_NON_UNIQUE] = $col[self::TABLEKEY_NON_UNIQUE];
			$temp[$col[self::TABLEKEY_KEYNAME]][self::TABLEKEY_COLNAME][] = $col[self::TABLEKEY_COLNAME];
		}

		$return = array();

		foreach($temp as $key => $def){
			$tempDef = new DBKeyDefinition();
			$tempDef->setSchema($def);
			$return[$key] = $tempDef;
		}

		return $return;
	}

	public function setSchema(array $schema){
		$this->keySchema = $schema;
	}

	public function compare( IDBKeyDefinition $other ) {
		$expected = $this->getNormalizedDefString();
		$actual = $other->getNormalizedDefString();
				
		return array(
			DBTableDefinition::RESULT_SUMMARY => array(
				DBTableDefinition::RESULT_EQUALS => $expected == $actual,
				DBTableDefinition::RESULT_EXPECTED => $expected,
				DBTableDefinition::RESULT_ACTUAL => $actual
			)
		);
	}

	public function getNormalizedDefString() {
		// order of columns in key is important as well, so we do no sorting in here!

		$isPrimary = strtoupper($this->keySchema[self::TABLEKEY_KEYNAME]) == 'PRIMARY';
		$defString = ($isPrimary ? 'PRIMARY ' : (empty($this->keySchema[self::TABLEKEY_NON_UNIQUE]) ? 'UNIQUE ' : '')) . 'KEY ' . strtoupper($this->keySchema[self::TABLEKEY_KEYNAME]) . '(' . implode(',', $this->keySchema[self::TABLEKEY_COLNAME]) .')';
	
		return $defString;
	}

	public function getValue($key) {
		return $this->keySchema[$key];
	}

}
