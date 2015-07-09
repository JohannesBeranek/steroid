<?php

require_once STROOT . '/datatype/class.DTDynamicRecordReferenceInstance.php';
require_once STROOT . '/datatype/class.DTDynamicForeignReference.php';

class DTPubDateReferenceInstance extends DTDynamicRecordReferenceInstance {
	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ( in_array( $recordClass::BACKEND_TYPE, array(
			Record::BACKEND_TYPE_CONTENT,
			Record::BACKEND_TYPE_EXT_CONTENT,
			Record::BACKEND_TYPE_DEV,
			Record::BACKEND_TYPE_ADMIN,
			Record::BACKEND_TYPE_CONFIG	
		), true ) || ( $recordClass::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET && $recordClass !== 'RCArea' ) ) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTDynamicForeignReference::getFieldDefinition( $fieldDef['classFieldName'], $fieldDef[ 'requireForeign' ] );
		}
	}
}
