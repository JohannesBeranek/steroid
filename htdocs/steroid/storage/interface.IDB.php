<?php
/**
 * 
 * @package steroid
 */

require_once __DIR__ . '/interface.ITransactionBased.php';

/**
 * Transactional DB Interface
 * 
 * @package steroid
 */
interface IDB extends ITransactionBased {
	/**
	 * Allows for opening connection as late as possible (or not at all, if not needed).
	 * 
	 * Normally this has already been called on a passed instance.
	 * Implementations need to make sure init can be called multiple times without any negative side effects.
	 * 
	 * @return void
	 */
	public function init();
	
	/**
	 * Returns TRUE if storage has been initialized, otherwise false 
	 *
	 * @return bool
	 */
	public function isInit();
	
	/**
	 * Escapes like String - you still need to call escape afterwards if you're doing a query directly via DB class!
	 */
	public function escapeLike( $like );
	
	
	/**
	 * @param string|string[] $values strings and arrays of strings (even multidimensional) may be freely mixed
	 * @param bool $addQuotes
	 * @param bool $keepNull
	 * @return string|string[] same structure as passed values
	 */
	public function escape( $values, $addQuotes, $keepNull );
	
	/**
	 * Fetch all rows matching the query
	 * 
	 * @param string $query
	 * @return array all rows matching the query as an array
	 */
	public function fetchAll( $query );
	
	/**
	 * Fetch and return the first row matching the query
	 * 
	 * Implementations may try to modify the query (' LIMIT 1') to optimize performance.
	 * 
	 * @param string $query
	 * @return first row matching the query
	 */
	public function fetchFirst( $query );
	
	/**
	 * Insert into DB
	 * 
	 * @param string $table DB Table to insert records into
	 * @param array $columnValuePairs array of column => value pairs to insert
	 * @param bool $orUpdate pass column => value pairs to update values on duplicate key
	 * 
	 * @return int the last generated auto_increment value of the table ( @see mysqli::$insert_id )
	 */
	public function insert( $table, array $columnValuePairs, array $orUpdate = NULL );
	
	/**
	 * Update values in DB
	 * 
	 * @param string $table DB Table to update values in
	 * @param string $where optional WHERE clause
	 * @param array $columnValuePairs array of column => value pairs to update
	 * @return int number of affected rows ( @see mysqli::$affected_rows )
	 */
	public function update( $table, $where, array $columnValuePairs );

	public function clearTable( $table, $useTransactionUnsafeTruncate = false );
	public function count( $table );

	public function createTable( $isTemporary, $table, array $columnDefinitions, array $keyDefinitions, $engine = NULL, $charset = NULL);
	public function alterTable( $table, array $modifiedColumns, array $newColumns, array $dropColumns, array $modifiedKeys, array $newKeys, array $dropKeys, $engine = NULL, $charset = NULL);

	public function getTableSchema($table);
	public function getColumnSchemaForTable($table);
	public function getTableKeys($table);
	
	public function getInsertID();
	public function getAffectedRows();	
	
	/**
	 * Try to obtain a named lock.
	 * 
	 * Only one lock can be acquired per connection at a time, new calls to getLock with different names
	 * result in previous locks being released.
	 * 
	 * Timeout of 0 makes this call non-blocking, otherwise getLock will wait up to $timeout seconds
	 * to acquire the lock.
	 * 
	 * Locks are automatically freed upon termination of connection or by mysql_change_user() which
	 * is called automatically upon new connect call to a persistent connection, so it's safe
	 * to use this function even with persistent connections.
	 * 
	 * @param string $name Name of lock
	 * @param int $timeout Seconds to wait for acquiring lock if it's already in use by a different connection
	 * @return null|int NULL in case of error, 1 if lock was obtained successfully, and 0 if the attempt timed out
	 */
	public function getLock( $name, $timeout );
	
	/**
	 * Try to release a named lock.
	 * 
	 * @param string $name Name of lock
	 * @return null|int NULL if the lock did not exist, 1 if lock was released successfully, and 0 if the lock could not be released (if it was established by a different thread)
	 */
	public function releaseLock( $name );
	
	/**
	 * Get status of a named lock.
	 * 
	 * @param string $name Name of lock
	 * @return null|int NULL in case of error, 1 if lock is free, and 0 if lock is in use
	 */
	public function isFreeLock( $name );
}

?>