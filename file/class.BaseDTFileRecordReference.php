<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/storage/record/class.Record.php';

/**
 * @package steroid\datatype
 */
class BaseDTFileRecordReference extends BaseDTRecordReference {
	protected function _setValue( $data, $loaded, $skipRaw = false, $skipReal = false ) {		
		if ( is_array( $data ) && array_key_exists( 'tmp_name', $data ) ) {
			$data = RCFile::get( $this->storage, array( 'filename' => $data ), false );
		}

		parent::_setValue( $data, $loaded, $skipRaw, $skipReal );
		
		
		if ((isset($this->config['allowedTypes']) || isset($this->config['allowedCategories'])) && isset($this->value) && ($filetype = $this->value->filetype) && !$loaded) {
			if (isset($this->config['allowedCategories']) && ($mimeCategory = $filetype->mimeCategory)) {
				if (in_array($mimeCategory, $this->config['allowedCategories'])) {
					$allowed = true;
				} else {
					$allowed = false;
				}	
			}
			
			if (isset($this->config['allowedTypes']) && empty($allowed) && ($mimeType = $filetype->mimeType)) {
				if (in_array($mimeType, $this->config['allowedCategories'])) {
					$allowed = true;
				} else {
					$allowed = false;
				}	
			}
			
			if (isset($allowed) && !$allowed) {
				$info = (isset($mimeCategory) ? $mimeCategory : '') . ';' . (isset($mimeType) ? $mimeType : '');
				
				throw new DisallowedFileTypeException('File type ' . $info . ' not allowed here: ' . get_class($this->record) . '->' . $this->fieldName);
			}
		}
	}
	
	public static function completeConfig( &$config, $recordClass, $fieldName ) {
		$config['branchFields'] = '*';
	}
}

class DisallowedFileTypeException extends Exception {}