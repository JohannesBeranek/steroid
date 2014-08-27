<?php
/**
 * @package steroid\page
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/domaingroup/class.DTSteroidDomainGroup.php';
require_once STROOT . '/language/class.DTSteroidLanguage.php';

require_once __DIR__ . '/class.RCPage.php';
require_once __DIR__ . '/class.DTSteroidPage.php';
require_once STROOT . '/storage/record/class.DTRecordClassSelect.php';

/**
 * @package steroid\page
 */
class RCDefaultParentPage extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_DEV;
	
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) ),
			'recordClass' => DTKey::getFieldDefinition( array( 'recordClass', 'domainGroup', 'language' ), true )
		);
	}
	
	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true ),
			'recordClass' => DTRecordClassSelect::getFieldDefinition( array( array( 'RCPage', 'pageTypeFilter' ) ) ),
			'page' => DTRecordReference::getFieldDefinition( 'RCPage' ),
			'domainGroup' => DTSteroidDomainGroup::getFieldDefinition(),
			'language' => DTSteroidLanguage::getFieldDefinition()
		);
	}
}