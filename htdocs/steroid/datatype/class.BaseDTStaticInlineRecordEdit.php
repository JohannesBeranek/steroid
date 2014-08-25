<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTForeignReference.php';

/**
 * class for foreign references (e.g. join tables, where the datatype's record is referenced by another record)
 *
 * @package steroid\datatype
 */
abstract class BaseDTStaticInlineRecordEdit extends BaseDTForeignReference {
	public static function getFieldDefinition( $recordClass = NULL, $requireSelf = false ) {
		return array(
			'dataType' => get_called_class(),
			'recordClass' => $recordClass,
			'nullable' => true,
			'requireSelf' => $requireSelf
		);
	}

	public static function getFormConfig( IRBStorage $storage, $owningRecordClass, $fieldName, $fieldDef ) {
		$fieldDef = parent::getFormConfig( $storage, $owningRecordClass, $fieldName, $fieldDef );

		$foreignRecordClass = $fieldDef[ 'recordClass' ];

		if ( !$foreignRecordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ) {
			$fieldDef[ 'editableRecordClassConfig' ] = static::getOtherSelectableRecordClassDefinition( $owningRecordClass, $foreignRecordClass );
			$alienRecordClass = $fieldDef[ 'editableRecordClassConfig' ][ 'recordClass' ];

			$fieldDef[ 'editableRecordClassConfig' ][ 'isSortable' ] = !!$alienRecordClass::getDataTypeFieldName( 'DTSteroidSorting' );
		} else {
			$fieldDef[ 'isSortable' ] = $foreignRecordClass::getDataTypeFieldName( 'DTSteroidSorting' );
		}

		return $fieldDef;
	}
}

?>