<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTForeignReference.php';

/**
 * @package steroid\datatype
 */
class DTDynamicForeignReference extends BaseDTForeignReference {
	public static function getFieldDefinition( $classFieldName, $requireSelf = true ) {
		return array(
			'classFieldName' => $classFieldName,
			'dataType' => __CLASS__,
			'nullable' => true,
			'requireSelf' => $requireSelf
		);
	}

	public static function getAdditionalJoinConditions( $owningRecordClass, $fieldName, array $config ) {
		return array( $fieldName . '.' . $config[ 'classFieldName' ], '=', array( $owningRecordClass ) ); // using string of owningRecordClass as value
	}

	protected function getForeignRecords() {
		$foreignRecordClass = $this->getRecordClass();
		$fields = array_keys( $foreignRecordClass::getOwnFieldDefinitions() );

		return $this->storage->selectRecords( $foreignRecordClass,
			array(
				'where' => array( $this->getForeignFieldName(), '=', array( $this->record->{Record::FIELDNAME_PRIMARY} ), 'AND', $this->config[ 'classFieldName' ], '=', array( get_class( $this->record ) ) ),
				'fields' => $fields
			)
		);
	}
}
