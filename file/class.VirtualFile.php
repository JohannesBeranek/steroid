<?php

require_once __DIR__ . '/interface.IFileInfo.php';

class VirtualFile implements IFileInfo {
	protected $storedFilename;

	protected $data;
	protected $mimeType;
	protected $filename;
	protected $fullFilename;

	public function __construct( $data, $mimeType = NULL, $filename = NULL ) {
		$this->data = $data;
		$this->mimeType = $mimeType;
		$this->filename = StringFilter::filterFilename( pathinfo( $filename, PATHINFO_BASENAME ) );
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
		return NULL;
	}

	public function getUploadedFilename() {
		return $this->filename;
	}

	public function getMimeType() {
		return $this->mimeType;
	}

	public function getMimeCategory() {
		return $this->mimeType === NULL ? NULL : strstr( $this->mimeType, '/', true );
	}

	public function getFileMeta( $name ) {
		return NULL;
	}

	public function getData() {
		return $this->data;
	}
}