<?php

require_once STROOT . '/clihandler/class.CLIHandler.php';

require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/html/class.DTHtml.php';

class CHHtml extends CLIHandler {
	public function performCommand($called, $command, array $params) {
		if (count($params) < 1) {
			$this->notifyError($this->getUsageText($called, $command, $params));
			return EXIT_FAILURE;
		}

		$cmd = array_shift($params);

		switch ($cmd) {
			case 'clean':
				if (count($params) < 1) {
					$this->notifyError($this->getUsageText($called, $command, $params));
					return EXIT_FAILURE;
				}

				$dryRun = false;
				$processAtOnce = 100;

				while($param = array_shift($params)) {
					switch ($param) {
						case '--dry-run':
							$dryRun = true;
						break;
						case '-n':
							$processAtOnce = intval(array_shift($params));

							if ($processAtOnce <= 0) {
								$this->notifyError($this->getUsageText($called, $command, $params));
								return EXIT_FAILURE;
							}
						break;
						default:
							if (isset($path)) {
								$this->notifyError($this->getUsageText($called, $command, $params));
								return EXIT_FAILURE;
							}

							list($recordClass, $field) = explode('.', $param);

							if (!$recordClass || !$field) {
								$this->notifyError($this->getUsageText($called, $command, $params));
								return EXIT_FAILURE;
							}
					}
				}

				if (!isset($recordClass) || !isset($field)) {
					$this->notifyError($this->getUsageText($called, $command, $params));
					return EXIT_FAILURE;
				}

				if ( ! $this->clean( $recordClass, $field, $dryRun, $processAtOnce ) ) {
					return EXIT_FAILURE;
				}


			break;
			default:
				$this->notifyError($this->getUsageText($called, $command, $params));
				return EXIT_FAILURE;
		}
	
		return EXIT_SUCCESS;
	}
	
	public function clean( $recordClass, $field, $dryRun = false, $processAtOnce = 100 ) {
		// TODO: check if $recordClass has a field $field

		// make sure garbage collection is enabled so we won't as easily run out of memory
		gc_enable();

		$this->storage->init();

		ClassFinder::find($recordClass, true);

		$total = $this->storage->select( $recordClass, array(), 0, 0, true );

		if ($dryRun) {
			echo "Dry run - will only display what would be done and not save anything\n";
		} else {
			echo "!!! CHANGES WILL ACTUALLY BE SAVED !!!\n";
		}

		echo "Processing up to $total records of type $recordClass\n";
		echo "Press return to continue, or ctrl+c to abort\n";
		fgetc(STDIN);

		// should be selectable
		$allowed = DTHtml::$defaultAllowed;

		$processed = 0;
		$changed = 0;

		echo "Processing ...\n";

		while ($processed < $total) {
			$tx = $this->storage->startTransaction();

			try {

				$records = $this->storage->select( $recordClass, array( 'fields' => array( $field ) ), $processed, $processAtOnce );

				foreach ($records as $recordValues) {
					$before = $recordValues[$field];

					if ($before != NULL) {
						$after = HtmlUtil::filter($before, $allowed);



						if ($after != $before) {
							echo "\nModifying record ${processed}\nFROM:\n",
								"----------------------------------------\n",
								$before,
								"\n",
								"----------------------------------------\n",
								"TO:",
								"----------------------------------------\n",
								$after,
								"\n\n";

							$changed ++;


							if ($dryRun) {
								echo "Dry run, change is not saved.\n";
							} else {
								echo "\n\n---------------\n\n";

								unset($recordValues[$field]);
								$record = $recordClass::get( $this->storage, $recordValues, true );
								$record->{$field} = $after;
								$record->save();

								echo "Saved record ${processed}.\n";

								// try to free up some memory
								$record->removeFromIndex();
							} 
						} else {
							echo "\rRecord ${processed} processed, no change";
						}
					}

					$processed ++;
					
				}

				$tx->commit();

			} catch(Exception $e) {
				$tx->rollback();
				throw $e;
			}

			// run a gc cycle to free memory
			gc_collect_cycles();
		}

		echo "\nProcessed $processed Records\n";

		if ($dryRun) {
			echo "${changed} Records would have been changed.\n";
		} else {
			echo "${changed} Records have been changed.\n";
		}

		return true;
	}

	public function getUsageText( $called, $command, array $params ) {
		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' ' . $command . ' command' => array(
				'usage:' => array(
					'php ' . $called . ' ' . $command . ' clean RecordClass.field [--dry-run] [-n Number]' => 'clean field of recordClass'
				)
			)
		) );

	}
	
}

