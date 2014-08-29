<?php

require_once STROOT . '/template/class.Template.php';

interface IHandleArea {
	public function handleArea( array $data, Template $template );
}
