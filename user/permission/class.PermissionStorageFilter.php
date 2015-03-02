<?php
/**
 * @package steroid\user\permission
 */

require_once STROOT . '/storage/interface.IRBStorageFilter.php';

require_once STROOT . '/user/class.User.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';

/**
 * @package steroid\user\permission
 */
class PermissionStorageFilter implements IRBStorageFilter {
	protected $user;
	protected $primaryRecordClass;
	protected $disable;
	
	public function __construct( User $user, $primaryRecordClass ) {
		$this->user = $user;
		$this->primaryRecordClass = $primaryRecordClass;

		$this->getPerms();
	}
	
	
	protected function getPerms() {
		$this->disable = true;
		$perms = $this->user->getAvailableRecordClasses();
		$this->disable = false;
		
		return $perms;
	}
	
	public function injectSelectFilter( $rc, &$conf, &$additionalJoinConf ) {
		if ($this->disable) return; // disable is only used internally
		
		// limit select filtering to primaryRecordClass - may be false, which means completely disabling select filter
		// in case of null, checks all record classes
		if ($rc !== $this->primaryRecordClass && $this->primaryRecordClass !== NULL) return;
		
		$perms = $this->getPerms();
		
		if (!array_key_exists($rc, $perms)) {
			throw new AccessDeniedException('Access denied for recordClass "' . $rc . '"', array(
					'rc' => $rc
				));
		}
		
		if ($perms[$rc]['restrictToOwn'] && ($field = $rc::getDataTypeFieldName( 'DTSteroidCreator' ))) {
			if (!empty($additionalJoinConf)) {
				array_unshift($additionalJoinConf, '(');
				array_push($additionalJoinConf, ')', 'AND');
			} else {
				$additionalJoinConf = array();
			}
			
			array_push($additionalJoinConf, $field, '=', array( $this->user->record ));
		}
	}
	
	public function checkSaveFilter( IRecord $record ) {		
		$perms = $this->getPerms();
		
		$rc = get_class($record);
		
		if (!array_key_exists($rc, $perms) || !$perms[$rc]['mayWrite']) {
			throw new AccessDeniedException( 'Access denied for recordClass "' . $rc . '" when trying to save record with values ' . Debug::getStringRepresentation($record->getValues()), array(
				'rc' => $rc,
				'record' => $record->getTitle()
			));
		}		
	}
	
	public function checkUpdateFilter( IRecord $record ) {
		$perms = $this->getPerms();
		
		$rc = get_class($record);
		
		if ($perms[$rc]['restrictToOwn'] && ($field = $record->getDataTypeFieldName( 'DTSteroidCreator' )) && ($this->user->record != $record->{$field} )) {
			throw new AccessDeniedException( 'Access denied for recordClass "' . $rc . '"', array(
					'rc' => $rc
				));
		}
	}
	
	public function checkInsertFilter( IRecord $record ) {
		// nothing to check here, as creator is automatically set to the user anyway, and writing in general is checked in save filter
	}
	
	public function checkDeleteFilter( IRecord $record ) {	
		$perms = $this->getPerms();
		
		$rc = get_class($record);
		
		if (!array_key_exists($rc, $perms) || !$perms[$rc]['mayWrite'] || ($perms[$rc]['restrictToOwn'] && ($field = $record->getDataTypeFieldName( 'DTSteroidCreator' )) && ($this->user->record != $record->{$field} ))) {
			throw new AccessDeniedException( 'Access denied for recordClass "' . $rc . '"', array(
				'rc' => $rc
				));
		}
	}
	
	public function checkFieldValue( $recordClass, $field, $comparator, $value ) {
		
	}
	
	public function modifySelectCacheName( &$name ) {
		$name = NULL; // disable select caching
	}
	
}


?>