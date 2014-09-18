<?php
/**
 * @package steroid\storage
 */


/**
 * @package steroid\storage
 */
interface IFileInfo {
	function getStoredFilename();

	function setStoredFilename( $filename );

	function setFullFilename( $filename );
	function getFullFilename();

	function getTempFilename();

	function getUploadedFilename();

	function getMimeType();

	function getMimeCategory();

	function getDownloadFilename();

	function getFileMeta( $name );

	function getData();
}

?>