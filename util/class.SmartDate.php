<?php
/**
 * @package steroid\util
 */
 
/**
 * @package steroid\util
 */
class SmartDate {
	public static function format( $date, $language ) {
		static $now, $today, $thisWeek;
		
		if ($now === NULL) {
			$now = time();
			$today = (int)($now / 86400);
			
			$thisWeek = date('o-W', $now);
		}
		
		$dateDay = (int)($date / 86400);
		
		if (($today + 1) === $dateDay) { // tomorrow
			switch($language) {
				case 'de':
					$smartDate = 'morgen';
				break;
				case 'en':
				default: // default to english
					$smartDate = 'tomorrow';
				break;
					
			}
		} elseif ($today === $dateDay) { // today
			switch($language) {
				case 'de':
					$smartDate = 'heute';
				break;
				case 'en':
				default: // default to english
					$smartDate = 'today';
			}
		} elseif (($today - 1) === $dateDay) { // yesterday
			switch($language) {
				case 'de':
					$smartDate = 'gestern';
				break;
				case 'en':
				default: // default to english
					$smartDate = 'yesterday';
			}
		} elseif ($thisWeek === date('o-W', $date)) { // same week
			$smartDate = strftime('%A', $date);
		} else { // future or past
			switch($language) {
				case 'de':
					$smartDate = 'am ' . ltrim(strftime('%e. %B', $date));
				break;
				case 'en':
					$smartDate = date('jS \of F', $date); // date always outputs in english, and strftime has no "S" modifier, so this is okay
				break;
				default:
					$smartDate = ltrim(strftime("%e. %B", $date)); // just rely on locale
			}
		}

		$dateYear = date('Y', $date);

		if (date('Y', $now) !== $dateYear) {
			$smartDate .= ' ' . $dateYear;
		}
		

		
		return $smartDate;
	}
}

?>
