<?php

class ImagickCLIUtil {
	public static function shortFloat( $f ) {
		$val = str_replace(',', '.', (string)((float)$f));

		$valParts = explode('.', $val);

		$val = $valParts[0];

		if (!empty($valParts[1])) {
			$val .= '.' . rtrim($valParts[1], '0');
		}

		return $val === '' ? '0' : $val;
	}
}