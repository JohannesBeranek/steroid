<?php
/**
 * @package steroid\domain
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTSteroidReturnCode.php';
require_once STROOT . '/domaingroup/class.DTSteroidDomainGroup.php';

/**
 * @package steroid\domain
 */

class RCDomain extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_CONFIG;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( Record::FIELDNAME_PRIMARY ) ),
			'uniqueDomain' => DTKey::getFieldDefinition( array( 'domain' ), true )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'domain' => DTString::getFieldDefinition( 255 ),
			'domainGroup' => DTSteroidDomainGroup::getFieldDefinition(),
			'returnCode' => DTSteroidReturnCode::getFieldDefinition(),
			'disableTracking' => DTBool::getFieldDefinition(),
			'noSSL' => DTBool::getFieldDefinition(),
			'redirectToUrl' => DTString::getFieldDefinition( 255, false, '', true ),
			'redirectToPage' => DTRecordReference::getFieldDefinition( 'RCPage' )
		);
	}

	protected function beforeSave( $isUpdate, $isFirst, array &$savePaths = NULL ) {
		parent::beforeSave( $isUpdate, $isFirst, $savePaths );

		//check if domain is taken
		$existing = $this->storage->selectFirst( 'RCDomain', array( 'fields' => array( 'domainGroup.*' ), 'where' => array( 'domain', '=', array( $this->domain ) ) ) );

		if ($existing !== NULL) {
			$existing = array_shift($existing);
	
			if ( (int)$existing['primary'] !== $this->domainGroup->primary ) {
				throw new DomainTakenException( 'This domain is already in use by "' . $existing['title'] . '"', array(
					'rc' => 'RCDomainGroup',
					'record' => $existing['title']
				) );
			}
		}

		//exit early so we don't set this back to primary if a new primary domain is being saved
		if(!self::isSaveOriginRecord($this)){
			return;
		}

		//check primary/alias
		$returnCodeField = $this->getDataTypeFieldName( 'DTSteroidReturnCode' );
		$domainGroupField = $this->getDataTypeFieldName( 'DTSteroidDomainGroup' );

		if ($this->fields[ $returnCodeField ]->hasBeenSet() ) {
			$ownReturnCode = $this->{$returnCodeField};

			$queryStruct = array( 'where' => array( 'domainGroup', '=', array( $this->{$domainGroupField} ) ) );

			if ( $isUpdate ) {
				$queryStruct[ 'where' ][ ] = 'AND';
				$queryStruct[ 'where' ][ ] = self::FIELDNAME_PRIMARY;
				$queryStruct[ 'where' ][ ] = '!=';
				$queryStruct[ 'where' ][ ] = array( $this->{self::FIELDNAME_PRIMARY} );
			}

			$siblingDomains = $this->storage->selectRecords( get_called_class(), $queryStruct );

			// set to primary if this is the only domain or no other domain is primary
			if ( $ownReturnCode != DTSteroidReturnCode::RETURN_CODE_PRIMARY ) {
				if ( empty( $siblingDomains ) ) {
					$this->{$returnCodeField} = DTSteroidReturnCode::RETURN_CODE_PRIMARY;
				} else {
					$hasPrimary = false;

					foreach ( $siblingDomains as $domain ) {
						if ( $domain->{$returnCodeField} == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) {
							$hasPrimary = true;
						}
					}

					if ( !$hasPrimary ) {
						$this->{$returnCodeField} = DTSteroidReturnCode::RETURN_CODE_PRIMARY;
					}
				}
			}

			// set current primary to alias if this is now primary
			if ( !empty( $siblingDomains ) && $ownReturnCode == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) {
				foreach ( $siblingDomains as $domain ) {
					if ( $domain->{$returnCodeField} == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) {
						$domain->{$returnCodeField} = DTSteroidReturnCode::RETURN_CODE_ALIAS;
						$domain->save();
					}
				}
			}
		}
	}
}

class DomainTakenException extends SteroidException {}