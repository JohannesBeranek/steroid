<?php
/**
*
* @package steroid\cli
*/

require_once STROOT . "/clihandler/class.CLIHandler.php";
require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/unittest/interface.IUnitTest.php';
require_once STROOT . '/util/class.Debug.php';

/**
 * 
 * @package steroid\cli
 *
 */
class CHUnitTest extends CLIHandler {

	// TODO: fix dependency execution order

	/**
	 * @var array stores all test classes found below WEBROOT
	 */
	protected $allTestClasses = array();

	/**
	 * @var array stores test classes to be used/executed
	 */
	protected $usedClasses = array();

	/**
	 * @var bool continue execution of tests even if one fails
	 */
	protected $forceAll = false;

	/**
	 * @var bool display list of all tests of specified test class (or all classes if none specified)
	 */
	protected $showTests = false;

	/**
	 * @var bool display expected and actual result for each test
	 */
	protected $verbose = false;

	public function performCommand($called, $command, array $params) {

		$resolvedParams = array();

		foreach ( $params as $param ) {

			switch($param) {
				case '-l':
				case '--show-tests':
					$this->showTests = true;
					break;
				case '-f':
				case '--force-all':
					$this->forceAll = true;
					break;
				case '-v':
				case '--verbose':
					$this->verbose = true;
					break;
				default:
					// first part is test class name, the rest are test names for that test class
					$temp = explode( ':', $param );
					$resolvedParams[ array_shift( $temp )] = $temp;
					break;
			}
		}

		if ( ! $this->getTestClasses( $resolvedParams ) ) {
			return EXIT_FAILURE;
		}

		if($this->showTests){
			$this->showTests();
			return EXIT_SUCCESS; // exit early as we don't need to do anything else here
		}

		foreach($this->usedClasses as $testClass){
			if(!$this->resolveDependencies( $testClass )){
				return EXIT_FAILURE;
			}
		}

		if ( !$this->executeTests( $this->usedClasses ) ) {
			return EXIT_FAILURE;
		}

		return EXIT_SUCCESS;
	}

	/**
	 * Used to show all available test classes and their tests
	 *
	 * Iterates over $this->usedClasses and echoes the class name and its available tests
	 * Only used in help mode
	 *
	 * @return string
	 */
	protected function showTests() {
		foreach($this->usedClasses as $className) {
			echo 'Class ' . UnitTest::className($className) . ' available tests: ' . "\n";

			foreach($className::getAvailableTests() as $testName) {
				echo $testName . "\n";
			}

			echo "\n";
		}
	}
	
	/**
	 * Returns all available test classes and their tests
	 *
	 * Iterates over $this->usedClasses and collects the class name and its available tests
	 * Only used for compgen
	 *
	 * @return string
	 */	
	protected function getAvailableTests() {
		$tests = array();
		
		foreach ( $this->usedClasses as $className ) {
			$classTests = array();
			
			foreach ( $className::getAvailableTests() as $testName ) {
				$classTests[] = $testName;
			}
			
			$tests[$className] = $classTests;
		}
		
		
		return $tests;
	}

	/**
	 * Iterates over specified array of classes and executes their tests
	 *
	 * Checks if tests exist, executes them, formats and echoes the results
	 *
	 * @param array $testClasses indexed array containing class names as values
	 *
	 * @return bool
	 */
	protected function executeTests( $testClasses ) {
		if(empty($testClasses)){
			return false;
		}

		foreach($testClasses as $testClass){

			echo "\n" . 'Running tests for class ' . UnitTest::className($testClass) . "\n";

			foreach($this->allTestClasses[$testClass]['tests'] as $test){

				if(!$testClass::hasTest($test)){
					echo UnitTest::fail( 'Class "' . UnitTest::className( $testClass ) . '" has no test "' . $test . '", aborting.');
					return false;
				}

				$result = $testClass::executeTest($this->storage, $test );

				$result['testName'] = $test;

				echo $this->formatResult($result);

				if(!$this->forceAll && !$result['success']){
					echo UnitTest::fail( "Test failed, aborting." );
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Formats result of each test for display
	 *
	 * @param array $result array containing success, expected result and actual result values return by each test
	 *
	 * @return string
	 */
	protected function formatResult( $result ){
		if ($result['success']) {
			$msg = UnitTest::success( $result[ 'testName' ] . ' successful' );
		} else {
			$msg = UnitTest::fail( $result[ 'testName' ] . ' NOT successful' );
		}

		if ($this->verbose) {
			if (array_key_exists('description', $result)) {
				$msg .= self::COLOR_DESCRIPTION . '   ' .$result['description'] . "\n" . self::COLOR_DEFAULT;
			}
			
			$msg .= '   Expected result: ' . Debug::getStringRepresentation($result['expected']) . "\n";
			$msg .= '   Actual result: ' . Debug::getStringRepresentation($result['actual']) . "\n";
		}

		return $msg;
	}

	/**
	 * Gets dependencies of the specified class and adds them to $this->usedClasses if it hasn't been included yet
	 *
	 * @param string $testClass name of a test class
	 */
	protected function resolveDependencies( $testClass ){

		$dependencies = $testClass::getDependencies();

		if(!empty($dependencies)){
			foreach($dependencies as $dependency){
				if(!isset( $this->allTestClasses[ $dependency ])){
					echo UnitTest::fail( 'Test class "' . UnitTest::className( $testClass ) . '" depends on class "' . UnitTest::className( $dependency ) . '" which could not be found!');
					return false;
				}

				if(!in_array($dependency, $this->usedClasses)){
					array_unshift($this->usedClasses, $dependency);
					return $this->resolveDependencies( $dependency );
				} else {
					$this->allTestClasses[ $dependency ][ 'tests' ] = $dependency::getAvailableTests();
				}
			}
		}

		return true;
	}

	/**
	 * Fetches and configures available and specified test classes
	 *
	 * Searches for files starting with "class.UT" and adds them to $this->allTestClasses
	 * If no parameters specifying test classes have been provided, or the a found test class was specified, it is added to $this->usedClasses
	 * Also sets the tests to be run for each class
	 *
	 * @param $dir Directory to search
	 * @param array $conf associative array with class names as index and arrays containing test names as values
	 */
	protected function getTestClasses( array $conf = NULL ){

		$classes = ClassFinder::getAll( ClassFinder::CLASSTYPE_UNITTEST, true );

		foreach($classes as $class){
			$classTests = NULL;

			//If no parameters specifying test classes have been provided, or the a found test class was specified, it is added to $this->usedClasses
			if ( empty( $conf ) || ( !empty( $conf ) && array_key_exists( $class[ClassFinder::CLASSFILE_KEY_CLASSNAME], $conf ) ) ) {
				if ( !in_array( $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME], $this->usedClasses ) ) {
					$this->usedClasses[ ] = $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME];
				}

				$classTests = !empty( $conf[ $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME] ] ) ? $conf[ $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME] ] : NULL;
			
				if ($classTests) {
					$allTests = $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME]::getAvailableTests();

					foreach ($classTests as $k => &$test) {
						if (strlen($test) > 0 && $test[0] == '^' && !in_array($test, $allTests, true)) {
							// if test did not match any available test, try regex search

							$newTests = preg_grep('/' . $test . '/', $allTests);

							if (!$newTests) {
								echo UnitTest::fail( 'Class "' . UnitTest::className( $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME] ) . '" has no test matching "' . $test . '", aborting.');
								return false;
							}

							foreach ($newTests as $ntest) {
								$classTests[] = $ntest;
							}

							unset($classTests[$k]);
						}
					}
				}
			}

			$testClass = array(
				'pathName' => $class[ ClassFinder::CLASSFILE_KEY_FULLPATH],
				'className' => $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME],
				'tests' => $classTests ? array_values($classTests) : $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME]::getAvailableTests()
			);

			$this->allTestClasses[ $class[ ClassFinder::CLASSFILE_KEY_CLASSNAME] ] = $testClass;
		}

		return true;
	}
	
	public function getUsageText($called, $command, array $params) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' unittest command' => array(
				'usage:' => array(
					'unittest' => 'run all tests of all available test classes',
					'unittest testClass' => 'run all tests of specified test class',
					'unittest testClass:test1:test2' => 'run specified tests of specified test class'	
				),
				'options:' => array(
					'-f, --force-all' => 'continue execution of tests even if one fails',
					'-l, --show-tests' => 'display list of tests for specified test class (or all classes if none specified)',
					'-v, --verbose' => 'display expected and actual result for each test'
				)
			)
		));
	}
	
	public function compgen($called, $command, array $params) {
		$this->getTestClasses(  );
		
		$availableTests = $this->getAvailableTests();

		$availableCommands = array();
		
		foreach ($availableTests as $testClass => $testClassTests) {
			$availableCommands[] = $testClass;
			
			foreach ($testClassTests as $testName) {
				$availableCommands[] = $testClass . ":" . $testName;
			}
		}

		return implode(' ', $availableCommands);	
	}
}



