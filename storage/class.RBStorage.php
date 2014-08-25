<?php
/**
 *
 * @package steroid\storage
 */

require_once STROOT . '/storage/class.Storage.php';
require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/storage/record/interface.IRecord.php';
require_once STROOT . '/storage/interface.IRBStorage.php';

require_once STROOT . '/storage/interface.IRBStorageFilter.php';

/**
 * Record Based Storage
 *
 * @package steroid\storage
 */
class RBStorage extends Storage implements IRBStorage, IRBStorageFilter {

	const SAVE_ACTION_NONE = 0;
	const SAVE_ACTION_UPDATE = 1;
	const SAVE_ACTION_CREATE = 2;

	const SELECT_FIELDNAME_FIELDS = 'fields';
	const SELECT_FIELDNAME_FIELDS_STATIC = 'fieldsStatic';
	const SELECT_FIELDNAME_WHERE = 'where';
	const SELECT_FIELDNAME_GROUPBY = 'groupBy';
	const SELECT_FIELDNAME_ORDERBY = 'orderBy';
	const SELECT_FIELDNAME_JOIN = 'join';

	const PLACEHOLDER_LIMIT = '\0LIMIT\0'; // there should be no unescaped \0 (nullbyte) in the query, so we can (ab-)use them here

	protected $foundRecords = 0;

	protected $filters = array();
	protected $recordsToEnsureSave = array();

	private static $internalQueryCache = array();
	private static $hasApc;

	private $aggregateInWhereConf;
	private $aggregateInOrderBy;

	protected $queryBuildTime = 0;

	public function getFoundRecords() {
		return $this->foundRecords;
	}

	public function getQueryBuildTime() {
		return $this->queryBuildTime;
	}

	protected function logQueryBuildTime( $time, $query ) {
		$this->queryBuildTime += $time;
	}

	/**
	 * builds select query string
	 *
	 * May directly return int for getTotal queries
	 */
	public function buildSelect( $mainRecordClass, $queryStruct = NULL, $start = NULL, $count = NULL, $getTotal = NULL, array $vals = NULL, $name = NULL, $noAutoSelect = false ) {
		$startTime = microtime( true );

		if ( !class_exists( $mainRecordClass, false ) ) {
			throw new InvalidArgumentException( '"' . $mainRecordClass . '" does not exist.' );
		}

		if ( isset( $queryStruct[ 'name' ] ) && $name === NULL ) {
			$name = $queryStruct[ 'name' ];
		}

		if ( isset( $queryStruct[ 'vals' ] ) && $vals === NULL ) {
			$vals = $queryStruct[ 'vals' ];
		}

		if ( $name !== NULL ) { // TODO: check if apc is available
			$name = WEBROOT . '__queryCache__' . $name;

			$this->modifySelectCacheName( $name );

			if ( $name !== NULL ) { // caching might have been disabled by some filter
				if ( isset( self::$internalQueryCache[ $name ] ) ) {
					$success = true;
					$queryInfo = self::$internalQueryCache[ $name ];
				} else {
					$success = false;
					if ( self::$hasApc || ( self::$hasApc !== false && self::$hasApc = extension_loaded( 'apc' ) ) ) {
						$queryInfo = apc_fetch( $name, $success );

						if ( $success && $queryInfo ) {
							self::$internalQueryCache[ $name ] = $queryInfo;
						}
					}
				}

				if ( $success && $queryInfo ) {
					if ( $getTotal && isset( $queryInfo[ 'countQuery' ] ) ) {
						$countQuery = $queryInfo[ 'countQuery' ];

						if ( $vals ) {
							$escapedVals = array();
							foreach ( $vals as $val ) {
								if ( is_array( $val ) ) {
									$escapedVals[ ] = implode( ',', $this->escape( $val ) );
								} else {
									$escapedVals[ ] = $this->escape( $val );
								}
							}

							$countQuery = vsprintf( $countQuery, $escapedVals );
						}

						if ( $count === 0 ) {
							$this->logQueryBuildTime( microtime( true ) - $startTime, $countQuery );
						}

						// TODO: shouldn't actually execute a query in this function!
						$countRow = $this->fetchFirst( $countQuery );
						$this->foundRecords = intval( array_shift( $countRow ) );

						if ( $count === 0 ) {
							return $this->foundRecords;
						}
					}

					if ( isset( $queryInfo[ 'query' ] ) ) {
						$query = $queryInfo[ 'query' ];
						$innerQuery = isset( $queryInfo[ 'innerQuery' ] ) ? $queryInfo[ 'innerQuery' ] : '';
						$queryAfter = isset( $queryInfo[ 'queryAfter' ] ) ? $queryInfo[ 'queryAfter' ] : '';
					}
				} else if ( isset( $queryInfo ) ) {
					unset( $queryInfo );
				}
			}
		}


		$hasLimit = ( ( $start !== NULL && intval( $start ) > 0 ) || $count !== NULL );

		if ( !isset( $query ) || $getTotal ) { // no cached query OR only query is cached, but not count query
			if ( $queryStruct === NULL ) {
				$queryStruct = array();
			}

			if ( $start !== NULL ) {
				$start = max( 0, intval( $start ) );
			}


			$tableCount = 1;

			$fields = array();
			$where = '';
			$group = array();
			$having = '';
			$order = array();


			$primaryKeys = array();
			$pathSynonymMapping = array( $mainRecordClass => 't0' );
			$pathRecordClassMapping = array( $mainRecordClass => $mainRecordClass );


			if ( isset( $queryStruct[ self::SELECT_FIELDNAME_FIELDS_STATIC ] ) ) {
				foreach ( $queryStruct[ self::SELECT_FIELDNAME_FIELDS_STATIC ] as $k => $v ) {
					$fields[ ] = $this->escape( $v ) . ' AS ' . $this->escapeObjectName( $k );
				}

				unset( $queryStruct[ self::SELECT_FIELDNAME_FIELDS_STATIC ] );
			}

			$filteredJoinData = false;
			$innerFrom = '';
			$null = NULL;
			$this->aggregateInOrderBy = NULL;

			$this->parseSelect( $mainRecordClass, $pathSynonymMapping, $pathRecordClassMapping, $mainRecordClass, $queryStruct, $fields, $innerFrom, $where, $group, $having, $order, $tableCount, $primaryKeys, $getTotal, $null, $noAutoSelect, $filteredJoinData );
			$from = ' FROM ' . $innerFrom;


			// FIXME: FEATURE TEMPORARY DISABLED DUE TO PERFORMANCE PROBLEMS!!!
			$filteredJoinData = false;

			if ( !$fields && $count !== 0 ) {
				throw new InvalidArgumentException( 'No fields provided for select' );
			}


			$wherePart = $where ? ( ' WHERE ' . $where ) : '';
			// don't need any fields provided for using count only


			// TODO: this does not take group by or having into account (order by has no effect on total, and we don't need the count for the limited query)
			if ( $getTotal ) {
				if ( is_array( $getTotal ) ) {
					$getTotalOrder = $order; // TODO: support complex constructs
					$order = array();

					$wp = array();

					foreach ( $getTotalOrder as $gto ) {
						$order[ ] = $gto[ 'orig' ];
						$wp[ ] = $gto[ 'str' ];
					}

					$imp = implode( ' OR ', $wp );

					$countWherePart = $wherePart ? ( '(' . $wherePart . ') AND (' . $imp . ')' ) : ( 'WHERE ' . $imp );
				} else {
					$countWherePart = $wherePart;
				}


				$totalGroupBy = array();

				foreach ( $primaryKeys[ $mainRecordClass ] as $pk ) {
					$totalGroupBy[ ] = 't0.' . $this->escapeObjectName( $mainRecordClass::getColumnName( $pk ) );
				}

				$countQuery = 'SELECT COUNT(*) FROM (SELECT 1 ' . $from . $countWherePart . ' GROUP BY ' . implode( ',', $totalGroupBy ) . ') countTable';

				if ( $count === 0 ) {
					if ( $name !== NULL ) {
						$queryInfo = array( 'countQuery' => $countQuery );

						self::$internalQueryCache[ $name ] = $queryInfo;

						// use apc_add here, as another php instance might already have saved the query		
						if ( self::$hasApc || ( self::$hasApc !== false && self::$hasApc = extension_loaded( 'apc' ) ) ) {
							apc_add( $name, $queryInfo );
						}
					}
				}

				if ( $vals !== NULL ) {
					$escapedVals = array();
					foreach ( $vals as $val ) {
						if ( is_array( $val ) ) {
							$escapedVals[ ] = implode( ',', $this->escape( $val ) );
						} else {
							$escapedVals[ ] = $this->escape( $val );
						}
					}

					// fill in positional arguments
					$countQuery = vsprintf( $countQuery, $escapedVals );
				}

				if ( $count === 0 ) {
					$this->logQueryBuildTime( microtime( true ) - $startTime, $countQuery );
				}

				// TODO: shouldn't actually execute a query in this function!	
				$countRow = $this->fetchFirst( $countQuery );
				$this->foundRecords = intval( array_shift( $countRow ) );

				if ( $count === 0 ) {
					return $this->foundRecords;
				}
			}


			$query = 'SELECT ' . implode( ',', $fields ) . $from;
			$groupByPart = $group ? ( ' GROUP BY ' . implode( ',', $group ) ) : '';

			$havingPart = $having ? ( ' HAVING ' . $having ) : '';

			$orderByPart = $order ? ( ' ORDER BY ' . implode( ',', $order ) ) : '';

			// JB 23.1.2014 also need to use subquery in case $filteredJoinData is set, as otherwise we could get partial data for joined tables
			if ( $hasLimit || $groupByPart || $havingPart || ( $name !== NULL ) || $filteredJoinData ) {
				// we may not leave orderByPart out in case we have a limit given, as we'll still want the result to be correctly ordered
				// TODO: why do we need the $wherePart in the $queryAfter if we already use it in the subQuery?
				if ( !$filteredJoinData ) {
					$queryAfter = $wherePart;
				} else {
					$queryAfter = '';
				}

				if ( $this->aggregateInOrderBy ) {
					$queryAfter .= $groupByPart;
				}

				$queryAfter .= $orderByPart;

				$subSynonym = 't' . $tableCount;

				$subFields = array();
				$keyParts = array();
				$groupFields = array();

				foreach ( $primaryKeys[ $mainRecordClass ] as $pk ) {
					$sf = 't0.' . $this->escapeObjectName( $mainRecordClass::getColumnName( $pk ) );
					$groupFields[ ] = $sf;
					$subFields[ ] = $sf . ' AS ' . $this->escapeObjectName( $pk );
					$keyParts[ ] = $subSynonym . '.' . $this->escapeObjectName( $pk ) . '= t0.' . $this->escapeObjectName( $mainRecordClass::getColumnName( $pk ) );
				}

				$subGroupBy = ( $groupByPart ? ( $groupByPart . ',' . implode( ',', $groupFields ) ) : ( ' GROUP BY ' . implode( ',', $groupFields ) ) );

				$subQuery = 'SELECT ' . implode( ',', $subFields ) . $from . $wherePart . $subGroupBy . $havingPart . $orderByPart . self::PLACEHOLDER_LIMIT;

				// this isn't needed, but doesn't take much performance and makes debugging way easier
				$add = $tableCount + 1;

				$subQuery = preg_replace_callback( '/`t([\d+])`/', function ( $matches ) use ( $add ) {
					return '`t' . ( intval( $matches[ 1 ] ) + $add ) . '`';
				}, $subQuery );

				$innerQuery = ' INNER JOIN (' . $subQuery . ') ' . $subSynonym . ' ON ' . implode( ' AND ', $keyParts );

				if ( $groupByPart || $havingPart || $filteredJoinData ) { // always need those parts in case groupBy and/or having is set or $filteredJoinData
					$query .= $innerQuery . $queryAfter;
					$innerQuery = $queryAfter = '';
				}
			} else {
				// we may not leave orderByPart out in case we have a limit given, as we'll still want the result to be correctly ordered
				$queryAfter = $wherePart . $orderByPart;

				$query .= $queryAfter;
				$queryAfter = '';
			}

			//	$query .= $groupByPart . $havingPart; // this can be left out, as we already grouped / applied having in the subquery in case one of those was given

			// TODO: this actually also applies if we got an aggregate function in fields


			if ( $name !== NULL ) {
				if ( !isset( $queryInfo ) ) {
					$queryInfo = array();
				}

				$queryInfo[ 'query' ] = $query;

				if ( isset( $innerQuery ) && $innerQuery !== '' && isset( $queryAfter ) && $queryAfter !== '' ) {
					$queryInfo[ 'innerQuery' ] = $innerQuery;
					$queryInfo[ 'queryAfter' ] = $queryAfter;
				}

				if ( isset( $countQuery ) ) {
					$queryInfo[ 'countQuery' ] = $countQuery;
				}

				// save query internally, so we can access it faster in the same request
				self::$internalQueryCache[ $name ] = $queryInfo;

				// use apc_store here to override potentially incomplete queryInfo
				if ( self::$hasApc || ( self::$hasApc !== false && self::$hasApc = extension_loaded( 'apc' ) ) ) {
					apc_store( $name, $queryInfo );
				}
			}
		}

		if ( $hasLimit ) {
			$query .= $innerQuery;
			$limitReplace = $this->buildLimit( $start, $count );
		} else {
			$limitReplace = '';
		}

		// PLACEHOLDER_LIMIT might be on $query (in case we had groupByPart and/or havingPart when creating it) or $innerQuery
		$query = str_replace( self::PLACEHOLDER_LIMIT, $limitReplace, $query );

		$query .= $queryAfter;

		if ( $vals !== NULL ) {
			if ( !isset( $escapedVals ) ) {
				$escapedVals = array();

				foreach ( $vals as $val ) {
					if ( is_array( $val ) ) {
						$escapedVals[ ] = implode( ',', $this->escape( $val ) );
					} else {
						$escapedVals[ ] = $this->escape( $val );
					}
				}
			}

			// fill in positional arguments	
			$query = vsprintf( $query, $escapedVals );
		}

		$this->logQueryBuildTime( microtime( true ) - $startTime, $query );

		return $query;
	}

	// TODO: change strings to const
	public function select( $mainRecordClass, $queryStruct = NULL, $start = NULL, $count = NULL, $getTotal = NULL, array $vals = NULL, $name = NULL, $noAutoSelect = false ) {
		if ( $count !== NULL && intval( $count ) <= 0 && !$getTotal ) {
			return array();
		}

		$query = $this->buildSelect( $mainRecordClass, $queryStruct, $start, $count, $getTotal, $vals, $name, $noAutoSelect );

		if ( !is_string( $query ) ) {
			return $query;
		}

		$rows = $this->fetchAll( $query );

		return $this->getResultsFromRows( $rows, $mainRecordClass );
	}

	public function getResultsFromRows( $rows, $mainRecordClass, $skipStatic = false ) {
		$results = array();

		// early out
		if ( count( $rows ) == 0 ) return $results;

		// TODO: optimize - takes a huge amount of time with many results - also should be possible with recursive function call
		$currentRecordList = array();

		// Building pathParts, columnNames and paths for firstRow increases performance with > 1 row
		$pathParts = array();
		$columnNames = array();
		$paths = array();
		$lastParts = array();
		$pathsBefore = array();
		$wraps = array();

		$firstRow = reset( $rows );

		$lastPath = NULL;
		$lastColumn = NULL;

		foreach ( $firstRow as $column => $value ) {
			$pp = explode( '.', $column );

			if ( $skipStatic && $pp[ 0 ] !== $mainRecordClass ) {
				continue;
			}

			$pathParts[ $column ] = $pp;

			$columnNames[ $column ] = array_pop( $pp );

			$path = implode( '.', $pp );
			$paths[ $column ] = $path;

			if ( $path !== $lastPath ) {
				$lastParts[ $column ] = array_pop( $pp );
				$pathsBefore[ $column ] = implode( '.', $pp );
				$lastPath = $path;

				if ( isset( $lastColumn ) ) {
					$wraps[ $lastColumn ] = true;
				}
			} else {
				$lastParts[ $column ] = $lastParts[ $lastColumn ];
				$pathsBefore[ $column ] = $pathsBefore[ $lastColumn ];

				$wraps[ $lastColumn ] = false;
			}

			$lastColumn = $column;
		}

		if ( isset( $lastColumn ) ) {
			$wraps[ $lastColumn ] = true;
		}

		foreach ( $rows as $row ) {
			$currentRecords = array();

			$hasNotNull = false;
			$cr = array();

			foreach ( $row as $column => $value ) {
				if ( !isset( $columnNames[ $column ] ) ) continue;

				$columnName = $columnNames[ $column ];
				$cr[ $columnName ] = $value;

				if ( $value !== null ) {
					$hasNotNull = true;
				}

				if ( $wraps[ $column ] ) {
					if ( $hasNotNull ) {
						$currentRecords[ $column ] = $cr;
						$hasNotNull = false;
					} else { // need this so empty foreignRecordReferences still get set (and return true for __isset)
						$currentRecords[ $column ] = NULL;
					}

					$cr = array();
				}
			}

			$currentRecordList[ ] = $currentRecords;
		}

		foreach ( $currentRecordList as $recordList ) {
			$pathPointers = array( $mainRecordClass => &$results );
			$pathPointersCurrent = array();

			foreach ( $recordList as $column => $record ) {
				$recordPath = $paths[ $column ];

				if ( $recordPath === $mainRecordClass ) {
					$isMulti = true;
				} else {
					$lastPart = $lastParts[ $column ];

					$pathBefore = $pathsBefore[ $column ];

					// in case intermediate columns weren't auto selected, we could end up with unconnectable paths
					// if so, we just skip those (they could have been selected for orderBy in a union query for example)
					if ( ( $isMulti = ( strpos( $lastPart, ':' ) !== false ) ) && !isset( $pathPointersCurrent[ $pathBefore ] ) ) {
						continue;
					}

					$pt = & $pathPointersCurrent[ $pathBefore ];

					if ( $isMulti && ( !array_key_exists( $lastPart, $pt ) || ( (array)$pt[ $lastPart ] !== $pt[ $lastPart ] ) ) ) {
						$pt[ $lastPart ] = array();
					}

					$pathPointers[ $recordPath ] = & $pt[ $lastPart ];
				}

				$pt = & $pathPointers[ $recordPath ];

				if ( $record === NULL ) continue;


				if ( $isMulti ) {

					$matchingRecord = null;

					foreach ( $pt as &$rec ) {
						$isSame = true;

						foreach ( $record as $colName => $value ) {

							if ( ( (array)$rec[ $colName ] === $rec[ $colName ] ) && ( (array)$value !== $value ) ) { // 'url' => 'url' => ('primary'
								$colPath = $recordPath . '.' . $colName;

								// in case column is itself a record reference

								if ( $rec[ $colName ][ Record::FIELDNAME_PRIMARY ] !== $value ) {
									$isSame = false;
									break;
								}

							} else if ( $rec[ $colName ] !== $value ) {
								$isSame = false;
								break;
							}
						}

						if ( $isSame ) {
							$matchingRecord = & $rec;
							break;
						}
					}

					if ( $matchingRecord !== null ) {
						$pathPointersCurrent[ $recordPath ] = & $matchingRecord;

						// it's important to remove the reference, as otherwhise the next time we set 
						// $matchingRecord to anything it will override the value referenced here
						unset( $matchingRecord );
					} else {
						$newIndex = count( $pt );
						$pt[ $newIndex ] = $record;
						$pathPointersCurrent[ $recordPath ] = & $pt[ $newIndex ];
					}

				} else {
					if ( $pt === NULL ) {
						$pt = $record;
					} else if ( (array)$pt !== $pt ) { // BaseDTRecordReference
						$record[ Record::FIELDNAME_PRIMARY ] = $pt;
						$pt = $record;
					}

					$pathPointersCurrent[ $recordPath ] = & $pt;
				}
			}

		}

		return $results;

	}


	// TODO: cache more aggressively
	protected function parseSelect( $currentRecordClass, &$pathSynonymMapping, &$pathRecordClassMapping, $path, $currentConf, &$fields, &$from, &$where, &$group, &$having, &$order, &$tableCount, array &$primaryKeys, $getTotal, &$additionalJoinConditions = NULL, $noAutoSelect = false, &$filteredJoinData = false ) {
		static $allowedOrderByDirections = array( self::ORDER_BY_ASC, self::ORDER_BY_DESC );


		$currentSynonym = $pathSynonymMapping[ $path ];

		$from = $this->escapeObjectName( $currentRecordClass::getTableName() ) . ' ' . $currentSynonym;

		if ( $additionalJoinConditions === NULL ) {
			$this->injectSelectFilter( $currentRecordClass, $currentConf, $currentConf[ 'where' ] );
		} else {
			$this->injectSelectFilter( $currentRecordClass, $currentConf, $additionalJoinConditions );
		}

		if ( !array_key_exists( $currentRecordClass, $primaryKeys ) ) {
			if ( $currentRecordClass::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) ) {
				$primaryKeys[ $currentRecordClass ] = array( Record::FIELDNAME_PRIMARY );
			} else {
				// force primary keys to get selected as well
				$primaryKeys[ $currentRecordClass ] = $currentRecordClass::getPrimaryKeyFields();
			}
		}

		$confFields = array_key_exists( self::SELECT_FIELDNAME_FIELDS, $currentConf ) ? $currentConf[ self::SELECT_FIELDNAME_FIELDS ] : array();

		// TODO: implement path resolving for fields

		if ( $confFields === '*' ) {
			$confFields = array( '*' );
		}

		$hasAll = false;

		foreach ( $confFields as $fieldIndex => $fieldName ) {
			if ( $fieldName === '*' ) {
				unset( $confFields[ $fieldIndex ] );

				if ( $hasAll ) continue; // make it possible to use '*' multiple times

				// TODO: cache?
				$ownFields = array_keys( $currentRecordClass::getOwnFieldDefinitions() );

				foreach ( $ownFields as $ownField ) {
					if ( !isset( $confFields[ $ownField ] ) && !in_array( $ownField, $confFields, true ) ) {
						$confFields[ ] = $ownField;
					}
				}

				$hasAll = true;
			}
		}

		if ( $noAutoSelect !== true ) {
			// $pks = $primaryKeys[ $currentRecordClass ];
			$keys = $currentRecordClass::getAllKeys();

			foreach ( $keys as $keyName => $key ) {
				if ( $keyName === 'primary' || ( !empty( $key[ 'unique' ] ) && !$noAutoSelect ) ) {
					foreach ( $key[ 'fieldNames' ] as $fieldName ) {
						if ( !array_key_exists( $fieldName, $confFields ) && !in_array( $fieldName, $confFields ) ) {
							$confFields[ ] = $fieldName;
						}
					}
				}
			}

			// minor performance optimization to avoid lazy loading sorting for foreign references
			if ( $currentRecordClass::fieldDefinitionExists( Record::FIELDNAME_SORTING ) && !in_array( Record::FIELDNAME_SORTING, $confFields ) ) {
				$confFields[ ] = Record::FIELDNAME_SORTING;
			}
		}

		reset( $confFields );

		while ( list( $fieldIndex, $fieldName ) = each( $confFields ) ) {
			// fieldIndex could be path as well ...
			if ( strpos( $fieldIndex, '.' ) !== false ) {
				$parts = explode( '.', $fieldIndex );

				$lastPart = array_pop( $parts );

				// $fieldName might be array
				$start =& $confFields;

				foreach ( $parts as $part ) {
					if ( !isset( $start[ $part ] ) ) {
						$start[ $part ] = array( 'fields' => array() );
					} elseif ( !isset( $start[ $part ][ 'fields' ] ) ) {
						$start[ $part ][ 'fields' ] = array();
					}

					$start =& $start[ $part ][ 'fields' ];
				}

				$start[ $lastPart ] = $fieldName;

				continue; // next iteration
			}

			// split paths
			if ( is_string( $fieldName ) && ( $newFieldName = strstr( $fieldName, '.', true ) ) !== false ) { // path
				$remaining = substr( $fieldName, strlen( $newFieldName ) + 1 );
				$fieldIndex = $newFieldName;


				if ( $fieldIndex === '*' ) {
					$ownFieldDefs = $currentRecordClass::getOwnFieldDefinitions();

					foreach ( $ownFieldDefs as $ownField => $fieldDef ) {
						if ( !isset( $confFields[ $ownField ] ) && !in_array( $ownField, $confFields, true ) ) {
							$dataType = $fieldDef[ 'dataType' ];

							if ( is_subclass_of( $dataType, 'BaseDTRecordReference' ) || is_subclass_of( $dataType, 'BaseDTForeignReference' ) ) {
								$confFields[ $ownField ] = array( 'fields' => array( $remaining ) );
							} else {
								$confFields[ ] = $ownField;
							}
						} else {
							// TODO: merging
							// throw new Exception('Merging not yet implemented.');
						}
					}

					continue;
				} else {
					$fieldName = array( 'fields' => array( $remaining ) );

					// TODO: need to actually merge paths in before selecting anything!

					// throw new Exception('No merging yet.');
				}
			}

			if ( is_array( $fieldName ) ) { // array
				$val = $fieldName;
				$fieldName = $fieldIndex;

				$fieldDef = $currentRecordClass::getFieldDefinition( $fieldName );
				$dataType = $fieldDef[ 'dataType' ];

				if ( is_subclass_of( $dataType, 'BaseDTRecordReference' ) ) {
					if ( empty( $fieldDef[ 'requireForeign' ] ) ) {
						$val[ 'optional' ] = true;
					}

					if ( $noAutoSelect !== true ) {
						// also select BaseDTRecordReference where it is, so we don't reselect record on setting value ( when doing Record::get(...) )
						$fields[ ] = $currentSynonym . '.' . $this->escapeObjectName( $currentRecordClass::getColumnName( $fieldName ) ) . ' AS ' . $this->escape( $path . '.' . $fieldName );
					}
				} else if ( is_subclass_of( $dataType, 'BaseDTForeignReference' ) ) {
					if ( !$noAutoSelect ) {
						$tempFieldNames = explode( ':', $fieldName );

						$foreignField = array_shift( $tempFieldNames ); // first part is foreignField

						// guarantee foreign field is selected as well
						// TODO: test if we can skip this, as BaseDTForeignReference sets $this->record on new values anyway
						if ( !array_key_exists( self::SELECT_FIELDNAME_FIELDS, $val ) ) {
							$val[ self::SELECT_FIELDNAME_FIELDS ] = array( $foreignField );
						} else if ( !array_key_exists( $foreignField, $val[ self::SELECT_FIELDNAME_FIELDS ] ) && !in_array( $foreignField, $val[ self::SELECT_FIELDNAME_FIELDS ] ) ) {
							$val[ self::SELECT_FIELDNAME_FIELDS ][ ] = $foreignField;
						}
					}
				}

				if ( !array_key_exists( self::SELECT_FIELDNAME_JOIN, $currentConf ) ) $currentConf[ self::SELECT_FIELDNAME_JOIN ] = array();
				if ( !array_key_exists( $fieldName, $currentConf[ self::SELECT_FIELDNAME_JOIN ] ) ) {
					$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ] = $val;
				} else { // recursive merge
					foreach ( $val as $name => $value ) {
						if ( isset( $currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] ) ) {
							if ( $currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] !== $value ) {
								$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] = array_merge( (array)$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ], $value );
							}
						} else {
							$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] = $value;
						}
					}
				}
			} else {
				if ( strpos( $fieldName, ':' ) !== false ) { // foreign ref as field
					$tempFieldNames = explode( ':', $fieldName );

					$foreignField = array_shift( $tempFieldNames ); // first part is foreignField

					if ( !array_key_exists( self::SELECT_FIELDNAME_JOIN, $currentConf ) ) $currentConf[ self::SELECT_FIELDNAME_JOIN ] = array();
					if ( !array_key_exists( $fieldName, $currentConf[ self::SELECT_FIELDNAME_JOIN ] ) ) {
						$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ] = array();
					}
					if ( !array_key_exists( 'fields', $currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ] ) ) {
						$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ 'fields' ] = array( $foreignField );
					} else if ( !array_key_exists( $foreignField, $currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ 'fields' ] ) && !in_array( $foreignField, $currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ 'fields' ] ) ) {
						$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ 'fields' ][ ] = $foreignField;
					}
				} else { // possible BaseDTRecordReference
					$fieldDef = $currentRecordClass::getFieldDefinition( $fieldName );
					$dataType = $fieldDef[ 'dataType' ];

					if ( is_subclass_of( $dataType, 'BaseDTRecordReference' ) ) {
						if ( !empty( $fieldDef[ 'branchFields' ] ) ) {
							$val = array( 'fields' => (array)$fieldDef[ 'branchFields' ] );

							if ( empty( $fieldDef[ 'requireForeign' ] ) ) {
								$val[ 'optional' ] = true;
							}

							if ( !array_key_exists( self::SELECT_FIELDNAME_JOIN, $currentConf ) ) $currentConf[ self::SELECT_FIELDNAME_JOIN ] = array();
							if ( !array_key_exists( $fieldName, $currentConf[ self::SELECT_FIELDNAME_JOIN ] ) ) {
								$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ] = $val;
							} else { // recursive merge
								foreach ( $val as $name => $value ) {
									if ( isset( $currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] ) ) {
										if ( $currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] !== $value ) {
											$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] = array_merge( (array)$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ], $value );
										}
									} else {
										$currentConf[ self::SELECT_FIELDNAME_JOIN ][ $fieldName ][ $name ] = $value;
									}
								}
							}
						}
					}

					$fields[ ] = $currentSynonym . '.' . $this->escapeObjectName( $currentRecordClass::getColumnName( $fieldName ) ) . ' AS ' . $this->escape( $path . '.' . $fieldName );
				}
			}
		}

		if ( array_key_exists( self::SELECT_FIELDNAME_JOIN, $currentConf ) ) {
			foreach ( $currentConf[ self::SELECT_FIELDNAME_JOIN ] as $referenceName => $joinConf ) { // $referenceName = foreignFieldName:foreignRecordClass
				$this->handleJoin( $joinConf, $path, $referenceName, $pathSynonymMapping, $tableCount, $currentRecordClass, $pathRecordClassMapping, $getTotal, $from, $currentSynonym, $fields, $where, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );
			}
		}


		if ( array_key_exists( 'where', $currentConf ) && !empty( $currentConf[ 'where' ] ) ) {

			$innerWhere = '';
			$this->parseWhereConf( $innerWhere, $path, $currentConf, $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );

			if ( $innerWhere ) {
				if ( $where ) {
					$where .= ' AND ';
				}

				$where .= '(' . $innerWhere . ')';
			}
		}

		if ( array_key_exists( 'groupBy', $currentConf ) ) { // FIXME: make it possible to use paths
			foreach ( $currentConf[ 'groupBy' ] as $groupByPart ) {
				$group[ ] = $currentSynonym . '.' . $this->escapeObjectName( $currentRecordClass::getColumnName( $groupByPart ) );
			}
		}

		if ( array_key_exists( self::SELECT_FIELDNAME_ORDERBY, $currentConf ) ) {
			foreach ( $currentConf[ self::SELECT_FIELDNAME_ORDERBY ] as $orderByPart => $direction ) {
				if ( (array)$direction === $direction ) { // more complex orderBy musst be passed in array form

					$field = '';

					$this->aggregateInWhereConf = NULL;

// TODO: this actually accepts stuff which isn't valid in orderBy (e.g. 'AND', 'OR', ...)					
					$this->parseWhereConf( $field, $path, array( 'where' => $direction[ 'conf' ] ), $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );

					$direction = $direction[ 'dir' ];

					if ( $this->aggregateInWhereConf ) {
						$this->aggregateInOrderBy = true;
					}

				} else {
					$direction = strtoupper( $direction );

					if ( !in_array( $direction, $allowedOrderByDirections ) ) {
						throw new InvalidArgumentException( 'Allowed orderBy directions: ' . implode( ', ', $allowedOrderByDirections ) . ' (case insensitive)' );
					}

					// TODO: implement resolving path for orderBy!
					if ( strpos( $orderByPart, '.' ) !== false ) {
						$orderByPathParts = explode( '.', $path . '.' . $orderByPart ); // $identifierParts
						$fieldName = array_pop( $orderByPathParts );

						$orderByPath = implode( '.', $orderByPathParts ); // $wherePath

						if ( !isset( $pathRecordClassMapping[ $orderByPath ] ) ) {
							$this->resolvePath( $orderByPath, $orderByPathParts, $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $where, $group, $having, $order, $primaryKeys, $noAutoSelect );
						}

						$recordClass = $pathRecordClassMapping[ $orderByPath ];
						$synonym = $pathSynonymMapping[ $orderByPath ];

					} else {
						$fieldName = $orderByPart;
						$synonym = $currentSynonym;
						$recordClass = $currentRecordClass;
					}

					$field = $synonym . '.' . $this->escapeObjectName( $recordClass::getColumnName( $fieldName ) );
				}

				$orderStr = $field . ' ' . $direction;

				if ( is_array( $getTotal ) ) {
					if ( !array_key_exists( $orderByPart, $getTotal ) ) {
						throw new InvalidArgumentException( 'Missing "' . $orderByPart . '" at correct position in $getTotal.' );
					}

					$currentVal = $this->escape( $getTotal[ $orderByPart ] );

					$op = array();

					foreach ( $order as $previousOrder ) {
						$op[ ] = $previousOrder[ 'field' ] . ' = ' . $previousOrder[ 'val' ]; // both are already correctly escaped
					}

					$newOrder = $field;

					switch ( $direction ) {
						case self::ORDER_BY_ASC:
							$newOrder .= ' < ';
							break;
						case self::ORDER_BY_DESC:
							$newOrder .= ' > ';
							break;
					}

					$newOrder .= $currentVal; // currentVal is already correctly escaped

					$op[ ] = $newOrder;

					$order[ ] = array(
						'field' => $field,
						'val' => $currentVal,
						'str' => '(' . implode( ' AND ', $op ) . ')',
						'orig' => $orderStr
					);
				} else {
					$order[ ] = $orderStr;
				}
			}
		}

		if ( array_key_exists( 'having', $currentConf ) ) {
			$innerHaving = '';
			$this->parseWhereConf( $innerHaving, $path, array( 'where' => $currentConf[ 'having' ] ), $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );

			if ( $innerHaving ) {
				if ( $having ) {
					$having .= ' AND ';
				}

				$having .= '(' . $innerHaving . ')';
			}
		}

	}

	// FIXME: $primaryKeys should not be null, as it is not marked default null in handleJoin!
	final protected function resolvePath( $wherePath, array $identifierParts, array &$pathRecordClassMapping, array &$pathSynonymMapping, &$tableCount, $getTotal, &$from, &$fields, &$where, &$group, &$having, &$order, array &$primaryKeys, $noAutoSelect = false ) {
		$unresolvedParts = array();

		while ( $wherePath && !isset( $pathRecordClassMapping[ $wherePath ] ) ) {
			array_unshift( $unresolvedParts, array_pop( $identifierParts ) );
			$wherePath = implode( '.', $identifierParts );
		}

		if ( !$wherePath ) {
			throw new LogicException( 'Unknown path: ' . implode( '.', $unresolvedParts ) );
		}

		$origWherePath = $wherePath;
		$joinConf = array();
		$confPointer = & $joinConf;

		foreach ( $unresolvedParts as $unresolvedPart ) {
			$identifierParts[ ] = $unresolvedPart;

			$confPointer = array( self::SELECT_FIELDNAME_JOIN => array( $unresolvedPart => array() ) );
			$confPointer = & $confPointer[ self::SELECT_FIELDNAME_JOIN ][ $unresolvedPart ];
		}

		$referenceName = array_shift( $unresolvedParts );

		$wherePath = implode( '.', $identifierParts );

		$rc = $pathRecordClassMapping[ $origWherePath ];
		$rcSynonym = $pathSynonymMapping[ $origWherePath ];

		$this->handleJoin(
			$joinConf[ self::SELECT_FIELDNAME_JOIN ][ $referenceName ],
			$origWherePath, // $path
			$referenceName, // $referenceName
			$pathSynonymMapping,
			$tableCount,
			$rc, // $currentRecordClass
			$pathRecordClassMapping,
			$getTotal,
			$from,
			$rcSynonym, // $currentSynonym
			$fields, $where, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );
	}

	final protected function handleJoin( array $joinConf, $path, $referenceName, array &$pathSynonymMapping, &$tableCount, $currentRecordClass, array &$pathRecordClassMapping, $getTotal, &$from, $currentSynonym, &$fields, &$where, &$group, &$having, &$order, array &$primaryKeys, $noAutoSelect = false, &$filteredJoinData = false ) {
		$isOptional = false;

		if ( array_key_exists( 'optional', $joinConf ) && !empty( $joinConf[ 'optional' ] ) ) {
			$isOptional = true;
		}

		$currentPath = $path ? ( $path . '.' . $referenceName ) : $referenceName;

		if ( !isset( $pathSynonymMapping[ $currentPath ] ) ) {
			$newSynonym = 't' . $tableCount;
			$tableCount++;

			$pathSynonymMapping[ $currentPath ] = $newSynonym;
		}

		$fd = $currentRecordClass::getFieldDefinition( $referenceName );
		$dt = $fd[ 'dataType' ];
		$additionalJoinConditions = $dt::getAdditionalJoinConditions( $currentRecordClass, $referenceName, $fd );

		if ( strpos( $referenceName, ':' ) === false ) { // BaseDTRecordReference
			if ( !empty( $fd[ 'recordClass' ] ) ) {
				$foreignRecordClass = $fd[ 'recordClass' ];
			} else {
				// FIXME: handle dynamic record reference
				error_log( 'Handling of dynamic record reference in RBStorage not yet implemented: ' . $currentRecordClass . '->' . $referenceName );
				return;
			}

			$currentColumn = $referenceName;
			$foreignColumn = Record::FIELDNAME_PRIMARY;

		} else { // BaseDTForeignReference
			$names = explode( ':', $referenceName );

			if ( count( $names ) != 2 ) {
				throw new LogicException( 'Malformed referenceName: "' . $referenceName . '"' );
			}

			$foreignRecordClass = $names[ 1 ];

			$foreignColumn = $names[ 0 ];
			$currentColumn = Record::FIELDNAME_PRIMARY;

			$isOptional = true; // foreign reference is always optional
		}

		$pathRecordClassMapping[ $currentPath ] = $foreignRecordClass;

		if ( !array_key_exists( $foreignRecordClass, $primaryKeys ) ) {
			$primaryKeys[ $foreignRecordClass ] = $foreignRecordClass::getPrimaryKeyFields();
		}

		if ( is_array( $getTotal ) && array_key_exists( $referenceName, $getTotal ) ) {
			$joinGetTotal = $getTotal[ $referenceName ];
		} else {
			$joinGetTotal = $getTotal;
		}

		$joinFrom = NULL;

		$filterJoinConditions = array();

		$this->parseSelect( $foreignRecordClass, $pathSynonymMapping, $pathRecordClassMapping, $currentPath, $joinConf, $fields, $joinFrom, $where, $group, $having, $order, $tableCount, $primaryKeys, $joinGetTotal, $filterJoinConditions, $noAutoSelect, $filteredJoinData );

		$additionalJoinStr = '';

		if ( $additionalJoinConditions ) {
			$this->parseWhereConf( $additionalJoinStr, $path, array( 'where' => $additionalJoinConditions ), $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );

			if ( $additionalJoinStr ) {
				$additionalJoinStr = ' AND ' . $additionalJoinStr;
			}
		}

		$joinStr = $newSynonym . '.' . $this->escapeObjectName( $foreignRecordClass::getColumnName( $foreignColumn ) ) . '=' . $currentSynonym . '.' . $this->escapeObjectName( $currentRecordClass::getColumnName( $currentColumn ) ) . $additionalJoinStr;

		if ( $filterJoinConditions ) {
			$filterJoinStr = '';
			$this->parseWhereConf( $filterJoinStr, $currentPath, array( 'where' => $filterJoinConditions ), $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );

			if ( $filterJoinStr ) {
				$joinStr = '(' . $joinStr . ') AND (' . $filterJoinStr . ')';
			}
		}

		// [JB 31.05.2013] FIX for mysql 5.5 failing query with ... JOIN ([tablename] [synonym]) ...
		if ( !preg_match( '/^`[^`]+` t\d+$/', $joinFrom ) ) {
			$joinFrom = '(' . $joinFrom . ')';
		}


		$from .= ' ' . ( $isOptional ? 'LEFT' : 'INNER' ) . ' JOIN ' . $joinFrom . ' ON ' . $joinStr;

	}

	// FIXME: most options may actually not be null in case we need to call resolvePath!
	protected function parseWhereConf( &$where, $path, $currentConf, &$pathRecordClassMapping, &$pathSynonymMapping = NULL,
	                                   &$tableCount = NULL, $getTotal = NULL, &$from = NULL, array &$fields = NULL, &$group = NULL, &$having = NULL, &$order = NULL, array &$primaryKeys = NULL, $noAutoSelect = false, &$filteredJoinData = false ) {

		static $allowedComparators = array( '<', '<=', '=', '>', '>=', '!=', 'LIKE', 'RLIKE' );
		static $allowedOperators = array( '+', '-', '*', '/' );
		static $allowedConnectors = array( 'AND' => 'AND', '&&' => 'AND', '||' => 'OR', 'OR' => 'OR' );

		$state = 0;
		$lastState = 0;
		$bracketLevel = 0;
		$lastComparator = '';
		$lastWasOperator = false;
		$functionLevel = 0;
		$dontPlaceLastComparator = false;

		$lastFunctionArgCount = 0;
		$lastFunction = NULL;

		$argCount = 0;
		$lastFunctionMaxArgs = 0;
		$lastFunctionMinArgs = 0;
		$lastFunctionMulti = false;

		$lastWasOperatorStack = array();

		$functionMultiStack = array();
		$functionStack = array();
		$functionArgCountStack = array();

		foreach ( $currentConf[ 'where' ] as $wherePartKey => $wherePart ) {
			if ( $wherePart === '(' ) {
				if ( $state !== 0 && ( $state !== 2 || !$lastFunction ) ) {
					throw new InvalidArgumentException( 'incorrect where syntax in state ' . $state . ' on key ' . $wherePartKey . ': ' . $wherePart . ', given where: ' . Debug::getStringRepresentation( $currentConf[ 'where' ] ) );
				}

				$where .= '(';
				$bracketLevel++;
				continue;
			} elseif ( $wherePart === ')' ) {
				if ( ( ( $state !== 3 && $state !== 1 ) || $bracketLevel === 0 ) && !( $functionLevel > 0 && ( $state === 1 || $state === 2 || ( $state === 0 && ( $lastFunctionMulti || $lastFunctionMinArgs === 0 ) ) ) ) ) {
					throw new InvalidArgumentException( 'incorrect where syntax in state ' . $state . ' on key ' . $wherePartKey . ': ' . $wherePart . ', given where: ' . Debug::getStringRepresentation( $currentConf[ 'where' ] ) );
				}

				$where .= ')';
				$bracketLevel--;

				if ( ( $state === 0 || $state === 1 || $state === 2 ) && $functionLevel > 0 ) {
					if ( $argCount < $lastFunctionMinArgs ) {
						throw new InvalidArgumentException( 'function ' . $lastFunction . '() takes at least ' . $lastFunctionMinArgs . ' arguments, got only ' . $argCount . ', key: ' . $wherePartKey );
					}

					$functionLevel--;

					if ( $functionMultiStack ) {
						$lastFunctionMulti = array_pop( $functionMultiStack );
					} else {
						$lastFunctionMulti = false;
					}

					if ( $functionStack ) {
						$lastFunction = array_pop( $functionStack );
						$argCount = array_pop( $functionArgCountStack );
						$lastWasOperator = array_pop( $lastWasOperatorStack );

						$lastFunctionMaxArgs = self::functionMaxArgumentCount( $lastFunction );
						$lastFunctionMinArgs = self::functionMinArgumentCount( $lastFunction );
					} else {
						$lastFunction = NULL;
						$argCount = 0;
					}

					if ( ( $lastFunctionMulti || $state === 2 ) && $functionLevel === 0 ) {
						$lastState = $state;
						$state++;
					}
				}
				continue;
			} elseif ( self::isFunction( $wherePart ) ) { // TODO: should it be possible to use function in state 1? (comparator or operator)
				if ( $lastFunction ) {
					if ( !$lastWasOperator ) {

						if ( $argCount > 0 ) {
							$where .= ',';
						}

						$argCount++;
					}

					$functionStack[ ] = $lastFunction;
					$functionArgCountStack[ ] = $argCount;
					$lastWasOperatorStack[ ] = false;
				}

				$lastFunction = $wherePart;
				$argCount = 0;
				$lastFunctionMaxArgs = self::functionMaxArgumentCount( $lastFunction );
				$lastFunctionMinArgs = self::functionMinArgumentCount( $lastFunction );

				$functionMultiStack[ ] = $lastFunctionMulti = self::isMultiArgumentFunction( $lastFunction );
				$functionLevel++;

				if ( $state === 2 && $lastState === 1 && !$lastWasOperator ) {
					$where .= ' ' . $lastComparator . ' ';

					// FIXME: interval support

				}

				$lastWasOperator = false;


				$where .= $wherePart;

				if ( self::isAggregateFunction( $wherePart ) ) {
					$this->aggregateInWhereConf = true;
				}

				$lastState = $state;
				continue;
			}

			if ( !$dontPlaceLastComparator ) {
				$dontPlaceLastComparator = $lastState === $state;
			}

			switch ( $state ) {
				case 0: // value or identifier
				case 2:
					if ( is_array( $wherePart ) ) { // value
						if ( count( $wherePart ) > 1 ) {
							if ( $state === 0 || ( $lastComparator !== '=' && $lastComparator !== '!=' && $lastComparator !== 'LIKE' && $lastComparator !== 'RLIKE' ) ) {
								throw new InvalidArgumentException( 'incorrect where syntax in state ' . $state . ' on key ' . $wherePartKey . ': ' . $wherePart . ', given where: ' . Debug::getStringRepresentation( $currentConf[ 'where' ] ) );
							}


							if ( $lastComparator === '!=' ) {
								$where .= ' NOT';
							}

							if ( $lastComparator === 'LIKE' || $lastComparator === 'RLIKE' ) {
								if ( !$dontPlaceLastComparator ) {
									$where .= ' ' . $lastComparator . ' ';
								}

								$where .= $this->escape( $like );
							} else {
								if ( !$dontPlaceLastComparator ) {
									$where .= ' IN (';
								}
								$where .= implode( ',', $this->escape( $wherePart ) ) . ')';
							}

							if ( $functionLevel > 0 && !$lastWasOperator ) {
								$argCount++;
							}

						} else {
							$where .= ( ( $state === 2 && !$dontPlaceLastComparator ) ? $lastComparator : '' ) . ( ( $argCount > 0 && !$lastWasOperator ) ? ', ' : ' ' ) . $this->escape( reset( $wherePart ) );

							// DUP: function check
							if ( $functionLevel > 0 && !$lastWasOperator ) {
								$argCount++;

								if ( $lastFunctionMulti && $lastFunctionMaxArgs && $argCount > $lastFunctionMaxArgs ) {
									throw new InvalidArgumentException( 'function ' . $lastFunction . '() may only take up to ' . $lastFunctionMaxArgs . ' arguments.' );
								}

							}

						}

						$lastWasOperator = false;
					} else { // identifier				
						if ( $wherePart === NULL ) {
							if ( $state != 2 || ( $lastComparator != '=' && $lastComparator != '!=' ) ) {
								throw new InvalidArgumentException( 'incorrect where syntax in state ' . $state . ' on key ' . $wherePartKey . ': ' . $wherePart . ', given where: ' . Debug::getStringRepresentation( $currentConf[ 'where' ] ) );
							}

							$where .= ' IS ' . ( $lastComparator === '!=' ? 'NOT NULL' : 'NULL' );

							$lastWasOperator = false;

							break;
						} elseif ( $wherePart instanceof RBStorageInterval ) {
							$where .= ' INTERVAL ';

							// $wherePost = ' ' . $wherePart->type;
							// $wherePart = $wherePart->value;

							$subWhere = '';
							$this->parseWhereConf( $subWhere, $path, array( 'where' => (array)$wherePart->value ), $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $group, $having, $order, $primaryKeys, $noAutoSelect, $filteredJoinData );

							$where .= $subWhere . ' ' . $wherePart->type;

							continue;

							// no break here, parse value normally
						} elseif ( in_array( $wherePart, $allowedOperators, true ) ) { // operator
							$where .= $wherePart;
							$lastWasOperator = true; // fixes comma handling

							$lastState = $state;

							continue 2;
						} elseif ( strlen( $wherePart ) == 4 && $wherePart[ 0 ] === '%' && ( (int)$wherePart[ 1 ] ) && $wherePart[ 2 ] === '$' && $wherePart[ 3 ] === 's' ) {
							// support %\d$s for setting positional arguments, from %1$s to %9$s

							if ( $state === 2 ) {
								switch ( $lastComparator ) {
									case '!=':
										$where .= ( $dontPlaceLastComparator ? '' : ' NOT IN (' ) . $wherePart . ')';
										break;
									case '=':
										$where .= ( $dontPlaceLastComparator ? '' : ' IN (' ) . $wherePart . ')';
										break;
									case '>':
									case '>=':
									case '<':
									case '<=':
										$where .= ( $dontPlaceLastComparator ? '' : ( ' ' . $lastComparator . ' ' ) ) . $wherePart;
										break;
									case 'LIKE':
										$where .= ( $dontPlaceLastComparator ? '' : ' LIKE ' ) . $wherePart;
									case 'RLIKE':
										$where .= ( $dontPlaceLastComparator ? '' : ' RLIKE ' ) . $wherePart;
								}
							} else {
								$where .= $wherePart;
							}

							if ( $functionLevel > 0 && !$lastWasOperator ) {
								$argCount++;
							}

							$lastWasOperator = false;

							break;
						}

						if ( $state === 0 ) {
							$where .= ( ( $argCount > 0 && !$lastWasOperator ) ? ', ' : ' ' );

							// DUP: function check
							if ( $functionLevel > 0 && $lastFunctionMulti ) {
								if ( $lastFunctionMaxArgs && $argCount > $lastFunctionMaxArgs ) {
									throw new InvalidArgumentException( 'function ' . $lastFunction . '() may only take up to ' . $lastFunctionMaxArgs . ' arguments.' );
								}
							}
						}

						if ( $functionLevel > 0 ) {
							$argCount++;
						}


						$wherePart = $path . '.' . $wherePart;

						$identifierParts = explode( '.', $wherePart );
						$fieldName = array_pop( $identifierParts );
						$wherePath = implode( '.', $identifierParts );


						if ( !isset( $pathRecordClassMapping[ $wherePath ] ) ) {
							$this->resolvePath( $wherePath, $identifierParts, $pathRecordClassMapping, $pathSynonymMapping, $tableCount, $getTotal, $from, $fields, $where, $group, $having, $order, $primaryKeys, $noAutoSelect );
						}

						// JB 23.1.2014 if wherePath is not on main record, we might be filtering a joined table
						if ( strpos( $wherePath, '.' ) !== false ) {
							$filteredJoinData = true;
						}

						$recordClass = $pathRecordClassMapping[ $wherePath ];

						$addPart = '';

						if ( $pathSynonymMapping ) {
							$synonym = $pathSynonymMapping[ $wherePath ];
							$addPart .= $synonym . '.';
						}

						$col = $recordClass::getColumnName( $fieldName );

						if ( !$col ) {
							throw new Exception( 'Unable to get column name for ' . $wherePart . ', fieldname = ' . $fieldName . ', recordClass = ' . $recordClass );
						}

						$wherePart = $addPart . $this->escapeObjectName( $col );

						if ( $state === 2 ) {
							switch ( $lastComparator ) {
								case '!=':
									$where .= ( $dontPlaceLastComparator ? '' : ' NOT IN (' ) . $wherePart . ')';
									break;
								case '=':
									$where .= ( $dontPlaceLastComparator ? '' : ' IN (' ) . $wherePart . ')';
									break;
								case 'LIKE':
									$where .= ( $dontPlaceLastComparator ? '' : ' LIKE ' ) . $wherePart;
									break;
								case 'RLIKE':
									$where .= ( $dontPlaceLastComparator ? '' : ' RLIKE ' ) . $wherePart;
									break;
								default:
									$where .= ( $dontPlaceLastComparator ? '' : ( ' ' . $lastComparator . ' ' ) ) . $wherePart;
							}
						} else {
							$where .= $wherePart;
						}

						if ( isset( $wherePost ) ) {
							$where .= $wherePost;
							unset( $wherePost );
						}
					}

					$dontPlaceLastComparator = false;
					$lastWasOperator = false;

					break;
				case 1: // comparator ( <, >, =, ...) or operator ( +, -, /, ... )
					$wherePart = strtoupper( $wherePart );

					if ( in_array( $wherePart, $allowedComparators, true ) ) {
						$lastComparator = $wherePart;
					} elseif ( in_array( $wherePart, $allowedOperators, true ) ) {
						$where .= ' ' . $wherePart . ' ';

						$lastWasOperator = true;

						$lastState = $state;
						$state = 0;
						continue 2; // skip incrementing state
					} else {
						throw new InvalidArgumentException( 'incorrect where syntax in state ' . $state . ' on key ' . $wherePartKey . ': ' . $wherePart . ', given where: ' . Debug::getStringRepresentation( $currentConf[ 'where' ] ) );
					}

					break;
				case 3: // connector (and, or) or operator ( +, -, /, ... )
					if ( !is_string( $wherePart ) ) {
						throw new InvalidArgumentException( 'incorrect where syntax in state ' . $state . ' on key ' . $wherePartKey . ': ' . Debug::getStringRepresentation( $wherePart ) . ', given where: ' . Debug::getStringRepresentation( $currentConf[ 'where' ] ) );
					}

					if ( in_array( $wherePart, $allowedOperators, true ) ) {
						$where .= ' ' . $wherePart . ' ';

						$lastWasOperator = true;

						$lastState = $state;
						$state = 2;
						continue 2; // skip incrementing state
					} else {
						$connector = strtoupper( $wherePart );

						if ( !array_key_exists( $connector, $allowedConnectors ) ) {
							throw new InvalidArgumentException( 'incorrect where syntax in state ' . $state . ' on key ' . $wherePartKey . ': ' . $wherePart . ', given where: ' . Debug::getStringRepresentation( $currentConf[ 'where' ] ) );
						}

						$where .= ' ' . $allowedConnectors[ $connector ] . ' ';
					}
					break;
			}


			if ( $functionLevel === 0 || !$lastFunctionMulti ) {
				$lastState = $state;
				$state = ( $state + 1 ) % 4;
			}
		}

		while ( $bracketLevel > 0 ) {
			$where .= ')';
			$bracketLevel--;
		}
	}


	public function selectFirst( $mainRecordClass, $queryStruct = NULL, $start = NULL, $getTotal = NULL, array $vals = NULL, $name = NULL, $noAutoSelect = false ) {
		$arr = $this->select( $mainRecordClass, $queryStruct, $start, 1, $getTotal, $vals, $name, $noAutoSelect );

		return array_shift( $arr );
	}

	// noAutoSelect = true will prevent all autoselect
	// noAutoSelect = truthy value !== true will try to keep autoselect to reasonable minimum
	public function selectRecords( $mainRecordClass, $queryStruct = NULL, $start = NULL, $count = NULL, $getTotal = NULL, array $vals = NULL, $name = NULL, $noAutoSelect = false ) {
		$rows = $this->select( $mainRecordClass, $queryStruct, $start, $count, $getTotal, $vals, $name, $noAutoSelect );

		if ( $count === 0 ) return $rows;

		$records = array();

		foreach ( $rows as $row ) {
			$records[ ] = $mainRecordClass::get( $this, $row );
		}

		return $records;
	}

	public function selectFirstRecord( $mainRecordClass, $queryStruct = NULL, $start = NULL, $getTotal = NULL, array $vals = NULL, $name = NULL, $noAutoSelect = false ) {
		$row = $this->selectFirst( $mainRecordClass, $queryStruct, $start, $getTotal, $vals, $name, $noAutoSelect );

		if ( $row === NULL ) {
			return NULL;
		}

		$record = $mainRecordClass::get( $this, $row );

		return $record;
	}


	public function save( IRecord $record, $isUpdate = NULL ) {
		if ( !$record->isDirty( false ) ) { // no need to check permissions in case record is not dirty (we're not actually doing anything in that case)
			return array(
				'action' => self::SAVE_ACTION_NONE,
				'affectedRows' => 0
			);
		}

		$this->checkSaveFilter( $record );

		$val = $record->getValues();

		if ( $isUpdate || $record->exists() ) {
			return $this->updateRecord( $record );
		}

		return $this->insertRecord( $record );
	}

	public function ensureSaveRecord( IRecord $record ) {
		if ( $this->transactionLevel == 0 ) {
			$record->save();
		} else {
			$this->recordsToEnsureSave[ ] = $record;
		}
	}

	protected function onLastTransactionEnded() {
		foreach ( $this->recordsToEnsureSave as $record ) {
			$record->save();
		}

		$this->recordsToEnsureSave = array();
	}

	/**
	 * Start transaction
	 *
	 * @return Transaction
	 */
	public function startTransaction() {
		$tx = parent::startTransaction();

		Record::storageStartTransaction( $this, $this->transactionLevel );

		return $tx;
	}

	public function commit( Transaction $transaction ) {
		parent::commit( $transaction );

		Record::storageCommit( $this, $this->transactionLevel );

		if ( $this->transactionLevel == 0 ) {
			$this->onLastTransactionEnded();
		}

	}

	public function rollback( Transaction $transaction ) {
		parent::rollback( $transaction );

		Record::storageRollback( $this, $this->transactionLevel );

		if ( $this->transactionLevel == 0 ) {
			$this->onLastTransactionEnded();
		}
	}

	protected function afterCleanup() {
		$this->onLastTransactionEnded();
	}

	protected function updateRecord( IRecord $record ) {
		$this->checkUpdateFilter( $record );

		$values = $record->getValues();

		// FIXME: use addWhereIdentity
		if ( $record::fieldDefinitionExists( Record::FIELDNAME_PRIMARY ) && ( $colName = $record::getColumnName( Record::FIELDNAME_PRIMARY ) ) && isset( $values[ $colName ] ) ) {
			$where = $this->escapeObjectName( $colName ) . '=' . $this->escape( $values[ $colName ] );
		} else {
			$primaryKeys = $record::getPrimaryKeyFields();

			$whereParts = array();

			foreach ( $primaryKeys as $pk ) {
				$colName = $record::getColumnName( $pk );

				if ( !isset( $values[ $colName ] ) ) { // must exist and may not be null either
					$values[ $colName ] = $record->{$pk};

					if ( !isset( $values[ $colName ] ) ) {
						throw new LogicException( 'Field "' . $pk . '" must be set in order to update, other values: ' . Debug::getStringRepresentation( $values ) );
					}
				}

				$whereParts[ ] = $this->escapeObjectName( $colName ) . '=' . $this->escape( $values[ $colName ] );
			}

			$where = implode( ' AND ', $whereParts );
		}

		$affectedRows = $this->update( $record::getTableName(), $where, $values );

		// TODO: enable this check again (needs some work in Record->copy, so already existing copies can be marked as non-dirty based upon unique keys as well)
//		if (!$affectedRows) {
//			throw new Exception( 'Record of class ' . get_class($record) . ' with values ' . Debug::getStringRepresentation($record->getValues()) . ' was not updated.' );
//		}

		return array(
			'action' => $affectedRows ? self::SAVE_ACTION_UPDATE : self::SAVE_ACTION_NONE,
			'affectedRows' => $affectedRows
		);
	}


	protected function insertRecord( IRecord $record ) {
		$this->checkInsertFilter( $record );

		$calculationFields = $record->fillCalculationFields();

		$table = $record::getTableName();
		$colVals = $record->getValues();

		if ( $calculationFields ) {
			$query = 'INSERT INTO ' . $this->escapeObjectName( $table ) . ' (' . implode( ',', $this->escapeObjectNameArray( array_keys( array_merge( $colVals, $calculationFields ) ) ) ) . ')'
					. ' SELECT ' . implode( ',', array_merge( $this->escape( $colVals, true, true ), $calculationFields ) )
					. ' FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ' . $this->escape( $table ) . ' AND TABLE_SCHEMA = ' . $this->escape( $this->database );

			$this->query( $query );

			$insertId = $this->conn->insert_id;
		} else {
			$insertId = $this->insert( $table, $colVals );
		}

		return array(
			'action' => self::SAVE_ACTION_CREATE,
			'insertID' => $insertId
		);
	}

	// FIXME: delete function in DB class, which should be leveraged here
	public function deleteRecord( IRecord $record ) {
		if ( !$record->exists() ) {
			return 0;
		}

		$this->checkDeleteFilter( $record ); // FIXME: should fall back to using rescueValues as well

		$queryStruct = array();
		$record->addWhereIdentity( $queryStruct, true );

		$recordClass = get_class( $record );

		$where = ' WHERE ';
		$prcmapping = array( $recordClass => $recordClass );
		$this->parseWhereConf( $where, $recordClass, $queryStruct, $prcmapping );


		$query = 'DELETE FROM ' . $this->escapeObjectName( $record->getTableName() ) . $where;
		$this->query( $query );

		$affectedRows = $this->getAffectedRows();

		if ( $affectedRows <= 0 ) {
			throw new NoActionPerformedException( 'Record of class "' . get_class( $record ) . '" was not deleted ; query: ' . $query );
		}

		return $affectedRows;
	}

	/**
	 * extension to basic escape so records can be passed as well
	 *
	 * in that case escape tries to return the field of their primary key
	 */
	public function escape( $values, $addQuotes = true, $keepNull = false ) {
		if ( is_array( $values ) || !is_object( $values ) ) {
			$escapedValues = parent::escape( $values, $addQuotes, $keepNull );
		} else {
			if ( !( $values instanceof IRecord ) ) {
				throw new InvalidArgumentException( 'Can only try to escape objects of class Record.' );
			}

			$escapedValues = parent::escape( $values->{Record::FIELDNAME_PRIMARY}, $addQuotes, $keepNull );
		}

		return $escapedValues;
	}


	public function registerFilter( IRBStorageFilter $filter, $identifier = NULL ) {
		if ( $filter === $this ) {
			throw new InvalidArgumentException( 'You may not set an RBStorage instance as its own filter!' );
		}

		if ( $identifier === NULL ) { // TODO: prevent key collision
			$identifier = '_' . count( $this->filters ); // prevent key from being used as numeric index
		}

		$this->filters[ $identifier ] = $filter;

		return $identifier;
	}

	public function unregisterFilter( $identifier ) {
		if ( !array_key_exists( $identifier, $this->filters ) ) {
			throw new InvalidArgumentException( 'No registered filter with identifier "' . $identifier . '" found' );
		}

		$filter = $this->filters[ $identifier ];
		unset( $this->filters[ $identifier ] );

		return $filter;
	}

	/**
	 * getFilter
	 *
	 * @return RBStorageDataTypeValueFilter
	 */
	public function getFilter( $identifier ) {
		if ( isset( $this->filters[ $identifier ] ) ) {
			return $this->filters[ $identifier ];
		}

		return NULL;
	}


	// IRBStorageFilter
	public function injectSelectFilter( $recordClass, &$conf, &$additionalJoinConf ) {
		foreach ( $this->filters as $filter ) {
			$filter->injectSelectFilter( $recordClass, $conf, $additionalJoinConf );
		}
	}

	public function modifySelectCacheName( &$name ) {
		foreach ( $this->filters as $filter ) {
			$filter->modifySelectCacheName( $name );
		}
	}


	public function checkSaveFilter( IRecord $record ) {
		foreach ( $this->filters as $filter ) {
			$filter->checkSaveFilter( $record );
		}
	}

	public function checkUpdateFilter( IRecord $record ) {
		foreach ( $this->filters as $filter ) {
			$filter->checkUpdateFilter( $record );
		}
	}

	public function checkInsertFilter( IRecord $record ) {
		foreach ( $this->filters as $filter ) {
			$filter->checkInsertFilter( $record );
		}
	}

	public function checkDeleteFilter( IRecord $record ) {
		foreach ( $this->filters as $filter ) {
			$filter->checkDeleteFilter( $record );
		}
	}

}

class NoActionPerformedException extends Exception {
}

final class RBStorageInterval {
	const SECOND = 'SECOND';
	const MINUTE = 'MINUTE';
	const HOUR = 'HOUR';
	const DAY = 'DAY';
	const MONTH = 'MONTH';
	const YEAR = 'YEAR';

	public static $types = array( self::SECOND, self::MINUTE, self::HOUR, self::DAY, self::MONTH, self::YEAR );

	protected $value;
	protected $type;

	public function __construct( $value, $type ) {
		if ( !in_array( $type, self::$types, true ) ) throw new InvalidArgumentException();

		$this->value = $value;
		$this->type = $type;
	}

	public function __get( $key ) {
		switch ( $key ) {
			case 'value':
				return $this->value;
			case 'type':
				return $this->type;
		}

		throw new InvalidArgumentException();
	}
}
