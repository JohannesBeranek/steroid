<?php
/**
 * @package steroid\url
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/storage/record/class.Record.php';

require_once __DIR__ . '/class.RCUrl.php';

/**
 * @package steroid\datatype
 */
class DTUrlRecordReference extends BaseDTRecordReference {
	/**
	 * returns field definition
	 *
	 * @param string $recordClass record class to reference ('RC....')
	 * @param bool   $requireForeign true to make field NOT NULL and only valid, if foreign record is set
	 * @param bool   $requireSelf true to automatically delete set foreign reference upon deletion of owning record
	 *
	 * @return array
	 */
	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'recordClass' => 'RCUrl',
			'nullable' => false,
			'requireForeign' => true,
			'requireSelf' => false,
			'default' => NULL
		);
	}

	protected function mayCopyReferenced() {
		return true;
	}

	protected function getFormValueFields() {
		$fields = array_keys( RCUrl::getFormFields( $this->storage, array() ) );

		return $fields;
	}
}