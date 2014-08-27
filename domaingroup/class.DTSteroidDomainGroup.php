<?php
/**
 * @package steroid\domaingroup
 */

require_once STROOT . '/storage/record/interface.IRecord.php';

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/domaingroup/class.RCDomainGroup.php';

/**
 * @package steroid\domaingroup
 */
class DTSteroidDomainGroup extends BaseDTRecordReference {
	public static function getFormRequired() {
		return true;
	}

	public static function getFieldDefinition( $required = true ) {
		return array(
			'dataType' => __CLASS__,
			'nullable' => !$required,
			'requireSelf' => false,
			'requireForeign' => $required,
			'recordClass' => 'RCDomainGroup',
			'constraints' => array( 'min' => 1, 'max' => 1 )
		);
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		$foreignRecordClass = $fieldConf[ 'recordClass' ];

		if ( $extraParams[ 'domainGroup' ] ) {
			$titleFields = RCLanguage::getTitleFieldsCached();

			$queryStruct = array(
				'fields' => array_merge($titleFields, array(Record::FIELDNAME_PRIMARY )),
				'where' => array(
					Record::FIELDNAME_PRIMARY,
					'=',
					array( $extraParams[ 'domainGroup' ] )
				)
			);

			$res = $storage->select( $foreignRecordClass, $queryStruct );

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
