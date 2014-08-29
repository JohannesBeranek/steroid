<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';

/**
 * @package steroid\datatype
 */
class BaseDTString extends DataType {
	const SEARCH_TYPE_PREFIX = 'prefix';
	const SEARCH_TYPE_SUFFIX = 'suffix';
	const SEARCH_TYPE_BOTH = 'both';

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, array $fieldDef ) {
		foreach ( $userFilters as $idx => $filterConf ) {
			if ( in_array( $fieldName, $filterConf[ 'filterFields' ] ) ) {
				if ( !isset( $queryStruct[ 'where' ] ) ) {
					$queryStruct[ 'where' ] = array();
				} else if ( !empty( $queryStruct[ 'where' ] ) ) {
					if ( isset( $filterConf[ 'filterModifier' ] ) ) {
						$queryStruct[ 'where' ][ ] = $filterConf[ 'filterModifier' ];
					} else {
						$queryStruct[ 'where' ][ ] = 'AND';
					}
				}

				$value = str_replace( '*', '%', $storage->escapeLike( $filterConf[ 'filterValue' ] ) );

				if ( $fieldDef[ 'searchType' ] === self::SEARCH_TYPE_BOTH || $fieldDef[ 'searchType' ] === self::SEARCH_TYPE_PREFIX ) {
					$value = '%' . $value;
				}

				if ( $fieldDef[ 'searchType' ] === self::SEARCH_TYPE_BOTH || $fieldDef[ 'searchType' ] === self::SEARCH_TYPE_SUFFIX ) {
					$value .= '%';
				}

				$queryStruct[ 'where' ][ ] = $fieldName;
				$queryStruct[ 'where' ][ ] = 'LIKE';
				$queryStruct[ 'where' ][ ] = (array)preg_replace( '/%+/', '%', $value );

				unset( $userFilters[ $idx ] );
			}
		}
	}
}

?>