<?php
/**
 *
 * @package steroid\cli
 */

require_once STROOT . "/clihandler/class.CLIHandler.php";
require_once STROOT . '/util/class.ClassFinder.php';
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

	protected $doCoverage = false;

	public function performCommand( $called, $command, array $params ) {
		foreach($params as $param){
			switch($param){
				case '-c':
				case '--coverage':
					$this->doCoverage = true;
					break;
			}
		}

		$this->allTestClasses = ClassFinder::getAll( ClassFinder::CLASSTYPE_UNITTEST, true );

		echo static::COLOR_DESCRIPTION . "\nResolving dependencies\n" . static::COLOR_DEFAULT;

		foreach ( $this->allTestClasses as $className => $conf ) {
			if ( !in_array( $className, $this->usedClasses ) ) {
				if ( !$this->resolveDependencies( $className ) ) {
					return EXIT_FAILURE;
				}

				$this->usedClasses[ ] = $className;
			}
		}

		foreach ( $this->usedClasses as $className ) {

			echo static::COLOR_DESCRIPTION . "\n\n" . 'Executing test class ' . static::COLOR_CLASSNAME . $className . "\n" . static::COLOR_DEFAULT;

			$cmd = escapeshellcmd( STROOT . '/unittest/phpunit.phar' ) . ' --bootstrap ' . escapeshellarg( WEBROOT . '/steroid/unittest/bootstrap.php' );

			if ( $this->doCoverage ) {
				$cmd .= ' --coverage-html '. escapeshellarg( STROOT . '/unittest/reports/' . $className );
			}

			//TODO: delete existing reports
			//TODO: generate php reports and merge them into a single html: http://stackoverflow.com/questions/10167775/aggregating-code-coverage-from-several-executions-of-phpunit

			$cmd .= ' ' . escapeshellarg( $this->allTestClasses[$className][ 'fullPath' ] );

			$descriptorspec = array(
				0 => array( "pipe", "r" ), // stdin is a pipe that the child will read from
				1 => array( "pipe", "w" ), // stdout is a pipe that the child will write to
				2 => array( "pipe", "w" ) // stderr
			);

			$process = proc_open( $cmd, $descriptorspec, $pipes );

			if ( is_resource( $process ) ) {
				fclose( $pipes[ 0 ] );

				$out = stream_get_contents( $pipes[ 1 ] );
				fclose( $pipes[ 1 ] );

				fclose( $pipes[ 2 ] );

				$returnCode = proc_close( $process );
			} else {
				throw new Exception('Unable to start phpUnit');
			}

			if ( $returnCode !== 0 ) {
				error_log("PHPUnit failed test:\n" . $out);

				return EXIT_FAILURE;
			}

			echo $out;
		}

		return EXIT_SUCCESS;
	}

	protected function resolveDependencies( $testClass ) {
		$dependencies = $testClass::$dependencies;

		echo "Class " . static::COLOR_CLASSNAME . $testClass . static::COLOR_DEFAULT . " has " . count($dependencies) . " dependencies\n";

		if ( !empty( $dependencies ) ) {
			foreach ( $dependencies as $dependency ) {
				if ( !isset( $this->allTestClasses[ $dependency ] ) ) {
					throw new Exception( 'Test class "' . $testClass . '" depends on class "' . $dependency . '" which could not be found!' );
					return false;
				}

				if ( !in_array( $dependency, $this->usedClasses ) ) {
					array_unshift( $this->usedClasses, $dependency );
					return $this->resolveDependencies( $dependency );
				}
			}
		}

		return true;
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' unittest command' => array(
				'usage:' => array(
					'unittest' => 'run all tests of all available test classes'
				),
				'options:' => array(
					'-c, --coverage' => 'generate coverage report(s)'
				)
			)
		) );
	}

	public function compgen( $called, $command, array $params ) {

	}
}

class PHPUnit_Framework_TestCase {
	// empty class to enable requiring test classes without having to include all the phpUnit classes
}




