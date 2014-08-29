<?php

interface IDBColumnDefinition {
	public function setDefinitionFromRecord( $key, array $fieldConf );

	public function setDefinitionFromTable( array $columnSchema );

	public function compare(IDBColumnDefinition $other);

	public function getValue($key);

	public function getCreate();
}
