<?php 

require_once __DIR__ . '/class.CLIHandler.php';

require_once STROOT . '/util/class.ClassFinder.php';

class CHHelp extends CLIHandler {
	public function performCommand($called, $command, array $params) {
		$classes = ClassFinder::getAll(ClassFinder::CLASSTYPE_CLIHANDLER);
		
		$commandsToClassDefinitions = array_combine(array_map(array($this, 'getCommandFromClassName'), array_keys($classes)), $classes);

		if (empty($params)) {
			$params = array( 'help' );
		}
		
		$cmd = array_shift($params);
		
		if (array_key_exists($cmd, $commandsToClassDefinitions)) {
			$classDef = $commandsToClassDefinitions[$cmd];
			
			$classFile = $classDef[ClassFinder::CLASSFILE_KEY_FULLPATH];
			$className = $classDef[ClassFinder::CLASSFILE_KEY_CLASSNAME];
			
			
			require_once $classFile;
			$cliHandler = new $className( $this->storage );
			
			echo $cliHandler->getUsageText( $called, $cmd, $params );
		} else {
			echo '"' . $cmd . '" is not a valid command' . "\n";
		}
	
	}
	
	public function getUsageText($called, $command, array $params) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' help command:' => array(
				'usage:' => array(
					'help command' => 'prints help for specified command'		
				)	
			)
		));
	
	}
	
	public function compgen($called, $command, array $params) {
		$availableCommands = $this->getAvailableCommands();

		return implode(' ', $availableCommands);	
	}
	
}

