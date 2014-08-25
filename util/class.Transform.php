<?php

class Transform {
	public static function md5_base64 ( $data ) {
		return preg_replace('/=+$/','',base64_encode(pack('H*',md5($data))));
	}
	
	public static function md5_base64_url ( $data ) {
		 return self::base64_to_url_base64( self::md5_base64($data) );
	}
	
	public static function url_base64_to_base64 ( $data ) {
		return strtr( $data, '-_', '+/' );
	}
	
	public static function base64_to_url_base64 ( $data ) {
		return strtr( $data, '+/', '-_' );
	}
}
