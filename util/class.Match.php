<?php

class Match {
	final public static multiFN( $str, array $arr, $flags = 0 ) {
		foreach ($arr as $entry) {
			if (fnmatch($entry, $str, $flags)) {
				return true;
			}
		}

		return false;
	}
}
