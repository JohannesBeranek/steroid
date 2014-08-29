<?php

interface IPageable {
	public function getAllItems();
	public function getTotal();
	public function getItems( $start, $count );
}

?>