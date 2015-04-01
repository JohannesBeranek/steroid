<?php


interface ISwitchUser {
	public function switchUser( RCUser $user );
	public function maySwitchUser();
	public function unswitchUser( $data );
}
