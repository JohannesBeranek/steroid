<?php

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';

require_once STROOT . '/storage/interface.IRBStorage.php';

require_once STROOT . '/util/class.Transform.php';
require_once STROOT . '/util/class.StringFilter.php';

require_once STROOT . '/page/class.RCPage.php';


class RCPreviewSecret extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;
	const SECRET_LENGTH = 22;
	
	const URL_PARAM = 'preview';

	protected static function getKeys(){
		return array(
			'primary' => DTKey::getFieldDefinition(array(Record::FIELDNAME_PRIMARY))
		);
	}

	protected static function getFieldDefinitions(){
		return array(
				Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
				'creator' => DTSteroidCreator::getFieldDefinition(),
				'mtime' => DTMTime::getFieldDefinition(),
				'ctime' => DTCTime::getFieldDefinition(),
				'secret' => DTString::getFieldDefinition( self::SECRET_LENGTH, true )
		);
	}
	
	public static function validate( IRBStorage $storage, $previewSecret ) {
		if ($previewSecret === NULL || strlen($previewSecret) !== self::SECRET_LENGTH) {
			return false;
		}
		
		$psOrig = $previewSecret;
		
		$previewSecret = Transform::url_base64_to_base64( $previewSecret );
		
		$previewSecret = StringFilter::filterBase64( $previewSecret );
		
		if (strlen($previewSecret) !== self::SECRET_LENGTH) {
			throw new Exception( 'Invalid preview secret characters ; before filtering: "' . $psOrig . '", after filtering: "' . $previewSecret . '"');
		}
				
		$rec = $storage->selectFirstRecord( 'RCPreviewSecret', array( 'fields' => '*', 'where' => array( 'secret', '=', array( $previewSecret ) ) ) );
		
		return $rec !== NULL;
	}	
	
	public function getPreviewParam( $forUrl = false ) {		
		return array( self::URL_PARAM => $forUrl ? Transform::base64_to_url_base64($this->secret) : $this->secret );
	}
	
	public static function getNewPreviewUrl( RCPage $page ) {
		$previewSecret = self::get( $page->getStorage(), array(), false );
		$previewSecret->save();
		
		// prefer http - in case frontend needs https, we'll get redirected anyway
		// TODO: make this localconf option
		return $page->getUrlForPage( $page, $previewSecret->getPreviewParam( true ), 'http', true );
	}
	
	protected function beforeSave( $isUpdate, $isFirst, array $savePaths = NULL ) {
		parent::beforeSave( $isUpdate, $isFirst, $savePaths ); // let creator + ctime be filled
		
		if ($isFirst && !$this->fields['secret']->hasBeenSet() && !$this->exists()) {
			$this->secret = Transform::md5_base64( $this->creator->{Record::FIELDNAME_PRIMARY} . '|' . $this->ctime );
		}
	}
}
