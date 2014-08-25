<?php
/**
 * @package steroid\url
 */

/**
 * @package steroid\url
 */
class UrlUtil {
	final public static function generateUrlFromString( $string ) {
		$string = trim( $string, '/' );

		$urlParts = explode( '/', $string );

		foreach ( $urlParts as &$urlPart ) {
			$urlPart = self::generateUrlPartFromString( $urlPart );
		}

		return '/' . implode( '/', $urlParts );
	}

	final public static function generateUrlPartFromString( $string ) { // TODO: make reserved/space replacing char/allowed characters configurable
		// does not work with osx iconv which is based on libiconv instead of libc - TODO: find working workaround for dumb osx implementation ...
		return str_replace( ' ', '-', trim( preg_replace( '/([^A-Za-z0-9]|\s)+/', ' ', strtolower( iconv( 'UTF-8', 'US-ASCII//TRANSLIT//IGNORE', $string ) ) ) ) );
	}

	/**
	 * Filters an url fragment according to RFC 3986
	 *
	 * Uses whitelisting
	 *
	 * @param string $fragment
	 *
	 * @return string filtered fragment
	 */
	final public static function filterUrlFragment( $fragment ) {
		return preg_replace( "/([^\!\$&'\(\)\*\+,;\=\-\._~\:@\/\?%a-zA-Z0-9]|%([^0-9a-fA-F]|$)([^0-9a-fA-F]|$))/", '', $fragment );
	}
}