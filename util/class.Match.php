<?php

class Match {
	final public static function multiFN( $str, array $arr, $flags = 0 ) {
		foreach ($arr as $entry) {
			if (fnmatch($entry, $str, $flags)) {
				return true;
			}
		}

		return false;
	}

	final public static function DNparts( array $patternParts, array $strParts ) {
		if (count($patternParts) !== count($strParts)) {
			return false;
		}

		while($patternParts) {
			$patterPart = array_pop($patternParts);
			$strPart = array_pop($strParts);

			if (fnmatch($patternPart, $strPart)) {
				return true;
			}
		}

		return false;
	}

	final public static function DN( $pattern, $str ) {
		$patternParts = explode('.', $pattern);
		$strParts = explode('.', $str);
	
		return self::DNparts( $patternParts, $strParts );
	}

	final public static function multiDN( $str, array $arr ) {
		$strParts = explode('.', $str);

		foreach ($arr as $entry) {
			$entryParts = explode('.', $entry);

			if (self::DNparts($entryParts, $strParts)) {
				return true;
			}
		}

		return false;
	}
}
