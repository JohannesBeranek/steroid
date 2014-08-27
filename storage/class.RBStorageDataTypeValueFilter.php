<?php

require_once __DIR__ . '/interface.IRBStorageFilter.php';

class RBStorageDataTypeValueFilter implements IRBStorageFilter {
	protected $dataTypeValues = array();
	protected $dataTypeValueDisabledFlags = array();
	
	public function __construct( array $dataTypeValues = NULL ) {
		$this->setDataTypeValues( $dataTypeValues );
	}
	
	public function setDataTypeValues( array $dataTypeValues = NULL ) {
		$this->dataTypeValues = $dataTypeValues === NULL ? array() : $dataTypeValues;
	}
	
	public function setDataTypeValue( $dataType, $value ) {
		$this->dataTypeValues[$dataType] = $value;
	}
	
	public function disableDataTypeValueFilter( $dataType ) {
		$this->dataTypeValueDisabledFlags[ $dataType ] = true;
	}
	
	public function enableDataTypeValueFilter( $dataType ) {
		if ( isset( $this->dataTypeValueDisabledFlags[ $dataType ] ) ) {
			unset( $this->dataTypeValueDisabledFlags[ $dataType ] );
		}
	}
	
	
	// IRBStorageFilter	
	public function injectSelectFilter( $recordClass, &$conf, &$additionalJoinConf ) {
		foreach ($this->dataTypeValues as $dataType => $value) {
			if (($field = $recordClass::getDataTypeFieldName( $dataType )) && empty($this->dataTypeValueDisabledFlags[ $dataType ])) {
				if (!empty($additionalJoinConf)) {
					// field might already be in there, but adding it nevertheless doesn't really hurt and is way easier and safer than any other option
					array_unshift($additionalJoinConf, '(');
					array_push($additionalJoinConf, ')', 'AND', $field, '=', array( $value ) );
				} else {
					$additionalJoinConf = array( $field, '=', array( $value ) );
				}
			}
		}
	}

	public function modifySelectCacheName( &$name ) {
		if ($name !== NULL) { // make sure no other filter has disabled caching this far
			foreach ($this->dataTypeValues as $dataType => $value) {
				if (empty($this->dataTypeValueDisabledFlags[ $dataType ])) {
					$name .= '_' . $dataType . ':' . $value;
				}
			}
		}
	}
	
	public function checkSaveFilter( IRecord $record ) {
		// TODO: security checks?
	}
	
	public function checkUpdateFilter( IRecord $record ) {
		// TODO: security checks?
	}
	
	public function checkInsertFilter( IRecord $record ) {
		// TODO: security checks?
	}
	
	public function checkDeleteFilter( IRecord $record ) {
		// TODO: security checks?
	}
}