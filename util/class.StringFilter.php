<?php
/**
*
* @package package_name
*/

/**
 *
 * @package package_name
 */
class StringFilter {
	
	/**
	 * Filter string to a valid classname
	 * 
	 * @param string $str
	 * @return string
	 */
	public static function filterClassName( $str ) {
		return preg_replace("/[^A-Za-z_]/", "", $str);
	}
	
	public static function filterFilenameWithPath( $str ) {
		return str_replace(chr(0), '', $str); // security fix for null byte poisoning
	}
	
	public static function filterFilename( $str ) {
		return basename(str_replace(array(chr(0), '/'), '', $str)); // security fix for null byte poisoning + remove directory separator + php own filter
	}
	
	public static function filterPath( $str ) {
		return str_replace(chr(0), '', $str); // security fix for null byte poisoning
	}

	public static function filterBase64( $str ) {
		return preg_replace('/[^a-zA-Z0-9\+\/]+/', '', $str);
	}

	public static function formatFileSize( $size, $digits = 1 ) {
		$units = array( ' B', ' KB', ' MB', ' GB', ' TB' );

		for ( $i = 0; $size >= 1024 && $i < 4; $i++ ) {
			$size /= 1024;
		}

		return str_replace(',', '.', round( $size, $digits ) ) . $units[ $i ];
	}
}