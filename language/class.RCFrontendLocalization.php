<?php

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';
require_once STROOT . '/language/class.DTSteroidLanguage.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTDisplayText.php';

require_once STROOT . '/user/class.RCUser.php';

class RCFrontendLocalization extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_CONFIG;

	protected static $translationTable = array();

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'id', 'language', 'live' ) ),
			'key' => DTKey::getFieldDefinition( array( 'key', 'live', 'language' ), true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'language' => DTSteroidLanguage::getFieldDefinition(),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'key' => DTString::getFieldDefinition( 127 ),
			'value' => DTText::getFieldDefinition( NULL, true ),
			'variables' => DTDisplayText::getFieldDefinition( NULL, true )
		);
	}

	public static function getTitleFields(){
		return array('value');
	}
	
	protected static function findPrefix( $prefix ) {
		$prefix = trim($prefix, '.');
		
		if ($prefix === '') {
			throw new Exception('Empty or invalid prefix passed to findPrefix function.');	
		}
		
		
		// array_reverse + array_pop should be faster than array_shift with more than 1 element, as there is no array reconstruction with array_pop
		$prefixParts = array_reverse(explode('.', $prefix));		
		
		$testPrefix = '';
		
		while ($prefixPart = array_pop($prefixParts)) {
			$testPrefix .= $prefixPart;
			
			if (isset(self::$translationTable[$testPrefix])) {
				return $testPrefix; // match found, no need to load anything
			}
			
			$testPrefix .= '.';
		}
		
		return false;
	}
	
	public static function loadTranslationTable( IRBStorage $storage, $prefix ) {
		if ($cachedPrefix = self::findPrefix($prefix)) return self::$translationTable[$cachedPrefix];

		$data = $storage->select( __CLASS__, array( 'fields' => array( 'key', 'value' ), 'where' => array( 'key', 'LIKE', array( $prefix . '%' ) ) ), NULL, NULL, NULL, NULL, NULL, true );

		$translations = array();
		
		foreach ($data as $entry) {
			$translations[ $entry[ 'key' ] ] = $entry[ 'value' ];
		}
		
		self::$translationTable[$prefix] = $translations;
		
		return $translations;
	}
	
	public static function getTranslation( IRBStorage $storage, $key ) {
		if ($cachedPrefix = self::findPrefix($key)) {
			$cache = self::$translationTable[ $cachedPrefix ];
		} else {
			$cache = self::loadTranslationTable($storage, $key);
		} 
		
		return isset($cache[ $key ]) ? $cache[ $key ] : false;
		
	}
}

?>