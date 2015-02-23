<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTInteger.php';
require_once __DIR__ . '/interface.IUseBitShift.php';
require_once STROOT . '/storage/record/interface.IRecord.php';


/**
 * datatype for the system's internal primary field. do not mess with this.
 */
class DTSteroidPrimary extends BaseDTInteger implements IUseBitShift {
	public static function getFormRequired() {
		return true;
	}

	/**
	 * Get bit shift calculation
	 *
	 * fills the calculationFields array with whatever calculationParts the field needs to calculate its value
	 *
	 * @param array $calculationParts
	 * @param array $calculationFields
	 */
	public function getBitShiftCalculation( array $calculationParts, array &$calculationFields ){

		$parts = array();

		foreach ($calculationParts as $part) {
			$parts[] = '(' . $part . ')';
		}

		if (!empty($parts)) {
			$calculationFields[ $this->colName ] = '(' . implode( ' | ', $parts ) . ')';
		}
	}

	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'unsigned' => true,
			'default' => null,
			'nullable' => false,
			'autoInc' => false,
			'bitWidth' => 32
		);
	}

	/**
	 * After save
	 *
	 * calculates the primary field's value according to insertID (if created) and whether or not the record has a DTSteroidID field
	 *
	 * @param array $saveResult
	 *
	 * @throws InvalidArgumentException
	 */
	public function afterSave( $isUpdate, array $saveResult, array &$savePaths = NULL  ) {
		if ($saveResult['action'] == RBStorage::SAVE_ACTION_CREATE) {
			if (!($idField = $this->record->getDataTypeFieldName('DTSteroidID'))) {
				throw new LogicException( get_class($this) . ' needs an DTSteroidID field in the same record, recordClass: "' . get_class($this->record) . '"' );
			}
			
			$val = $this->record->getFieldValue($idField);

			if ($liveDT = $this->record->getDataTypeFieldName( 'DTSteroidLive' )) {
				$val |= ( $this->record->getFieldValue($liveDT) & DTSteroidLive::FIELD_BIT_WIDTH ) << DTSteroidLive::FIELD_BIT_POSITION;
			}
	
			if ($langDT = $this->record->getDataTypeFieldName( 'DTSteroidLanguage' )) {
				$val |= ( $this->record->getFieldValue($langDT)->{Record::FIELDNAME_PRIMARY} & DTSteroidLanguage::FIELD_BIT_WIDTH) << DTSteroidLanguage::FIELD_BIT_POSITION;
			}
			
			// [JB 22.02.2013] don't set value directly on $this->values 
			// otherwise we skip re-indexing which could cause problems
	
			$this->record->{$this->fieldName} = $val; 
		}
	}

	// [JB 22.02.2013] setting id/live/language helps with indexing
	// [JB 06.08.2014] corrected typos so code actually does something useful :)
	// Tested: this actually decreases performance by about 2.5x 
/*
	public function setValue( $data = NULL, $loaded = false ) {
		parent::setValue( $data, $loaded );
		
		if (isset($this->values[$this->colName]) && ($val = $this->values[$this->colName])) {
		 	// 0, false, NULL are not valid values for DTSteroidPrimary in DB  
		 
			if ($idField = $this->record->getDataTypeFieldName( 'DTSteroidID' )) {
				$newVal = $val & DTSteroidID::FIELD_BIT_WIDTH;
		 		
		 		if (!$this->record->isReadable($idField) || $newVal !== $this->record->getFieldValue( $idField )) {
		 			$this->record->{$idField} = $newVal;
		 		}
			}	
			
			if ($liveDT = $this->record->getDataTypeFieldName( 'DTSteroidLive' )) {
				$newVal = ($val >> DTSteroidLive::FIELD_BIT_POSITION) & DTSteroidLive::FIELD_BIT_WIDTH;
				
				if (!$this->record->isReadable($liveDT) || $newVal !== $this->record->getFieldValue( $liveDT )) {
					$this->record->{$liveDT} = $newVal;
				}
			}

			if ($langDT = $this->record->getDataTypeFieldName( 'DTSteroidLanguage' )) {
				$newVal = ($val >> DTSteroidLanguage::FIELD_BIT_POSITION) & DTSteroidLanguage::FIELD_BIT_WIDTH;
				
				if ($this->record->isReadable($langDT)) {
					$langVal = $this->record->getFieldValue( $langDT );

					if ($langVal !== NULL && $langVal->fieldHasBeenSet('id')) {
						$currentVal = $langVal->getFieldValue('id');
					}
				}
				
				
				if (isset($currentVal) && $newVal !== $currentVal) {
					$this->record->{$langDT} = $newVal;
				}
			}
	
	 
		}
		 
		 
	}
*/ 
	 

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedRecords ) {
		// don't copy steroid primary, it will be autogenerated anew on insert
		// TODO: we should actually be able to correctly copy steroid primary by simple bitfield maths
	}
	
	// should only be called with a dataType known to exist in the record!
	public function interpolateValue( $dataType ) {
		if (isset($this->values[$this->colName]) && ($val = $this->values[$this->colName])) {
			switch ($dataType) {
				case 'DTSteroidID':
					return $val & DTSteroidID::FIELD_BIT_WIDTH;
				break;
				case 'DTSteroidLive':
					return ($val >> DTSteroidLive::FIELD_BIT_POSITION) & DTSteroidLive::FIELD_BIT_WIDTH;
				break;
				case 'DTSteroidLanguage':
					return ($val >> DTSteroidLanguage::FIELD_BIT_POSITION) & DTSteroidLanguage::FIELD_BIT_WIDTH;
				break;
			}
		}
		
		return NULL;
	}
}