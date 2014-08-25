<?php
/**
 * @package steroid\area
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';

require_once STROOT . '/area/class.DTAreaForeignReference.php';

require_once STROOT . '/area/class.RCArea.php';

/**
 * @package steroid\area
 */
class DTAreaReference extends BaseDTRecordReference {
	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'recordClass' => 'RCArea',
			'nullable' => false,
			'requireForeign' => true,
			'requireSelf' => false,
			'default' => NULL
		);
	}
	
	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ( $fieldDef[ 'recordClass' ] == $recordClass ) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTAreaForeignReference::getFieldDefinition();
		}
	}
/*
	public static function getFormConfig( IRBStorage $storage, $owningRecordClass, $fieldName, $fieldDef ) {
		$fieldDef[ 'formFields' ] = $fieldDef[ 'recordClass' ]::getFormFields($storage);

		return $fieldDef;
	}
*/
	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		$rc = $fieldConf[ 'recordClass' ];

		$defaults = $rc::getDefaultValues( $storage, array_keys( $rc::getFormFields( $storage) ), $extraParams );

		return $defaults;
	}

	public function getFormValue() {
		$val = $this->record->getFieldValue($this->fieldName);

		if ( $val ) {
			$fields = array_keys( $val->getFormFields($this->storage) );

			$val = $val->getFormValues( $fields );
		}

		return $val;
	}

	public function getFormRecords( array &$records ) {
		$val = $this->record->getFieldValue( $this->fieldName );

		if ( $val ) {
			$fields = array_keys( $val->getFormFields( $this->storage ) );

			if ( !in_array( $val, $records, true ) ) {
				$records[ ] = $val;
			}

			$val->getFormRecords( $records, $fields );
		}

		return $val;
	}
	
	protected function mayCopyReferenced() {
		return true;
	}
}
