<?php
/**
 * @package stlocal\ext\person
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/language/class.DTSteroidLanguage.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once __DIR__ . '/class.RCMenu.php';
require_once __DIR__ . '/class.RCMenuItem.php';
require_once __DIR__ . '/class.DTMenuItemForeignReference.php';
 
/**
 * @package stlocal\ext\person
 */
class RCDefaultMenu extends Record {
	
	const BACKEND_TYPE = Record::BACKEND_TYPE_DEV;

	const MENU_KEY_MAIN = 'menu_main';
	const MENU_KEY_FOOTER = 'menu_footer';

	protected static function getKeys(){
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'id', 'live', 'language' ) )
		);
	}

	protected static function getFieldDefinitions(){
		return array(
			self::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'language' => DTSteroidLanguage::getFieldDefinition(),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'title' => DTString::getFieldDefinition(127),
			'key' => DTString::getFieldDefinition(127)
		);
	}
}

?>