<?php
/**
 * @package steroid\datatype
 */


require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/datatype/class.DTInt.php';

/**
 * @package steroid\datatype
 */
class DTDynamicRecordReferenceInstance extends BaseDTRecordReference {
	protected $tempValue;
	
	public static function getFieldDefinition( $classFieldName, $requireForeign = false, $requireSelf = false ) {
		return array(
			'dataType' => get_called_class(),
			'classFieldName' => $classFieldName,
			'nullable' => !$requireForeign,
			'requireForeign' => $requireForeign,
			'requireSelf' => $requireSelf,
			'default' => NULL
		);
	}
	
	public static function getColName( $fieldName, array $config = NULL ) {
		return $fieldName;
	}
	
	public static function adaptFieldConfForDB( array &$fieldConf ) {
		$fieldConf = DTInt::getFieldDefinition( true );
	}
	
	
	public static function getTitleFields( $fieldName, $config ) {
		return NULL;
	}
	
	public function fillUpValues( array $values, $loaded ) {
		if (($recordClass = $this->getRecordClass()) && $this->value && !($this->value instanceof $recordClass)) {
			$this->record->setValues( array($this->fieldName => NULL), false );
		}

		parent::fillUpValues($values, $loaded);
		
		$this->tryUpdateValue();
	}
	
	protected function getRecordClass() {
		$classFieldName = $this->config['classFieldName'];
		
		$ret = NULL;
		
		if (isset($this->record->{$classFieldName}) || $this->record->exists()) {
			$ret = $this->record->getFieldValue( $classFieldName );		
		}
		
		return $ret;
	}
	
	protected function _setValue( $data, $loaded, $skipRaw = false, $skipReal = false ) {
		if ($data === NULL || $data === '' || ($recordClass = $this->getRecordClass())) {
			$this->tempValue = NULL;
			
			parent::_setValue($data, $loaded, $skipRaw, $skipReal);
		} else {
			// TODO: merge tempValue?
			$this->tempValue = $data;

			$this->dirty = !$loaded;
		}
	}
	
	public function tryUpdateValue() {
		if (( $recordClass = $this->getRecordClass())) {
			if (isset($this->tempValue)) {
				$this->record->setValues( array( $this->fieldName => $this->tempValue), !$this->dirty );				

				$this->tempValue = NULL;
			} else if (isset( $this->values[ $this->colName ])) {
				if (!$this->value) {
					$this->value = $recordClass::get( $this->storage, array( Record::FIELDNAME_PRIMARY => $this->values[ $this->colName ]), false );
				} else if (!isset($this->value->{Record::FIELDNAME_PRIMARY}) || $this->value->{Record::FIELDNAME_PRIMARY} != $this->values[ $this->colName ]) {
					// TODO: what if fieldname primary is a record reference itself?
					$this->value->{Record::FIELDNAME_PRIMARY} = $this->values[ $this->colName ];
				}
			} 
		} 
	}
	
	public function getValue() {
		if (!$this->value) {
			$this->tryUpdateValue();
		} 
		
		return $this->value;
	}
	
	public function beforeSave( $isUpdate ) {
		$this->tryUpdateValue();
		
		parent::beforeSave( $isUpdate );
	}
	
	public function beforeDelete( array &$basket = NULL ) {
		$this->tryUpdateValue();
		
		parent::beforeDelete( $basket );
	}
	
	public function hasBeenSet() {
		return $this->value !== NULL || $this->tempValue !== NULL;
	}

	// FIXME: this not workin?
	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ($recordClass::fieldDefinitionExists(Record::FIELDNAME_PRIMARY)) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTDynamicForeignReference::getFieldDefinition( $fieldDef['classFieldName'], $fieldDef[ 'requireForeign' ] );
		}
	}
}