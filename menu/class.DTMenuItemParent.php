<?php

require_once STROOT . '/datatype/class.DTParentReference.php';
require_once __DIR__ . '/class.DTMenuItemChildren.php';

class DTMenuItemParent extends DTParentReference {
	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ( $fieldDef[ 'recordClass' ] == $recordClass ) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTMenuItemChildren::getFieldDefinition( $fieldDef[ 'requireForeign' ] );
		}
	}
}
