<?php
/**
 * @package steroid\area
 */

require_once STROOT . '/datatype/class.BaseDTForeignReference.php';
require_once STROOT . '/area/class.RCArea.php';
require_once STROOT . '/util/class.StringFilter.php';

/**
 * Base Class for DT in RCArea referenced by join records between RCArea and RCPage/RCTemplate/... as well as referenced by RCElementInArea
 * 
 * $this->record is always an RCArea here
 *
 * @package steroid\area
 */
class BaseDTAreaForeignReference extends BaseDTForeignReference {

	public function getFormValue() {
		$recordClass = $this->getRecordClass();

		if ($recordClass == 'RCElementInArea') {
			$recs = $this->record->{$this->fieldName};

			$values = array();

			foreach($recs as $rec) {
				$recordClass = $rec->class;
					
				$values[] = array(
					'primary' => $rec->primary,
					Record::FIELDNAME_SORTING => $rec->sorting,
					'class' => $recordClass,
					'element' => $rec->element->getFormValues(array_keys( $recordClass::getFormFields( $this->storage) ) ),
					'columns' => $rec->columns,
					'fixed' => $rec->fixed,
					'hidden' => $rec->hidden
				);
			}

			return $values;
		}

		return parent::getFormValue();
	}
}