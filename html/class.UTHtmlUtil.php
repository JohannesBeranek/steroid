<?php
/**
* @package steroid\html
*/

require_once STROOT . '/html/class.HtmlUtil.php';

/**
* @package steroid\html
*/
class UTHtmlUtil extends PHPUnit_Framework_TestCase {
	static $dependencies = array();
	
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
			'data' => '<div><span></div>',
			'allowed' => array( 'div', 'span' ),
			'expected' => false,
			'description' => 'HTML span tag not closed correctly'
		),
		array(
			'data' => '<div></span></div>',
			'allowed' => array( 'div', 'span' ),
			'expected' => false,
			'description' => 'HTML span tag not opened correctly'
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

	protected static $testSetsRepair = array(
		array(
			'data' => '<div><div></div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div tag with 2 opening, and 1 closing tag, div is allowed'
		),
		array(
			'data' => '<div></span></div>',
			'allowed' => array( 'div', 'span' ),
			'expected' => false,
			'description' => 'HTML span tag not opened correctly'
		),
		array(
			'data' => '<div><span></div>',
			'allowed' => array( 'div', 'span' ),
			'expected' => false,
			'description' => 'HTML span tag not closed correctly'
		),
		array(
			'data' => '<div></div></div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div tag with 2 closing tags, div is allowed'
		),
		array(
			'data' => '<div>',
			'allowed' => array( 'div' ),
			'expected' => false,
			'description' => 'HTML div tag without "/" and without closing tag, div is allowed'
		),
		array(
			'data' => '</br>',
			'allowed' => array( 'br' => null ),
			'expected' => false,
			'description' => 'HTML br closing tag without opening tag, br is allowed'
		),
	);

	protected static $testSetHtrunc = array(
		array(
			'data' => '<b>Landesrätin</b><br><br>Sprecherin der Grünen Frauen Tirol <a href="http://frauen.tirol.gruene.at">frauen.tirol.gruene.at</a>&nbsp;<br><a href="PageRecord 67109634"><b>Biografie und Infos</b>&nbsp;</a><a href="mailto:christine.baur@gruene.at">christine.baur@gruene.at</a>​',
			'length' => 255,
			'expected' => 255,
			'description' => 'HTML longer than 255 characters should be truncated to shorter than or exactly 255'
		)
	);
	
	public function testHTML() {
		$testSets = array(
			'valid' => static::$testSetsValid,
			'filter' => static::$testSetsFilter,
			'repair' => static::$testSetsRepair,
			'htrunc' => static::$testSetHtrunc
		);

		foreach($testSets as $type => $set){
			switch ( $type ) {
				case 'valid':
					foreach ( $set as $conf ) {
						$res = HtmlUtil::isValid( $conf[ 'data' ], $conf[ 'allowed' ] );

						$this->assertEquals($conf['expected'], $res);
					}
					break;
				case 'filter':
					foreach ( $set as $conf ) {
						$res = HtmlUtil::filter( $conf[ 'data' ], $conf[ 'allowed' ] );

						$this->assertEquals( $conf[ 'expected' ], $res );
					}
					break;
				case 'repair':
					foreach ( $set as $conf ) {
						$res = HtmlUtil::repair( $conf[ 'data' ] );

						$isValid = HtmlUtil::isValid( $res, $conf[ 'allowed' ] );

						if ( !$isValid ) {
							echo HtmlUtil::getLastInvalidMessage();
						}

						$this->assertEquals( true, $isValid );
					}

					break;
				case 'htrunc':
					foreach($set as $conf){
						$res = HtmlUtil::htrunc($conf['data'], $conf['length'], true, false, NULL, true, false, false);

						$this->assertEquals($conf['expected'], mb_strlen($res, 'UTF-8'));
					}
					break;
			}
		}
	}
	

}
