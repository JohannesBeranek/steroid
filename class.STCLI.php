<?php
/**
 * Mainclass for Steroid CLI
 * @package steroid\cli
 */


require_once __DIR__ . "/class.ST.php";

require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/clihandler/class.CLIHandler.php';

class STCLI extends ST {	
	/** @var string */
	protected $called;
	
	/** @var string */
	protected $command;
	
	/** @var string[] */
	protected $params;	
	
	
	/**
	 * 
	 * @param IStorage $storage
	 * @param string[] $args
	 */
	
	public function __construct( Config $conf, IRBStorage $storage, array $args ) {
		parent::__construct($conf, $storage);
		
		
		$this->called = array_shift( $args );
		
		$this->parseGlobalParams( $args );
			
		
		$this->command = array_shift( $args );
		$this->params = $args;
	}
	
	final private function parseGlobalParams( array &$args ) {
		while( $args ) {
			switch ( $args[0] ) {
				// some php versions have faulty gc implementations
				case '--gc-disable':
					gc_disable();		
					array_shift($args);	
					echo "gc_disabe()\n";
				break;
				case '--mem-limit':
					array_shift($args);
					if ( is_numeric($args[0]) ) {
						$memLimit = array_shift($args);
						ini_set('memory_limit', $memLimit);
						echo "ini_set('memory_limit', " . $memLimit . ")\n";
					}
				break;
				default:
					return;
			}
		}
	}
	
	/**
	 * @return int
	 */
	public function __invoke() {
		require_once STROOT . '/cli/clidefines.php';
		
		try {
			$cliHandler = CLIHandler::getCLIHandler( $this->storage, $this->command );
			$params = CLIHandler::parseParams( $this->params );		
	
			$returnCode = $cliHandler->performCommand( $this->called, $this->command, $params );
		} catch( Exception $e ) { // catch other exceptions which may be unknown to us
			Log::write( $e );
			
			$returnCode = EXIT_FAILURE;
		}
		
		return $returnCode;
	}
}

class UnknownInstallTaskException extends Exception {}

class InvalidCLIHandlerException extends Exception {}