<?php
/**
 * @package steroid\file
 */


require_once STROOT . '/file/interface.IFileInfo.php';
require_once STROOT . '/util/class.StringFilter.php';

/**
 * @package steroid\file
 */
class DownloadFileInfo implements IFileInfo {
	protected $storedFilename;

	protected $tempFilename;
	protected $fullFilename;

	public function __construct( $url ) {
		$this->tempFilename = $url;

		if ( StringFilter::filterFilenameWithPath( $this->tempFilename ) !== $this->tempFilename ) {
			throw new SecurityException( 'Unsafe tmp filename in upload, there must be something wrong with the code.' );
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

	public function getDownloadFilename() {
		return $this->getUploadedFilename();
	}

	public function getTempFilename() {
		return $this->tempFilename;
	}

	public function getUploadedFilename() {
		return pathinfo( $this->tempFilename, PATHINFO_BASENAME );
	}

	public function getMimeType() {
		return NULL;
	}

	public function getMimeCategory() {
		return NULL;
	}

	public function getFileMeta( $name ) {
		return NULL;
	}

	public function getData() {
		return file_get_contents( $this->getTempFilename );
	}
}