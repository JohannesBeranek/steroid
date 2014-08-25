<?php

interface IDBTableDefinition {
	public function compareWithTable( IDBTableDefinition $other, array $columnNames );

	public function getTableDefinitionValue($key);

	public function loadDefinitionFromTable($table);

	public function loadDefinitionFromRecordClass($recordClassName);

	public function getColumnDefinition( $columnName );

	public function getKeyDefinition($keyName);

	public function update($forceUnsafe, $dropColumns, $dropKeys, array $tableComparison);
}
