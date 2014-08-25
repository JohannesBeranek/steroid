<?php
/**
*
* @package steroid\cli
*/

require_once __DIR__ . '/class.CLIHandler.php';

/**
 * 
 * @package steroid\cli
 *
 */
class CHCompgen extends CLIHandler {
	public function performCommand($called, $command, array $params) {
		$command = array_shift($params);
		
		$cliHandler = CLIHandler::getCLIHandler($this->storage, $command);
		
		$compgen = $cliHandler->compgen( $called, $command, $params );
		
		echo $compgen;
		
		return EXIT_SUCCESS;
	}
	
	public function getUsageText($called, $command, array $params) {		
		$availableCommands = $this->getAvailableCommands();
		
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' compgen command' => array(
				'compgen [COMMAND]' => 'get compgen bash autocomplete list for command'
			)
		));
	}
	
	public function compgen($called, $command, array $params) {
		$availableCommands = $this->getAvailableCommands();

		return implode(' ', $availableCommands);	
	}	
}