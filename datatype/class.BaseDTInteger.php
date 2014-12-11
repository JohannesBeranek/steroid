<?php
/**
 * @package steroid\datatype
 */

require_once STROOT . '/datatype/class.DataType.php';

/**
 * basic class for integer type values
 */
abstract class BaseDTInteger extends DataType {
	public function setValue( $data = NULL, $loaded = false ) {
		if ( $data === NULL || is_int( $data ) || ( is_float( $data ) && (float)intval( $data ) === $data ) || (string)(int)$data === $data ) {
			parent::setValue( $data === NULL ? NULL : (int)$data, $loaded );
		}
	}

	public function copy( array &$values, array $changes, array &$missingReferences, array &$originRecords, array &$copiedRecords ) {
		if ( !$this->config[ 'autoInc' ] ) {
			$values[ $this->fieldName ] = $this->record->{$this->fieldName};
		}
	}

	public function afterSave( $isUpdate, array $saveResult ) {
		parent::afterSave( $isUpdate, $saveResult );

		if ( $saveResult[ 'action' ] == RBStorage::SAVE_ACTION_CREATE && $this->config[ 'autoInc' ] ) {
			error_log(get_class($this->record) . ' id:' . $saveResult[ 'insertID' ]);
			$this->record->{$this->fieldName} = $saveResult[ 'insertID' ];
		}
	}
}
