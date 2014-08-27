<?php

require_once STROOT . '/storage/interface.ITransactionBased.php';
require_once STROOT . '/file/interface.IFileInfo.php';


interface IFileStore extends ITransactionBased {
	/**
	 * Upload file
	 *
	 * @param IFileInfo $file normally built on an entry from $_FILES
	 *
	 */
	public function uploadFile( IFileInfo $file );
	
	/**
	 * Remove file
	 * 
	 * @param IFileInfo $fileRecord
	 *
	 */
	public function unlinkFile( IFileInfo $fileRecord );
	
	/**
	 * Download file
	 * 
	 * @param IFileInfo $file
	 */
	public function downloadFile( IFileInfo $file );
	
	/**
	 * Sends file associated with $fileRecord
	 * 
	 * @param IFileInfo $fileRecord
	 * 
	 */
	public function sendFile( IFileInfo $fileRecord, $forceDownload = false );
	
	
	public function getStorageDirectory();
}