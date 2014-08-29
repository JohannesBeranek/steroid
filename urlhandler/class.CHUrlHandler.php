<?php
/*
 * @package steroid\urlhandler
 */

require_once STROOT . '/clihandler/class.CLIHandler.php';
 
require_once STROOT . '/util/class.ClassFinder.php';

require_once STROOT . '/file/class.Filename.php';
require_once STROOT . '/urlhandler/class.RCUrlHandler.php';
 
/*
 * @package steroid\urlhandler
 */
 
class CHUrlHandler extends CLIHandler {
	
	public function performCommand( $called, $command, array $params ) {
		if (count($params) < 1) {
			$this->notifyError( $this->getUsageText($called, $command, $params));
			return EXIT_FAILURE;
		}
		
		$this->storage->init();
		
		
		switch( $params[0] ) {
			case 'sync':
				$transaction = $this->storage->startTransaction();
				
				$uhs = ClassFinder::getAll( ClassFinder::CLASSTYPE_URLHANDLER );
				
				$uhRecords = array();
				
				// make sure existing are in db
				foreach ($uhs as $uh) {
					$uhclass = $uh[ClassFinder::CLASSFILE_KEY_CLASSNAME];
					$uhpath = Filename::getPathWithoutWebroot($uh[ClassFinder::CLASSFILE_KEY_FULLPATH]);
					
					
					if (!( $uhRecord = $this->storage->selectFirstRecord( 'RCUrlHandler', array( 'where' => array( 'className', '=', array( $uhclass ) ) ) ) ) ) {
						$uhRecord = RCUrlHandler::get( $this->storage, array( 'className' => $uhclass ), false );
					}
					
					$uhRecord->filename = $uhpath;
					$uhRecord->save();
					
					$uhRecords[] = $uhRecord;
				}
				
				// delete not found ones
				$existingRecords = $this->storage->selectRecords( 'RCUrlHandler' );
				
				foreach ($existingRecords as $existingRecord) {
					$found = false;
					
					foreach ($uhRecords as $uhRecord) {
						if ($uhRecord == $existingRecord) {
							$found = true;
							break;
						}
					}
					
					if (!$found) {
						$existingRecord->delete();
					}
				}
				
				$transaction->commit();
				
				echo "Sync complete. " . count($uhRecords) . " UrlHandler.\n";
				
			break;
		}
		
		return EXIT_SUCCESS;
	} 
	
	public function getUsageText($called, $command, array $params) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' urlhandler command:' => array(
				'usage:' => array(
					'php ' . $called . ' urlhandler sync' => 'sync urlhandler'
				)
			)
		));
	}
}

?>