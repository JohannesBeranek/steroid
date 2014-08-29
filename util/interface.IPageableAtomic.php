<?php

interface IPageableAtomic  {
	public function getAllItems();
	public function getTotal();
	public function setRange( $start, $count );
	public function getItems();
}

?>