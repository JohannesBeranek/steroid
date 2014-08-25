<?php
/**
 * @package steroid\area
 */
 
require_once STROOT . '/area/class.BaseDTAreaForeignReference.php';
 
/**
 * Class for DT in RCArea referenced by join records between RCArea and RCPage/RCTemplate/... as well as referenced by RCElementInArea
 *
 * @package steroid\area
 */
class DTAreaForeignReference extends BaseDTAreaForeignReference {

	public static function getFieldDefinition() {
		return array(
			'dataType' => __CLASS__,
			'nullable' => true,
			'requireSelf' => true
		);
	}
	
	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		return null;
	}
}