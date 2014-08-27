<?php
/**
 * @package steroid\util
 */

require_once __DIR__ . '/interface.IPageable.php';

require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/request/class.RequestInfo.php'; 
 
/**
 * @package steroid\util
 */
class Pager {
	protected $storage;
	
	protected $recordClass;
	protected $recordClassIsColumn;
	
	protected $queryStruct;
	protected $identifier;
	
	/**
	 * Number of items per page
	 * 
	 * May be changed from outside (via __set) ; should not be changed between calling getData etc. for the same paged data, 
	 * otherwise you could get inconsistent data 
	 * 
	 * @var int
	 */
	protected $itemsPerPage;
	
	protected $provideAll;
	protected $cycle;
	
	protected $data;
	protected $page;
	
	protected $pageMode;
	protected $pageOffset = 0;
	
	protected static $preprocessedIdentifier = array();
	
	const PAGE_MODE_QUERYSTRUCT = 'querystruct';
	const PAGE_MODE_RECORDS = 'records';
	const PAGE_MODE_QUERY = 'query';
	const PAGE_MODE_PAGEABLE = 'pageable';
	const PAGE_MODE_PAGEABLE_ATOMIC = 'pageableAtomic';
	
	const DEFAULT_IDENTIFIER_SEPARATOR = ',';
	
	public function __set( $name, $value ) {
		switch ($name) {
			case 'itemsPerPage':
				$value = (int)$value;
				if ($value !== $this->itemsPerPage) {
					$this->itemsPerPage = $value;
					
					$this->data = NULL;
				}
			break;
			case 'provideAll':
				$value = (bool)$value;
				if ($value !== $this->provideAll) {
					$this->provideAll = $value;
					
					if ($value && (empty($this->data) || empty($this->data['allItems']))) {
						$this->data = NULL;
					} // in case we already have all items, we don't remove them from data
				}
			break;
			case 'pageOffset':
				$value = (int)$value;
				if ($value !== $this->pageOffset) {
					$this->pageOffset = $value;
						
					$this->data = NULL;
				}
			break;
			case 'page':
				if ($this->page !== NULL && $value !== $this->page) {
					$this->data = NULL;
				}

				$this->page = $value === NULL ? NULL : (int)$value;
			break;
			default:
				throw new Exception('Unknown property: "' . $name . '"');
		}
	}
	
	/**
	 * $queryStruct may be array of records as well, or query (page will append ' LIMIT ...' in case of query; pass fieldname where you select recordClass for $recordClass in this case!)
	 * 
	 * $identifier may be a single string, or an array of 2-3 values with: variable name, mark string [, separator = ',']
	 * using a custom separator is not recommended, as it might lead to problems when using different separators with the same identifier
	 * 
	 */
	public function __construct( IRBStorage $storage, $recordClass, $queryStruct, $identifier, $itemsPerPage = NULL, $page = NULL, $provideAll = NULL, $cycle = false ) {
		$this->storage = $storage;
		$this->recordClass = $recordClass;
		$this->queryStruct = $queryStruct;
		
		if (is_string($queryStruct)) {
			$this->pageMode = self::PAGE_MODE_QUERY;
			
			$this->recordClassIsColumn = substr($recordClass, 0, 2) !== 'RC';	
		} elseif (is_array($queryStruct)) {
			if (reset($queryStruct) instanceof IRecord) {
				$this->pageMode = self::PAGE_MODE_RECORDS;
			} else {
				$this->pageMode = self::PAGE_MODE_QUERYSTRUCT;
			}
		} elseif ($queryStruct instanceof IPageable) {
			$this->pageMode = self::PAGE_MODE_PAGEABLE;
		} elseif ($queryStruct instanceof IPageableAtomic ) {
			$this->pageMode = self::PAGE_MODE_PAGEABLE_ATOMIC;
		} else {
			throw new Exception('Unsupported paging type: ' . Debug::getStringRepresentation($queryStruct));
		}
		
		$this->identifier = $identifier;
		$this->itemsPerPage = $itemsPerPage;
		$this->page = $page;		
		
		$this->provideAll = (bool)$provideAll;
		$this->cycle = (bool)$cycle;
	}
	
	protected function extractRecords( $itemsRaw )  {
		$items = array();
		
		if ($this->recordClassIsColumn) {
			$prefix = NULL;
			$fieldDefs = array();
			
			if ($itemsRaw) {
				$firstRecord = reset($itemsRaw);
				
				foreach ($itemsRaw as $rawItem) {
					$recordClass = $rawItem[$this->recordClass];
					
					if (!isset($fieldDefs[$recordClass])) {
						$fieldDefs[$recordClass] = $recordClass::getFieldDefinitionsCached();
					}
									
					unset($rawItem[$this->recordClass]);
					$rawData = array();
					
					foreach ($rawItem as $k => $v) {
						if (!$prefix) {
							$pos = strpos( $k, '.' );
							
							if ($pos === false) {
								$prefix = false;
							} else {
								$prefix = substr($k, 0, $pos + 1);
								$prefixLen = strlen($prefix);
							}
						}
						
						if ($prefix !== false && substr($k, 0, $prefixLen) == ($prefix)) {
							$k = substr($k, $prefixLen);
						}
							
						if (isset($fieldDefs[$recordClass][$k])) {
							$rawData[$k] = $v;
						}
					}
					
					$items[] = $recordClass::get( $this->storage, $rawData, true );
				}
			}
		} else {
			$resultsRaw = $this->storage->getResultsFromRows( $itemsRaw, $this->recordClass, true );
			
			$items = array();
			$recordClass = $this->recordClass;
			
			foreach ($resultsRaw as $rawResult) {
				$items[] = $recordClass::get( $this->storage, $rawResult, true );
			}
		}
		
		return $items;
	}
	
	protected function compute() {
		if ($this->itemsPerPage === NULL) {
			throw new Exception('You have to set itemsPerPage before using page functions.');
		}
		
		if ($this->page === NULL) {
			$requestInfo = RequestInfo::getCurrent();
			
			// new identifier handling: allow for multiple pager to use the same param
			if (is_array($this->identifier)) {
				$identifier = reset($this->identifier);
				$identifierMark = next($this->identifier);
				
				// cache processing of identifier statically, as we might access it multiple times in a single page run
				if (!isset(self::$preprocessedIdentifier[$identifier])) {
					if (($separator = next($this->identifier)) === false) $separator = self::DEFAULT_IDENTIFIER_SEPARATOR; 
					
					$val = $requestInfo->getQueryParam($identifier);
					
					if ($val && !is_array($val)) {
						$arr = explode($separator, $val);
						
						// process all parts at once
						for ( $i = 1, $ii = count($arr); $i < $ii; $i+=2) {
							self::$preprocessedIdentifier[$identifier][$arr[$i-1]] = intval($arr[$i]);
						}
					} else {
						self::$preprocessedIdentifier[$identifier] = false;
					}
				}

				$page = isset(self::$preprocessedIdentifier[$identifier][$identifierMark]) ? self::$preprocessedIdentifier[$identifier][$identifierMark] : 0;

			} else {
				$page = intval($requestInfo->getQueryParam($this->identifier));
			}
	
			
			$page -= $this->pageOffset;
		} else {
			$page = $this->page;
		}
		
		$page = max(0, $page);
		
		if ( $this->pageMode === self::PAGE_MODE_PAGEABLE_ATOMIC ) {
			$this->queryStruct->setRange( $this->itemsPerPage * $page, $this->itemsPerPage ); // $page is not limited to <= maxPage here, as we can't yet know what maxPage is!
		}
		
		// get total count to determine if page is actually valid
		if ($this->pageMode === self::PAGE_MODE_RECORDS) {
			$allItems = $this->queryStruct;
		} elseif ($this->provideAll) {
			if ( $this->pageMode === self::PAGE_MODE_QUERYSTRUCT ) {
				$allItems = $this->storage->selectRecords( $this->recordClass, $this->queryStruct );
			} elseif (	$this->pageMode === self::PAGE_MODE_QUERY ) {
				// items are arrays, not records in this case!
				$allItemsRaw = $this->storage->fetchAll( $this->queryStruct );
						
				
				$allItems = $this->extractRecords( $allItemsRaw );
			} elseif ( $this->pageMode === self::PAGE_MODE_PAGEABLE || $this->pageMode === self::PAGE_MODE_PAGEABLE_ATOMIC ) {
				$allItems = $this->queryStruct->getAllItems();
			}
		}
		
		if (isset($allItems)) {	
			$total = count($allItems);
		} elseif ( $this->pageMode === self::PAGE_MODE_QUERY ) {
			$totalRow = $this->storage->fetchFirst( 'SELECT COUNT(*) FROM (' . $this->queryStruct . ') tpagerCountTable');
			$total = reset($totalRow);
		} elseif ( $this->pageMode === self::PAGE_MODE_PAGEABLE || $this->pageMode === self::PAGE_MODE_PAGEABLE_ATOMIC ) {
			$total = $this->queryStruct->getTotal();
		} elseif ( $this->pageMode === self::PAGE_MODE_QUERYSTRUCT ) {
			$total = $this->storage->selectRecords( $this->recordClass, $this->queryStruct, NULL, 0, true );
		}
		
		$maxPage = max(0, floor(($total - 1) / $this->itemsPerPage));
		
		$currentPage = min( $maxPage, $page );
	
		$start = $this->itemsPerPage * $currentPage;
	
		if ($total == 0) {
			$items = array();
		} else {
			if (isset($allItems)) {
				$items = array_slice($allItems, $start, $this->itemsPerPage);
			} elseif ( $this->pageMode === self::PAGE_MODE_QUERY ) {
				$itemsRaw = $this->storage->fetchRange( $this->queryStruct, $start, $this->itemsPerPage );
				
				$items = $this->extractRecords( $itemsRaw );
			} elseif ( $this->pageMode === self::PAGE_MODE_PAGEABLE || $this->pageMode === self::PAGE_MODE_PAGEABLE_ATOMIC ) {
				$items = $this->queryStruct->getItems( $start, $this->itemsPerPage );
			} elseif ( $this->pageMode === self::PAGE_MODE_QUERYSTRUCT ) {
				$items = $this->storage->selectRecords( $this->recordClass, $this->queryStruct, $start, $this->itemsPerPage ); // TODO: reuse parsed $queryStruct for better performance			
			}
		}
		
		$this->data = array( 
			'items' => $items,
			'itemsPerPage' => $this->itemsPerPage,
			'start' => $start,
			'total' => $total,
			'maxPage' => $maxPage,
			'currentPage' => $currentPage,
			'id' => $this->identifier,
			'offset' => $this->pageOffset
		);
		
		if (isset($allItems)) {
			$this->data['allItems'] = $allItems;
		}
	}
	
	public function getData() {
		if (!$this->data) $this->compute();
						
		return $this->data;
	}

	public function hasNext() {
		$data = $this->getData();
		
		return $this->cycle || $data['maxPage'] > $data['currentPage'];
	}

	public function getNextParams( array $otherParams = NULL, $nullOnFirst = true ) {		
		$data = $this->getData();

		$nextPage = $data['currentPage'] + 1;
		
		$computedPage = ($nextPage > $data['maxPage'] ? ($this->cycle ? 0 : $data['maxPage']) : $nextPage);
		
		return $this->_getPageParams( $computedPage, $otherParams, $nullOnFirst );
	}

	public function getPlaceholderParams( $placeholder, array $otherParams = NULL ) {
		$params = $otherParams === NULL ? array() : $otherParams;

		$this->_setIdentifierParam( $params, $placeholder );

		return $params;
	}

	public function getPageParams( $page = NULL, array $otherParams = NULL, $nullOnFirst = true ) {
		$data = $this->getData();

		$computedPage = max( 0, min( $data[ 'maxPage' ], $page !== NULL ? intval($page) : $data['currentPage'] ));

		return $this->_getPageParams( $computedPage, $otherParams, $nullOnFirst );
	}
	
	public function hasPrev() {
		$data = $this->getData();
		
		return $this->cycle || $data['currentPage'] > 0;
	}
	
	public function getPrevParams( array $otherParams = NULL, $nullOnFirst = true ) {		
		$data = $this->getData();

		$prevPage = $data['currentPage'] - 1;
		
		$computedPage = ($prevPage < 0 ? ($this->cycle ? $data['maxPage'] : max( 0, $prevPage )) : $prevPage);
		
		return $this->_getPageParams( $computedPage, $otherParams, $nullOnFirst );
	}
	
	protected function _setIdentifierParam( array &$params, $value ) {
		if (is_array($this->identifier)) {
			$identifier = reset($this->identifier);
			$identifierMark = next($this->identifier);
				
			if (($separator = next($this->identifier)) === false) $separator = self::DEFAULT_IDENTIFIER_SEPARATOR; 	
			
			$params[$identifier] = $identifierMark . $separator . $value;	
		} else {
			$params[$this->identifier] = $value;
		}
	} 
	
	protected function _getPageParams( $computedPage, array $otherParams = NULL, $nullOnFirst = true ) {
		$params = $otherParams === NULL ? array() : $otherParams;
		
		if ($computedPage === 0 && $nullOnFirst) {
			$this->_setIdentifierParam($params, NULL);
		} else {
			$this->_setIdentifierParam($params, $computedPage + $this->pageOffset);
		}
		
		return $params;
	}
}