<?php
/**
 * @package steroid\file
 */

require_once STROOT . '/file/class.BaseDTFileRecordReference.php';

/**
 * @package steroid\file
 */
class DTPDFRecordReference extends BaseDTFileRecordReference {
	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'recordClass' => 'RCFile',
			'nullable' => false,
			'requireForeign' => true,
			'requireSelf' => false,
			'default' => NULL,
			'allowedCategories' => array('application'),
			'allowedTypes' => array('application/pdf'),
			'constraints' => array( 'min' => 1, 'max' => 1 )
		);
	}

	public static function listFormat( User $user, IRBStorage $storage, $fieldName, $fieldDef, $value ) {
		if ( !$value || !$value->{Record::FIELDNAME_PRIMARY} ) {
			return $value;
		}

		$val = $value->listFormat( $user, array() );

		return $val[ 'filename' ];
	}

	protected function getFormValueFields() {
		$recordClass = $this->getRecordClass();

		$ownTitleFields = $recordClass::getOwnTitleFields();

		$fields = array_merge( $ownTitleFields, array( 'filename' ) );

		return $fields;
	}
}