<?php
/**
 * @package steroid\file
 */


require_once STROOT . '/file/interface.IFileInfo.php';
require_once STROOT . '/util/class.StringFilter.php';

/**
 * @package steroid\file
 */
class UploadedFileInfo implements IFileInfo {
	protected $storedFilename;

	protected $tempFilename;
	protected $uploadedFilename;

	protected $mimeType;
	protected $fullFilename;

	public function __construct( array $fileObject ) {
		$this->tempFilename = $fileObject[ 'tmp_name' ];

		// this shouldn't ever happen, as construct should only be called with an entry from $_FILES, be we check nevertheless 
		if ( StringFilter::filterFilenameWithPath( $this->tempFilename ) !== $this->tempFilename ) {
			throw new SecurityException( 'Unsafe tmp filename in upload, there must be something wrong with the code.' );
		}

		$this->uploadedFilename = $fileObject[ 'name' ];

		if ( StringFilter::filterFilename( $this->uploadedFilename ) !== $this->uploadedFilename ) {
			throw new SecurityException( 'Unsafe filename in upload.' );
		}
	}

	public function getStoredFilename() {
		return $this->storedFilename;
	}


	public function setStoredFilename( $filename ) {
		$this->storedFilename = $filename;
	}

	public function setFullFilename( $filename ) {
		$this->fullFilename = $filename;
	}

	public function getFullFilename() {
		return $this->fullFilename;
	}

	public function getTempFilename() {
		return $this->tempFilename;
	}

	public function getUploadedFilename() {
		return $this->uploadedFilename;
	}

	public function getDownloadFilename() {
		return $this->getUploadedFilename();
	}

	public function getMimeType() {
		if ( !$this->mimeType ) {
			$finfo = new finfo( FILEINFO_MIME_TYPE );
			$this->mimeType = $finfo->file( $this->tempFilename );
		}

		return $this->mimeType;
	}

	public function getMimeCategory() {
		return strstr( $this->getMimeType(), '/', true );
	}

	public function getFileMeta( $name ) {
		// TODO: should be able to extract meta
		return NULL;
	}

	public function getData() {
		return file_get_contents( $this->getTempFilename() );
	}
}

?>