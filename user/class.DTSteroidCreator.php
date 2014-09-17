<?php
/**
 * @package steroid\user
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/user/class.RCUser.php';
require_once STROOT . '/user/class.User.php';

/**
 * @package steroid\user
 */
class DTSteroidCreator extends BaseDTRecordReference {

	public static function getFieldDefinition() {
		return array(
			'dataType' => get_called_class(),
			'nullable' => false,
			'requireForeign' => true,
			'requireSelf' => false,
			'recordClass' => 'RCUser',
			'constraints' => array( 'min' => 1, 'max' => 1 )
		);
	}

	public function beforeSave( $isUpdate ) {
		if ( !$isUpdate && $this->value === NULL ) {
			$user = User::getCurrent(); // TODO: dependency injection somehow?

			if ( !$user || !$user->record ) {
				throw new Exception( 'Unable to save record with creator without current user. Current record: '  . Debug::getStringRepresentation( $this->record->getValues() ) );
			}

			$this->setValue( $user->record );
		}
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		if(isset($extraParams['user']) && $extraParams[ 'user' ]->record){
			$fieldsToSelect = RCUser::getTitleFieldsCached();
			$fieldsToSelect[] = Record::FIELDNAME_PRIMARY;

			$res = $storage->select($fieldConf['recordClass'], array(
				'fields' => $fieldsToSelect,
				'where' => array(
					Record::FIELDNAME_PRIMARY,
					'=',
					array($extraParams['user']->record->primary)
				)
			));

			if ( empty( $res ) ) {
				throw new RecordDoesNotExistException( 'Cannot create default value of "' . $foreignRecordClass . '" with primary ' . $extraParams[ 'parent' ], array( 
					'rc' => $foreignRecordClass
				));
			}

			$default = $res[ 0 ];
		} else {
			$default = NULL;
		}

		return $default;
	}

	public static function isRequiredForPermissions() {
		return true;
	}

}
