<?php

function array_diff_strict( $first, $second ) {
	$ret = array();
	
	foreach ($first as $entry) {
		if (!in_array($entry, $second, true)) {
			$ret[] = $entry;
		}
	}
	
	return $ret;
}
