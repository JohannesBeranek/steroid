<?php
$I = new AcceptanceTester( $scenario );

BECommons::logIn($I);

$I->seeElement('.STModule_content');

$I->click('.STModule_content');

$I->see( 'Page' );

$I->seeElement('tr[aria-label=Page]');