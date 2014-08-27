<?php
/**
 * @package steroid\language
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTSteroidPrimary.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';

require_once STROOT . '/datatype/class.DTMTime.php';


/**
 * @package steroid\language
 */
class RCLanguage extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_ADMIN;

	protected $hasCreatedNewPermissions = false;

	protected static function getKeys(){
		return array(
			'primary' => DTKey::getFieldDefinition(array('id', 'live'))
		);
	}

	protected static function getFieldDefinitions(){
		return array(
			self::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'title' => DTString::getFieldDefinition(127),
			'iso639' => DTString::getFieldDefinition(4),
			'locale' => DTString::getFieldDefinition(20),
			'ctime' => DTCTime::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'isDefault' => DTBool::getFieldDefinition()
		);
	}

	public static function getStaticRecords(IRBStorage $storage){
		return array(
			array(
				'live' => DTSteroidLive::LIVE_STATUS_PREVIEW,
				'title' => 'English',
				'iso639' => 'en',
				'isDefault' => true,
				'locale' => 'en_US.UTF-8'
			)
		);
	}

	protected function afterSave( $isUpdate, $isFirst, array $saveResult ) {
		if ( !$isUpdate && !$this->hasCreatedNewPermissions ) {
			$this->hasCreatedNewPermissions = true;

			$user = User::getCurrent();

			if ( $user ) {
				$this->copyPermissionsForUser( $user );
			}
		}

		parent::afterSave( $isUpdate, $isFirst, $saveResult );
	}

	protected function copyPermissionsForUser( $user ) {
		$currentPermissions = $this->storage->selectRecords( 'RCDomainGroupLanguagePermissionUser', array( 'fields' => array( 'domainGroup', 'language', 'permission', 'user' ), 'where' => array( 'language', '=', array( $user->getSelectedLanguage() ), 'AND', 'user', '=', array( $user->record ) ) ) );

		foreach ( $currentPermissions as $perm ) {
			$newPerm = RCDomainGroupLanguagePermissionUser::get( $this->storage, array(
				'domainGroup' => $perm->domainGroup,
				'language' => $this,
				'permission' => $perm->permission,
				'user' => $user->record
			), false );

			$newPerm->save();
		}
	}
}

?>