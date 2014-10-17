<?php
/**
 * @package steroid\template
 */

require_once STROOT . '/datatype/class.BaseDTRecordReference.php';
require_once STROOT . '/template/class.RCTemplate.php';
require_once STROOT . '/user/class.User.php';

/**
 * @package steroid\template
 */
class DTTemplate extends BaseDTRecordReference {
	public static function getFieldDefinition( $requireForeign = false ) {
		return array(
			'dataType' => __CLASS__,
			'recordClass' => 'RCTemplate',
			'nullable' => !$requireForeign,
			'requireForeign' => $requireForeign,
			'requireSelf' => false,
			'default' => NULL,
			'constraints' => array( 'min' => 1, 'max' => 1 )
		);
	}

	public static function getDefaultValue( IStorage $storage, $fieldName = NULL, array $fieldConf = NULL, array $extraParams = NULL ) {
		if ( empty( $fieldConf ) ) {
			throw new InvalidArgumentException( '$fieldConf must be set' );
		}

		$templatesAvailable = self::getTemplatesAvailable( $storage, $extraParams[ 'recordClasses' ][ 0 ] );

		if ( !$templatesAvailable ) {
			throw new MissingTemplateException( 'Missing template for record of class "' . $extraParams[ 'recordClasses' ][ 0 ] . '"', array(
				'rc' => $extraParams[ 'recordClasses' ][ 0 ]
				) );
		}

		$templatesAvailable->getTitle();

		$values = array();
		$fieldDefinitions = RCTemplate::getFieldDefinitionsCached();

		foreach ( $fieldDefinitions as $fieldName => $fieldDefinition ) {
			if ( isset( $templatesAvailable->{$fieldName} ) ) {
				$values[ $fieldName ] = $templatesAvailable->{$fieldName};
			}
		}

		return $values;
	}

	protected static function getTemplatesAvailable( IStorage $storage, $recordClass ) {
		$qs = array( 'where' => array( 'recordClass', '=', array( $recordClass ), 'AND', 'live', '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) ) );

		if ( $recordClass === 'RCPage' ) {
			$user = User::getCurrent();
			$domainGroup = $user->getSelectedDomainGroup();

			$rootPage = $storage->selectFirst( 'RCPage', array( 'where' => array( 'parent', '=', NULL, 'AND', 'domainGroup', '=', array( $domainGroup ), 'AND', 'live', '=', array( DTSteroidLive::LIVE_STATUS_PREVIEW ) ) ) );

			$qs[ 'orderBy' ] = array( 'ctime' => DB::ORDER_BY_ASC );
			array_push( $qs[ 'where' ], 'AND', 'isStartPageTemplate', '=', array( empty( $rootPage ) ? 1 : 0 ) );
		}

		return $storage->selectFirstRecord( 'RCTemplate', $qs );
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, array $fieldDef ) {
		if ( $mainRecordClass !== 'RCTemplate' && $recordClass === 'RCTemplate' ) {
			if ( !isset( $queryStruct[ 'where' ] ) ) {
				$queryStruct[ 'where' ] = array();
			} else if ( !empty( $queryStruct[ 'where' ] ) ) {
				$queryStruct[ 'where' ][ ] = 'AND';
			}

			$queryStruct[ 'where' ][ ] = 'recordClass';
			$queryStruct[ 'where' ][ ] = '=';
			$queryStruct[ 'where' ][ ] = array( $mainRecordClass );
		}

		parent::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass, $fieldName, $fieldDef );
	}
}

class MissingTemplateException extends SteroidException {
}
