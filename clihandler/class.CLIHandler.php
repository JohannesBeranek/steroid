<?php
/**
*
* @package steroid\cli
*/

require_once __DIR__ . '/interface.ICLIHandler.php';

/**
 * @package steroid\cli
 */
abstract class CLIHandler implements ICLIHandler {
	/** @var IRBStorage */
	protected $storage;

	/** @var bool */
	protected $gotLock;
	

	const RESULT_COLOR_SUCCESS = "\033[0;32m";
	const RESULT_COLOR_WARNING = "\033[0;33m";
	const RESULT_COLOR_FAILURE = "\033[0;31m";
	const COLOR_DEFAULT = "\033[0;0m";
	const COLOR_CLASSNAME = "\033[0;36m";
	const COLOR_DESCRIPTION = "\033[0;35m";
	
	/**
	 * String prepended to arguments print in usage message.
	 * 
	 * @var string
	 */
	const USAGE_ARGUMENT_INDENT = '  ';
	
	public function __construct( IRBStorage $storage ) {
		session_id('__cli__'); // fixes dumb facebook sdk trying to start session without session_id() set
		$this->storage = $storage;
	}
	
	/**
	 * 
	 * @param array $unknown
	 * @param string $called
	 * @param string $command
	 * @param array $params
	 * 
	 * @return void
	 */
	protected function handleUnknown( array $unknown, $called, $command, array $params ) {
		$this->notifyError( 'Unknown parameters: ' . implode( '', $filteredParams ) . "\n" . getUsageText( $called, $command, $params ) );
		

	}
	
	/**
	 * 
	 * @param string $value
	 * @return void
	 */
	protected function notifyError( $value ) {
		fwrite( STDERR, $value );
		
		// TODO: log?
	}
	
	/**
	 * 
	 * @param array $arguments
	 * @param int $maximumLineWidth
	 * 
	 * @throws InvalidArgumentException
	 * 
	 * @return string
	 */
	protected function formatUsageArguments( array $arguments, $maximumLineWidth = NULL, $startLevel = 0 ) { // 80x24 = default linux terminal size
		if ($maximumLineWidth === NULL) {
			$maximumLineWidth = exec('tput cols');
		} else if ($maximumLineWidth <= 0) {
			throw new InvalidArgumentException('$maximumLineWidth must be > 0');
		}
		
		$argumentSpace = 0;
		
		$indent = self::USAGE_ARGUMENT_INDENT;
		
		// find longest argument
		$getArgumentSpace = function( $n, $level ) use ( &$getArgumentSpace, &$argumentSpace, $indent ) {
			foreach ($n as $argument => $description) {
				if (is_array($description)) {
					$getArgumentSpace( $description, $level + 1 );
				} else {
					$argumentSpace = max(strlen($argument) + strlen($indent) * $level, $argumentSpace);
				}
			}
		};
		
		$getArgumentSpace( $arguments, $startLevel );
		
		
		
		// add spaces between descriptions and arguments
		$argumentSpace += 3;
		
	

		$descriptionIndent = $argumentSpace;
		$descriptionWidth = $maximumLineWidth - $descriptionIndent;
		
		
		$ret = '';
		
		$format = function( $arguments, $level ) use ( &$format, &$ret, $indent, $argumentSpace, $descriptionIndent, $descriptionWidth ) {
			$i = 0;
			
			$isIndexed = !(bool)count(array_filter(array_keys($arguments), 'is_string'));
			
			$argumentIndent = str_repeat($indent, $level);
				
			foreach ($arguments as $argument => $description) {	
				
				if (is_array($description) && $i > 0) {
					$ret .= "\n";
				}
				
				$i++;
				
				if ($isIndexed) {
					if (!is_array($description)) {
						$argument = $description;
						$description = '';
					} else {
						$argument = '';
					}
				}
				
				$argument = trim($argument);
					
				$ret .= $argumentIndent . str_pad($argument, $argumentSpace - strlen($argumentIndent));
					
				if (is_array($description)) {
					$ret .= "\n";
					$format( $description, $level + 1 );
				} else {
					$description = trim($description);
					
					if ($description != '') {
						if (strlen($description) > $descriptionWidth) {
							$descriptionLines = explode("\n", wordwrap($description, $descriptionWidth, "\n", true));
								
							$ret .= array_shift($descriptionLines) . "\n";
								
							foreach($descriptionLines as $descriptionLine) {
								$ret .= str_repeat(' ', $descriptionIndent) . $descriptionLine . "\n";
							}
								
						} else {
							$ret .= $description . "\n";
						}
				
					} else {
						$ret .= "\n";
					}
				}
			}
		};
		
		$format( $arguments, $startLevel );
		
		
		
		return $ret;
	}
	
	protected function getCommandFromClassName( $className ) {
		return strtolower(substr($className, 2));
	}
	
	protected static function getLockKey() {
		static $key;
		
		if ($key === NULL) {
			$key = ST::PRODUCT_NAME . '_' . fileinode(WEBROOT) . '_' . get_called_class();		
		}
		
		return $key;
	}
	
	// Locking functionality
	protected function waitLock( $timeout ) {
		if ($this->gotLock) return true;
		
		$key = static::getLockKey();
		
		if ($this->storage->getLock( $key, $timeout ) == 1) {
			$this->gotLock = true;
	
			// make sure we unlock on shutdown
			register_shutdown_function(array($this, 'unlock'));
			
			return true;
		} 
				
		$this->gotLock = false;
		return false;
	}

	protected function tryLock() {
		return $this->waitLock( 0 );
	}
	
	/**
	 * Returns whether lock is free right now
	 */
	protected function getLockStatus() {
		$key = static::getLockKey();
		
		return (bool)$this->storage->isFreeLock( $key );
	}
	
	/**
	 * Unlock (if we got the lock)
	 * 
	 * needs to be public to be callable by shutdown function
	 */
	public function unlock() {
		if ($this->gotLock) {
			$key = static::getLockKey();
			
			if ($this->storage->releaseLock($key)) {
				$this->gotLock = false;	
			} else {
				error_log( 'Unable to release acquired lock "' . $key . '"' );
			}		
		}
	}
	
	/**
	 * Make sure we unlock on destruct
	 */
	public function _destruct() {
		$this->unlock();
	}
	
	protected function getAvailableCommands() {
		$classes = ClassFinder::getAll(ClassFinder::CLASSTYPE_CLIHANDLER);
		
		$availableCommands = array_map(array($this, 'getCommandFromClassName'), array_keys($classes));
		
		return $availableCommands;
	}
	
	public static function getCLIHandler( $storage, $command ) {
		$classFile = STROOT . '/clihandler/class.CLIHandlerDefault.php';
		$className = 'CLIHandlerDefault';
		
		$prefix = ClassFinder::CLASSTYPE_CLIHANDLER;
		$classes = ClassFinder::getAll($prefix, false);
		
		// FIXME: use StringFilter
		$compare = strtolower($prefix . $command);
		
		foreach ($classes as $class => $classDef) {
			if (strtolower($class) == $compare) {
				$classFile = $classDef[ ClassFinder::CLASSFILE_KEY_FULLPATH ];
				$className = $classDef[ ClassFinder::CLASSFILE_KEY_CLASSNAME ];
				break;
			}
		}
		
		require_once $classFile;
		$cliHandler = new $className( $storage );
		
		return $cliHandler;
	}
	
	public static function parseParams( $passedParams ) {
		$params = array();
		
		foreach ($passedParams as $param) {
			if (strlen($param) > 2 && substr($param, 0, 1) == '-' && substr($param, 1, 1) != '-') {
				for ($i = 1, $len = strlen($param); $i < $len; $i++) {
					$params[] = '-' . $param[$i];
				}
				
				
			} else {
				$params[] = $param;
			}
		}
		
		return $params;
	}
	
	public function compgen( $called, $command, array $params ) {
		return '';
	}
}
