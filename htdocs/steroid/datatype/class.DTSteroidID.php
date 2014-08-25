<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTInteger.php';
require_once __DIR__ . '/interface.IContributeBitShift.php';
require_once __DIR__ . '/interface.IUseBitShift.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * datatype for the system's internal ID field. do not mess with this.
 */
class DTSteroidID extends BaseDTInteger implements IContributeBitShift {

	/**
	 * the field's bit width
	 */
	const FIELD_BIT_WIDTH = 0xFFFFFF;

	public static function getFormRequired() {
		return true;
	}

	/**
	 * Fill bit shift calculation parts
	 *
	 * fills the calculationParts array with whatever (mysql) statement the field needs to calculate its value
	 *
	 * @param array $calculationParts
	 */
	public function fillBitShiftCalculationParts( array &$calculationParts ) {
		if (isset( $this->values[ $this->colName ]) && !empty( $this->values[ $this->colName ] )) {
			$calculationParts[get_called_class()] = $this->values[ $this->colName ];
		} else {
			$calculationParts[get_called_class()] = 'auto_increment & ' . static::FIELD_BIT_WIDTH;
		}
	}



	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'unsigned' => true,
			'default' => NULL,
			'nullable' => false,
			'autoInc' => true,
			'bitWidth' => 32
		);
	}
	
	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedRecords ) {
		$values[$this->fieldName] = $this->record->{$this->fieldName};		// always copy DTSteroidID
	}
}