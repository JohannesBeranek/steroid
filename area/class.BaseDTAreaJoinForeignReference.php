<?php
/**
 * @package steroid\area
 */

require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/datatype/class.BaseDTForeignReference.php';

require_once STROOT . '/area/class.RCArea.php';

/**
 * Class for DT in RCPage/RCTemplate/... references by join records also referencing RCArea in a field called 'area'
 *
 * @package steroid\area
 */
class BaseDTAreaJoinForeignReference extends BaseDTForeignReference {
	public static function getFieldDefinition() {
		return array(
			'dataType' => get_called_class(),
			'nullable' => true,
			'requireSelf' => true
		);
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		return null;
	}

	public function getFormValue() {
		// support lazy loading
		$joinRecords = $this->record->getFieldValue( $this->fieldName );

		$areas = array();

		$rc = $this->getRecordClass();
		$fields = array_keys( $rc::getFormFields( $this->storage ) );

		foreach ( $joinRecords as $joinRecord ) {
			$areas[ ] = $joinRecord->getFormValues( $fields );
		}

		return empty( $areas ) ? NULL : $areas;
	}

	public static function getFormConfig( IRBStorage $storage, $owningRecordClass, $fieldName, $fieldDef ) {
		$fieldDef = parent::getFormConfig( $storage, $owningRecordClass, $fieldName, $fieldDef );

		$widgets = array();

		$recordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );

		foreach ( $recordClasses as $className => $classInfo ) {
			if ( $className::BACKEND_TYPE == Record::BACKEND_TYPE_WIDGET ) {
				$widgetConf = array(
					'className' => $className,
					'formFields' => $className::getFormFields( $storage )
				);

				$widgets[ ] = $widgetConf;
			}
		}

		$fieldDef[ 'widgets' ] = $widgets;

		return $fieldDef;
	}

	protected function mayCopyForeignRecord( array $changes, IRecord $record ) {
		return true;
	}

	protected function mayCopyForeignRecordClass( array $changes ) {
		return true;
	}

	public static function fillRequiredPermissions( &$permissions, $fieldName, $fieldDef, $owningRecordClass, $titleOnly = false ) {
		$titleOnly = false;
		$allRecordClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, true );

		$widgets = array();

		foreach ( $allRecordClasses as $className => $x ) {
			if ( $className::BACKEND_TYPE == Record::BACKEND_TYPE_WIDGET ) {
				$widgets[ ] = $className;
			}
		}

		foreach ( $widgets as $widget ) {
			if ( !isset( $permissions[ $widget ] ) ) {
				$permissions[ $widget ] = array(
					'mayWrite' => 1,
					'isDependency' => 1,
					'restrictToOwn' => 0
				);

				$widget::fillRequiredPermissions( $permissions, $titleOnly );
			}
		}

		if ( !isset( $permissions[ 'RCElementInArea' ] ) ) {
			$permissions[ 'RCElementInArea' ] = array(
				'mayWrite' => 1
			);

			RCElementInArea::fillRequiredPermissions( $permissions, $titleOnly );
		}
	}
}
