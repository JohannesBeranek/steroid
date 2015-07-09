<?php

require_once STROOT . '/datatype/class.DTDynamicRecordReferenceInstance.php';
require_once STROOT . '/element/class.ElementRecord.php';
require_once STROOT . '/datatype/class.DTDynamicForeignReference.php';


class DTDynamicElementReferenceInstance extends DTDynamicRecordReferenceInstance {
	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ( is_subclass_of( $recordClass, 'ElementRecord' ) ) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTDynamicForeignReference::getFieldDefinition( $fieldDef[ 'classFieldName' ], $fieldDef[ 'requireForeign' ] );
		}
	}

	protected function mayCopyReferenced() {
		return true;
	}
}