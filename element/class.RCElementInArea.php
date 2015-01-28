<?php
/**
 * @package steroid\element
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTTinyInt.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTSteroidHidden.php';
require_once STROOT . '/datatype/class.DTSteroidSorting.php';

require_once STROOT . '/area/class.RCArea.php';

require_once STROOT . '/datatype/class.DTDynamicRecordReferenceClass.php';
require_once STROOT . '/element/class.DTDynamicElementReferenceInstance.php';

/**
 * @package steroid\element
 */

class RCElementInArea extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'k_area' => DTKey::getFieldDefinition( array( 'area' ) ),
			'k_elementclass' => DTKey::getFieldDefinition( array( 'element', 'class' ) )
// do not make sorting columns part of a unique key, as MySQL does not support deferrable unique keys and thus can get key conflicts while updating values (e.g. when swapping 2 sortings)
//			'un' => DTKey::getFieldDefinition(array( 'area', self::FIELDNAME_SORTING ), true, false)
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'area' => DTAreaReference::getFieldDefinition(),
			Record::FIELDNAME_SORTING => DTSteroidSorting::getFieldDefinition(),
			'class' => DTDynamicRecordReferenceClass::getFieldDefinition( 'element', true ),
			'element' => DTDynamicElementReferenceInstance::getFieldDefinition( 'class', true, false ),
			'fixed' => DTBool::getFieldDefinition(),
			'columns' => DTTinyInt::getFieldDefinition( true ),
			'hidden' => DTSteroidHidden::getFieldDefinition()
		);
	}

	protected static function getLoadableKeys() {
		static $loadableKeys;

		if ( $loadableKeys === NULL ) {
			$loadableKeys = parent::getLoadableKeys();
			$loadableKeys[ 'unique' ] = DTKey::getFieldDefinition( array( 'area', self::FIELDNAME_SORTING ), true, false );
		}

		return $loadableKeys;
	}
	
	public function isVisible() {
		// if hidden return false
		if ($this->getFieldValue("hidden")) {
			return false;
		}
		
		$elm = $this->getFieldValue("element");
		$currentDateTime = date("Y-m-d H:i:s");
		$pubStart = NULL;
		$pubEnd = NULL;

		if($pubStartFieldName = $elm::getDataTypeFieldName( 'DTPubStartDateTime' )){
			$pubStart = $elm->getFieldValue( $pubStartFieldName );
		}

		if( $pubEndFieldName = $elm::getDataTypeFieldName( 'DTPubEndDateTime' )){
			$pubEnd = $elm->getFieldValue( $pubEndFieldName );
		}

		// if element has getpubdates check if element is visible
		if($pubStart || $pubEnd){
			// welcome to funky town!
			return ( !$pubEnd && $pubStart < $currentDateTime ) || ( !$pubStart && $pubEnd > $currentDateTime ) || ( $pubStart && $pubEnd && ( $pubEnd < $pubStart && $pubStart < $currentDateTime ) || ( $pubStart < $pubEnd && $pubStart < $currentDateTime && $pubEnd > $currentDateTime ) || ( $pubStart > $pubEnd && $pubStart > $currentDateTime && $pubEnd > $currentDateTime ) );
		}

		// return true by default
		return true;

	}

	public function duplicate(RCArea $newArea){
		$values = array(
			Record::FIELDNAME_PRIMARY => NULL,
			'area' => $newArea,
			Record::FIELDNAME_SORTING => $this->{Record::FIELDNAME_SORTING},
			'class' => $this->class,
			'element' => $this->element->duplicate(),
			'fixed' => $this->fixed,
			'columns' => $this->columns,
			'hidden' => $this->hidden
		);

		$newElementInArea = RCElementInArea::get($this->storage, $values, false);

		return $newElementInArea;
	}
}