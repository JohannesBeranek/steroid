<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTInteger.php';
require_once __DIR__ . '/interface.IContributeBitShift.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

/**
 * datatype for the system's internal live status field. do not mess with this.
 */
class DTSteroidLive extends BaseDTInteger implements IContributeBitShift {

	/**
	 * the field's bit position
	 */
	const FIELD_BIT_POSITION = 24;

	/**
	 * the field's bit width
	 */
	const FIELD_BIT_WIDTH = 1;

	/**
	 * constant for a preview record's live status field
	 */
	const LIVE_STATUS_PREVIEW = 0;

	/**
	 * constant for a live record's live status field
	 */
	const LIVE_STATUS_LIVE = 1;

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
	public function fillBitShiftCalculationParts( array &$calculationFields ){
		$calculationFields[__CLASS__] = '(' . (isset($this->values[$this->colName]) ? $this->values[$this->colName] : static::LIVE_STATUS_PREVIEW) . ' & ' . static::FIELD_BIT_WIDTH . ') << ' . static::FIELD_BIT_POSITION;
	}

	public static function getOtherLiveStatus( $liveStatus ) {
		if ( $liveStatus === self::LIVE_STATUS_LIVE ) {
			return self::LIVE_STATUS_PREVIEW;
		} else if ( $liveStatus === self::LIVE_STATUS_PREVIEW ) {
			return self::LIVE_STATUS_LIVE;
		}
		
		throw new InvalidArgumentException( "provided Argument must be one of: LIVE_STATUS_LIVE, LIVE_STATUS_PREVIEW");
	}

	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'unsigned' => true,
			'default' => static::LIVE_STATUS_PREVIEW,
			'nullable' => false,
			'autoInc' => false,
			'bitWidth' => 1
		);
	}

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedRecords ) {
		if ( isset( $changes[ 'live' ] )) {
			$values[$this->fieldName] = $changes['live'];
		} else {
			parent::copy( $values, $changes, $missingReferences, $originRecords, $copiedRecords );		
		}
	}

}