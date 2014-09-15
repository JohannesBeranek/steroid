<?php
/**
* @package steroid\gfx
*/

require_once STROOT . '/unittest/class.UnitTest.php';

require_once STROOT . '/gfx/class.GFX.php';

/**
 * @package steroid\gfx
 */
class TestGFX extends GFX {
	public function testCommand( $params ) {
		return (bool)$this->executeCommand( $params['command'], $params, NULL, true );
		

	}
}

/**
* @package steroid\gfx
*/
class UTGFX extends UnitTest {
	public static function getAvailableTests() {
		$tests = array();	
			
		for ($i = 0, $len = count(self::$testSetsRenders); $i < $len; $i++) {
			$tests[] = 'renders-' . $i;
		}
		

		
		return $tests;
	}
	
	protected static $testSetsRenders = array(
		array(
			'data' => array(
				'command' => 'convert',
				"letterSpacing" => 50, 
				"gravity" => 9, 
				"text" => "NEIN ZUM PRATER-CASINO", 
				"font" => "stlocal/res/fonts/GothamNarrow-Ultra.otf", 
				"color" => "#FFF", 
				"fontSize" => 36, 
				"fontKerning" => 0,5, 
				"backgroundColor" => 4278221055, 
				"paddingLeft" => 18, 
				"paddingTop" => 6, 
				"paddingRight" => 6, 
				"paddingBottom" => 6, 
				"y" => 0 
			),
			'expected' => true,
			'description' => 'Imagick Text Render Fail'
		),
		array(
			'data' => array(
				'command' => 'convert',
				"letterSpacing" => 50, 
				"gravity" => 9, 
				"text" => "AG-ABEND IM BEZIRKSLOKAL", 
				"font" => "stlocal/res/fonts/GothamNarrow-Ultra.otf", 
				"color" => 2226394367, 
				"fontSize" => 36, 
				"fontKerning" => 0.5, 
				"backgroundColor" => "#FFF", 
				"paddingLeft" => 21, 
				"paddingTop" => 6, 
				"paddingRight" => 6, 
				"paddingBottom" => 6, 
				"y" => 0
			),
			'expected' => true,
			'description' => 'Imagick Text Render Fail#2'
		)
	);

	public static function executeTest( IStorage $storage, $testName ) {
		$p = explode('-', $testName);	
		$testFunc = $p[0];
		$testNum = $p[1];
		
		switch ($testFunc) {
			case 'renders':
				$testSets = self::$testSetsRenders;
				$res = self::tryRender($testSets[ $testNum ][ 'data' ], $storage);
			break;
			case 'filter':
				$testSets = self::$testSetsFilter;
				$res = HtmlUtil::filter($testSets[ $testNum ][ 'data' ], $testSets[ $testNum ][ 'allowed' ]);
			break;
			default:
				throw new Exception( 'Valid tests: isValid, filter');
		}

		
		$result = array(
			'success' => $res === $testSets[ $testNum ][ 'expected' ],
			'expected' => $testSets[ $testNum ][ 'expected' ],
			'actual' => $res,
			'description' => $testSets[ $testNum ][ 'description' ] . ' ' . HtmlUtil::getAndClearLastInvalidMessage()
		);
		
		return $result;
	}
	
	private final static function tryRender( $data, $storage ) {
		$result = false;
		
		try {
			require_once STROOT . '/cache/class.FileCache.php';
			$cache = new FileCache();
		
			$gfx = new TestGFX( $cache, $storage);
			
			$result = $gfx->testCommand( $data );
		} catch (Exception $e) {
			$result = Debug::getStringRepresentation($e);
		}
		
		return $result;
	}

}