<?php
/**
 * @package steroid\file
 */

require_once STROOT . '/file/class.BaseDTFileRecordReference.php';

require_once STROOT . '/gfx/class.GFX.php';

/**
 * @package steroid\file
 */
class DTImageRecordReference extends BaseDTFileRecordReference {
	public static function getFieldDefinition( $requireForeign = false, $allowedTypes = NULL ) {
		return array(
			'dataType' => __CLASS__,
			'recordClass' => 'RCFile',
			'nullable' => !$requireForeign,
			'requireForeign' => $requireForeign,
			'requireSelf' => false,
			'default' => NULL,
			'allowedCategories' => array( 'image' ),
			'allowedTypes' => $allowedTypes === NULL ? GFX::$supportedMimeTypes : $allowedTypes,
			'constraints' => array( 'min' => $requireForeign ? 1 : 0, 'max' => 1 )
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