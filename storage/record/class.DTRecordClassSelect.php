<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTString.php';
require_once STROOT . '/util/class.ClassFinder.php';

/**
 * base class for tinyint values
 */
class DTRecordClassSelect extends BaseDTString {

	public function __construct( IStorage &$storage, IRecord $record, array &$values, $fieldName = NULL, array $config = NULL ) {
		static::getClasses( $config );

		parent::__construct( $storage, $record, $values, $fieldName, $config );
	}

	final protected static function getClasses( array &$config ) {
		$recordClasses = (array)$config[ 'values' ];

		foreach ( $recordClasses as $key => $rc ) {
			if ( is_callable( $rc ) ) {
				unset( $recordClasses[ $key ] );
				$recordClasses = array_merge( $recordClasses, $rc() );
			}
		}

		$config[ 'values' ] = $recordClasses;
	}

	public static function getFieldDefinition( $recordClasses = NULL, $nullable = false, $default = NULL ) {
		return array(
			'dataType' => get_called_class(),
			'maxLen' => 127,
			'isFixed' => false,
			'default' => $default,
			'nullable' => (bool)$nullable,
			'values' => $recordClasses
		);
	}

	public static function getFormConfig( IRBStorage $storage, $owningRecordClass, $fieldName, $fieldDef ) {
		$fieldDef = parent::getFormConfig( $storage, $owningRecordClass, $fieldName, $fieldDef );

		static::getClasses( $fieldDef );

		return $fieldDef;
	}

	final public static function getRecordClassesWithDataType( $dataType ) {
		$recordClasses = self::getAllRecordClasses( true );

		$val = array();

		foreach ( $recordClasses as $recordClass ) {
			if ( $recordClass::getDataTypeFieldName( $dataType ) ) {
				$val[ ] = $recordClass;
			}
		}

		return $val;
	}

	final public static function getAllRecordClasses( $require = false ) {
		return self::getRecordClassNamesFromClassFinderArray( ClassFinder::getAll( ClassFinder::CLASSTYPE_RECORD, $require, NULL ) );
	}

	final protected static function getRecordClassNamesFromClassFinderArray( $recordClasses ) {
		$names = array();

		if ( !empty( $recordClasses ) ) {
			foreach ( $recordClasses as $recordClass ) {
				$names[ ] = $recordClass[ 'className' ];
			}
		}

		return $names;
	}
	/*
		public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
			self::getClasses( $fieldConf );

			$values = $fieldConf['values'];

			$val = array();

			if (empty($values)) {
				return $val;
			}

			$hasSelected = false;

			foreach($values as $value) {
				$selected = (!$hasSelected) && ($value === $fieldConf['default']);

				if ($selected) {
					$hasSelected = true;
				}

				$val[] = array(
					'value' => $value,
					'label' => $value,
					'selected' => $selected
				);
			}

			if (!$hasSelected && $val) {
				$val[0]['selected'] = true;
			}

			return $val;
		}
	*/

}