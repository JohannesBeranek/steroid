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
			case 'append':
				if ( count( $params ) < 3 ) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;	
				}
				
				$this->storage->init();
				
				$parentDomainGroupPrimary = (int)array_pop( $params );
				
				if ($parentDomainGroupPrimary <= 0) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;	
				}
				
				$parentDomainGroup = $this->storage->fetchRecord('RCDomainGroup', array( 'fields' => '*', 'where' => array( 'primary', '=', array($parentDomainGroupPrimary))));
				
				if ($parentDomainGroup === NULL) {
					$this->notifyError( $this->getUsageText( $called, $command, $params ) );
					return EXIT_FAILURE;	
				}
				
				reset( $params );
				while( $domainGroup = next($params) ) {
					if ( !$this->appendDomainDroup( (int)$domainGroup, $parentDomainGroup ) )
						return EXIT_FAILURE;
				}
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
						'delete DOMAINGROUP_PRIMARY [DOMAINGROUP_PRIMARY ...]' => 'delete specified domaingroup',
						'append DOMAINGROUP_PRIMARY [DOMAINGROUP_PRIMARY ...] PARENT_DOMAINGROUP_PRIMARY' => 'make domaingroup child of parent domaingroup'
					)
				)
			)
		) );
	}
	
	final private function listDomainGroups() {
		$this->storage->init();
					
		$domainGroups = $this->storage->selectRecords( 'RCDomainGroup', array( 'fields' => array( 'primary', 'title', 'parent' ) ) );
				
		$sorted = $this->listDomainGroupsSort( NULL, $domainGroups );
		
		
		$listHelper = function( $arr, $indent = 0 ) use (&$listHelper) {
			foreach ($arr as $domainGroupArr) {
				$domainGroup = $domainGroupArr['domainGroup'];
			
				echo str_repeat("\t", $indent) . str_pad( $domainGroup->primary, 10, " ", STR_PAD_LEFT ) . " " . $domainGroup->getTitle() . "\n";
				
				if ($domainGroupArr['children']) {
					$listHelper( $domainGroupArr['children'], $indent + 1 );
				}
			}
		};
		
		$listHelper( $sorted );
		
	
	}
	
	final private function listDomainGroupsSort( $parent, $domainGroups ) {
		$children = array();
		
		foreach ($domainGroups as $k => $domainGroup) {
			if ($domainGroup->parent === $parent) {
				$child = array( 'domainGroup' => $domainGroup );
				
				$child['children'] = $this->listDomainGroupsSort( $domainGroup, $domainGroups );
				
				$children[] = $child;
			}
		}
		
		return $children;
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
			try {
				if ($tx->isActive()) {
					$tx->rollback();
				}
			} catch ( Exception $rollbackException ) {
				// might cause exception "transaction is not active"
				// if transaction takes too long
				
				Log::write($e);
				throw $rollbackException;
			}
			
			throw( $e );
		}
		
		echo "\n\tDeleted domainGroup with primary " . $domainGroupPrimary . "\n";
		
		return true;
	}
	
	final private function appendDomainGroup( $domainGroupPrimary, $parentDomainGroup ) {
		$domainGroup = $this->storage->fetchRecord('RCDomainGroup', array( 'fields' => '*', 'where' => array( 'primary', '=', array($domainGroupPrimary))));
		
		$domainGroup->parent = $parentDomainGroup;
		
		echo "\nAppending domainGroup " . $domainGroup->getTitle() . " to domainGroup " . $parentDomainGroup->getTitle();
		
		// only changing a single value here, no need for transaction
		$domainGroup->save();
		
		echo "\nDone.\n";
		
		return true;
	}

	private $recordsDeleted = 0;

	public function recordHookAfterDelete( IRBStorage $storage, IRecord $record, array &$basket = NULL ) {
		$this->recordsDeleted ++;
		
		echo "\r" . str_pad(str_repeat(".", $this->recordsDeleted % 5), 5, " ", STR_PAD_RIGHT) . $this->recordsDeleted . " Records deleted";
	}
}