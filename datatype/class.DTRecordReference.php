<?php
/**
 * @package steroid\datatype
 */

require_once __DIR__ . '/class.BaseDTRecordReference.php';
require_once STROOT . '/storage/record/class.Record.php';

/**
 * @package steroid\datatype
 */
class DTRecordReference extends BaseDTRecordReference {
	/**
	 * returns field definition
	 *
	 * @param string $recordClass record class to reference ('RC....')
	 * @param bool   $requireForeign true to make field NOT NULL and only valid, if foreign record is set
	 * @param bool   $requireSelf true to automatically delete set foreign reference upon deletion of owning record
	 *
	 * @return array
	 */
	public static function getFieldDefinition( $recordClass, $requireForeign = false, $requireSelf = false ) {
		// we don't check for class existance here, as it is okay to require it later on
		if ( empty( $recordClass ) ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		return array(
			'dataType' => get_called_class(),
			'recordClass' => $recordClass,
			'nullable' => !$requireForeign,
			'requireForeign' => $requireForeign,
			'requireSelf' => $requireSelf,
			'default' => NULL,
			'constraints' => array( 'min' => $requireForeign ? 1 : 0, 'max' => 1 )
		);
	}
}