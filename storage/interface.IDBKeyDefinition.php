<?php

interface IDBKeyDefinition {
	public function setDefinitionFromRecord( $recordClassName = NUL, $keyName, array $keyDef );

	public function createDefinitionFromTable( array $keySchema );

	public function setSchema(array $schema);

	public function compare(IDBKeyDefinition $other);

	public function getValue($key);
	
	public function getNormalizedDefString();
}
