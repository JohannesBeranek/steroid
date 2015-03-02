<?php
/**
 * @package steroid\datatype
 */
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

require_once STROOT . '/file/class.Filename.php';

/**
 * base class for string type values
 *
 * @package steroid\datatype
 */
class DTFilename extends DTString {
	// TODO: 0 byte filtering in setValue

	public function beforeSave( $isUpdate, array &$savePaths = NULL ) {
		if ( isset( $this->values[ $this->colName ] ) && !empty( $this->values[ $this->colName ] ) && !is_readable( Filename::getPathInsideWebroot( $this->values[ $this->colName ] ) ) ) {
			throw new InvalidArgumentException( 'File "' . $this->values[ $this->colName ] . '" does not exist or is not readable' );
		}

		// TODO: normalize value
		

		parent::beforeSave( $isUpdate, $savePaths );
	}

}