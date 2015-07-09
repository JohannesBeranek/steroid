<?php

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/language/class.DTSteroidLanguage.php';
require_once STROOT . '/datatype/class.DTParentReference.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTBool.php';
require_once STROOT . '/file/class.DTFilename.php';

require_once STROOT . '/template/class.DTTemplateAreaJoinForeignReference.php';

require_once STROOT . '/template/class.Template.php';
require_once STROOT . '/storage/record/class.DTRecordClassSelect.php';

class RCTemplate extends Record {

	const BACKEND_TYPE = Record::BACKEND_TYPE_ADMIN;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'id', 'language', 'live' ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'title' => DTString::getFieldDefinition( 255 ),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'language' => DTSteroidLanguage::getFieldDefinition(),
			'filename' => DTFilename::getFieldDefinition( 127, false, NULL, false ), // TODO: DTLocalFile
			'widths' => DTString::getFieldDefinition( 255, false, NULL, false ), // unsigned int csv ; count of widths gives columns (first width value is column 0 etc)
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
//			'recordClass' => DTString::getFieldDefinition(127),
			'recordClass' => DTRecordClassSelect::getFieldDefinition( array( array( 'RCTemplate', 'recordClassFilter' ) ) ),
			'isStartPageTemplate' => DTBool::getFieldDefinition(),
			'template:RCTemplateArea' => DTTemplateAreaJoinForeignReference::getFieldDefinition()
		);
	}

	public static function recordClassFilter() {
		return array_merge( DTRecordClassSelect::getRecordClassesWithDataType( 'DTSteroidPage' ), array( 'RCPage' ) );
	}

	protected static function getEditableFormFields() {
		return array_keys( static::getFieldDefinitions() );
	}

	public function getTemplateInstance() {
		return new Template( $this->storage, $this->filename );
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $isSearchField = false ) {
		if ( isset( $mainRecordClass ) && $mainRecordClass != 'RCTemplate' ) {
			if ( !isset( $queryStruct[ 'where' ] ) ) {
				$queryStruct[ 'where' ] = array();
			} else if ( !empty( $queryStruct[ 'where' ] ) ) {
				$queryStruct[ 'where' ][ ] = 'AND';
			}

			$queryStruct[ 'where' ][ ] = 'recordClass';
			$queryStruct[ 'where' ][ ] = '=';
			$queryStruct[ 'where' ][ ] = array( $mainRecordClass );
		}

		parent::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $isSearchField );
	}

	public static function getDisplayedFilterFields() {
		return array();
	}
}