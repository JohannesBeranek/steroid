<?php
/**
 * @package steroid\util
 */

require_once STROOT . '/cache/class.FileCache.php';
require_once STROOT . '/file/class.Filename.php';
require_once STROOT . '/file/interface.IFileInfo.php';
require_once STROOT . '/storage/interface.IRBStorage.php';

require_once STROOT . '/request/interface.IRequestInfo.php';
require_once __DIR__ . '/class.RCGFXJob.php';
require_once STROOT . '/file/class.RCFile.php';

require_once STROOT . '/util/class.Config.php';
require_once __DIR__ . '/class.ImagickCLI.php';

require_once STROOT . '/util/class.Responder.php';
//
// Known bugs:
// - GD: transparency isn't always handled correctly
// - IM: sometimes get drawing primitive errors when rendering text
//
// Missing features:
// - IM: text rendering with advanced kerning
// - GD/IM: maxWidth, maxHeight
// - GD: shadowAlpha
// - GD: cases where shadow starts with x > 0 and/or y > 0

/**
 * GFX Util Class
 *
 * usage:
 *        src= source of file
 *        alt= alt-text of img-taggetCr
 *        width= .. height = ..  targetwidth/height
 *        cropscale=1 => scale smaller side (width/height) to targetsize, crop other side
 *        fit=#ffffff => targetwidth is boundary box; scale larger side; fit is the color used for empty space
 *
 * rotate:
 *        degrees=...
 *        rotateScale=... (default: 3)
 *
 * shadow:
 *            shadowOffsetX=
 *            shadowOffsetY=
 *            shadowSpread=
 *            shadowBlur=
 *            shadowColor=
 *            shadowAlpha=
 *
 * Undocumented:
 *  - watermarks
 *  - watermarksFirst
 *  - sizeByWatermarks
 *  - constraints
 *  - ...
 *
 * @package steroid\util
 */
class GFX {
	protected $cache;
	protected $localDir;
	protected $mode;

	protected $useAdvancedLetterSpacing = true;
	// default to true, as "simple" letterSpacing differs for GD vs Imagick and produces ugly results
	protected $skipGenerate;
	protected $storage;

	protected static $imagickUseCLI;
	protected static $imagickCLICommand;

	protected static $imagickAvailable;

	// ImageMagick version reported by imagick
	protected static $imagickVersion;
	protected static $imagickHasPango;
	protected static $imagickExtentMultiplier;

	protected static $gdAvailable;
	protected static $useGDForText;

	protected static $kerningTables;
	protected static $fonts;

	protected static $gdFontUnitMultiplier;

	protected $useImagick;
	protected $reflowTrickeryClass;

	const MODE_META = 'meta';
	const MODE_TAG = 'tag';
	const MODE_SRC = 'src';
	const MODE_SEND = 'send';

	const URL_PARAM = 'j';

	const RETURN_HASH = 1;
	const RETURN_PARAMS = 2;

	// using these numbers makes it possible to use bitmask checks (e.g. GRAVITY_NORTHEAST = GRAVITY_EAST | GRAVITY_NORTH)
	const GRAVITY_NORTH = 1;
	const GRAVITY_EAST = 2;
	const GRAVITY_SOUTH = 4;
	const GRAVITY_WEST = 8;

	const GRAVITY_NORTHEAST = 3;
	const GRAVITY_SOUTHEAST = 6;
	const GRAVITY_NORTHWEST = 9;
	const GRAVITY_SOUTHWEST = 12;

	const GRAVITY_CENTER = 0;

	const COLOR_TRANSPARENT = 'transparent';
	// Imagick uses the same string, so this should not be changed

	/**
	 * 1x1px blank gif
	 */
	const BLANK_GIF = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

	/**
	 * Should not be changed
	 *
	 * It's not possible to declare an array as const, thus we need to use static
	 */
	public static $supportedMimeTypes = array('image/png', 'image/jpeg', 'image/gif');

	/**
	 * Validate hex color string.
	 *
	 * Returns true if string is valid hex color, false otherwise
	 *
	 * valid example: #ffffff
	 *
	 * @return bool
	 */
	final public static function validateHexColor($color) {
		return preg_match('/^(:?#|0x)?[0-9a-fA-F]{1,8}/', $color);
	}

	final public static function getComponents($c, $includeAlpha = false) {// RGBA
		if ($c === self::COLOR_TRANSPARENT) {
			if (!$includeAlpha) {
				throw new Exception('GFX: can not use color ' . self::COLOR_TRANSPARENT . ' in a non-alpha context.');
			}

			$components = array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0);
		} else {
			$c = (int)$c;

			$alphaShift = $includeAlpha ? 8 : 0;

			$components = array('r' => ($c>>(16 + $alphaShift)) & 0xFF, 'g' => ($c>>(8 + $alphaShift)) & 0xFF, 'b' => ($c>>($alphaShift)) & 0xFF);

			if ($includeAlpha) {
				$components['a'] = $c & 0xFF;
			}
		}

		return $components;
	}

	final public static function fromRGBA($r, $g, $b, $a = 0xFF) {
		return ((($r & 0xFF)<<24) | (($g & 0xFF)<<16) | (($b & 0xFF)<<8) | ($a & 0xFF));
	}

	final public static function toImagickColor($c, $includeAlpha = false) {
		if ($c === self::COLOR_TRANSPARENT) {
			if (!$includeAlpha) {
				throw new Exception('GFX: can not use color ' . self::COLOR_TRANSPARENT . ' in a non-alpha context.');
			}

			return $c;
		}

		// rgba(...) notation does not work with ImagickPixel

		$components = self::getComponents((int)$c, $includeAlpha);

		// php imagick does not like hex format for colors with alpha
		$ret = '#' . str_pad(dechex($components['r']), 2, '0', STR_PAD_LEFT) . str_pad(dechex($components['g']), 2, '0', STR_PAD_LEFT) . str_pad(dechex($components['b']), 2, '0', STR_PAD_LEFT);

		if ($includeAlpha) {
			$ret .= str_pad(dechex($components['a']), 2, '0', STR_PAD_LEFT);
		}

		/*
		 if ($includeAlpha) {
		 $ret = 'rgba(' . $components['r'] . ',' . $components['g'] . ',' . $components['b'] . ',' . ( $components['a'] / 0xFF ) . ')';
		 } else {
		 $ret = 'rgba(' . $components['r'] . ',' . $components['g'] . ',' . $components['b'] . ')';
		 }
		 */

		return $ret;
	}

	final public static function toGDColor($im, $c, $includeAlpha = false) {
		if ($c === self::COLOR_TRANSPARENT) {
			if (!$includeAlpha) {
				throw new Exception('GFX: can not use color ' . self::COLOR_TRANSPARENT . ' in a non-alpha context.');
			}

			$color = imagecolorallocatealpha($im, 0, 0, 0, 127);
		} else {

			$colorComponents = self::getComponents($c, $includeAlpha);

			if ($includeAlpha) {
				$color = imagecolorallocatealpha($im, $colorComponents['r'], $colorComponents['g'], $colorComponents['b'], ((~((int)$colorComponents['a'])) & 0xFF)>>1
				//					127 - floor( $colorComponents[ 'a' ] * 0.5 )
				);
			} else {
				$color = imagecolorallocate($im, $colorComponents['r'], $colorComponents['g'], $colorComponents['b']);
			}

		}

		return $color;
	}

	final public static function parseHex($h, $includeAlpha = false) {
		if (is_int($h))
			return $h;

		if (!self::validateHexColor($h)) {
			throw new Exception('GFX: Invalid color "' . $h . '"');
		}

		if (substr($h, 0, 2) === '0x') {
			$h = substr($h, 2);
		} elseif ($h{0} === '#') {
			$h = substr($h, 1);
		}

		$len = strlen($h);

		switch ( $len ) {
			case 1 :
				// 111111FF
				$hx = str_repeat($h{0}, 6) . 'FF';
				break;
			case 2 :
				// 11111122
				$hx = str_repeat($h{0}, 6) . $h{1} . $h{1};
				break;
			case 3 :
				// 112233FF
				$hx = $h{0} . $h{0} . $h{1} . $h{1} . $h{2} . $h{2} . 'FF';
				break;
			case 4 :
				// 11223344
				$hx = $h{0} . $h{0} . $h{1} . $h{1} . $h{2} . $h{2} . $h{3} . $h{3};
				break;
			case 5 :
				// 11223345
				$hx = $h{0} . $h{0} . $h{1} . $h{1} . $h{2} . $h{2} . $h{3} . $h{4};
				break;
			case 6 :
				// 123456FF
				$hx = $h{0} . $h{1} . $h{2} . $h{3} . $h{4} . $h{5} . 'FF';
				break;
			case 7 :
				// 12345677
				$hx = $h{0} . $h{1} . $h{2} . $h{3} . $h{4} . $h{5} . $h{6} . $h{6};
				break;
			case 8 :
				// 12345678
				$hx = $h{0} . $h{1} . $h{2} . $h{3} . $h{4} . $h{5} . $h{6} . $h{7};
				break;
			default :
				throw new Exception('GFX: Unable to parse hex color "' . $h . '"');
		}

		$val = hexdec($includeAlpha ? $hx : substr($hx, 0, 6));

		return $val;
	}

	public static function handleRequest(IRequestInfo $requestInfo, FileCache $cache, IRBStorage $storage) {
		$hash = $requestInfo -> getQueryParam(self::URL_PARAM);

		if (strlen($hash) !== 32) {
			throw new Exception('GFX: Invalid hash (' . $hash . ') length (' . strlen($hash) . ') in request.');
			// TODO: SecurityException
		}

		// TODO: additional check?
		$job = $storage -> selectFirstRecord('RCGFXJob', array('fields' => '*', 'where' => array('hash', '=', '%1$s'), 'vals' => array($hash), 'name' => 'RCGFXJob_verify'));

		//		if ( !$job ) {
		//			throw new Exception( 'GFX: No job for hash found.' ); // TODO: SecurityException
		//		}

		if ($job) {
			$modifiedSince = $requestInfo -> getServerInfo('HTTP_IF_MODIFIED_SINCE');

			// TODO: allow no_cache query parameter for installation = development localconf.ini.php setting

			// no_cache header is not allowed here, because it would make it too easy to DDoS server with this
			if ((empty($_GET['no_cache']) || Config::key('mode', 'installation') !== 'development') && ($modifiedSince && strtotime($modifiedSince) >= strtotime($job -> ctime))) {
				header_remove('Content-Length');
				Responder::sendReturnCodeHeader(304);
				// TODO: might be good to add correct Content-Type
			} else {
				$params = json_decode($job -> params, true);

				$gfx = new self($cache, $storage);
				$gfx -> setSkipGenerate(false);
				$gfx -> setMode(self::MODE_SEND);

				if (($meta = $gfx -> executeCommand($params['command'], $params, $job)) === false) {
					throw new Exception('GFX: Sending image failed.');
				}
			}

		}
	}

	public function __construct(FileCache $cache, IRBStorage $storage) {
		$this -> cache = $cache;
		$this -> storage = $storage;
		$this -> localDir = WEBROOT;
		$this -> mode = self::MODE_META;

		if (self::$imagickAvailable === NULL) {
			if (self::$imagickUseCLI = Config::key('gfx', 'imagick_cli')) {
				if (is_string(self::$imagickUseCLI) && ($path = Filename::unwind(self::$imagickUseCLI)) && (is_executable($path))) {
					self::$imagickCLICommand = $path;
				} else {
					self::$imagickCLICommand = 'convert';
				}

				self::$imagickUseCLI = true;

				exec(escapeshellcmd(self::$imagickCLICommand) . ' -version', $out, $returnCode);

				if ($returnCode === 0) {
					$info = array();

					foreach ($out as $line) {
						$exp = explode(":", $line, 2);
						if (count($exp) !== 2)
							continue;

						list($key, $val) = $exp;
						$info[$key] = $val;
					}

					if (isset($info['Version'])) {
						self::$imagickVersion = preg_replace('/^ImageMagick ([^ ]+).*$/', '$1', $info['Version']);

						self::$imagickUseCLI = TRUE;

						ImagickCLI::$convertCommand = self::$imagickCLICommand;
					} else {
						self::$imagickUseCLI = FALSE;
					}
				} else {
					self::$imagickUseCLI = FALSE;
				}
			}

			if (!self::$imagickUseCLI && (self::$imagickAvailable = extension_loaded('imagick'))) {
				$imagickObject = self::getImagick();

				$imagickVersionData = $imagickObject -> getVersion();

				$imagickObject -> destroy();
				unset($imagickObject);

				self::$imagickVersion = preg_replace('/^ImageMagick ([^ ]+).*$/', '$1', $imagickVersionData['versionString']);

				// Pango might be used for text processing in the future (as soon as it supports specifying font files)
				// self::$imagickHasPango = strnatcmp( self::$imagickVersion, '6.7.6-3' ) >= 0 && self::$imagickVersion !== '6.8.0-7'; // min version according to doc + avoid buggy version

			}

			if (isset(self::$imagickVersion)) {
				self::$imagickExtentMultiplier = strnatcmp(self::$imagickVersion, '6.6.4-2') >= 0 ? -1 : 1;
			}
		}

		if (self::$gdAvailable === NULL) {
			self::$gdAvailable = extension_loaded('gd') && function_exists('gd_info');
		}

		if (self::$useGDForText === NULL) {// detect osx, so we use GD for text rendering (imagick 3.1.0RC2 (and probably below) kills apache on ImagickDraw::setFont)
			self::$useGDForText = !self::$imagickUseCLI && (!self::$imagickAvailable || (PHP_OS === 'Darwin' && version_compare(phpversion('imagick'), '3.1.0RC2') <= 0 && !Config::key('gfx', 'unfix_osx')));
		}

		if (self::$gdFontUnitMultiplier === NULL && self::$gdAvailable) {
			$info = gd_info();
			$versionString = $info['GD Version'];
			preg_match('/\d+(?:\.\d+(?:\.\d+))?/', $versionString, $matches);

			if ($matches) {
				$versionNumber = $matches[0];
				$version = version_compare($versionNumber, 2, '>=') ? 2 : 1;
			} else {
				$version = 1;
			}

			self::$gdFontUnitMultiplier = $version > 1 ? 0.75 : 1;
			// 72dpi / 96dpi = 3/4 = 0.75
		}

		$this -> useImagick = self::$imagickAvailable || self::$imagickUseCLI;
	}

	public function setReflowTrickeryClass($class) {
		$this -> reflowTrickeryClass = $class ? htmlspecialchars($class, ENT_COMPAT, "UTF-8") : NULL;
	}

	public function setLocalDir($dir) {
		$this -> localDir = $dir;
	}

	public function setMode($mode) {
		$this -> mode = $mode;
	}

	public function setUseImagick($value) {
		$this -> useImagick = (bool)$value && self::$imagickAvailable;
	}

	/**
	 * @param bool $skip
	 */
	public function setSkipGenerate($skip) {
		$this -> skipGenerate = (bool)$skip;
	}

	public function getUseAdvancedLetterSpacing() {
		return (bool)$this -> useAdvancedLetterSpacing;
	}

	public function setUseAdvancedLetterSpacing($bool) {
		$this -> useAdvancedLetterSpacing = (bool)$bool;
	}

	public function resize($params) {
		return $this -> executeCommand('resize', $params);
	}

	public function rotate($params) {
		return $this -> executeCommand('rotate', $params);
	}

	public function convert($params) {
		return $this -> executeCommand('convert', $params);
	}

	public function execute($params) {
		return $this -> executeCommand($params['command'], $params);
	}

	public function pass($params) {
		return $this -> executeCommand('pass', $params);
	}

	final private function getFont($fontFile) {
		require_once STROOT . '/font/fontparse.php';

		if (!isset(self::$fonts[$fontFile])) {
			self::$fonts[$fontFile] = new OTTTFont($fontFile);
		}

		return self::$fonts[$fontFile];
	}

	final private function getKerningTable($fontFile) {
		if (!isset(self::$kerningTables)) {
			self::$kerningTables = array();
		}

		if (!isset(self::$kerningTables[$fontFile])) {
			$font = $this -> getFont($fontFile);

			if (isset($font -> tables['kern'])) {
				/* @var $kerningTable Fonttable */
				$kerningTable = &$font -> tables['kern'];
				$fh = $font -> open();
				fseek($fh, $kerningTable -> offset);

				$data = array();
				$data['version'] = FileRead::read_USHORT($fh);
				$data['nTables'] = FileRead::read_USHORT($fh);
				$data['subtables'] = array();

				for ($i = 0; $i < $data['nTables']; $i++) {
					$subtable = array();
					$subtable['version'] = FileRead::read_USHORT($fh);
					$subtable['length'] = FileRead::read_USHORT($fh);
					$subtable['coverage'] = FileRead::read_USHORT($fh);

					if (((((int)$subtable['coverage'])>>7) & 1) === 0) {
						$subtable['nPairs'] = FileRead::read_USHORT($fh);
						$subtable['searchRange'] = FileRead::read_USHORT($fh);
						$subtable['entrySelector'] = FileRead::read_USHORT($fh);
						$subtable['rangeShift'] = FileRead::read_USHORT($fh);
						$subtable['pairs'] = array();
						$subtable['opairs'] = array();
						// ordered pairs for lookup

						for ($n = 0; $n < $subtable['nPairs']; $n++) {
							$pair = array();
							$pair['left'] = FileRead::read_USHORT($fh);
							$pair['right'] = FileRead::read_USHORT($fh);
							$pair['value'] = FileRead::read_FWORD($fh);
							$subtable['pairs'][] = $pair;

							if (!isset($subtable['opairs'][$pair['left']])) {
								$subtable['opairs'][$pair['left']] = array();
							}

							$subtable['opairs'][$pair['left']][$pair['right']] = $pair['value'];
						}
					}

					$data['subtables'][] = $subtable;
				}

				fclose($fh);

				self::$kerningTables[$fontFile] = $data;
			} else {
				self::$kerningTables[$fontFile] = false;
			}

		}

		return self::$kerningTables[$fontFile];
	}

	final private function getCharIndex($fontFile, $character) {
		$font = $this -> getFont($fontFile);

		return $font -> get_index($character);
	}

	final private function getKerning($fontFile, $fontSize, $left, $right) {
		$font = $this -> getFont($fontFile);

		$kerningTable = $this -> getKerningTable($fontFile);

		if ($kerningTable !== false && ($leftIndex = $this -> getCharIndex($fontFile, $left)) !== false && ($rightIndex = $this -> getCharIndex($fontFile, $right)) !== false) {
			foreach ($kerningTable[ 'subtables' ] as $subtable) {
				if (isset($subtable['opairs'][$leftIndex][$rightIndex])) {
					$kerning = $subtable['opairs'][$leftIndex][$rightIndex];

					$quadSize = $font -> get_quad_size();

					return $kerning * $fontSize / $quadSize;
				}
			}
		}

		return 0;
	}

	final private function getRSB($fontFile, $fontSize, $char) {
		$font = $this -> getFont($fontFile);

		$glyph = $font -> get_glyph($char);

		if ($glyph) {
			$rsb = $glyph -> rsb;

			if ($rsb) {
				$quadSize = $font -> get_quad_size();

				return $rsb * $fontSize / $quadSize;
			}
		}

		return 0;
	}

	final private function getAdvanceWidth($fontFile, $fontSize, $char) {
		$font = $this -> getFont($fontFile);

		$tableLoader = $font -> getOTFTableLoader();

		if (($hmtxTable = $tableLoader -> get_hmtx_table($font)) && ($charIndex = $this -> getCharIndex($fontFile, $char)) && ( isset($hmtxTable -> hMetrics[$charIndex]))) {
			$quadSize = $font -> get_quad_size();
			return ((float)$hmtxTable -> hMetrics[$charIndex]['advanceWidth']) * $fontSize / $quadSize;
		}

		return 0;
	}

	public function getFontMetrics($font, $size, $text, $weight = NULL, $letterSpacing = NULL, $multiline = NULL) {
		$fontFile = Filename::getPathInsideWebrootWithLocalDir($font, $this -> localDir);

		$hash = md5(json_encode(array($fontFile, $size, $text, $weight, $multiline)));
		$key = 'GFX_getFontMetrics_' . $hash;

		$success = false;

		if (!($hasAPC = function_exists('apc_fetch')) || !($metrics = apc_fetch($hash, $success)) || $success === false) {
			if ($this -> useImagick && !self::$useGDForText) {
				$draw = self::getImagickDraw();
				$draw -> setFont($fontFile);
				// hangs Apache on OSX
				$draw -> setFontSize($size);

				if (!empty($letterSpacing) && !$this -> useAdvancedLetterSpacing) {
					$draw -> setTextKerning($letterSpacing);
					// this sets fixed kerning, so it's like using letterSpacing on a font without kerning tables (which looks rather ugly most of the time)
				}

				if (isset($weight)) {
					$draw -> setFontWeight($weight);
				}

				$im = self::getImagick();

				if ($multiline === NULL) {
					$metrics = $im -> queryFontMetrics($draw, $text);
				} else {
					$metrics = $im -> queryFontMetrics($draw, $text, (bool)$multiline);
				}

				$draw -> destroy();
				unset($draw);

				$im -> destroy();
				unset($im);
			} else {// TODO: weight support? letterspacing? multiline?
				if (!function_exists('imagettfbbox')) {
					throw new Exception('GFX: Need imagettfbbox to get font metrics in GD mode (should be provided by freetype part of php).');
				}

				if ($this -> useAdvancedLetterSpacing) {
					$box = imagettfbbox($size * self::$gdFontUnitMultiplier, 0, $fontFile, $text);
				} else {
					// FIXME: as imagettfbbox actually does the kerning right, we would need to add together the boxes for all characters one by one to get the same size as when rendering!
					$box = imagettfbbox($size * self::$gdFontUnitMultiplier, 0, $fontFile, $text);
				}

				// TODO: characterWidth, characterHeight, ascender, descender, maxHorizontalAdvance, originX, originY
				$metrics = array('textWidth' => abs($box[4] - $box[0]), 'textHeight' => abs($box[5] - $box[1]), 'boundingBox' => array('x1' => $box[6], 'y1' => $box[7], 'x2' => $box[2], 'y2' => $box[3]));
			}

			// GD always uses custom letterSpacing, imagick only if useAdvancedLetterSpacing is set
			if (!empty($letterSpacing) && ($this -> useAdvancedLetterSpacing || self::$useGDForText || !$this -> useImagick)) {
				// simple/correct method: for each space between characters (strlen-1, no special treatment for whitespace) we add letterSpacing divided by 1000 multiplied by fontSize
				$strlen = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

				// TODO: if no mb_strlen is available, we should try preg_split and count ?

				if ($strlen > 1) {
					$addedSpace = ($strlen - 1) * $letterSpacing * $size / 1000;

					$metrics['textWidth'] += $addedSpace;
					$metrics['boundingBox']['x2'] += $addedSpace;
				}
			}

			if ($hasAPC) {
				apc_store($key, $metrics);
				// using locks would be too much here, as we don't really care if this runs multiple times
			}
		}

		return $metrics;
	}

	protected function executeCommand($command, $params, $job = NULL, $returnData = false) {
		if (!self::$gdAvailable && !self::$imagickAvailable) {
			throw new Exception('GFX: Need at least GD or Imagick (ImageMagick PECL Extension) available to work.');
		}

		if (isset($params['src']) && $params['src'] instanceof RCFile) {
			if ($renderConfig = $params['src'] -> renderConfig) {
				$params = array_merge($renderConfig, $params);
			}
		}

		// $params['im'] is for internal use only
		if (!isset($params['im']) && !isset($params['src']) && !isset($params['backgroundColor']) && (!isset($params['text']) || !isset($params['font']))) {
			if (!empty($params['watermarks'])) {
				$params['backgroundColor'] = self::COLOR_TRANSPARENT;
				$params['sizeByWatermarks'] = true;
			} else {
				throw new Exception('GFX: param \'src\', \'backgroundColor\' or \'text\' & \'font\' must be given.');
			}
		}

		if (isset($params['src'])) {
			if ($params['src'] instanceof IFileInfo) {
				$fileInfo = $params['src'];

				$params['src'] = $params['src'] -> getFullFilename();
			}

			if (is_string($params['src'])) {
				$filename = trim($params['src']);

				if (strlen($filename) === 0) {
					throw new Exception('GFX: param \'src\' may not be empty.');
				}
			}// else: is_array( $params[ 'src' ] )

			switch ( $command ) {
				case 'resize' :
					if (!isset($params['width']) && !isset($params['height'])) {
						throw new Exception('GFX: param \'width\' and/or param \'height\' must be given.');
					}
					break;

				case 'rotate' :
					if (!isset($params['degrees'])) {
						throw new Exception('[GFX::rotate] : param \'degrees\' must be given.');
					}
					break;
			}

			if (isset($params['aspect'])) {
				$aspect = $params['aspect'];

				if (!isset($params['width'])) {
					$params['width'] = (int)(intval($params['height']) * ($params['aspect']));
				} else if (!isset($params['height'])) {
					$params['height'] = (int)(intval($params['width']) / ($params['aspect']));
				} else {
					throw new Exception('GFX: may not use param \'aspect\' without width/height or with width AND height (you should use \'aspect\' while providing width OR height)');
				}
			}
		}

		if (!$returnData || ($returnData !== self::RETURN_HASH && $returnData !== true)) {
			static $allowedFormats = array('png', 'png24', 'png8', 'jpg', 'jpeg', 'gif');

			// TODO: $determineFormat should also look for 'format' in sub src
			$determineFormat = function($params, $altformat = NULL) use (&$determineFormat, $allowedFormats) {
				if (isset($params['format'])) {// TODO: might not be needed with $returnData
					if (!in_array($params['format'], $allowedFormats)) {
						throw new Exception('GFX: param \'format\' must be one of: ' . implode(', ', $allowedFormats) . '.');
					}

					$format = $params['format'];
				} else {

					if (!isset($params['src'])) {
						throw new Exception('GFX: You need to provide format if you do not provide src param');
					}

					$isArray = false;

					if (is_array($params['src'])) {// try to determine/find format recursively
						$isArray = true;
					} else if ($params['src'] instanceof IFileInfo) {// RCFile is also instanceof IFileInfo
						$pi = pathinfo($params['src'] -> getFullFilename());
					} else if (is_string($params['src'])) {
						$pi = pathinfo($params['src']);
					} else {
						throw new Exception('GFX: unable to automatically determine format');
					}

					if (isset($pi['extension']) && in_array($pi['extension'], $allowedFormats)) {
						$format = $pi['extension'];
					} else {
						if (isset($params['altformat']) && in_array($params['altformat'], $allowedFormats)) {
							$format = $params['altformat'];
						} else if ($altformat !== NULL && in_array($altformat, $allowedFormats)) {
							$format = $altformat;
						} else if ($isArray) {
							return $determineFormat($params['src'], isset($params['altformat']) ? $params['altformat'] : $altformat);
						} else {

							throw new Exception("GFX: unable to autodetect format from file [filename = " . (isset($filename) ? $filename : 'NOT SET') . ", extension = " . (isset($pi['extension']) ? $pi['extension'] : 'NOT SET') . "], please provide param 'format' with one of: " . implode(', ', $allowedFormats) . '.');
						}
					}
				}

				return $format;
			};

			$format = $determineFormat($params);

		}

		if (isset($params['src']) && is_string($params['src'])) {// src might still be array
			$_image_path = Filename::getPathInsideWebrootWithLocalDir($filename, $this -> localDir);

			// GD Only, as Imagick supports pretty much every image type anyway
			if (!$this -> useImagick) {
				$_image_data = getimagesize($_image_path);

				if (!$_image_data) {
					if (!file_exists($_image_path)) {
						throw new Exception("GFX: unable to find '{$_image_path}'.");
					} else if (!is_readable($_image_path)) {
						throw new Exception("GFX: unable to read '{$_image_path}'.");
					} else {
						throw new Exception("GFX: '{$_image_path}' is not a valid image file.");
					}
				}

				if ($_image_data[2] !== IMAGETYPE_GIF && $_image_data[2] !== IMAGETYPE_JPEG && $_image_data[2] !== IMAGETYPE_PNG) {
					throw new UnsupportedImageTypeException("GFX: Unsupported image type.");
				}
			} // TODO: check for valid Imagick file in case of useImagick

		}

		// fetch params for hash generation
		$hashParams = array('command' => $command);

		if (!empty($params['sizeByWatermarks'])) {
			$params['sizeByWatermarks'] = $hashParams['sizeByWatermarks'] = true;

			if (!empty($params['watermarksFirst'])) {
				$params['watermarksFirst'] = $hashParams['watermarksFirst'] = true;
			}
		}

		if (isset($params['focus'])) {
			$focus = array();

			if (isset($params['focus']['x'])) {
				$focus['x'] = $params['focus']['x'];
			}

			if (isset($params['focus']['y'])) {
				$focus['y'] = $params['focus']['y'];
			}

			if ($focus) {
				$params['focus'] = $hashParams['focus'] = $focus;
			} else {
				unset($params['focus']);
				// invalid values
			}

			unset($focus);
		}

		if (isset($params['src'])) {
			if (isset($_image_path)) {// might not be set in case 'src' is an array
				$hashParams['filemtime'] = filemtime($_image_path);
			}

			if (isset($filename)) {// might not be set in case 'src' is an array
				$hashParams['src'] = $filename;
			}

			if (!$returnData) {// we don't care for format in case we only return whatever we get
				$hashParams['format'] = $format;

				// format specific params
				if ($format === 'jpg' || $format === 'jpeg') {
					if (isset($params['quality'])) {
						$quality = (int)$params['quality'];
					} else {
						$quality = 95;
						// DEFAULT JPG QUALITY - TODO: read from localconf
					}

					$hashParams['quality'] = $quality;
				}
			}

			// TODO: why not allow render text directly on image at start - text would be scaled / cropped with image that way...
			if (isset($params['text'])) {
				throw new Exception('GFX: Text can only be rendered atop canvas (backgroundColor or src param), use watermarks param');
			}
		} elseif (isset($params['text'])) {// text, might have bg color set though
			if (!isset($params['font'])) {
				throw new Exception('GFX: You need to provide font param as well if you want to render text.');
			}

			$params['font'] = Filename::getPathInsideWebrootWithLocalDir($params['font'], $this -> localDir);

			$hashParams['text'] = $params['text'];
			$hashParams['font'] = $params['font'];

			// check for changed filemtime of font - this should run against stat cache if called multiple times through a request
			$hashParams['fontmtime'] = filemtime($params['font']);
			// TODO: handle font file could not be found

			if (!$returnData && isset($format)) {
				$hashParams['format'] = $format;
			}

			if (isset($params['fontSize'])) {
				$hashParams['fontSize'] = $params['fontSize'] = (int)$params['fontSize'];
			}

			if (isset($params['letterSpacing'])) {
				$hashParams['letterSpacing'] = $params['letterSpacing'] = floatval($params['letterSpacing']);
			}

			if (isset($params['fontWeight'])) {
				$hashParams['fontWeight'] = $params['fontWeight'] = (int)$params['fontWeight'];
			}

			if (isset($params['color'])) {
				$hashParams['color'] = $params['color'] = $params['color'] === self::COLOR_TRANSPARENT ? self::COLOR_TRANSPARENT : self::parseHex($params['color'], true);
			}
		}

		if (!isset($params['backgroundColor'])) {
			$params['backgroundColor'] = self::COLOR_TRANSPARENT;
		} elseif ($params['backgroundColor'] !== self::COLOR_TRANSPARENT) {
			$params['backgroundColor'] = self::parseHex($params['backgroundColor'], true);
		}

		$hashParams['backgroundColor'] = $params['backgroundColor'];

		if ($command !== 'pass') {
			if (isset($params['rgb'])) {
				$rgb = self::parseHex($params['rgb'], false);
				// no alpha needed here

				$hashParams['rgb'] = $rgb;
			}

			if ($command === 'resize' || $command === 'convert') {
				$cropscale = !empty($params['cropscale']);

				if (isset($params['fit'])) {

					if ($params['fit'] === true) {
						$fit = true;
					} else {
						$fit = self::parseHex($params['fit'], true);
					}

					$hashParams['fit'] = $fit;
				} else {
					$fit = false;
				}

				$hashParams['cropscale'] = $cropscale;

				if (isset($params['width'])) {
					$targetWidth = intval($params['width']);
					$hashParams['width'] = $targetWidth;
				}

				if (isset($params['height'])) {
					$targetHeight = intval($params['height']);
					$hashParams['height'] = $targetHeight;
				}
			}

			if (($command === 'rotate' || $command === 'convert') && isset($params['degrees'])) {
				$degrees = floatval($params['degrees']);
				while ($degrees >= 360)
					$degrees -= 360;
				while ($degrees < 0)
					$degrees += 360;

				$hashParams['degrees'] = $degrees;

				if (isset($params['rotateScale'])) {
					$rotateScale = floatval($params['rotateScale']);
				} else {
					$rotateScale = 3;
				}

				$hashParams['rotateScale'] = $rotateScale;

				$fastRotate = !empty($params['fastRotate']);
				$hashParams['fastRotate'] = $fastRotate;
			}

			if (isset($params['border']) && isset($params['borderColor'])) {
				$border = (int)$params['border'];
				$borderColor = $params['borderColor'] === self::COLOR_TRANSPARENT ? self::COLOR_TRANSPARENT : self::parseHex($params['borderColor'], true);
			} else {
				$border = 0;
				$borderColor = self::COLOR_TRANSPARENT;
			}

			$hashParams['border'] = $border;
			$hashParams['borderColor'] = $borderColor;

			static $directions = array('Top', 'Right', 'Bottom', 'Left');

			foreach ($directions as $dir) {
				$k = 'padding' . $dir;

				if (!empty($params[$k])) {
					$params[$k] = (int)$params[$k];
					$hashParams[$k] = $params[$k];
				}
			}

			$shadowOffsetX = isset($params['shadowOffsetX']) ? intval($params['shadowOffsetX']) : 0;
			$shadowOffsetY = isset($params['shadowOffsetY']) ? intval($params['shadowOffsetY']) : 0;
			$shadowSpread = isset($params['shadowSpread']) ? intval($params['shadowSpread']) : 0;
			$shadowBlur = isset($params['shadowBlur']) ? max(0, intval($params['shadowBlur'])) : 0;
			$shadowColor = isset($params['shadowColor']) ? self::parseHex($params['shadowColor']) : 0;
			$shadowAlpha = isset($params['shadowAlpha']) ? min(1, max(0, floatval($params['shadowAlpha']))) : 1;

			$hashParams['shadowOffsetX'] = $shadowOffsetX;
			$hashParams['shadowOffsetY'] = $shadowOffsetY;
			$hashParams['shadowSpread'] = $shadowSpread;
			$hashParams['shadowBlur'] = $shadowBlur;
			$hashParams['shadowColor'] = $shadowColor;
			$hashParams['shadowAlpha'] = $shadowAlpha;

			if (!empty($params['watermarks']) && is_array($params['watermarks'])) {
				$hashParams['watermarks'] = $params['watermarks'];
				// TODO: make this resistant to a change of parameter order + parse hash recursive
			}

			if (!empty($params['constraints']) && is_string($params['constraints'])) {
				$hashParams['constraints'] = $params['constraints'];
				// TODO: normalize
			}

			if (!empty($params['trim'])) {
				$hashParams['trim'] = $params['trim'] = true;
			}

			if (!empty($params['trimOnLoad'])) {
				$hashParams['trimOnLoad'] = $params['trimOnLoad'] = true;
			}
		}

		$saveParams = $hashParams;

		if (isset($params['src']) && is_array($params['src'])) {
			$subParams = $this -> executeCommand('convert', $params['src'], NULL, self::RETURN_HASH | self::RETURN_PARAMS);

			$hashParams['src'] = $subParams[0];
			$saveParams['src'] = $subParams[1];
		}

		if (!$returnData || ($returnData !== true && ($returnData & self::RETURN_HASH))) {
			// generate hash for cached filename
			$hash = md5(json_encode($hashParams));
			// json_encode is faster than serialize and produces smaller output, which in return should make md5 faster

			if ($returnData === self::RETURN_HASH) {
				return $hash;
			}

			// decide file extension
			switch ( $format ) {
				case 'png' :
				case 'png24' :
				case 'png8' :
					$extension = 'png';
					$mimetype = 'image/png';
					break;

				case 'jpg' :
				case 'jpeg' :
					$extension = 'jpg';
					$mimetype = 'image/jpeg';
					break;

				case 'gif' :
					$extension = 'gif';
					$mimetype = 'image/gif';
					break;
			}

			$fn = $hash . ((isset($targetWidth) || isset($targetHeight)) ? '_' . (isset($targetWidth) ? $targetWidth : '') . 'x' . (isset($targetHeight) ? $targetHeight : '') : '') . '.' . $extension;
		}

		if ($returnData === self::RETURN_PARAMS) {
			return $saveParams;
		} else if ($returnData === (self::RETURN_HASH | self::RETURN_PARAMS)) {
			return array($hash, $saveParams);
		}

		$mode = isset($params['mode']) ? $params['mode'] : $this -> mode;

		// TODO: allow no_cache query parameter for installation = developement localconf.ini.php setting

		// these aren't stored in hashparams, as saving noCache params wouldn't make sense
		// no_cache url parameter is only used in development mode
		$noCache = !empty($params['noCache']) || (!empty($_GET['no_cache']) && Config::key('mode', 'installation') === 'development');
		$noCacheSave = !empty($params['noCacheSave']) || (!empty($_GET['no_cache_save']) && Config::key('mode', 'installation') === 'development');

		// check for cached file existence
		if ($returnData || !$this -> skipGenerate) {
			if ($returnData || $noCache || !$this -> cache -> exists($fn)) {
				if (!$returnData && !$noCacheSave) {
					$this -> cache -> lock($fn);
				}

				try {
					if ($returnData || $noCache || !$this -> cache -> exists($fn)) {// check again, might have been generated in the meantime
						if (isset($params['src']) && is_array($params['src'])) {
							$params['im'] = $this -> executeCommand('convert', $params['src'], NULL, true);
						}

						if (!empty($params['im'])) {
							$im = $params['im'];

							if ($this -> useImagick) {// Imagick
								$imageSize = $im -> getImageGeometry();

								$oldWidth = $imageSize['width'];
								$oldHeight = $imageSize['height'];

								$backgroundColorPixel = self::toImagickColor(self::COLOR_TRANSPARENT, true);
							} else {// GD
								$oldWidth = imagesx($im);
								$oldHeight = imagesy($im);
							}
						} else {
							if ($this -> useImagick) {
								// Might throw ImagickException on error
								$im = self::getImagick();

								$backgroundColorPixel = self::toImagickColor($params['backgroundColor'], true);

								// TODO: do we need this in case of no loaded image? is it respected in case of loaded image?
								self::setIMBackgroundColor($im, $backgroundColorPixel);

								if (isset($_image_path)) {
									if (!$im -> readImage($_image_path)) {
										throw new Exception("GFX: file doesn't exist, isn't readable, image is broken, or image type is not supported: '{$_image_path}', using Imagick");
									}

									// convert CMYK to sRGB
									if ($im -> getImageColorspace() === $this -> getConstant('COLORSPACE_CMYK')) {
										// The following is buggy (inverts colors) with Imagick 3.0.0RC2, so we use $im->profileImage instead which works
										// $im->setImageColorspace( Imagick::COLORSPACE_sRGB );

										$im -> profileImage('icc', file_get_contents(__DIR__ . '/sRGB_v4_ICC_preference.icc'));

										// profile is stripped again later on, so no need to strip it here
									}

									$imageSize = $im -> getImageGeometry();

									$oldWidth = $imageSize['width'];
									$oldHeight = $imageSize['height'];

								} else {

									if (isset($params['text'])) {
										if (trim($params['text']) === '') {
											throw new Exception('Unable to render empty text');
										}

										if (self::$useGDForText) {// fall back to GD on OSX
											if (!self::$gdAvailable) {
												throw new Exception('GFX: Unable to fall back to GD on OSX, as it does not seem to be available. Please install/enable GD for php.');
											}

											$textImage = $this -> renderGDText($params);

											// save alpha information
											imagealphablending($textImage, false);
											imagesavealpha($textImage, true);

											// FIXME: find more efficient way than intermediate png encoding
											ob_start();
											imagepng($textImage, NULL, 0, PNG_NO_FILTER);
											$imageData = ob_get_clean();
											imagedestroy($textImage);
											unset($textImage);

											$im -> readImageBlob($imageData);
											unset($imageData);

										} else {
											$draw = self::getImagickDraw();
											$draw -> setFont($params['font']);
											// hangs apache on OSX

											$draw -> setFontSize($params['fontSize']);

											if (!empty($params['letterSpacing']) && !$this -> useAdvancedLetterSpacing) {
												$draw -> setTextKerning($params['letterSpacing']);
												// actually sets constant kerning instead of real letterspacing/tracking, which might look rather ugly
											}

											if (isset($params['fontWeight'])) {
												$draw -> setFontWeight($params['fontWeight']);
											}

											if (isset($params['color'])) {
												$draw -> setFillColor(self::toImagickColor($params['color'], true));
											}

											// $draw->setTextUndercolor( $backgroundColorPixel );

											// height multiplicator of 1.1 in the following code was chosen arbitrarely to help account for letters outside of normal lineheight like umlauts

											// TODO: make configurable
											$draw -> setStrokeAntialias(true);
											$draw -> setTextAntialias(true);

											if ($this -> useAdvancedLetterSpacing && !empty($params['letterSpacing'])) {
												$metrics = $this -> getFontMetrics($params['font'], $params['fontSize'], $params['text'], isset($params['fontWeight']) ? $params['fontWeight'] : NULL, !empty($params['letterSpacing']) ? $params['letterSpacing'] : NULL);
												$temp_x = -$metrics['boundingBox']['x1'];

												$textArr = preg_split('//u', $params['text'], -1, PREG_SPLIT_NO_EMPTY);

												for ($i = 0, $ii = count($textArr); $i < $ii; $i++) {
													if ($i > 0) {
														$space = $params['letterSpacing'] * $params['fontSize'] / 1000 + $this -> getKerning($params['font'], $params['fontSize'], $textArr[$i - 1], $textArr[$i]);
														$temp_x += $space;
													}

													// $bbox = imagettftext($textImage, $params['fontSize'] * self::$gdFontUnitMultiplier, 0, round($temp_x), -$metrics['boundingBox']['y1'], $color, $params['font'], $params['text'][$i]);
													$oldLocale = setlocale(LC_ALL, 'C');
													
													try {
														$draw -> annotation($temp_x, $metrics['ascender'] * 1.1, $textArr[$i]);
														// TODO: instead of ascender use bbox of whole text string
													} catch(Exception $e) {
														setlocale(LC_ALL, $oldLocale);
														throw($e);
													}
													
													setlocale(LC_ALL, $oldLocale);
													
													$temp_x += $this -> getAdvanceWidth($params['font'], $params['fontSize'], $textArr[$i]);
												}

											} else {
												$metrics = $im -> queryFontMetrics($draw, $params['text']);
												
												$oldLocale = setlocale(LC_ALL, 'C');
												
												try {
													$draw -> annotation(0, $metrics['ascender'] * 1.1, $params['text']);
													// TODO: instead of ascender use bbox
												} catch(Exception $e) {
													setlocale(LC_ALL, $oldLocale);
													throw $e;
												}
												
												setlocale(LC_ALL, $oldlocale);
											}

											// TODO: find out why newImage is sometimes too small (why we add +5 to width), especially with small fonts (8px etc)
											// for height, this is to account for characters reaching outside of normal lineheight (e.g. umlauts)
											$im -> newImage(ceil($metrics['textWidth']) + 5, ceil($metrics['textHeight'] * 1.2), $backgroundColorPixel);

											// this might throw an exception for a non conforming drawing operation (exception code 460)
											// FIXME: debug why
											$im -> drawImage($draw);

											$draw -> destroy();
											unset($draw);
										}

										$im -> trimImage(0);
										$im -> setImagePage(0, 0, 0, 0);

										$imageSize = $im -> getImageGeometry();

										$oldWidth = $imageSize['width'];
										$oldHeight = $imageSize['height'];
									} elseif (isset($params['width']) && isset($params['height'])) {
										$im -> newImage($params['width'], $params['height'], $backgroundColorPixel);

										$oldWidth = $params['width'];
										$oldHeight = $params['height'];
									}
									// TODO: else?
								}

							} else {// GD
								if (isset($_image_path)) {
									// create image object - GIF support was removed from GD from version 1.6 onwards and re-added in 2.0
									switch ( $_image_data[ 2 ] ) {
										case IMAGETYPE_JPEG :
											$im = imagecreatefromjpeg($_image_path);
											break;

										case IMAGETYPE_PNG :
											$im = imagecreatefrompng($_image_path);
											break;

										case IMAGETYPE_GIF :
											$im = imagecreatefromgif($_image_path);
											break;

										// TODO: would be nice to support TIFF

										default :
											throw new Exception("GFX: broken image or unsupported image type: '{$_image_data[2]}': '{$_image_path}'.");
											break;
									}

									$oldWidth = $_image_data[0];
									$oldHeight = $_image_data[1];
								} elseif (isset($params['text'])) {
									$im = $this -> renderGDText($params);

									$oldWidth = imagesx($im);
									$oldHeight = imagesy($im);
								} elseif (!empty($params['sizeByWatermarks'])) {
									// prevent generating background image which would have to be thrown away again
									$oldWidth = 0;
									$oldHeight = 0;
								} else {
									$oldWidth = $params['width'];
									$oldHeight = $params['height'];
								}

								$im = $this -> GDHandleBackground(isset($im) ? $im : NULL, $oldWidth, $oldHeight, $params, isset($_image_data) ? $_image_data : NULL);

							}
						}

						// isset check prevents problems with sizeByWatermarks + trimOnLoad with GD
						if (isset($im) && !empty($params['trimOnLoad'])) {
							$this -> trimImage($im);

							if ($this -> useImagick) {
								$imageSize = $im -> getImageGeometry();

								$oldWidth = $imageSize['width'];
								$oldHeight = $imageSize['height'];
							} else {
								$oldWidth = imagesx($im);
								$oldHeight = imagesy($im);
							}
						}

						// watermarks in case of watermarksFirst setting
						if (!empty($params['watermarks']) && !empty($params['watermarksFirst'])) {
							$im = $this -> addWatermarks($im, $params);
						}

						// TODO: make it possible to call with command 'convert' and only pass degrees
						if ((isset($targetWidth) && $targetWidth != $oldWidth) || (isset($targetHeight) && $targetHeight != $oldHeight)) {
							$cropOffsetX = 0;
							$cropOffsetY = 0;

							if (isset($targetWidth) && isset($targetHeight)) {
								if ($cropscale) {
									$nw = (float)$targetHeight / (float)$oldHeight * (float)$oldWidth;

									if ($nw > $targetWidth) {// crop width
										$cropX = ($nw - $targetWidth) * ($oldWidth / $nw);
										// total crop x on original image

										if (!empty($params['focus']['x'])) {
											$cropOffsetX = intval($this -> getCropOffset($cropX, $oldWidth, $params['focus']['x']));
										} else {
											$cropOffsetX = intval($cropX / 2);
										}

										$oldWidth = round($oldWidth - $cropX);
									} else if ($nw < $targetWidth) {// crop height
										$nh = (float)$targetWidth / (float)$oldWidth * (float)$oldHeight;
										$cropY = ($nh - $targetHeight) * ($oldHeight / $nh);

										if (!empty($params['focus']['y'])) {
											$cropOffsetY = intval($this -> getCropOffset($cropY, $oldHeight, $params['focus']['y']));
										} else {
											$cropOffsetY = intval($cropY / 2);
										}

										$oldHeight = round($oldHeight - $cropY);
									}
									// else: no cropping needed
								} else if ($fit !== false) {// target is boundary-box

									$scaleX = (float)$targetWidth / (float)$oldWidth;
									$scaleY = (float)$targetHeight / (float)$oldHeight;

									$dst_w = $targetWidth;
									$dst_h = $targetHeight;
									$dst_x = 0;
									$dst_y = 0;

									if ($scaleX > $scaleY) {// whitespace left and right
										$dst_w = intval($oldWidth * (float)$scaleY);

										if ($fit === true) {
											$targetWidth = $dst_w;
										} else {
											$dst_x = intval(($targetWidth - $dst_w) / 2);
										}
									} else {// whitespace above and below
										$dst_h = intval($oldHeight * (float)$scaleX);

										if ($fit === true) {
											$targetHeight = $dst_h;
										} else {
											$dst_y = intval(($targetHeight - $dst_h) / 2);
										}
									}
								} else {// resize to width & height
									// ?
								}
							} else {
								if (isset($targetWidth)) {// resize to width
									$targetHeight = $targetWidth / $oldWidth * $oldHeight;
								} else {// resize to height
									$targetWidth = $targetHeight / $oldHeight * $oldWidth;
								}
							}

							if (empty($dst_w) || empty($dst_h)) {
								if (empty($targetWidth) || empty($targetHeight)) {
									throw new Exception('Zero or less width or height given.');
								}

								$dst_w = $targetWidth;
								$dst_h = $targetHeight;
							}

							if (!isset($dst_x) || !isset($dst_y)) {
								$dst_x = 0;
								$dst_y = 0;
							}

							if (empty($degrees)) {
								// TODO: support padding

								if ($border > 0) {
									$targetWidth += $border * 2;
									$targetHeight += $border * 2;

									$dst_x += $border;
									$dst_y += $border;
								}

								// FIXME: optimize: we don't need to create an extra image when we're only resizing without fit and/or border

								if (!$this -> useImagick) {// GD Code
									$newImage = self::imagecreatetruecolor($targetWidth, $targetHeight, self::COLOR_TRANSPARENT);

									if ($border !== 0) {
										$borderCol = self::toGDColor($newImage, $borderColor, true);
										// imagecolorallocatealpha( $newImage, $borderColor >> 16, ( $borderColor >> 8 ) & 0xFF, $borderColor & 0xFF, 0 );
										imagesetthickness($newImage, $border);

										$bh = $border * 0.5;

										imagealphablending($newImage, false);
										imagerectangle($newImage, $bh, $bh, $targetWidth - $bh - 1, $targetHeight - $bh - 1, $borderCol);
										// imagefill( $newImage, 0, 0, $borderCol );

										// reset thickness
										imagesetthickness($newImage, 1);

										imagecolordeallocate($newImage, $borderCol);
									}

									// fill image to prevent black background when doing operation "fit"
									if ($fit && !is_bool($fit)) {
										$fillColor = self::toGDColor($newImage, $fit, true);
										// imagecolorallocatealpha( $newImage, $fit >> 16, ( $fit >> 8 ) & 0xFF, $fit & 0xFF, 0 );

										imagealphablending($newImage, false);

										if ($border > 0) {
											imagefilledrectangle($newImage, $border, $border, $targetWidth - $border - 1, $targetHeight - $border - 1, $fillColor);
										} else {
											imagefill($newImage, 0, 0, $fillColor);
										}

										imagecolordeallocate($newImage, $fillColor);
									}

								}

								$imageWidth = $targetWidth;
								$imageHeight = $targetHeight;
							}

							// TODO: fit&border in case of rotate shouldn't use dup code
							if ($command === 'convert' && !empty($degrees)) {// if rotate: crop -> rotate -> resize
								$scaleW = $oldWidth / $dst_w;
								$scaleH = $oldHeight / $dst_h;

								$bw = ($border > 0 ? $border * $scaleW : 0);
								$bh = ($border > 0 ? $border * $scaleH : 0);

								// first crop
								if ($this -> useImagick) {
									if ($fit && !is_bool($fit)) {
										self::setIMBackgroundColor($im, $this -> toImagickColor($fit, true));
									} else {
										if ($border < 0) {
											self::setIMBackgroundColor($im, $this -> toImagickColor($borderColor, true));
										} else {
											self::setIMBackgroundColor($im, self::COLOR_TRANSPARENT);
										}
									}

									// TODO: shouldn't border come after resize? ...
									if ($border > 0) {
										$im -> borderImage($this -> toImagickColor($borderColor, true), $bw, $bh);
									}

									if ($rotateScale > 1) {
										$tw = round($oldWidth * $rotateScale);
										$th = round($oldHeight * $rotateScale);

										// TODO: correct colorspace
										$im -> resizeImage($tw, $th, $this -> getConstant('FILTER_LANCZOS'), 1);
									}

									// TODO: support padding

									$im -> rotateImage(self::COLOR_TRANSPARENT, $degrees);

									$imageSize = $im -> getImageGeometry();

									$imageWidth = $oldWidth = $imageSize['width'];
									$imageHeight = $oldHeight = $imageSize['height'];
								} else {// FIXME: merge dup code, use correct way of bordering
									$tempImage = self::imagecreatetruecolor($oldWidth + 2 * $bw, $oldHeight + 2 * $bh, self::COLOR_TRANSPARENT);

									if ($border !== 0) {
										$borderCol = imagecolorallocatealpha($tempImage, $borderColor>>16, ($borderColor>>8) & 0xFF, $borderColor & 0xFF, 0);
										imagealphablending($tempImage, false);
										imagefill($tempImage, 0, 0, $borderCol);
									}

									// TODO: support padding
									if ($fit && !is_bool($fit)) {
										$fillColor = imagecolorallocatealpha($tempImage, $fit>>16, ($fit>>8) & 0xFF, $fit & 0xFF, 0);
										imagealphablending($tempImage, false);

										if ($border > 0) {
											imagefilledrectangle($tempImage, $bw + 1, $bh + 1, $targetWidth - $bw - 1, $targetHeight - $bh - 1, $fillColor);
										} else {
											imagefill($tempImage, 0, 0, $fillColor);
										}
									}

									imagealphablending($tempImage, true);
									imagecopy($tempImage, $im, $bw, $bh, isset($cropOffsetX) ? $cropOffsetX : 0, isset($cropOffsetY) ? $cropOffsetY : 0, $oldWidth, $oldHeight);

									imagedestroy($im);
									unset($im);

									// upscale

									if ($rotateScale <= 1) {
										$upscaledTempImage = $tempImage;
									} else {
										$tw = round($oldWidth * $rotateScale);
										$th = round($oldHeight * $rotateScale);
										$upscaledTempImage = self::imagecreatetruecolor($tw, $th, self::COLOR_TRANSPARENT);

										$this -> GDCopyResampled($upscaledTempImage, $tempImage, 0, 0, 0, 0, $oldWidth * $rotateScale, $oldHeight * $rotateScale, $oldWidth, $oldHeight);

										imagedestroy($tempImage);
										unset($tempImage);
									}

									// rotate
									if ($fastRotate) {// only faster if php imagerotate is actually available
										$im = imagerotate($upscaledTempImage, 360 - $degrees, -1);
									} else {
										$im = imagerotateEquivalent::rotate($upscaledTempImage, 360 - $degrees, -1);
									}

									// imagealphablending( $im, false );

									imagedestroy($upscaledTempImage);
									unset($upscaledTempImage);

									$imageWidth = $oldWidth = imagesx($im);
									$imageHeight = $oldHeight = imagesy($im);
								}

								$temp = imagerotateEquivalent::getRotatedDimension($oldWidth, $oldHeight, deg2rad($degrees));

								$origNewWidth = $temp[2];
								$origNewHeight = $temp[5];

								if (isset($cropOffsetX))
									unset($cropOffsetX);
								if (isset($cropOffsetY))
									unset($cropOffsetY);

								$diffW = round(($imageWidth - $origNewWidth) / $scaleW);
								$diffH = round(($imageHeight - $origNewHeight) / $scaleH);

								if (!$this -> useImagick) {
									// TODO: don't create separately for rotated and not-rotated version
									$newImage = self::imagecreatetruecolor($targetWidth + $diffW, $targetHeight + $diffH, self::COLOR_TRANSPARENT);
								}

								$dst_w += $diffW;
								$dst_h += $diffH;
							}

							if ($this -> useImagick) {
								if (isset($cropOffsetX) || isset($cropOffsetY)) {
									$im -> cropImage($oldWidth, $oldHeight, $cropOffsetX, $cropOffsetY);
									// $im->setImagePage(0, 0, 0, 0); // TODO: might be needed for gif images - test!
								}
								if (empty($degrees) && $fit && !is_bool($fit)) {
									$fitColor = $this -> toImagickColor($fit, true);
									// $im->setBackgroundColor( $fitColor ); // TODO: should probably only be used for the added region
									self::setIMBackgroundColor($im, $fitColor);
								}

								// $im->setColorspace( Imagick::COLORSPACE_SRGB );
								// $im->gammaImage( 1.0, Imagick::CHANNEL_ALL & ~Imagick::CHANNEL_ALPHA );
								// TODO: bestfit bad option?
								$im -> resizeImage($dst_w, $dst_h, $this -> getConstant('FILTER_LANCZOS'), 1);

								if ($dst_x !== 0 || $dst_y !== 0 || $targetWidth !== $dst_w || $targetHeight !== $dst_h) {
									$im -> extentImage($targetWidth, $targetHeight, $dst_x * self::$imagickExtentMultiplier, $dst_y * self::$imagickExtentMultiplier);
								}

								if (empty($degrees) && $border > 0) {
									$im -> borderImage($this -> toImagickColor($borderColor, true), $border, $border);
								}

								// $im->gammaImage( 2.2, Imagick::CHANNEL_ALL & ~Imagick::CHANNEL_ALPHA );

								// TODO: repage?

								// $newImage->compositeImage( $im, $this->getConstant( 'COMPOSITE_BLEND' ), $dst_x, $dst_y );
								// $newImage->compositeImage( $im, $this->getConstant( 'COMPOSITE_OVER' ), $dst_x, $dst_y );

								// $im->clear();
								// unset( $im );

								// $im = $newImage;
								// unset( $newImage );

								$imageSize = $im -> getImageGeometry();
								$imageWidth = $imageSize['width'];
								$imageHeight = $imageSize['height'];

								// FIXME
								// $im->extentImage( $targetWidth, $targetHeight, -$dst_x, -$dst_y );

							} else {
								imagealphablending($newImage, true);
								// we need to enable alphablending (in case old image has alpha channel, we would get bad output otherwise)

								if (!$this -> GDCopyResampled($newImage, $im, $dst_x, $dst_y, isset($cropOffsetX) ? $cropOffsetX : 0, isset($cropOffsetY) ? $cropOffsetY : 0, $dst_w, $dst_h, $oldWidth, $oldHeight)) {
									//					if (! imagecopyresized($newImage, $im, $dst_x, $dst_y, $cropOffsetX, $cropOffsetY, $dst_w, $dst_h, $oldWidth, $oldHeight)) {
									imagedestroy($newImage);
									imagedestroy($im);

									throw new Exception("GFX: Image resizing failed.");
								}

								imagedestroy($im);
								unset($im);

								$im = $newImage;
								unset($newImage);

								// imagefilter($im, IMG_FILTER_BRIGHTNESS, -255);

								// FIXME: remove unneeded imagesx/imagesy calls
								$imageWidth = imagesx($im);
								$imageHeight = imagesy($im);
							}
						} elseif (empty($params['sizeByWatermarks']) || !empty($params['watermarksFirst'])) {
							// padding support
							if ($this -> useImagick) {
								$extent = false;
								$ex = 0;
								$ey = 0;

								if (!empty($params['paddingTop'])) {
									$oldHeight += $params['paddingTop'];
									$ey = $params['paddingTop'];
									$extent = true;
								}

								if (!empty($params['paddingRight'])) {
									$oldWidth += $params['paddingRight'];
									$extent = true;
								}

								if (!empty($params['paddingBottom'])) {
									$oldHeight += $params['paddingBottom'];
									$extent = true;
								}

								if (!empty($params['paddingLeft'])) {
									$oldWidth += $params['paddingLeft'];
									$ex = $params['paddingLeft'];
									$extent = true;
								}

								if ($extent) {
									$im -> extentImage($oldWidth, $oldHeight, $ex * self::$imagickExtentMultiplier, $ey * self::$imagickExtentMultiplier);
								}
							}

							if (isset($border) && $border > 0) {
								if ($this -> useImagick) {// Imagick
									/* @var $im Imagick */
									$im -> borderImage($this -> toImagickColor($borderColor, true), $border, $border);
								} else {// GD
									// TOOO: use correct way of bordering
									$targetWidth = $oldWidth + $border * 2;
									$targetHeight = $oldHeight + $border * 2;

									$newImage = self::imagecreatetruecolor($targetWidth, $targetHeight, self::COLOR_TRANSPARENT);

									$borderCol = imagecolorallocatealpha($newImage, $borderColor>>16, ($borderColor>>8) & 0xFF, $borderColor & 0xFF, 0);
									imagealphablending($newImage, false);
									imagefill($newImage, 0, 0, $borderCol);

									$fillColor = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
									// imagealphablending already disabled heere
									imagefilledrectangle($newImage, $border + 1, $border + 1, $targetWidth - $border - 1, $targetHeight - $border - 1, $fillColor);
									imagecolordeallocate($newImage, $fillColor);

									imagealphablending($newImage, true);
									imagecopy($newImage, $im, $border, $border, 0, 0, $oldWidth, $oldHeight);
									imagedestroy($im);
									$im = $newImage;
								}
							}

							if (!empty($degrees)) {
								// DUP
								if ($this -> useImagick) {
									$im -> rotateImage(self::COLOR_TRANSPARENT, $degrees);

									$imageSize = $im -> getImageGeometry();
									$imageWidth = $oldWidth = $imageSize['width'];
									$imageHeight = $oldHeight = $imageSize['height'];
								} else {
									$newImage = imagerotateEquivalent::rotate($im, 360 - $degrees, -1);

									imagedestroy($im);
									unset($im);

									$im = $newImage;
									unset($newImage);

									$imageWidth = $oldWidth = imagesx($im);
									$imageHeight = $oldHeight = imagesy($im);
								}
								// DUP end
							}
						}

						if (empty($params['sizeByWatermarks']) || !empty($params['watermarksFirst'])) {
							if ($command !== 'pass' && isset($rgb)) {

								if ($this -> useImagick) {
									// TODO: make it possible to use a different opacity
									$im -> colorizeImage(self::toImagickColor($rgb, false), 1.0);
								} else {

									imagealphablending($im, false);

									$rgbComponents = self::getComponents($rgb, false);
									// no alpha needed here

									// TODO: opacity? (like with imagick)
									imagefilter($im, IMG_FILTER_COLORIZE, $rgbComponents['r'], $rgbComponents['g'], $rgbComponents['b']);
								}
							}

							if ($command === 'rotate' && !empty($degrees)) {// in the case of 'convert' we do the rotation in the resizing code already
								// DUP
								if ($this -> useImagick) {
									$im -> rotateImage(self::COLOR_TRANSPARENT, $degrees);

									$imageSize = $im -> getImageGeometry();
									$imageWidth = $oldWidth = $imageSize['width'];
									$imageHeight = $oldHeight = $imageSize['height'];
								} else {
									$newImage = imagerotateEquivalent::rotate($im, 360 - $degrees, -1);

									imagedestroy($im);
									unset($im);

									$im = $newImage;
									unset($newImage);

									$imageWidth = $oldWidth = imagesx($im);
									$imageHeight = $oldHeight = imagesy($im);
								}
								// DUP end
							}

							// shadow stuff
							// TODO: shadow alpha
							if ($command !== 'pass' && ($shadowSpread !== 0 || $shadowBlur !== 0)) {
								if ($this -> useImagick) {
									$shadow = $this -> getClone($im);
									$shadow -> setImageBackgroundColor($this -> toImagickColor($shadowColor, true));

									$shadow -> shadowImage($shadowAlpha, 0, $shadowOffsetX, $shadowOffsetY);

									$shadow -> setGravity($this -> getConstant('GRAVITY_CENTER'));

									// TODO: correct colorspace
									$shadow -> resizeImage($imageWidth + $shadowSpread * 2, $imageHeight + $shadowSpread * 2, $this -> getConstant('FILTER_UNDEFINED'), 1);

									$shadow -> blurImage(0, $shadowBlur);

								} else {
									$shw = ceil($imageWidth + $shadowSpread * 2 + $shadowBlur);
									$shh = ceil($imageHeight + $shadowSpread * 2 + $shadowBlur);

									$sw = round($shadowSpread + $shadowBlur * 0.5);
									$shx = $sw - $shadowOffsetX;
									$shy = $sw - $shadowOffsetY;

									$addX = max(0, -$shx);
									$addY = max(0, -$shy);

									$shadowImage = self::imagecreatetruecolor($shw, $shh);
									imagealphablending($shadowImage, false);

									$ct = $shw * $shh;

									$imgArr = new SplFixedArray($ct);

									$rs = round($shadowSpread - $shadowBlur * 0.5);

									for ($x = 0; $x < $shw; $x++) {
										for ($y = 0; $y < $shh; $y++) {
											$n = $y * $shw + $x;
											$ox = $x - $shx;
											$oy = $y - $shy;

											if ($ox < 0 || $oy < 0 || $ox >= $imageWidth || $oy >= $imageHeight) {
												$imgArr[$n] = 127;
											} else {
												$imgArr[$n] = imagecolorat($im, $ox, $oy)>>24;
											}
										}
									}

									// spread
									$nArr = new SplFixedArray($ct);
									for ($i = 0; $i < $rs; $i++) {
										// corners
										$nArr[0] = min($imgArr[0], $imgArr[1], $imgArr[$shw], $imgArr[$shw + 1]);
										// top left
										$n = $shw - 1;
										// top right
										$nArr[$n] = min($imgArr[$n - 1], $imgArr[$n], $imgArr[$n + $shw - 1], $imgArr[$n + $shw]);
										$n = ($shh - 1) * $shw;
										// left bottom
										$nArr[$n] = min($imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n], $imgArr[$n + 1]);
										$n = $shh * $shw - 1;
										// right bottom
										$nArr[$n] = min($imgArr[$n - $shw - 1], $imgArr[$n - $swh], $imgArr[$n - 1], $imgArr[$n]);

										// first + last col
										for ($y = 1; $y < ($shh - 1); $y++) {
											$n = $y * $shw;
											// first col
											$nArr[$n] = min($imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n], $imgArr[$n + 1], $imgArr[$n + $shw], $imgArr[$n + $shw + 1]);

											$n = $n + $shw - 1;
											// last
											$nArr[$n] = min($imgArr[$n - $shw - 1], $imgArr[$n - $shw], $imgArr[$n - 1], $imgArr[$n], $imgArr[$n + $shw - 1], $imgArr[$n + $shw]);
										}

										for ($x = 1; $x < ($shw - 1); $x++) {
											// first row
											$nArr[$x] = min($imgArr[$x - 1], $imgArr[$x], $imgArr[$x + 1], $imgArr[$x + $shw - 1], $imgArr[$x + $shw], $imgArr[$x + $shw + 1]);
											for ($y = 1; $y < ($shh - 1); $y++) {
												$n = $y * $shw + $x;
												$nArr[$n] = min($imgArr[$n - $shw - 1], $imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n - 1], $imgArr[$n], $imgArr[$n + 1], $imgArr[$n + $shw - 1], $imgArr[$n + $shw], $imgArr[$n + $shw + 1]);
											}
											// last row
											$n = $y * $shw + $x;
											$nArr[$n] = min($imgArr[$n - $shw - 1], $imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n - 1], $imgArr[$n], $imgArr[$n + 1]);
										}

										$tmp = $imgArr;
										$imgArr = $nArr;
										$nArr = $tmp;
									}

									// blur
									for ($i = 0; $i < $shadowBlur; $i++) {
										// corners
										$nArr[0] = round((min($imgArr[1], $imgArr[$shw], $imgArr[$shw + 1]) + $imgArr[0]) * 0.5);
										// top left
										$n = $shw - 1;
										// top right
										$nArr[$n] = round((min($imgArr[$n - 1], $imgArr[$n + $shw - 1], $imgArr[$n + $shw]) + $imgArr[$n]) * 0.5);
										$n = ($shh - 1) * $shw;
										// left bottom
										$nArr[$n] = round((min($imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n + 1]) + $imgArr[$n]) * 0.5);
										$n = $shh * $shw - 1;
										// right bottom
										$nArr[$n] = round((min($imgArr[$n - $shw - 1], $imgArr[$n - $swh], $imgArr[$n - 1]) + $imgArr[$n]) * 0.5);

										// first + last col
										for ($y = 1; $y < ($shh - 1); $y++) {
											$n = $y * $shw;
											// first col
											$nArr[$n] = round((min($imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n + 1], $imgArr[$n + $shw], $imgArr[$n + $shw + 1]) + $imgArr[$n]) * 0.5);

											$n = $n + $shw - 1;
											// last
											$nArr[$n] = round((min($imgArr[$n - $shw - 1], $imgArr[$n - $shw], $imgArr[$n - 1], $imgArr[$n + $shw - 1], $imgArr[$n + $shw]) + $imgArr[$n]) * 0.5);
										}

										for ($x = 1; $x < ($shw - 1); $x++) {
											// first row
											$nArr[$x] = round((min($imgArr[$x - 1], $imgArr[$x + 1], $imgArr[$x + $shw - 1], $imgArr[$x + $shw], $imgArr[$x + $shw + 1]) + $imgArr[$x]) * 0.5);
											for ($y = 1; $y < ($shh - 1); $y++) {
												$n = $y * $shw + $x;
												$nArr[$n] = round((min($imgArr[$n - $shw - 1], $imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n - 1], $imgArr[$n + 1], $imgArr[$n + $shw - 1], $imgArr[$n + $shw], $imgArr[$n + $shw + 1]) + $imgArr[$n]) * 0.5);
											}
											// last row
											$n = $y * $shw + $x;
											$nArr[$n] = round((min($imgArr[$n - $shw - 1], $imgArr[$n - $shw], $imgArr[$n - $shw + 1], $imgArr[$n - 1], $imgArr[$n + 1]) + $imgArr[$n]) * 0.5);
										}

										$tmp = $imgArr;
										$imgArr = $nArr;
										$nArr = $tmp;
									}

									$cr = $shadowColor>>16;
									$cg = ($shadowColor>>8) & 0xFF;
									$cb = $shadowColor & 0xFF;
									$colors = new SplFixedArray(128);

									for ($i = 0; $i < 128; $i++) {
										$colors[$i] = imagecolorallocatealpha($shadowImage, $cr, $cg, $cb, $i);
									}

									for ($x = 0; $x < $shw; $x++) {
										for ($y = 0; $y < $shh; $y++) {
											$n = $y * $shw + $x;
											imagesetpixel($shadowImage, $x, $y, $colors[$imgArr[$n]]);
										}
									}

									imagealphablending($shadowImage, true);

									if ($shx < 0 || $shy < 0) {
										$addX = max(0, -$shx);
										$addY = max(0, -$shy);
										$tmpImg = self::imagecreatetruecolor($shw + $addX, $shh + $addY, self::COLOR_TRANSPARENT);
										// TODO: copy orig image
									} else {
										imagecopy($shadowImage, $im, $shx, $shy, 0, 0, $imageWidth, $imageHeight);
									}

									imagedestroy($im);
									unset($im);
									$im = $shadowImage;

									$imageWidth = imagesx($im);
									$imageHeight = imagesy($im);
								}
							}
						}

						// watermark
						if (!empty($params['watermarks']) && empty($params['watermarksFirst'])) {
							$im = $this -> addWatermarks($im, $params);
						}

						if (!empty($params['trim'])) {
							$this -> trimImage($im);

						}

						if ($returnData) {
							return $im;
						} else {
							if ($this -> useImagick) {
								// TODO
								$im -> stripImage();

								// use
								// identify -list format
								// to get a list of locally supported formats ; for web we're only interested in jpg, png, png8 and gif
								$im -> setImageFormat($format);

								switch ( $extension ) {
									case 'png' :
										if ($format !== 'png8') {
											$im -> setImageDepth(8);
											// otherwhise we get 16bit per channel png
										}

										$im -> setImageCompressionQuality(0);
										// lowest filesize png
										break;
									case 'jpg' :
										$im -> setImageCompression($this -> getConstant('COMPRESSION_JPEG'));
										$im -> setImageCompressionQuality($quality);
										break;
									case 'gif' :
										break;
								}

								$filedata = $im -> getImageBlob();

								$im -> destroy();
								unset($im);
							} else {
								if (isset($format) && ($format == 'png8' || $format == 'gif')) {
									imagetruecolortopalette($im, true, 256);
									// DITHER ON
								}

								ob_start();
								switch ( $extension ) {
									case 'png' :
										imagealphablending($im, false);
										imagesavealpha($im, true);

										imagepng($im, NULL, 9, PNG_ALL_FILTERS);
										break;

									case 'jpg' :
										imagejpeg($im, NULL, $quality);
										break;

									case 'gif' :
										imagealphablending($im, false);
										imagesavealpha($im, true);

										imagegif($im);
										break;
								}

								$filedata = ob_get_clean();

								imagedestroy($im);
								unset($im);
							}

							if (!$noCacheSave) {
								$this -> cache -> set($fn, $filedata);
							}
						}

					}
				} catch ( Exception $e ) {
					if (!$returnData && !$noCacheSave) {
						$this -> cache -> unlock($fn);
					}

					throw ($e);
				}

				if (!$returnData && !$noCacheSave) {
					$this -> cache -> unlock($fn);
				}
			} else {// file exists, get width / height from it
				if ($mode !== self::MODE_SEND || ($job !== NULL && (!$job -> width || !$job -> height))) {
					if ($this -> useImagick) {
						$im = self::getImagick($this -> cache -> convertKey($fn));
						$imageSize = $im -> getImageGeometry();
						$imageWidth = $imageSize['width'];
						$imageHeight = $imageSize['height'];
					} else {
						$sizes = getimagesize($this -> cache -> convertKey($fn));
						$imageWidth = $sizes[0];
						$imageHeight = $sizes[1];
					}

				}
			}

			if ($job !== NULL && ((!empty($imageWidth) && $job -> width != $imageWidth) || (!empty($imageHeight) && $job -> height != $imageHeight))) {
				if (!empty($imageWidth)) {
					$job -> width = $imageWidth;
				}

				if (!empty($imageHeight)) {
					$job -> height = $imageHeight;
				}

				$job -> save();
			}

			if ($mode !== self::MODE_SEND) {
				$src = $this -> cache -> getUrlForKey($fn);
			}
		}

		if (!$returnData && $mode !== self::MODE_SEND) {// even in case of skipGenerate not set we need to make sure we got a job for the file
			$jobExists = (bool)($job = $this -> storage -> selectFirstRecord('RCGFXJob', array('fields' => '*', 'where' => array('hash', '=', '%1$s'), 'vals' => array($hash), 'name' => 'RCGFXJob_verify')));

			if (!$jobExists) {
				$job = RCGFXJob::get($this -> storage, array('hash' => $hash, 'params' => json_encode($saveParams)), false);
				$job -> save();
			}

			if (isset($src)) {
				$fileSource = $src;
			}

			$src = '/gfx?' . self::URL_PARAM . '=' . $hash;

			if (!isset($imageWidth)) {
				if (isset($params['width'])) {
					$imageWidth = $params['width'];
				} elseif ($jobExists && $job -> width) {
					$imageWidth = $job -> width;
				}
			}

			if (!isset($imageHeight)) {
				if (isset($params['height'])) {
					$imageHeight = $params['height'];
				} elseif ($jobExists && $job -> height) {
					$imageHeight = $job -> height;
				}
			}

			// TODO: compute based on aspect ratio
			// TODO: compute width / height so we can put them into img tag even on first request
		}

		switch ( $mode ) {
			case self::MODE_TAG :
				$ret = '<img src="' . htmlspecialchars($src, ENT_COMPAT, "UTF-8") . '"';

				if (!empty($imageWidth)) {
					$ret .= ' width="' . $imageWidth . '"';
				}

				if (!empty($imageHeight)) {
					$ret .= ' height="' . $imageHeight . '"';
				}

				if (empty($params['attr'])) {
					$params['attr'] = array();
				}

				if (!array_key_exists('alt', $params['attr']) && isset($fileInfo)) {
					$alt = $fileInfo -> getFileMeta('alt');

					$params['attr']['alt'] = ($alt === NULL) ? '' : $alt;
				}

				foreach ($params[ 'attr' ] as $name => $value) {
					if ($value === NULL)
						continue;

					if (ctype_digit($name)) {
						$name = $value;
					}

					$ret .= ' ' . $name . '="' . htmlspecialchars($value, ENT_COMPAT, "UTF-8") . '"';
				}

				$ret .= ' />';

				if ($this -> reflowTrickeryClass && ((!empty($imageWidth) && !empty($imageHeight) && ($aspect = $imageWidth / $imageHeight)) || (!empty($aspect))) && !empty($params['rt'])) {
					$paddingBottomPercentage = str_replace(',', '.', sprintf("%.8f", 100 / $aspect));
					// str_replace to cope for locales with ',' as separator

					$ret = '<div class="' . $this -> reflowTrickeryClass . '" style="padding-bottom:' . $paddingBottomPercentage . '%">' . $ret . '</div>';
					// already escaped when setting property!
				}
				break;
			case self::MODE_SRC :
				$ret = $src;
				break;
			case self::MODE_SEND :
				if (!$noCacheSave) {
					return $this -> cache -> send($fn, $mimetype);
				}

				// TODO: $mtime, $ifModifiedSince ?
				return Responder::sendString($filedata, $mimetype);

				break;
			case self::MODE_META :
			default :
				$ret = array('fn' => $fn, 'width' => isset($imageWidth) ? $imageWidth : NULL, 'height' => isset($imageHeight) ? $imageHeight : NULL, 'src' => $src);

				if (isset($fileSource)) {// set in case of skipGenerate = false
					$ret['fileSource'] = $fileSource;
				}
		}

		return $ret;
	}

	final private function setIMBackgroundColor($im, $color) {
		$im -> setBackgroundColor($color);

		if (!self::$imagickUseCLI) {
			// try { // might get an exception "Can not process empty Imagick object", but we don't care for that
			if ($im -> getNumberImages() > 0) {
				$im -> setImageBackgroundColor($color);
			}
			// } catch(Exception $e) {

			// }
		}
	}

	final private function getClone($im) {
		if (self::$imagickUseCLI) {
			return clone $im;
		} else {
			return $im -> clone();
		}
	}

	final private function getConstant($constName) {
		if (self::$imagickUseCLI) {
			return constant('ImagickCLI::' . $constName);
		} else {
			return constant('Imagick::' . $constName);
		}
	}

	final private function getImagick($files = NULL) {
		if (self::$imagickUseCLI) {
			return new ImagickCLI($files);
		} else {
			return new Imagick($files);
		}
	}

	final private function getImagickDraw() {
		if (self::$imagickUseCLI) {
			return new ImagickDrawCLI();
		} else {
			return new ImagickDraw();
		}
	}

	/**
	 * Returns crop offset according to focus (operates in 1 Dimension)
	 *
	 * @param float        $crop how much to crop in total (pixels on source)
	 * @param float        $oldSize original size
	 * @param float        $targetSize new size
	 * @param float|string absolute or percentage value for focus
	 */
	final private function getCropOffset($crop, $oldSize, $focus) {
		$focus = $this -> absolutize($focus, $oldSize);

		$crop = min($crop, max(0, $crop / 2 - ($oldSize / 2 - $focus)));

		return $crop;
	}

	/**
	 * Converts % notation
	 *
	 * @return float
	 */
	final private function absolutize($val, $ref) {
		if (is_string($val) && strpos($val, '%') !== false) {// convert percentage focus to absolute number
			$val = $ref * (float)$val / 100;
		} else {
			$val = (float)$val;
		}

		return $val;
	}

	/**
	 * Trims an image either using imagick or GD (depending on $this->useImagick setting)
	 */
	final private function trimImage(&$im) {
		if ($this -> useImagick) {
			$im -> trimImage(0);
			$im -> setImagePage(0, 0, 0, 0);
		} else {
			if (function_exists('imagecropauto')) {// php >= 5.5.0
				$im = imagecropauto($im, IMG_CROP_DEFAULT);

				if ($im === false) {
					throw new Exception();
				}
			} else {
				$hex = imagecolorat($im, 0, 0);
				$width = imagesx($im);
				$height = imagesy($im);

				$b_top = 0;
				$b_lft = 0;
				$b_btm = $height - 1;
				$b_rt = $width - 1;

				// top
				for (; $b_top < $height; ++$b_top) {
					for ($x = 0; $x < $width; ++$x) {
						if (imagecolorat($im, $x, $b_top) !== $hex) {
							break 2;
						}
					}
				}

				// return false when all pixels are trimmed
				if ($b_top === $height)
					return false;
				// TODO: check if imagick acts the same way!

				// bottom
				for (; $b_btm >= 0; --$b_btm) {
					for ($x = 0; $x < $width; ++$x) {
						if (imagecolorat($im, $x, $b_btm) !== $hex) {
							break 2;
						}
					}
				}

				// left
				for (; $b_lft < $width; ++$b_lft) {
					for ($y = $b_top; $y <= $b_btm; ++$y) {
						if (imagecolorat($im, $b_lft, $y) !== $hex) {
							break 2;
						}
					}
				}

				// right
				for (; $b_rt >= 0; --$b_rt) {
					for ($y = $b_top; $y <= $b_btm; ++$y) {
						if (imagecolorat($im, $b_rt, $y) !== $hex) {
							break 2;
						}
					}
				}

				$b_btm++;
				$b_rt++;

				$bw = $b_rt - $b_lft;
				$bh = $b_btm - $b_top;

				if ($bw !== $width || $bh !== $height) {
					// copy cropped portion
					$im2 = self::imagecreatetruecolor($bw, $bh, self::COLOR_TRANSPARENT);

					imagecopy($im2, $im, 0, 0, $b_lft, $b_top, $bw, $bh);

					imagedestroy($im);
					$im = $im2;
				}
			}
		}
	}

	final private function renderGDText($params) {
		$metrics = $this -> getFontMetrics($params['font'], $params['fontSize'], $params['text'], isset($params['fontWeight']) ? $params['fontWeight'] : NULL, !empty($params['letterSpacing']) ? $params['letterSpacing'] : NULL);

		$w = ceil($metrics['textWidth']) + max(0, $metrics['boundingBox']['x1']) + 2;
		$h = ceil($metrics['textHeight']) + max(0, $metrics['boundingBox']['y1']) + 1;

		$textImage = self::imagecreatetruecolor($w, $h, self::COLOR_TRANSPARENT);

		$color = self::toGDColor($textImage, $params['color'], true);

		if (empty($params['letterSpacing'])) {
			imagettftext($textImage, $params['fontSize'] * self::$gdFontUnitMultiplier, 0, $metrics['boundingBox']['x1'], -$metrics['boundingBox']['y1'] - 1, $color, $params['font'], $params['text']);
		} else {
			$temp_x = -$metrics['boundingBox']['x1'];

			if (function_exists('mb_split') && function_exists('mb_regex_encoding') && function_exists('mb_internal_encoding')) {
				mb_regex_encoding('UTF-8');
				mb_internal_encoding('UTF-8');
				$textArr = mb_split('', $params['text']);
			} else {
				// might not work correctly on utf8 characters unfortunately :(
				$textArr = preg_split('//u', $params['text'], -1, PREG_SPLIT_NO_EMPTY);
			}

			if ($this -> useAdvancedLetterSpacing) {

				for ($i = 0, $ii = count($textArr); $i < $ii; $i++) {
					if ($i > 0) {
						$space = $params['letterSpacing'] * $params['fontSize'] / 1000 + $this -> getKerning($params['font'], $params['fontSize'], $textArr[$i - 1], $textArr[$i]);
						$temp_x += $space;
					}

					imagettftext($textImage, $params['fontSize'] * self::$gdFontUnitMultiplier, 0, round($temp_x), -$metrics['boundingBox']['y1'], $color, $params['font'], $textArr[$i]);

					$temp_x += $this -> getAdvanceWidth($params['font'], $params['fontSize'], $params['text'][$i]);
				}
			} else {// simple letterspacing ; ignores built-in font kerning and right side bearing of characters
				for ($i = 0, $ii = count($textArr); $i < $ii; $i++) {
					$bbox = imagettftext($textImage, $params['fontSize'] * self::$gdFontUnitMultiplier, 0, $temp_x, -$metrics['boundingBox']['y1'], $color, $params['font'], $textArr[$i]);
					$temp_x += $params['letterSpacing'] * $params['fontSize'] / 1000 + ($bbox[2] - $bbox[0]);
				}
			}
		}

		return $textImage;
	}

	final private function GDHandleBackground($im, $oldWidth, $oldHeight, $params, $_image_data, $offsetX = 0, $offsetY = 0, $force = false) {
		if (($oldWidth && $oldHeight) && ($params['backgroundColor'] !== self::COLOR_TRANSPARENT || !isset($im) || $force || (isset($_image_data[2]) && ($_image_data[2] === IMAGETYPE_PNG || $_image_data[2] === IMAGETYPE_GIF)))) {
			$backgroundImage = self::imagecreatetruecolor($oldWidth, $oldHeight, $params['backgroundColor']);

			if (isset($im)) {// we might have a generated color canvas as well, in which case $im does not exist yet
				imagealphablending($backgroundImage, true);

				imagecopy($backgroundImage, $im, $offsetX, $offsetY, 0, 0, $oldWidth, $oldHeight);

				imagedestroy($im);
			}

			$im = $backgroundImage;
		}

		return $im;
	}

	final private static function imagecreatetruecolor($width, $height, $fill = FALSE) {
		$image = imagecreatetruecolor($width, $height);

		if ($image === FALSE) {
			throw new Exception('GFX: Unable to create image of size ' . $width . 'x' . $height);
		}

		if ($fill !== FALSE) {
			$fillColor = self::toGDColor($image, $fill, true);
			imagealphablending($image, false);

			imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, $fillColor);

			imagecolordeallocate($image, $fillColor);

			// reset alphablending to default value
			imagealphablending($image, true);
		}

		return $image;
	}

	/**
	 * override of GD imagecopyresampled with better handling of gamma
	 *
	 * For Images without transparency this could be done a lot simpler (and faster), but this function should always work
	 * equivalent to: imagecopyresampled( $dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );
	 *
	 * TODO: make use of GD inbuilt color channel copy functions
	 */
	final private function GDCopyResampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) {
		// return imagecopyresampled( $dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );

		// prevent out of bounds notice in HHVM
		$dst_w = (int)($dst_w);
		$dst_h = (int)($dst_h);

		$src_w = (int)($src_w);
		$src_h = (int)($src_h);

		$alpha_image = self::imagecreatetruecolor($src_w, $src_h);

		// override alpha channel
		imagealphablending($alpha_image, false);

		// copy alpha channel
		for ($x = $src_x, $xe = $src_x + $src_w; $x < $xe; $x++) {
			for ($y = $src_y, $ye = $src_y + $src_h; $y < $ye; $y++) {
				$alpha = (imagecolorat($src_image, $x, $y)>>24) & 0xFF;
				$color = imagecolorallocatealpha($alpha_image, 0, 0, 0, $alpha);
				imagesetpixel($alpha_image, $x, $y, $color);
				imagecolordeallocate($alpha_image, $color);
			}
		}

		// sRGB can be approximated with gamma 2.2
		imagegammacorrect($src_image, 2.2, 1.0);

		imagealphablending($dst_image, false);
		// override alpha
		imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
		imagegammacorrect($dst_image, 1.0, 2.2);

		$alpha_resized_image = self::imagecreatetruecolor($dst_w, $dst_h);
		imagealphablending($alpha_resized_image, false);
		imagecopyresampled($alpha_resized_image, $alpha_image, 0, 0, 0, 0, $dst_w, $dst_h, $src_w, $src_h);

		for ($x = 0; $x < $dst_w; $x++) {
			for ($y = 0; $y < $dst_h; $y++) {
				$xd = $x + $dst_x;
				$yd = $y + $dst_y;

				$alpha = (imagecolorat($alpha_resized_image, $x, $y)>>24) & 0xFF;
				$rgb = imagecolorat($dst_image, $xd, $yd);
				$r = ($rgb>>16) & 0xFF;
				$g = ($rgb>>8) & 0xFF;
				$b = $rgb & 0xFF;

				$color = imagecolorallocatealpha($dst_image, $r, $g, $b, $alpha);
				imagesetpixel($dst_image, $xd, $yd, $color);
				imagecolordeallocate($dst_image, $color);
			}
		}

		// cleanup
		imagedestroy($alpha_image);
		imagedestroy($alpha_resized_image);

		return true;
	}

	final private function addWatermarks($im, $params) {
		if (!empty($params['sizeByWatermarks'])) {
			// [JB 26.11.2013 imageWidth/imageHeight changed from 1 to 0]
			$imageWidth = 0;
			$imageHeight = 0;

			$firstRun = true;

			// startx, starty
			$sx = 0;
			$sy = 0;
		}

		foreach ($params[ 'watermarks' ] as $watermark) {
			$wim = $this -> executeCommand('convert', $watermark, NULL, true);

			if ($this -> useImagick) {
				$imageSize = $wim -> getImageGeometry();

				$w = $imageSize['width'];
				$h = $imageSize['height'];

				if (!isset($imageWidth) || !isset($imageHeight)) {
					$imageSize = $im -> getImageGeometry();

					if (!isset($imageWidth)) {
						$imageWidth = $imageSize['width'];
					}

					if (!isset($imageHeight)) {
						$imageHeight = $imageSize['height'];
					}
				}
			} else {// GD
				$w = imagesx($wim);
				$h = imagesy($wim);

				// $im might not be set with sizeByWatermarks
				if (isset($im)) {
					if (!isset($imageWidth)) {
						$imageWidth = imagesx($im);
					}

					if (!isset($imageHeight)) {
						$imageHeight = imagesy($im);
					}
				}
			}

			$x = isset($watermark['x']) ? $this -> absolutize($watermark['x'], $imageWidth) : 0;
			$y = isset($watermark['y']) ? $this -> absolutize($watermark['y'], $imageHeight) : 0;

			$gravity = isset($watermark['gravity']) ? $watermark['gravity'] : self::GRAVITY_NORTHWEST;

			switch ( $gravity ) {
				case self::GRAVITY_NORTHWEST :
					break;
				case self::GRAVITY_NORTH :
					$x += (($imageWidth - $w) / 2);
					break;
				case self::GRAVITY_NORTHEAST :
					$x = $imageWidth - $w - $x;
					break;
				case self::GRAVITY_WEST :
					$y += (($imageHeight - $h) / 2);
					break;
				case self::GRAVITY_CENTER :
					$x += (($imageWidth - $w) / 2);
					$y += (($imageHeight - $h) / 2);
					break;
				case self::GRAVITY_EAST :
					$x = $imageWidth - $w - $x;
					$y += (($imageHeight - $h) / 2);
					break;
				case self::GRAVITY_SOUTHWEST :
					$y = $imageHeight - $h - $y;
					break;
				case self::GRAVITY_SOUTH :
					$x += (($imageWidth - $w) / 2);
					$y = $imageHeight - $h - $y;
					break;
				case self::GRAVITY_SOUTHEAST :
					$x = $imageWidth - $w - $x;
					$y = $imageHeight - $h - $y;
					break;
			}

			if (!empty($params['sizeByWatermarks'])) {
				if (!($gravity & self::GRAVITY_EAST)) {
					$x -= $sx;
				}

				if (!($gravity & self::GRAVITY_SOUTH)) {
					$y -= $sy;
				}

				$newWidth = $imageWidth;
				$newHeight = $imageHeight;

				if (($x + $w) > $imageWidth) {
					$newWidth += ($x + $w) - $imageWidth;
				}

				if ($x < 0) {
					$newWidth -= $x;
				}

				if (($y + $h) > $imageHeight) {
					$newHeight += ($y + $h) - $imageHeight;
				}

				if ($y < 0) {
					$newHeight -= $y;
				}

				$x = (int)$x;
				$y = (int)$y;

				if ($this -> useImagick) {
					if ($firstRun && $this -> useImagick) {
						if ($newWidth > 0 && $newHeight > 0) {
							$im -> newImage($newWidth, $newHeight, self::toImagickColor($params['backgroundColor'], true));

							$firstRun = false;
						}
					} elseif ($newWidth > $imageWidth || $newHeight > $imageHeight || $x < 0 || $y < 0) {
						// TODO: shouldn't we use the same check as in GD code (> instead of !==, and check $x/$y)
						$im -> extentImage($newWidth, $newHeight, min($x, 0), min($y, 0));
					}

					if ($x < 0) {
						$sx += $x;
						$x = 0;
					}

					if ($y < 0) {
						$sy += $y;
						$y = 0;
					}
				} else {
					if ($firstRun) {// $firstRun, so create new image
						// TODO: test if negative, zero, and positive x/y are handled correctly

						if ($x < 0) {
							$sx += $x;
							$x = 0;
						}

						if ($y < 0) {
							$sy += $y;
							$y = 0;
						}

						if ($newWidth > 0 && $newHeight > 0) {
							$im = self::imagecreatetruecolor($newWidth, $newHeight, self::COLOR_TRANSPARENT);

							$firstRun = false;
						}
					} else {// not $firstRun, so need to extent existing image
						if ($newWidth > $imageWidth || $newHeight > $imageHeight || $x < 0 || $y < 0) {
							$im = $this -> GDHandleBackground($im, $newWidth, $newHeight, array('backgroundColor' => self::COLOR_TRANSPARENT), NULL, -min($x, 0), -min($y, 0), true);

							if ($x < 0) {
								$sx += $x;
								$x = 0;
							}

							if ($y < 0) {
								$sy += $y;
								$y = 0;
							}
						}
					}
				}

				$imageWidth = $newWidth;
				$imageHeight = $newHeight;

			} else {// if sizeByWatermarks
				if (isset($watermark['constraints'])) {
					$this -> applyConstraints($wim, $x, $y, $w, $h, $watermark['constraints'], $im, $imageWidth, $imageHeight);
				}
			}

			if ($this -> useImagick) {
				$im -> compositeImage($wim, $this -> getConstant('COMPOSITE_OVER'), $x, $y);
				// TODO: flattenImages does not seem to yield better results - test if we can leave it out
				// $im -> flattenImages();
			} else {
				// might not be set in case watermark yielded image with 0 width or 0 height
				if (isset($im)) {
					// make sure we're using correct alpha blending
					imagealphablending($im, true);

					imagecopy($im, $wim, $x, $y, 0, 0, $w, $h);
					imagedestroy($wim);
				}
			}

		}

		// apply backgroundcolor once at the end, as otherwhise it could be blended again and again
		if (!$this -> useImagick && $params['backgroundColor'] !== self::COLOR_TRANSPARENT && isset($newWidth) && isset($newHeight) && $newWidth > 0 && $newHeight > 0) {
			$im = $this -> GDHandleBackground($im, $newWidth, $newHeight, $params, NULL, 0, 0);
		}

		return $im;
	}

	protected function checkConstraint($op, $sideComp, $refSideComp) {
		switch ($op) {
			case '<=' :
				$keep = $sideComp <= $refSideComp;
				break;
			case '=' :
				$keep = $sideComp === $refSideComp;
				break;
			case '>=' :
				$keep = $sideComp >= $refSideComp;
				break;
		}

		return $keep;
	}

	protected function applyConstraints(&$image, &$x, &$y, &$w, &$h, $constraints, $refImage, $refImageWidth, $refImageHeight) {
		$constraints = explode(',', $constraints);

		$ow = (int)$w;
		$oh = (int)$h;

		foreach ($constraints as $constraint) {
			$valid = preg_match('/([whxy])(<=|>=|=)([neswtrbl])((?:\+|-)[0-9]+%?)?/', $constraint, $matches);

			if ($valid) {
				$modifyVal = $matches[1];
				$op = $matches[2];
				$side = $matches[3];

				switch ($side) {
					case 'n' :
					case 't' :
						$sideLength = &$h;
						$sideComp = $y;
						$refSideComp = 0;

						break;
					case 'e' :
					case 'r' :
						$sideLength = &$w;
						$sideComp = $x + $w;
						$refSideComp = $refImageWidth;
						break;
					case 's' :
					case 'b' :
						$sideLength = &$h;
						$sideComp = $y + $h;
						$refSideComp = $refImageHeight;
						break;
					case 'w' :
					case 'l' :
						$sideLength = &$w;
						$sideComp = $x;
						$refSideComp = 0;
						break;
				}

				$add = isset($matches[4]) ? $this -> absolutize($matches[4], $sideLength) : '0';

				if (!$this -> checkConstraint($op, $sideComp, $refSideComp)) {
					switch ($side) {
						case 'n' :
						case 't' :
							if ($modifyVal === 'y') {
								$y = $refSideComp + $add;
							} elseif ($modifyVal === 'h') {
								$h = $y + $h - $refSideComp - $add;
								$y = $refSideComp + $add;
							}
							break;
						case 'e' :
						case 'r' :
							if ($modifyVal === 'x') {
								$x = $refSideComp - $w - $add;
							} elseif ($modifyVal === 'w') {
								$w = $refSideComp - $x - $add;
							}
							break;
						case 's' :
						case 'b' :
							if ($modifyVal === 'y') {
								$y = $refSideComp - $y - $add;
							} elseif ($modifyVal === 'h') {
								$h = $refSideComp - $y - $add;
							}
							break;
						case 'w' :
						case 'l' :
							if ($modifyVal === 'x') {
								$x = $refSideComp + $add;
							} elseif ($modifyVal === 'w') {
								$w = $x + $w - $refSideComp - $add;
								$x = $refSideComp + $add;

							}
							break;
					}
				}
			}
		}

		$w = (int)$w;
		$h = (int)$h;

		if ($w !== $ow || $h !== $oh) {
			// keep aspect
			$changeFactorW = abs($w / $ow - 1);
			$changeFactorH = abs($h / $oh - 1);

			if ($changeFactorW > $changeFactorH) {
				$h = $w / $ow * $oh;
			} else {
				$w = $h / $oh * $ow;
			}

			$this -> resizeImage($image, $w, $h);
		}
	}

	protected function resizeImage(&$im, $w, $h) {
		if ($this -> useImagick) {
			$im -> resizeImage($w, $h, $this -> getConstant('FILTER_LANCZOS'), 1);
		} else {
			$newImage = imagecreatetruecolor($w, $h);

			$this -> GDCopyResampled($newImage, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));

			imagedestroy($im);

			$im = $newImage;
		}
	}

}

/**
 * Imagerotate replacement. ignore_transparent is work for png images
 *
 * Also, have some standard functions for 90, 180 and 270 degrees.
 * Rotation is clockwise
 */
class imagerotateEquivalent {
	public static function getRotatedDimension($srcw, $srch, $theta) {
		// Calculate the width of the destination image.
		$temp = array(self::rotateX(0, 0, 0 - $theta), self::rotateX($srcw, 0, 0 - $theta), self::rotateX(0, $srch, 0 - $theta), self::rotateX($srcw, $srch, 0 - $theta));

		$minX = floor(min($temp));
		$maxX = ceil(max($temp));
		$width = $maxX - $minX;

		// Calculate the height of the destination image.
		$temp = array(self::rotateY(0, 0, 0 - $theta), self::rotateY($srcw, 0, 0 - $theta), self::rotateY(0, $srch, 0 - $theta), self::rotateY($srcw, $srch, 0 - $theta));

		$minY = floor(min($temp));
		$maxY = ceil(max($temp));
		$height = $maxY - $minY;

		return array($minX, $maxX, $width, $minY, $maxY, $height);
	}

	protected static function rotateX($x, $y, $theta) {
		return $x * cos($theta) - $y * sin($theta);
		//		return $x * cos($theta) + $y * sin($theta); // theoretically correct
	}

	protected static function rotateY($x, $y, $theta) {
		return $x * sin($theta) + $y * cos($theta);
		//		return -$x * sin($theta) + $y * cos($theta); // theoretically correct
	}

	public static function rotate($srcImg, $angle, $bgcolor, $ignore_transparent = 0) {
		$srcw = imagesx($srcImg);
		$srch = imagesy($srcImg);

		// Normalize angle
		$angle%=360;

		// Set rotate to clockwise
		// $angle = -$angle;

		if ($angle == 0) {

			return $srcImg;
		}

		// Convert the angle to radians
		$theta = deg2rad($angle);

		// Standart case of rotate
		if ((abs($angle) == 90) || (abs($angle) == 270)) {
			$width = $srch;
			$height = $srcw;

			if (($angle == 90) || ($angle == -270)) {
				$minX = 0;
				$maxX = $width;
				$minY = -$height + 1;
				$maxY = 1;
			} else if (($angle == -90) || ($angle == 270)) {
				$minX = -$width + 1;
				$maxX = 1;
				$minY = 0;
				$maxY = $height;
			}
		} else if (abs($angle) === 180) {
			$width = $srcw;
			$height = $srch;
			$minX = -$width + 1;
			$maxX = 1;
			$minY = -$height + 1;
			$maxY = 1;
		} else {
			list($minX, $maxX, $width, $minY, $maxY, $height) = self::getRotatedDimension($srcw, $srch, $theta);
		}

		$destimg = imagecreatetruecolor($width, $height);

		// should be off for all the setpixel calls anyway
		imagealphablending($destimg, false);

		if ($ignore_transparent === 0) {
			imagefill($destimg, 0, 0, imagecolorallocatealpha($destimg, 255, 255, 255, 127));
		}

		if ($bgcolor !== -1) {
			$bgcolor = imagecolorsforindex($srcImg, $bgcolor);
		}

		// sets all pixels in the new image
		// RBAM - Rotate Bitmap Area Mapping - nice looking edges but blurry image
		/*
		 for($x=$minX; $x<$maxX; $x++) {
		 for($y=$minY; $y<$maxY; $y++) {

		 // get target coordinates for source pixel
		 $tX = (float)self::rotateX($x, $y, $theta);
		 $tY = (float)self::rotateY($x, $y, $theta);

		 $fx = floor($tX); $fy = floor($tY);
		 $cx = ceil($tX); $cy = ceil($tY);

		 $oneX = ((float)$fx == $tX);
		 $oneY = ((float)$fy == $tY);

		 $coords = array();

		 if ($oneX) {
		 if ($oneY) {
		 $coords[] = array($tX, $tY);
		 } else {
		 $coords[] = array($tX, $fy);
		 $coords[] = array($tX, $cy);
		 }
		 } else {
		 if ($oneY) {
		 $coords[] = array($fx, $tY);
		 $coords[] = array($cx, $tY);
		 } else {
		 $coords[] = array($fx, $fy);
		 $coords[] = array($fx, $cy);
		 $coords[] = array($cx, $fy);
		 $coords[] = array($cx, $cy);
		 }
		 }

		 $color = array('red' => 0.0, 'green' => 0.0, 'blue' => 0.0, 'alpha' => 0.0);
		 $rgbmult = 0.0;
		 $alphamult = 0.0;

		 foreach ($coords as $xy) {
		 $multX = (float)abs($tX - (float)$xy[0]);
		 $multY = (float)abs($tY - (float)$xy[1]);
		 $mult = (1.0 - $multX) * (1.0 - $multY);

		 if ($xy[0] >= 0 && $xy[0] < $srcw && $xy[1] >= 0 && $xy[1] < $srch) {

		 $rgbmult += (float)$mult;
		 $alphamult += (float)$mult;

		 $colorIndex = imagecolorat($srcImg, $xy[0], $xy[1]);
		 $newColor = imagecolorsforindex($srcImg, $colorIndex);

		 foreach ($newColor as $k => $v) {
		 $color[$k] += (float)$v * (float)$mult;
		 }

		 } else { // catching errors
		 if ($bgcolor == -1) { // transparent background
		 $alphamult += (float)$mult;
		 $color['alpha'] += 127.0 * (float)$mult;
		 } else {
		 $rgbmult += (float)$mult;
		 $alphamult += (float)$mult;

		 foreach ($bgcolor as $k => $v) {
		 $color[$k] += (float)$v * (float)$mult;
		 }
		 }
		 }

		 } // foreach end

		 if ($rgbmult == 0) {
		 $color = array('ref' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 127);
		 } else {
		 if (!$oneX || !$oneY) { // normalize
		 $color['red'] = round((float)$color['red'] / $rgbmult);
		 $color['green'] = round((float)$color['green'] / $rgbmult);
		 $color['blue'] = round((float)$color['blue'] / $rgbmult);
		 $color['alpha'] = round((float)$color['alpha'] / $alphamult);
		 }
		 }

		 $color = imagecolorallocatealpha($destimg, $color['red'], $color['green'], $color['blue'], $color['alpha']);

		 imagesetpixel($destimg, $x-$minX, $y-$minY, $color);
		 }
		 }
		 */
		// NEW algorithm
		$a = 3.0;
		// size of the kernel for lanczos formula

		for ($x = $minX; $x < $maxX; $x++) {
			for ($y = $minY; $y < $maxY; $y++) {
				//				$sourceX = (float)self::rotateX($x, $y, -$theta); // derotate!
				//				$sourceY = (float)self::rotateY($x, $y, -$theta); // derotate!

				$sourceX = (float)self::rotateX($x, $y, $theta);
				$sourceY = (float)self::rotateY($x, $y, $theta);

				$sX = ceil($sourceX - 1.0);
				$sY = ceil($sourceY - 1.0);

				$uX = floor($sourceX + 1.0);
				$uY = floor($sourceY + 1.0);

				$values = array('red' => 0.0, 'green' => 0.0, 'blue' => 0.0, 'alpha' => 0.0);
				$totalCoeff = array('red' => 0.0, 'green' => 0.0, 'blue' => 0.0, 'alpha' => 0.0);

				for ($testX = $sX; $testX <= $uX; $testX += 1.0) {
					for ($testY = $sY; $testY <= $uY; $testY += 1.0) {
						$dX = (float)$testX - $sourceX;
						$dY = (float)$testY - $sourceY;
						$dist = sqrt($dX * $dX + $dY * $dY);

						if ($dist <= 1.0) {
							if ($dist == 0.0) {
								$coeff = 1.0;
								// optimization
							} else if ($dist < $a) {// $-a < $dist does not need to be checked, as $dist can not be negative
								// lanczos formula
								$coeff = $a * sin(M_PI * $dist) * sin(M_PI * $dist / $a) / (M_PI * M_PI * $dist * $dist);
								// http://en.wikipedia.org/wiki/Lanczos_resampling
								//								$coeff = sin(M_PI * $dist) / (M_PI * $dist) * sin(M_PI * $dist / $a) / (M_PI * $dist / $a); // http://www.orrery.us/node/39
								//								$coeff = 1.0 - $dist; // linear
							} else {
								$coeff = 0.0;
							}

							if ($coeff == 0.0)
								continue;
							// optimization

							if ($testX < 0 || $testX >= $srcw || $testY < 0 || $testY >= $srch) {
								if ($bgcolor === -1) {// transparent background
									$values['alpha'] += 127.0 * $coeff;
									$totalCoeff['alpha'] += $coeff;
								} else {
									$color = array();

									foreach ($bgcolor as $k => $v) {
										$values[$k] += (float)$v * $coeff;
										$totalCoeff[$k] += $coeff;
									}
								}

							} else {
								$colorIndex = imagecolorat($srcImg, $testX, $testY);
								$color = imagecolorsforindex($srcImg, $colorIndex);

								foreach ($color as $k => $v) {
									$values[$k] += (float)$v * $coeff;
									$totalCoeff[$k] += $coeff;
								}
							}
						}
					}

				}

				foreach ($values as $k => $v) {
					if ($totalCoeff[$k] == 0.0) {
						$values[$k] = 0.0;
					} else {
						$values[$k] = $v / $totalCoeff[$k];
					}
				}

				$color = imagecolorallocatealpha($destimg, round($values['red']), round($values['green']), round($values['blue']), round($values['alpha']));

				imagesetpixel($destimg, $x - $minX, $y - $minY, $color);

			}
		}

		return $destimg;
	}

}

// imagerotate replacement if it's not available (e.g. debian php package)

if (!function_exists("imagerotate")) {
	function imagerotate($srcImg, $angle, $bgcolor = 0, $ignore_transparent = 0) {
		return imagerotateEquivalent::rotate($srcImg, $angle, $bgcolor, $ignore_transparent);
	}

}

class UnsupportedImageTypeException extends Exception {
}
