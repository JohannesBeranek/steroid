<?php
/**
*
* @package steroid\cli
*/

require_once STROOT . '/clihandler/class.CLIHandler.php';

require_once STROOT . '/storage/class.DBInfo.php';

/**
 * 
 * @package steroid\cli
 *
 */
class CHDBSync extends CLIHandler {

	protected $perfomUpdate = false;
	protected $forceUnsafeUpdate = false;
	protected $dropColumns = false;
	protected $resetTables = false;
	protected $insertStatic = false;
	protected $dropKeys = false;

	protected $recordClasses = array();

	public function performCommand( $called, $command, array $params ) {
		$this->storage->init();


		foreach ( $params as $param ) {

			switch ( $param ) {
				case '-u':
				case '--update':
					$this->perfomUpdate = true;
					break;
				case '-f':
				case '--force-unsafe':
					$this->forceUnsafeUpdate = true;
					break;
				case '-c':
				case '--drop-columns':
					$this->dropColumns = true;
					break;
				case '-r':
				case '--reset-tables':
					$this->resetTables = true;
					break;
				case '-i':
				case '--insert-static':
					$this->insertStatic = true;
					break;
				case '-k':
				case '--drop-keys':
					$this->dropKeys = true;
					break;
				default:
					$temp = explode( ':', $param );
					$this->recordClasses[ array_shift( $temp ) ] = $temp;
					break;
			}
		}

		if ( $this->insertStatic && !($this->resetTables && $this->perfomUpdate) ) {
			echo 'Cannot use parameter -i without -r and -u' . "\n";
			return;
		}

		if ($this->resetTables) {
			$checkString = "YES I AM AWARE OF WHAT I AM DOING";
			
			echo 'All tables will be dropped prior to performing anything else. Please type "' . $checkString . '" to continue, anything else to abort: ';
			$fr = fopen("php://stdin", "r");
			
			$input = fgets($fr, 128);
			$input = trim($input);
			fclose($fr);
			
			if ($input !== $checkString) {
				echo "Aborting.\n";
				return;		
			}
			
			$ct = 5;
			do {
				echo "\rWill perform reset in " . $ct . " seconds, press ctrl+c to abort...";
				sleep(1);
				$ct--;
			} while($ct > 0);
			
			echo "\nResetting tables. You have been warned.\n\n";
			sleep(1);
		}

		$dbInfo = new DBInfo( $this->storage, $this->recordClasses );

		$dbInfo->execute( $this->perfomUpdate, $this->forceUnsafeUpdate, $this->dropColumns, $this->resetTables, $this->insertStatic, $this->dropKeys );
	}
	
	public function getUsageText($called, $command, array $params) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' dbsync command' => array(
				'usage:' => array(
					'php ' . $called . ' dbsync' => 'compares all record classes and lists the differences between expected and actual table and column definitions',
					'php ' . $called . ' dbsync recordClassName' => 'compares and lists the differences between expected and actual table and column definitions for specified record class',
				),
				
				'options:' => array(
					'-u, --update' => 'performs update operation for compatible definitions',
					'-f, --force-unsafe' => 'performs update operation regardless of compatibility',
					'-c, --drop-columns' => 'drop columns that exist in table but are not specified in record class',
					'-k, --drop-keys' => 'drop keys that exist in table but are not specified in record class',
					'-r, --reset-tables' => 'drop all tables before performing other actions (! CAUTION !)',
					'-i, --insert-static' => 'insert static records (urlHandler, language,..), can only be used in conjunction with -r and -u'
				)
			)
		));
	}
}



?>
