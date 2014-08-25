<?php

// fix for buggy parse_url in some php versions
function st_parse_url ( $url, $component = -1 ) {
	$urlParts = parse_url($url, $component);
	
	if (substr($url, 0, 2) === "//") {
		$url = 'http:' . $url;
		$urlParts = parse_url($url);
		unset($urlParts['scheme']);
	}
	
	return $urlParts;
}