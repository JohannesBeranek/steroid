<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTForeignReference.php';

/**
 * class for foreign references (e.g. join tables, where the datatype's record is referenced by another record)
 * 
 * @package steroid\datatype
 */
class DTMenuItemForeignReference extends BaseDTForeignReference {
	public static function getFieldDefinition() {
		return array(
			'dataType' => get_called_class(),
			'nullable' => true,
			'requireSelf' => true
		);
	}

	protected function getForeignFormFields( $recordClass = NULL ) {
		if ( !$recordClass ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		return array_keys( $recordClass::getFormFields( $this->storage ) );
	}

	protected function addNestedRecords( array &$records ) {
		if ( get_class( $this->record ) === 'RCMenu' ) { // RCMenuItem also uses DTMenuItemForeignReference for its children
			foreach ( $records as &$foreignRecord ) { // foreach with reference, so we also iterate through newly added records, making this recursive
				if ( ( isset($foreignRecord->{'parent:RCMenuItem'}) || $foreignRecord->exists() ) && ( $children = $foreignRecord->{'parent:RCMenuItem'}) ) {
					foreach ( $children as $child ) {
						if ( !in_array( $child, $records, true )) {
							$records[] = $child;
						}
					}
				}
			}
		}
	}
}

?>