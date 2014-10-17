<?php
/**
 * @package steroid\datatype
 */
 
require_once STROOT . '/datatype/class.BaseDTString.php';
require_once STROOT . '/storage/record/interface.IRecord.php';

require_once STROOT . '/request/class.RequestInfo.php';
require_once STROOT . '/file/class.UploadedFileInfo.php';
require_once STROOT . '/cache/class.FileCache.php';
require_once STROOT . '/gfx/class.GFX.php';

/**
 * base class for string type values
 * 
 * @package steroid\datatype
 */
class DTFile extends BaseDTString {
	protected $uploadedFile;
	
	/**
	 * 
	 * @param int $maxLen
	 * @param bool $isFixed
	 * @param string $default
	 * @param bool $nullable
	 * 
	 */
	public static function getFieldDefinition( $nullable = false ) {

		return array(
			'dataType' => get_called_class(),
			'maxLen' => 127,
			'fixed' => false,
			'default' => NULL,
			'nullable' => (bool)$nullable
		);
	}
	
	public function cleanup() {
		unset($this->uploadedFile);
	}

	public function setValue( $data = NULL, $loaded = false ) {

		if (is_array($data)) { // can not be $loaded
			if (isset($data['filename']) && !isset($data['tmp_name'])) {
				$data = $data['filename']; // input from Record::getFormValues
			} else {
				if (!isset($data['tmp_name'])) {
					throw new InvalidArgumentException( 'Invalid value' );
				}

				/** @var RequestInfo */
				$currentRequestInfo = RequestInfo::getCurrent();

				$fileInfoObject = $currentRequestInfo->getFileInfoForFile( $data['tmp_name'] ); // 'tmp_name' is defined by php itself

				if (!$fileInfoObject) {
					throw new SecurityException( 'Someone is trying to trick file upload!' );
				}

				$this->uploadedFile = new UploadedFileInfo($fileInfoObject);

				// will be set later in beforeSave - otherwise we'd have to upload here, which could potentially break transactions
				$data = null;

				// $this->record can always only be RCFile (or compatible)
				// also this has to be done here instead of in RCFile::beforeSave, as we need the uploaded filename
				if (!isset($this->record->downloadFilename)) {
					$this->record->downloadFilename = $this->uploadedFile->getUploadedFilename();
				}
			}
		}
		
		parent::setValue( $data, $loaded );
	}

	public function beforeSave( $isUpdate ) {
		if ($this->uploadedFile) {
			$this->storage->uploadFile($this->uploadedFile);
				
			$data = $this->uploadedFile->getStoredFilename();
			
			parent::setValue( $data, false );
		
			$this->uploadedFile = null;
		}
	}
}