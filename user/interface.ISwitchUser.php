<?php


interface ISwitchUser {
	public function switchUser( $userID );
	public function maySwitchUser();
	public function unswitchUser( $data );
}
