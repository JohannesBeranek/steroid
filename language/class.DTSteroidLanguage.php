<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/interface.IContributeBitShift.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/language/class.RCLanguage.php';
require_once STROOT . '/user/class.User.php';

/**
 * datatype for the system's internal language field. do not mess with this.
 */
class DTSteroidLanguage extends BaseDTRecordReference implements IContributeBitShift {

	/**
	 * the field's bit width
	 */
	const FIELD_BIT_WIDTH = 63;

	/**
	 * the field's bit position
	 */
	const FIELD_BIT_POSITION = 25;

	public static function getFormRequired() {
		return true;
	}

	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'nullable' => false,
			'requireSelf' => false,
			'requireForeign' => true,
			'recordClass' => 'RCLanguage',
			'constraints' => array( 'min' => 1, 'max' => 1)
		);
	}
	

	/**
	 * Fill bit shift calculation parts
	 *
	 * fills the calculationParts array with whatever (mysql) statement the field needs to calculate its value
	 *
	 * @param array $calculationParts
	 */
	public function fillBitShiftCalculationParts( array &$calculationFields ) {
		if (!isset( $this->values[ $this->colName ])) {
			throw new InvalidValueForFieldException('Language field must be set to calculate primary field');
		}

		$calculationFields[get_called_class()] = '(' . (int)$this->values[$this->colName] . ' & ' . static::FIELD_BIT_WIDTH . ') << ' . static::FIELD_BIT_POSITION;
	}

	public function beforeSave( $isUpdate, array $savePaths = NULL ){
		if(!$this->hasBeenSet() && !$this->record->exists()){
			$user = User::getCurrent();

			$this->setValue($user->getSelectedLanguage());
		}
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		if($extraParams === NULL){
			$extraParams = array();
		}

		$foreignRecordClass = $fieldConf[ 'recordClass' ];

		if ( isset($extraParams[ 'language' ]) ) {
//			$res = RCLanguage::get($storage, array( Record::FIELDNAME_PRIMARY => $extraParams['language'] ), Record::TRY_TO_LOAD);
//
//			$res->getTitle();

			$titleFields = RCLanguage::getTitleFieldsCached();

			$queryStruct = array(
				'fields' => array_merge($titleFields, array(Record::FIELDNAME_PRIMARY )),
				'where' => array(
					Record::FIELDNAME_PRIMARY,
					'=',
					array( $extraParams[ 'language' ] )
				)
			);

			$res = $storage->select( $foreignRecordClass, $queryStruct );

			if ( empty( $res ) ) {
				throw new RecordDoesNotExistException( 'Cannot create default value of "' . $foreignRecordClass . '" with primary ' . $extraParams[ 'parent' ], array( 
					'rc' => $foreignRecordClass
				));
			}

			$default = $res[0];
		} else {
			$default = NULL;
		}

		return $default;
	}

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedRecords ) {
		if ( isset($changes['language']) ) {
			$values[$this->fieldName] = $changes['language'];
		} else {
			parent::copy( $values, $changes, $missingReferences, $originRecords, $copiedRecords );
		}

	}

	public static function isRequiredForPermissions() {
		return true;
	}
}