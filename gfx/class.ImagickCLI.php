<?php

require_once __DIR__ . '/class.ImagickDrawCLI.php';
require_once __DIR__ . '/class.ImagickCLIUtil.php';

class ImagickCLI {
	// define fd aliases for STDIN/STDOUT, as using '-' for both could get them mixed up
	const STDIN = '-'; // 'fd:0' should work according to docs, but does not
	const STDOUT = '-'; // 'fd:1' should work according to docs, but does not

	const FORMAT_TEMP = 'miff';

	const DATA_IN = 'miff:-';
	const DATA_IN_UNKNOWN = '-';
	const DATA_OUT = 'miff:-';

	// ---------- mirrored consts of Imagick
	// channel consts where selected this way so they can directly be used for the convert command

	// TODO: make these work with bit operations (e.g. use bitmasks and translate on usage)
	const CHANNEL_ALL = 'All';
	const CHANNEL_RED = 'R';
	const CHANNEL_GREEN = 'G';
	const CHANNEL_BLUE = 'B';
	const CHANNEL_ALPHA = 'A';
	const CHANNEL_CYAN = 'C';
	const CHANNEL_MAGENTA = 'M';
	const CHANNEL_YELLOW = 'Y';
	const CHANNEL_BLACK = 'K';
	const CHANNEL_OPACITY = 'O';
	const CHANNEL_INDEX = 'Index';
	const CHANNEL_RGB = 'RGB';
	const CHANNEL_RGBA = 'RGBA';
	const CHANNEL_CMYK = 'CMYK';
	const CHANNEL_CMYKA = 'CMYKA';

	// colorspace consts as reported by convert
	const COLORSPACE_CMYK = 'CMYK';
	const COLORSPACE_SRGB = 'sRGB';

	// -compress consts by convert documentation
	const COMPRESSION_JPEG = 'JPEG';
	const COMPRESSION_NONE = 'None';
	const COMPRESSION_BZIP = 'BZip';
	const COMPRESSION_JPEG2000 = 'JPEG2000';
	const COMPRESSION_LOSSLESS = 'Lossless';
	const COMPRESSION_LZW = 'LZW';
	const COMPRESSION_RLE = 'RLE';
	const COMPRESSION_ZIP = 'Zip';
	const COMPRESSION_GROUP4 = 'Group4';
	const COMPRESSION_FAX = 'Fax';


	const COMPOSITE_OVER = 'src-over';
	const COMPOSITE_BLEND = 'blend';


	const FILTER_LANCZOS = 'Lanczos';
	const FILTER_UNDEFINED = 'UNDEFINED';

	// TODO: check with Imagick which values to use for the following consts
	const GRAVITY_CENTER = 8;


	// ----------
	public static $convertCommand;

	// ----------
	protected $options;

	protected $outputFormat;
	protected $outputCompression;
	protected $outputCompressionQuality;

	protected $data; // intermediate image data, if required
	protected $id; // for easier debugging
	protected static $idCounter = 0;

	protected $dataPlaceholder;

	// needs to be saved for operations which rely on backgroundColor, as imagemagick does not recover backgroundColor setting from miff stream
	protected $backgroundColor;

	public function __construct( $files = NULL ) {
		$this->dataPlaceholder = self::DATA_IN;

		if ($files) {
			$this->options = (array)$files;
		} else {
			$this->options = array();
		}
		
		$this->id = ++self::$idCounter;
	}

	public function trimImage( $fuzz ) {
		if (!$fuzz === NULL) {
			$fuzz = 0;
		}

		array_push( $this->options, '-fuzz', (int)($fuzz * 100) . '%', '-trim' );
	}

	public function setImagePage( $width , $height , $x , $y ) {
		array_push( $this->options, '-repage', $this->geometry( $width, $height, $x, $y ) );
	}

	// $multiline = NULL : autodetect
	// returns array:
	// 'ascender', 'textWidth', 'textHeight', 'boundingBox'[x1, y1, x2, y2]
	public function queryFontMetrics( ImagickDrawCLI $properties , $text, $multiline = NULL ) {
		// debug command writes to stderr instead of stdout
		$this->convert( 
			array( '-debug', 'annotate', 'xc:', '-font', $properties->getFont(), '-pointsize', $properties->getFontSize(), '-annotate', '0', $text, 'null:' ), 
		false, false, $out );

		// TODO: support different multiline arguments
		$lineCountExpected = 1 + substr_count( $text, "\n" );

		// parse http://www.imagemagick.org/Usage/text/#font_info
		if (( $lineCount = preg_match_all('/Metrics:(.*)width: (?P<width>[^;]+); height: (?P<height>[^;]+); ascent: (?P<ascent>[^;]+); descent: (?P<descent>[^;]+); max advance: ([^;]+); bounds: (?P<bx1>[^,]+),(?P<by1>[^ ]+)\s+(?P<bx2>[^,]+),(?P<by2>[^;]+); origin: (?P<originX>[^,]+),(?P<originY>[^,]+);.*$/m', $out, $matches)) !== $lineCountExpected ) {
			throw new Exception('Unable to get metrics, expected linecount: ' . $lineCountExpected . ' ; got: ' . $lineCount . ' ; matches: ' . print_r($matches, true));
		}

		// TODO: 'boundingBox', 'width', and 'height' computation are probably wrong
		// TODO: origin and bounds seem to be of the last character only

		$metrics = array(
			'originX' => $matches['originX'][0],
			'originY' => $matches['originY'][0],
			'boundingBox' => array(
				'x1' => $matches['bx1'][0],
				'y1' => $matches['by1'][0],
				'x2' => $matches['bx2'][0],
				'y2' => $matches['by2'][0]
			),
			'textWidth' => $matches['width'][0],
			'textHeight' => $matches['height'][0],
			'ascender' => $matches['ascent'][0],
			'descender' => $matches['descent'][0]
		);


		return $metrics;
	}



	public function getImageGeometry() {
		$this->flush();

		// TODO: optimize for getting size of original image, e.g. when $this->options only has files and $this->data is empty
		// TODO: parse from miff file directly?

		$out = $this->convert( array( $this->dataPlaceholder, '-ping', '-format', '%wx%h', 'info:' ), true, false );

		$sizes = explode('x', $out);

		// TODO: find out if float values should be possible
		$ret = array( 'width' => (int)$sizes[0], 'height' => (int)$sizes[1] );

		return $ret;
	}

	public function setBackgroundColor( $color ) {
		$this->backgroundColor = $color;

		array_push( $this->options, '-background', $color );
	}

	public function readImage( $filename ) {
		$this->options[] = $filename;

		return is_readable( $filename ) && is_file( $filename );
	}

	public function getImageColorspace() {
		$this->flush();

		// TODO: optimize for getting size of original image, e.g. when $this->options only has files and $this->data is empty
		// TODO: parse from miff file directly?

		return $this->convert( array( $this->dataPlaceholder, '-ping', '-format', '%[colorspace]', 'info:' ), true, false );
	}

	public function colorizeImage( $colorize, $opacity ) {
		array_push( $this->options, '-fill', $colorize, '-colorize', (int)($opacity * 100) );
	}

	public function profileImage( $name, $profile ) {
		if ($profile === NULL) { // remove profile by $name : +profile
			array_push( $this->options, '+profile', $name );
		} else { // add profile provided in $profile : -profile
			array_push( $this->options, '-profile', $profile );
		}
	}

	// no one knows what the $filename parameter is for in this function, but it's there ...
	public function readImageBlob( $image, $filename = NULL ) {
		// this actually overrides all data we have, which is not exactly the same as the imagick readblob function

		$this->data = $image;

		$this->dataPlaceholder = self::DATA_IN_UNKNOWN;

		// do not flush options, as there might already be a set backgroundColor or other option
	}

	/**
	* Final render
	*
	* Return image as specified with format option
	*/
	public function getImageBlob() {
		$options = $this->options;

		array_unshift($options, $this->dataPlaceholder);

	

		if ($this->outputCompression !== NULL) {
			array_push( $options, '-compress', $this->outputCompression );
		}

		if ($this->outputCompressionQuality !== NULL) {
			array_push( $options, '-quality', $this->outputCompressionQuality );
		}

		if ($this->outputFormat !== NULL) {
			array_push( $options, $this->outputFormat . ':' . self::STDOUT );
		} else {
			array_push( $options, self::STDOUT );
		}

		$out = $this->convert( $options, true, false );

		return $out;
	}

	public function newImage( $cols, $rows, $background, $format = NULL ) {
		array_push( $this->options, '-size', $this->geometry($cols, $rows), 'canvas:' . $background );

		if ($format !== NULL) {
			array_push( $this->options, '-format', $format );
		}
	}


	public function drawImage( ImagickDrawCLI $draw ) {
		$this->options = array_merge( $this->options, $draw->getCommands() );
	}

	public function borderImage( $bordercolor, $width, $height ) {
		// compose is reset to default 'over', as '-bordercolor'+'-border' do compositing according to imagemagick docs
		array_push( $this->options, '-bordercolor', $bordercolor, '-compose', 'over', '-border', ImagickCLIUtil::shortFloat($width) . 'x' . ImagickCLIUtil::shortFloat($height) );
	}

	public function resizeImage( $columns, $rows, $filter, $blur, $bestfit = false ) {
		if ($columns <= 0 || $rows <= 0) 
			throw new Exception('resizing to negative or zero size');

	//	array_push( $this->options, '-filter', $filter, '-define', 'filter:blur=' . ImagickCLIUtil::shortFloat($blur), '-resize', $this->geometry( $columns, $rows ) . ( $bestfit ? '' : '!' ) );
	// using -colorspace LAB distorts colors strangely
	//	array_push( $this->options, '-filter', $filter, '-define', 'filter:blur=' . ImagickCLIUtil::shortFloat($blur), '-distort', 'resize', $this->geometry( $columns, $rows ) . ( $bestfit ? '' : '!' ), '-colorspace', 'sRGB' );
		array_push( $this->options, '-resize', $this->geometry( $columns, $rows ) . ( $bestfit ? '' : '!' ) );
	}

	public function extentImage( $width, $height, $x, $y ) {
		if ($width <= 0 || $height <= 0)
			throw new Exception('extent to negative or zero size');
		
		$this->addBackgroundColor();

		array_push( $this->options, '-extent', $this->geometry( $width, $height, $x, $y ) );
	}

	protected function addBackgroundColor() {
		$backgroundColor = isset($this->backgroundColor) ? $this->backgroundColor : 'transparent';

		if ($backgroundColor === 'transparent')
			$backgroundColor = 'none';

		array_push( $this->options, '-background', $backgroundColor );
	}

	protected function addDensity() {
		$density = 72;

		array_push( $this->options, '-density', $density );
	}

	public function cropImage( $width, $height, $x, $y ) {
		if ($width <= 0 || $height <= 0)
			throw new Exception('cropping to negative or zero size');

		// TODO: get rid of +repage ; unfortunately -page 0x0+0+0 is not equivalent
		array_push( $this->options, '-crop', $this->geometry( $width, $height, $x, $y ), '+repage' );
	}

	public function rotateImage( $background, $degrees ) {
		array_push( $this->options, '-background', $background, '-rotate', $degrees );
	}

	public function compositeImage( ImagickCLI $composite_object, $composite, $x, $y, $channel = ImagickCLI::CHANNEL_ALL ) {
		$this->flush();

		// append data of second image - miff images can be concatenated
		// $this->data = $composite_object->getRawData() . $this->data;
		$this->data .= $composite_object->getRawData();

		array_push( $this->options, '-geometry', $this->geometry(NULL, NULL, $x, $y), '-compose', $composite, '-channel', $channel, '-composite' );
	}

	public function flattenImages() {
		// set backgroundColor anew, so newly created space gets filled correctly
		$backgroundColor = isset($this->backgroundColor) ? $this->backgroundColor : 'transparent';

		if ($backgroundColor === 'transparent')
			$backgroundColor = 'none';

		array_push( $this->options, '-background', $backgroundColor, '-flatten' );
	}

	public function stripImage() {
		array_push( $this->options, '-strip' );
	}



	public function setImageDepth( $depth ) {
		array_push( $this->options, '-depth', (int)$depth );
	}

	public function setImageFormat( $format ) {
		$this->outputFormat = $format;
	}

	public function setImageCompression( $compression ) {
		$this->outputCompression = $compression;
	}

	public function setImageCompressionQuality( $quality ) {
		$this->outputCompressionQuality = $quality;
	}

	public function destroy() {
		// noop
	}


	// helper for composite and the like
	public function getRawData() {
		$this->flush();

		return $this->data;
	}


	// ----------

	// flushes options and regenerates data
	protected function flush() {
		if (!$this->options)
			return;

		if ($this->data !== NULL) {
			array_unshift( $this->options, $this->dataPlaceholder );
		}

		array_push( $this->options, self::DATA_OUT );

		$this->convert( $this->options, $this->data !== NULL );

		$this->dataPlaceholder = self::DATA_IN;

		$this->options = array();
	}


	// execute convert command:
	protected function convert( $options, $dataIn = true, $dataOut = true, &$err = NULL ) {
		$cmd = escapeshellcmd( self::$convertCommand );

		foreach ($options as $option) {
			$cmd .= ' ' . escapeshellarg( $option );
		}

		$descriptorspec = array(
			0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
			1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
			2 => array("pipe", "w") // stderr
		);

		$process = proc_open($cmd, $descriptorspec, $pipes);

		if (is_resource($process)) {
			if ( $dataIn ) {
				fwrite( $pipes[0], $this->data ); 
			}

			fclose( $pipes[0] );

			$out = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			$err = stream_get_contents($pipes[2]);
			fclose($pipes[2]);

			$returnCode = proc_close($process);
		}

		if ( $returnCode !== 0 ) {
			throw new Exception( 'Convert returned returncode !== 0: ' . var_export($returnCode, true) . ' ; executed: ' . $cmd . ' ; error: ' . $err . "\n\n data:\n[" . var_export($this->data, true) . "]");
		} else if ( $dataOut ) {
			$this->data = $out;
		}


		return $out;
	}


	// build geometry string
	protected function geometry( $width, $height, $x = 0, $y = 0 ) {
		$geometry = '';

		if (isset($width) || isset($height)) {
			if (isset($width)) {
				$geometry .= ImagickCLIUtil::shortFloat($width);
			}

			$geometry .= 'x';

			if (isset($height)) {
				$geometry .= ImagickCLIUtil::shortFloat($height);
			}
		}

		$x = ImagickCLIUtil::shortFloat( $x );
		$y = ImagickCLIUtil::shortFloat( $y );

		if ($x >= 0)
			$geometry .= '+';

		$geometry .= $x;

		if ($y >= 0)
			$geometry .= '+';

		$geometry .= $y;

		return $geometry;
	}



}
