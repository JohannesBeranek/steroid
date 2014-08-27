<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
 
require_once "vendor/autoload.php";
 
// used to automatically generate env map
use Symfony\Component\Yaml\Parser;

 
class RoboFile extends \Robo\Tasks {
	use \Codeception\Task\MergeReports;
	use \Codeception\Task\SplitTestsByGroups;
	

	private $envMap;
	private $projectRoot = 'vendor/bin';
	
	public function __construct() {		
		// parse yml to generate env map
		$yaml = new Parser();
		
		// may throw exception in case of parse error
		$value = $yaml->parse( file_get_contents( __DIR__ . '/vendor/bin/tests/acceptance.suite.yml' ) );			

		$this->envMap = array();
		
		foreach ($value['env'] as $name => $env) {
			$this->envMap[$name] = $env;
		}
	}

	public function parallelAll() {
		$this->parallelSplitTests();
		$result = $this->parallelRun();
		$this->parallelMergeResults();
		
		return $result;
	}

	public function parallelSplitTests() {
		$this->taskSplitTestFilesByGroups( count( $this->envMap ) )
			->projectRoot( $this->projectRoot )
			->testsFrom( 'vendor/bin/tests/acceptance' )
			->groupsTo( 'vendor/bin/tests/_log/p' )
			->run();
	}

	public function parallelRun() {
		$parallel = $this->taskParallelExec();
		
		reset($this->envMap);
		
		for ($i = 1; $i <= count( $this->envMap ); $i++ ) {
			$currentEnv = key( $this->envMap );
			next( $this->envMap );	
			
			$parallel->process(
				$this->taskCodecept()
					->configFile( 'vendor/bin/codeception.yml' )
					->suite( 'acceptance' )
					->group( 'p' . $i )
					->env( $currentEnv )
					->xml( 'vendor/bin/tests/_log/results_' . $i . '.xml' )
			);
		}
		
		return $parallel->run();
	}

	public function parallelMergeResults() {
		$merge = $this->taskMergeXmlReports();
		
		for ( $i = 1; $i <= count( $this->envMap ); $i++ ) {
			$merge->from( 'vendor/bin/tests/_log/results_' . $i . '.xml' );
		}
		
		$merge->into( 'vendor/bin/tests/_log/result.xml' )
			->run();
	}
}