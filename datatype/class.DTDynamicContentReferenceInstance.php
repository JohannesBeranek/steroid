<?php

require_once STROOT . '/datatype/class.DTDynamicRecordReferenceInstance.php';
require_once STROOT . '/datatype/class.DTDynamicForeignReference.php';

class DTDynamicContentReferenceInstance extends DTDynamicRecordReferenceInstance {
	public static function getFieldDefinition( $classFieldName, $requireForeign = false, $requireSelf = false, $allowedBackendTypes = array() ) {
		$fieldDef = parent::getFieldDefinition( $classFieldName, $requireForeign, $requireSelf );

		$fieldDef[ 'allowedBackendTypes' ] = $allowedBackendTypes;

		return $fieldDef;
	}

	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ( in_array( $recordClass::BACKEND_TYPE, $fieldDef[ 'allowedBackendTypes' ] ) ) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTDynamicForeignReference::getFieldDefinition( $fieldDef[ 'classFieldName' ], $fieldDef[ 'requireForeign' ] );
		}
	}
}