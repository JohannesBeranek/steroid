<?php
/**
 *
 * @package steroid\domaingroup
 */
 
require_once STROOT . '/clihandler/class.CLIHandler.php'; 

require_once __DIR__ . '/class.RCDomainGroup.php';

require_once STROOT . '/storage/record/interface.IRecordHookAfterDelete.php';

/**
 *
 * @package steroid\domaingroup
 */
class CHDomainGroup extends CLIHandler implements IRecordHookAfterDelete {
	public function performCommand( $called, $command, array $params ) {
		if (count($params) < 1) {
			$this->notifyError( $this->getUsageText( $called, $command, $params ) );
			return EXIT_FAILURE;
		}
		
		switch ($params[0]) {
			case 'list':
				if ( count( $params ) !== 1 ) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;
				} 
				
				$this->listDomainGroups();
			break;
			case 'delete':
				if ( count( $params ) < 2 ) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;
				}
				
				$this->storage->init();
				
				// skip first param, domaingroups are from $params[1] onwards
				reset( $params );
				while ( $domainGroup = next($params) )
					if ( !$this->deleteDomainGroup( (int)$domainGroup ) ) 
						return EXIT_FAILURE;
				
			break;
			default:
				$this->notifyError( $this->getUsageText( $called, $command, $params ) );
				return EXIT_FAILURE;
		}
		
		return EXIT_SUCCESS;
	}
	
	
	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' ' . $command . ' command' => array(
				'usage:' => array(
					'php ' . $called . ' ' . $command . ' COMMAND' => '',
					'available commands' => array(
						'list' => 'list available domainGroups with primary and title',
						'delete DOMAINGROUP_PRIMARY [DOMAINGROUP_PRIMARY ...]' => 'delete specified domaingroup'
					)
				)
			)
		) );
	}
	
	final private function listDomainGroups() {
		$this->storage->init();
					
		$domainGroups = $this->storage->select( 'RCDomainGroup', array( 'fields' => array( 'primary', 'title' ) ) );
		
		foreach ($domainGroups as $domainGroup) {
			echo str_pad( $domainGroup['primary'], 10, " ", STR_PAD_LEFT ) . " " . $domainGroup['title'] . "\n";
		}
	}
	
	final private function deleteDomainGroup( $domainGroupPrimary ) {
		$domainGroupRecord = $this->storage->selectFirstRecord( 'RCDomainGroup', array( 'fields' => '*', 'where' => array( 
			'primary', '=', array( $domainGroupPrimary )
		) ) );
		
		if (! $domainGroupRecord) {
			throw new Exception( 'No domainGroup record with primary ' . $domainGroupPrimary . ' found.' );
		}
		
		echo "Are you sure you want to delete domainGroup '" . $domainGroupRecord->getTitle() . "' ? [y/N]: ";
		flush();
		
		$confirmation = trim( fgets( STDIN ));
		echo "\n";
		
		if ( $confirmation !== 'y' && $confirmation !== 'Y' ) {
			echo "Canceled request to delete domainGroup '" . $domainGroupRecord->getTitle() . "'.\n";
			
			// continue with execution
			return true; 
		}
		
		echo "Starting transaction to delete domainGroup with primary " . $domainGroupPrimary . "\n";
		
		// add hooks so we can display some kind of progress
		Record::addHook($this, Record::HOOK_TYPE_AFTER_DELETE);
		
		$tx = $this->storage->startTransaction();
		
		try {
			$domainGroupRecord->delete();
			
			$tx->commit();
		} catch( Exception $e ) {
			$tx->rollback();
			throw( $e );
		}
		
		echo "\n\tDeleted domainGroup with primary " . $domainGroupPrimary . "\n";
		
		return true;
	}

	private $recordsDeleted = 0;

	public function recordHookAfterDelete( IRBStorage $storage, IRecord $record, array &$basket = NULL ) {
		$this->recordsDeleted ++;
		
		echo "\r" . str_pad(str_repeat(".", $this->recordsDeleted % 5), 5, " ", STR_PAD_RIGHT) . $this->recordsDeleted . " Records deleted";
	}
}