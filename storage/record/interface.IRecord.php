<?php

// FIXME: complete interface ( copy, ... )
interface IRecord {
	/**
	 * Returns the table name for the record class
	 *
	 * @return string
	 */
	public static function getTableName();

	/**
	 * Returns the table charset
	 *
	 * @return string|null
	 */
	public static function getTableCharset();

	/**
	 * Returns the table collation
	 *
	 * @return string|null
	 */
	public static function getTableCollation();

	/**
	 * Returns the table engine
	 *
	 * @return string|null
	 */
	public static function getTableEngine();


	/**
	 * Returns the column name of specified field
	 *
	 *
	 * @param null $fieldName
	 *
	 * @return string|null
	 * @throws InvalidArgumentException
	 */
	public static function getColumnName( $fieldName = NULL );

	/**
	 * Returns whether the specified field name exists
	 *
	 *
	 * @param string $fieldName
	 *
	 * @return bool
	 */
	public static function fieldDefinitionExists( $fieldName );

	/**
	 * Returns datatype configuration for specified field
	 *
	 *
	 * @param tring $fieldName
	 *
	 * @return array
	 */
	public static function getFieldDefinition( $fieldName );

	/**
	 * Loads all not loaded fields of this record
	 *
	 *
	 */
	public function load();

	/**
	 * Returns whether the record has any dirty fields
	 *
	 *
	 * @return bool
	 */
	public function isDirty( $checkForeign );


	/**
	 * Returns wheter record is supposed to exist in db
	 *
	 * @return bool
	 */
	public function exists();

	/**
	 * Saves the record to the storage
	 *
	 * @return mixed
	 */
	public function save( &$savePaths = NULL );


	/**
	 * Deletes the record from the storage
	 *
	 * @param array $basket if passed, will be filled with records which will be deleted
	 *
	 * @return mixed
	 */
	public function delete( array &$basket = NULL );

	/**
	 * Called by referencing field
	 */
	public function notifyReferenceRemoved( IRecord $originRecord, $reflectingFieldName, $triggeringFunction, array &$basket = NULL );

	/**
	 * Called by referencing field
	 */
	public function notifyReferenceAdded( IRecord $originRecord, $reflectingFieldName, $loaded );


	/**
	 * Returns special values for internal DTSteroidPrimary, DTSteroidLive, DTSteroidID and DTSteroidLanguage fields
	 * Used if the record gets inserted into storage
	 *
	 * @return array
	 */
	public function fillCalculationFields();

	/**
	 * Checks whether the specified field exists
	 *
	 *
	 * @param $fieldName
	 *
	 * @return mixed
	 */
	public function __isset( $fieldName );

	/**
	 * Returns the specified field's value
	 *
	 *
	 * @param $fieldName
	 *
	 * @return mixed
	 */
	public function __get( $fieldName );

	/**
	 * Sets the specified field's value
	 *
	 *
	 * @param $fieldName
	 * @param $value
	 *
	 * @return mixed
	 */
	public function __set( $fieldName, $value );

	/**
	 * Returns the record's column names and values
	 *
	 *
	 * @return array
	 */
	public function getValues();

	public function checkForDelete();


	/**
	 *
	 * @param array $values
	 * @param bool  $loaded
	 */

	public function setValues( array $values, $loaded = false );

	/**
	 * Returns field name of the first occurrence of specified datatype in this record's fields
	 *
	 *
	 * @param string|null $dataTypeClass
	 *
	 * @return string|null
	 */
	public function getFieldNameOfDataType( $dataTypeClass = NULL );

	public static function getTitleFieldsCached();

	public static function getAvailableActions();

	public function addWhereIdentity( array &$queryStruct, $rescueValues = false );

	public function refreshField( $fieldName );
}

class NoSuchFieldException extends Exception {
}

?>