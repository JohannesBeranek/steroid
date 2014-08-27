<?php
/**
 * @package steroid\sync
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';

require_once STROOT . '/datatype/class.DTInt.php';

require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTDateTime.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTBool.php';

require_once STROOT . '/datatype/class.DTSteroidLive.php';

/**
 * @package steroid\sync
 */
abstract class SyncApiRecord extends Record {
	const ALLOW_CREATE_IN_SELECTION = 1;
	const BACKEND_TYPE = Record::BACKEND_TYPE_EXT_CONTENT;
	const KEYFIELD_NAME = 'key';
    const SECRETFIELD_NAME = 'secret';

	const ACTION_SYNC = 'syncRecord';

	protected static function getKeys() {
		return array();
	}

	protected static function addGeneratedKeys( array &$keys ) {
		$keys[ 'primary' ] = DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) );
		$keys[ 'apikey' ] = DTKey::getFieldDefinition( array( static::KEYFIELD_NAME, static::SECRETFIELD_NAME ), true );
	}

	protected static function getFieldDefinitions() {
		return array();
	}

	protected static function addGeneratedApiKeyFieldDefinition( array &$fieldDefinitions ) {
		$fieldDefinitions[ static::KEYFIELD_NAME ] = DTString::getFieldDefinition( 127 ); // TODO: create and use datatype DTApiKey instead
        $fieldDefinitions[ static::SECRETFIELD_NAME ] = DTString::getFieldDefinition( 127 ); // TODO: create and use datatype DTApiKey instead
	}

	protected static function addGeneratedFieldDefinitions( array &$fieldDefinitions ) {
		$fieldDefinitions[ Record::FIELDNAME_PRIMARY ] = DTInt::getFieldDefinition( true, true );
		$fieldDefinitions[ 'title' ] = DTString::getFieldDefinition( 127, false, NULL, true );

		static::addGeneratedApiKeyFieldDefinition( $fieldDefinitions );

		$fieldDefinitions[ 'lastSync' ] = DTDateTime::getFieldDefinition( true );
		$fieldDefinitions[ 'lastSyncTry' ] = DTDateTime::getFieldDefinition( true );
		$fieldDefinitions[ 'autoSyncInterval' ] = DTInt::getFieldDefinition( true, false, NULL, true );
		$fieldDefinitions[ 'autoSync' ] = DTBool::getFieldDefinition( true );
		$fieldDefinitions[ 'creator' ] = DTSteroidCreator::getFieldDefinition();
		$fieldDefinitions[ 'ctime' ] = DTCTime::getFieldDefinition();
		$fieldDefinitions[ 'mtime' ] = DTMtime::getFieldDefinition();

		$fieldDefinitions[ 'lastErrorCount' ] = DTInt::getFieldDefinition( true, false, 0 );
		$fieldDefinitions[ 'domainGroup' ] = DTSteroidDomainGroup::getFieldDefinition( true );

		// TODO: add error tracking (link to RCLog ?) 
	}

	// TODO: wouldn't it be better to have a specialized datatype to handle this?
	protected function copyJoins( array $fieldNames ) {
		foreach ( $fieldNames as $fieldName => $field ) {
			if ( isset( $this->{$fieldName} ) ) {
				$joinedItems = array();

				foreach ( $this->{$fieldName} as $joinedItem ) {
					$liveFieldName = $joinedItem->{$field}->getDataTypeFieldName( 'DTSteroidLive' );

					if ( $joinedItem->{$field}->{$liveFieldName} == DTSteroidLive::LIVE_STATUS_PREVIEW ) {
						$joinedItems[ ] = $joinedItem;

						// TODO: constify 'live'
						$joinedItemLiveRecord = $joinedItem->{$field}->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_LIVE ) );

						if ( $joinedItemLiveRecord->exists() ) {
							$joinedItems[ ] = array( $field => $joinedItemLiveRecord );
						}
					}
				}

				$this->{$fieldName} = $joinedItems;
			}
		}
	}

	protected static function getDisplayedListFields() {
		return array( 'title', 'domainGroup', static::KEYFIELD_NAME, static::SECRETFIELD_NAME, 'lastSync', 'lastSyncTry', 'lastErrorCount' );
	}

	public static function getAvailableActions( $mayWrite = false, $mayPublish = false, $mayHide = false, $mayDelete = false, $mayCreate = false ) {
		$actions = parent::getAvailableActions( $mayWrite, $mayPublish, $mayHide, $mayDelete, $mayCreate );

		$actions[ ] = self::ACTION_SYNC;

		return $actions;
	}

	public function doSync( $complete = false ) {
		throw new LogicException( 'Cannot call syncRecord on abstract class, did you forget to implement?' );
	}
}

?>