<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';

require_once STROOT . '/datatype/class.DTParentForeignReference.php';

/**
 * Basic class for parent references
 *
 * (i.e. where a record has a reference to another record of the same record class)
 *
 * @package steroid\datatype
 */
class DTParentReference extends BaseDTRecordReference {
	public static function getFieldDefinition() {
		return array(
			'dataType' => get_called_class(),
			'nullable' => true,
			'requireForeign' => false,
			'requireSelf' => false,
			'constraints' => array( 'min' => 0, 'max' => 1 )
		);
	}

	public static function completeConfig( &$config, $recordClass, $fieldName ) {
		$config[ 'recordClass' ] = $recordClass;
	}

	public static function getColName( $fieldName, array $config = NULL ) {
		return $fieldName . '_' . Record::FIELDNAME_PRIMARY;
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( isset( $extraParams[ 'parent' ] ) ) {

			$recordClass = $extraParams[ 'recordClasses' ][ count( $extraParams[ 'recordClasses' ] ) - 1 ];

			$queryStruct = array(
				'fields' => $recordClass::getTitleFieldsCached()
			);

			$queryStruct[ 'fields' ][ ] = Record::FIELDNAME_PRIMARY;

			$queryStruct[ 'where' ] = array( Record::FIELDNAME_PRIMARY, '=', array( $extraParams[ 'parent' ] ) );

			$values = $storage->selectFirst( $recordClass, $queryStruct );

			return $values;
		}

		return NULL;
	}

	public static function getForeignReferences( $recordClass, $calledClass, $fieldName, $fieldDef, &$fieldNames ) {
		if ( $fieldDef[ 'recordClass' ] == $recordClass ) {
			$fieldNames[ $fieldName . ':' . $calledClass ] = DTParentForeignReference::getFieldDefinition();
		}
	}

	public function getValue() {
		if ( $this->value === $this->record ) { // avoid endless recursion
			return NULL;
		}

		return $this->value;
	}

	protected function _setValue( $data, $loaded, $skipRaw = false, $skipReal = false ) {
		if(($data instanceof IRecord && $data === $this->record)
		|| ( ( is_string( $data ) || is_int( $data ) ) && $this->record->{Record::FIELDNAME_PRIMARY} == $data)
		|| ( is_array( $data ) && $data[ Record::FIELDNAME_PRIMARY ] == $this->record->{Record::FIELDNAME_PRIMARY}) ){
			throw new ParentOfItselfException('Record cannot be parent of itself');
		}

		parent::_setValue($data, $loaded, $skipRaw, $skipReal);
	}
}

class ParentOfItselfException extends SteroidException {

}