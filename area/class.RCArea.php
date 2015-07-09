<?php
/**
 * @package steroid\area
 */

require_once STROOT . '/element/class.ElementRecord.php';

require_once STROOT . '/datatype/class.DTString.php';

/**
 * @package steroid\area
 */
class RCArea extends ElementRecord {
	const KEY_TEMPLATE_FILE = 'areaTemplateFile';

	const WIDGET_TYPE = ElementRecord::WIDGET_TYPE_GENERAL;

	protected static function getFieldDefinitions() {
		return array(
			'title' => DTString::getFieldDefinition( 127, false, NULL, true )
		);
	}

	public function getFormValues( array $fields ) {
		if ( !in_array( 'area:RCElementInArea', $fields ) ) {
			$fields[ ] = 'area:RCElementInArea';
		}

		return parent::getFormValues( $fields );
	}

	protected function satisfyRequireReferences() {
		$fieldDefinitions = $this->getForeignReferences();
		$fields = array_intersect_key( $this->fields, $fieldDefinitions );

		unset( $fields[ 'area:RCElementInArea' ] );

		return $this->getReferenceCount( $fields, true );
	}

	public function handleArea( array $data, Template $template ) {
		if ( !empty( $data[ self::KEY_TEMPLATE_FILE ] ) ) {
			$template->put( $data[ self::KEY_TEMPLATE_FILE ] );
		} else {
			$this->placeElements( $template, $data[ 'columns' ] );
		}
	}

	public function placeElements( Template $template, $columns ) {
		$colsFilled = 0;
		$elementsInRow = 0;
		$rowpos = 1;

		// saves a lot of queries
		$elementJoins = $this->getChildren();

		foreach ( $elementJoins as $elementJoin ) {
			if ( $elementJoin->isVisible() ) {
				
				
					$elementColumns = $elementJoin->columns;

					if ( $elementColumns < $columns ) {
						if ( ( $colsFilled + $elementColumns ) > $columns ) {
							$elementsInRow = $colsFilled = 0;
							$rowpos++;
						}

						$colsFilled += $elementColumns;
						$elementsInRow++;
					} else {
						$colsFilled = $elementColumns;
						$rowpos++;
					}

					$template->placeElement( array( 'element' => $elementJoin->element->load(), 'columns' => $elementColumns, 'colpos' => $elementsInRow, 'rowpos' => $rowpos ) );
			
			}
		}
	}

	public function getChildren() {
		$children = array();

		// saves a lot of queries
		$elementJoins = $this->fields[ 'area:RCElementInArea' ]->load();

		return $elementJoins;
	}
	
	public function hideFromAffectedRecordData() {
		return true;
	}

	protected function getCopiedForeignFields() {
		return array( 'area:RCElementInArea' );
	}

	public function duplicate(){
		$newArea = parent::duplicate();

		$newElementsInArea = array();
		$elementsInArea = $this->{'area:RCElementInArea'};

		foreach($elementsInArea as $elementInArea){
			$newElementsInArea[] = $elementInArea->duplicate($newArea);
		}

		$newArea->{'area:RCElementInArea'} = $newElementsInArea;

		return $newArea;
	}
}