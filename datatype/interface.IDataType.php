<?php
/**
 * @package steroid\datatype
 */

// FIXME: update interface

/**
 * @package steroid\datatype
 */
interface IDataType {

	/**
	 *
	 * Get column name
	 *
	 * returns the column name of this datatype in db
	 *
	 * @static
	 *
	 * @param       $fieldName
	 * @param array $config
	 *
	 * @return string
	 */
	public static function getColName( $fieldName, array $config = NULL );

	/**
	 * Set value
	 *
	 * @param null $data the value according to datatype
	 * @param bool $loaded whether the value was read from db (will not set the field dirty then)
	 * @param string $fieldName as which fieldName is the data being set
	 */
	public function setValue( $data = NULL, $loaded = false );

	/**
	 * Set dirty - ONLY FOR EMERGENCY CASES LIKE RESTORING BACKUP
	 * 
	 * @param bool $dirty
	 */
	public function setDirty( $dirty );

	/**
	 * Get value
	 *
	 * returns value according to specific datatype
	 *
	 * @return mixed
	 */
	public function getValue();

	public function getFormValue();

	/**
	 * Before save
	 *
	 * called before the record is saved in storage
	 * 
	 * @param bool $isUpdate
	 */
	public function beforeSave( $isUpdate );

	/**
	 * After save
	 *
	 * called after the record has been saved in storage. $saveResult will hold info whether the record was inserted or updated, and the insertID or affectedRows respectively
	 * 
	 * @param bool $isUpdate
	 * @param array $saveResult
	 */
	public function afterSave( $isUpdate, array $saveResult );

	/**
	 * Before delete
	 *
	 * called before the record is deleted from storage
	 */
	public function beforeDelete( array &$basket = NULL );

	/**
	 * After delete
	 *
	 * called after the record has been deleted from storage
	 */
	public function afterDelete(array &$basket = NULL );
	
	/**
	 * Has the value of the dataType ever been set?
	 */
	public function hasBeenSet();
	
	
	public static function getTitleFields( $fieldName, $config );
	
	public static function fillTitleFields( $fieldName, &$titleFields, $config );
	
	public function refresh();
}

?>