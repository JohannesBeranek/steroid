<?php
use \Codeception\Util\Locator;

class BECommons {
	public static $username = 're5et@gmx.at';
	public static $password = 'get4lif3.';

	public static function logIn( $I ) {
		$I->wantTo( 'login to the backend' );
		$I->amOnPage( '/steroid' );

		$I->waitForElement( '#steroid form', 30 );

		$I->fillField( 'Username', self::$username );
		$I->fillField( 'Password', self::$password );

		$I->submitForm( '#steroid form', array() );

		$I->wait( 5 );

		$I->cantSee( 'Login incorrect' );

		$I->waitForText( 'Logout', 20 );
	}
}

?>