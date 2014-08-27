<?php
/**
* @package steroid\html
*/

require_once STROOT . '/unittest/class.UnitTest.php';
require_once STROOT . '/html/class.HtmlUtil.php';

/**
* @package steroid\html
*/
class UTHtmlUtil extends UnitTest {
	public static function getAvailableTests() {
		$tests = array();	
			
		for ($i = 0, $len = count(self::$testSetsValid); $i < $len; $i++) {
			$tests[] = 'isValid-' . $i;
		}
		
		for ($i = 0, $len = count(self::$testSetsFilter); $i < $len; $i++) {
			$tests[] = 'filter-' . $i;
		}
		
		return $tests;
	}
	
	protected static $testSetsValid = array(
		array(
			'data' => '',
			'allowed' => array(),
			'expected' => true,
			'description' => 'Empty String'
		),
		array(
			'data' => 'asd',
			'allowed' => array(),
			'expected' => true,
			'description' => 'String without tags / comments'
		),	
		array(
			'data' => '<!>',
			'allowed' => array(),
			'expected' => false,
			'description' => 'Empty comment'
		),
		array(
			'data' => '<br>',
			'allowed' => array(),
			'expected' => false,
			'description' => 'HTML br tag without "/", nothing allowed'
		),
		array(
			'data' => '<br>',
			'allowed' => array( 'br' ),
			'expected' => true,
			'description' => 'HTML br tag without "/", br is allowed'
		),
		array(
			'data' => '<br>',
			'allowed' => array( 'br' => null ),
			'expected' => true,
			'description' => 'HTML br tag without "/", br is allowed and passed as key with null value'
		),
		array(
			'data' => '<br>',
			'allowed' => array( 'br' => array() ),
			'expected' => true,
			'description' => 'HTML br tag without "/", br is allowed and passed as key with empty array as value'
		),
		array(
			'data' => '</br>',
			'allowed' => array( 'br' => null ),
			'expected' => false,
			'description' => 'HTML br closing tag without opening tag, br is allowed'
		),
		array(
			'data' => '<br/>',
			'allowed' => array( 'br' => null ),
			'expected' => true,
			'description' => 'HTML self closing br tag, br is allowed'
		),
		array(
			'data' => '<div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div tag without "/" and without closing tag, div is allowed'
		),
		array(
			'data' => '<div></div>',
			'allowed' => array( 'div' ),
			'expected' => true,
			'description' => 'HTML div tag with closing tag, div is allowed'
		),
		array(
			'data' => '<div></div></div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div tag with 2 closing tags, div is allowed'
		),
		array(
			'data' => '<div><div></div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div tag with 2 opening, and 1 closing tag, div is allowed'
		),
		array(
			'data' => '<div><br></div>',
			'allowed' => array( 'div', 'br' ),
			'expected' => true,
			'description' => 'HTML div tag enclosing br tag, div and br are allowed'
		),		
		array(
			'data' => '<div><span></span></div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div tag with 2 closing tags, div is allowed'
		),		
		array(
			'data' => '<div class="test"></div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div with class attribute, div is allowed without attributes set'
		),		
		array(
			'data' => '<div class="test"></div>',
			'allowed' => array( 'div' => array( 'class' ) ),
			'expected' => true,
			'description' => 'HTML div with class attribute, div is allowed with class attribute'
		),
		array(
			'data' => '<ul><li>List item 1</li><li>List item 2</li></ul>',
			'allowed' => array( 'ul' => NULL, 'li' => NULL ),
			'expected' => true,
			'description' => 'Unsorted list with 2 items, ul+li allowed'
		)
		
	);
	
	protected static $testSetsFilter = array(
		array(
			'data' => '<div></div><div>',
			'allowed' => array( 'div' ),
			'expected' => '<div></div><div></div>',
			'description' => 'Empty div tag, div allowed'
		),
		array(
			'data' => '<div></div><div>',
			'allowed' => array( ),
			'expected' => '',
			'description' => 'Empty div tag, div not allowed'
		)
	);
	
	public static function executeTest( IStorage $storage, $testName ) {
		$p = explode('-', $testName);	
		$testFunc = $p[0];
		$testNum = $p[1];
		
		switch ($testFunc) {
			case 'isValid':
				$testSets = self::$testSetsValid;
				$res = HtmlUtil::isValid($testSets[ $testNum ][ 'data' ], $testSets[ $testNum ][ 'allowed' ]);		
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
	

}

?>