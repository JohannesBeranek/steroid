<?php
/**
* Interface for CLIHandler
*
* @package steroid\cli
*/

require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/cli/clidefines.php';


/**
 * Interface for CLIHandler
 * 
 * @package steroid\cli
 */
interface ICLIHandler {
	public function __construct( IRBStorage $storage );
	
	/**
	 * Performs a given command
	 * 
	 * @param string $called
	 * @param string $command
	 * @param string[] $params
	 * @return int exitcode
	 */
	public function performCommand( $called, $command, array $params );
	
	public function compgen( $called, $command, array $params );
	
	/**
	 * Get help text
	 * 
	 * @param string $called
	 * @param string $command
	 * @param string[] $params
	 * @return string
	 */
	
	public function getUsageText( $called, $command, array $params );
}


?>