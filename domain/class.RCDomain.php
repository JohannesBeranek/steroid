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
			'noSSL' => DTBool::getFieldDefinition()
		);
	}

	protected function beforeSave( $isUpdate, $isFirst ) {
		parent::beforeSave( $isUpdate, $isFirst );

		$returnCodeField = $this->getDataTypeFieldName( 'DTSteroidReturnCode' );
		$domainGroupField = $this->getDataTypeFieldName( 'DTSteroidDomainGroup' );

		if ( $this->fields[ $returnCodeField ]->hasBeenSet() ) {
			$ownReturnCode = $this->{$returnCodeField};

			$queryStruct = array( 'where' => array( 'domainGroup', '=', array( $this->{$domainGroupField} ) ) );

			if ( $isUpdate ) {
				$queryStruct[ 'where' ][ ] = 'AND';
				$queryStruct[ 'where' ][ ] = self::FIELDNAME_PRIMARY;
				$queryStruct[ 'where' ][ ] = '!=';
				$queryStruct[ 'where' ][ ] = array( $this->{self::FIELDNAME_PRIMARY} );
			}

			$siblingDomains = $this->storage->selectRecords( get_called_class(), $queryStruct );

			if ( $ownReturnCode != DTSteroidReturnCode::RETURN_CODE_PRIMARY ) { // set to primary if this is the only domain or no other domain is primary
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

			if ( !empty( $siblingDomains ) && $ownReturnCode == DTSteroidReturnCode::RETURN_CODE_PRIMARY ) { // set current primary to alias if this is now primary
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