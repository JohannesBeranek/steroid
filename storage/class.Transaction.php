<?php 
/**
 * @package steroid
 */

require_once __DIR__ . '/interface.ITransactionBased.php';

/**
 * @package steroid
 */
class Transaction {
	protected $active;
	
	/** @var int */
	protected $level;
	
	/** @var string */
	protected $name;
	
	/** @var ITransactionBased */
	protected $transactionTarget;
	
	public function __construct( ITransactionBased $transactionTarget, $level, $name = null ) {
		$this->transactionTarget = $transactionTarget;
		$this->level = $level;
		$this->name = $name;
		
		$this->active = true;
	}
	
	public function commit() {
		$this->finish();
		
		$this->transactionTarget->commit( $this );
	}
	
	public function rollback() {
		$this->finish();
		
		$this->transactionTarget->rollback( $this );
	}
	
	public function isActive() {
		return $this->active;
	}
	
	protected function finish() {
		if (! $this->active) {
			throw new TransactionInactiveException('Transaction not active');
		}
		
		$this->active = false;
	}
	
	public function __destruct() {
		if ($this->active) {
			try {
				$this->transactionTarget->release( $this );
			} catch(Exception $e) {
				error_log(get_class($e)." thrown within __destruct of Transaction. Message: ".$e->getMessage(). "  in " . $e->getFile() . " on line ".$e->getLine());
				error_log('Exception trace stack: ' . $e->getTraceAsString());
			}
			
			$this->active = false;
		}
	}
	
	public function __get( $name ) {
		switch($name) {
			case 'level': return $this->level;
			case 'name': return $this->name;
		}
	
		throw new InvalidArgumentException('Unknown property "' . $name . '"');
	}	
}

class TransactionInactiveException extends Exception {}