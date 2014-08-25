<?php

require_once __DIR__ . '/class.Transaction.php';

interface ITransactionBased {
	/**
	 * @return Transaction
	 */
	public function startTransaction();
	
	/**
	 * @param Transaction $transaction
	 */
	public function commit( Transaction $transaction );
	
	/**
	 * @param Transaction $transaction
	 */
	public function rollback( Transaction $transaction );
	
	/**
	 * Called when transaction ends without being commited or rollbacked 
	 * 
	 * This can happen when variable holding transaction goes out of scope.
	 * 
	 * @param Transaction $transaction
	 */
	public function release( Transaction $transaction );
}

class OpenTransactionOnDestructionException extends Exception {}