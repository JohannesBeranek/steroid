<?php

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/datatype/class.DTDynamicRecordReferenceClass.php';
require_once STROOT . '/pubdate/class.DTPubDateReferenceInstance.php';
require_once STROOT . '/datatype/class.DTPubStartDateTime.php';
require_once STROOT . '/datatype/class.DTPubEndDateTime.php';

class RCPubDateEntries extends Record {
	const BACKEND_TYPE = self::BACKEND_TYPE_SYSTEM;

	const DO_PUBLISH = 'publish';
	const DO_UNPUBLISH = 'unpublish';

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'element' => DTKey::getFieldDefinition( array( 'recordType', 'elementId', 'do' ), true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( false, true ),
			'recordType' => DTDynamicRecordReferenceClass::getFieldDefinition( 'elementId' ),
			// use special DTDynamicRecordReferenceInstance which only adds foreign ref to record classes which can have pubdate
			'elementId' => DTPubDateReferenceInstance::getFieldDefinition( 'recordType', true ),
			'pubDate' => DTDateTime::getFieldDefinition(),
			'do' => DTString::getFieldDefinition()
		);
	}

	public static function fillForcedPermissions( array &$permissions ) {
		$permissions[ get_called_class() ] = array(
			'mayWrite' => 1,
			'isDependency' => 0,
			'restrictToOwn' => 0
		);
	}

	public static function addToFieldSets( $recordClass = NULL ) {
		if ( empty( $recordClass ) ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		if ( $recordClass::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET
				&& $recordClass !== 'RCArea'
		) {
			return array(
				'fs_pubdates' => array(
					'pubStart',
					'pubEnd',
					'_startCollapsed'
				)
			);
		}

		return NULL;
	}

	public static function addToFieldDefinitions( $recordClass, array $existingFieldDefinitions ) {
		$fieldDefinitions = array();

		if ( $recordClass::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET
				&& $recordClass !== 'RCArea'
		) {

			$fieldDefinitions[ 'pubStart' ] =
					DTPubStartDateTime::getFieldDefinition( true, false );
			$fieldDefinitions[ 'pubEnd' ] =
					DTPubEndDateTime::getFieldDefinition( true, false );

		}

		return $fieldDefinitions;

	}

	protected static function addToEditableFormFields( $recordClass, $fieldName, &$editableFormFields ) {

		if ( is_array( $editableFormFields ) && count( $editableFormFields ) > 0 ) {

			if ( $recordClass::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET
					&& $recordClass !== 'RCArea'
			) {

				$editableFormFields[ ] = 'pubStart';
				$editableFormFields[ ] = 'pubEnd';

			}


		}

	}

}