<?php
/**
 * @package steroid\element
 */

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/area/interface.IHandleArea.php';
require_once STROOT . '/template/class.Template.php';
require_once STROOT . '/page/class.RCPage.php';

require_once STROOT . '/file/class.RCFile.php';


/**
 * @package steroid\element
 */
abstract class ElementRecord extends Record implements IHandleArea {
	// FIXME: move to extensions
	const WIDGET_TYPE_LIST = 'list';
	const WIDGET_TYPE_CRM = 'crm';
	const WIDGET_TYPE_MEDIA = 'media';
	const WIDGET_TYPE_EXTERNAL = 'external';
	const WIDGET_TYPE_GENERAL = 'general';
	const WIDGET_TYPE_TEASER = 'teaser';
	const WIDGET_TYPE_PERSON = 'person';
	const WIDGET_TYPE_EVENT = 'event';

	const BACKEND_TYPE = Record::BACKEND_TYPE_WIDGET;
	const WIDGET_TYPE = self::WIDGET_TYPE_GENERAL;

	protected function requireReferences() {
        // there's a difference if this function returns 1 or true
		$ret = parent::requireReferences();

		if ($ret) {
			return $ret;
		}

		return true;
    }

    protected function satisfyRequireReferences() {
        return parent::satisfyRequireReferences() && (bool)(
            $this->fields['element:RCElementInArea']->getReferenceCount() + $this->fields['recordPrimary:RCClipboard']->getReferenceCount()
        );
    }


	protected static function addGeneratedFieldDefinitions( array &$fieldDefinitions ) {
		$fieldDefinitions[ self::FIELDNAME_PRIMARY ] = DTSteroidPrimary::getFieldDefinition();
		$fieldDefinitions[ 'id' ] = DTSteroidID::getFieldDefinition();
		$fieldDefinitions[ 'live' ] = DTSteroidLive::getFieldDefinition();
		$fieldDefinitions[ 'ctime' ] = DTCTime::getFieldDefinition();
		$fieldDefinitions[ 'mtime' ] = DTMTime::getFieldDefinition();

		$fieldDefinitions[ 'element:RCElementInArea' ] = DTDynamicForeignReference::getFieldDefinition( 'class', true ); // TODO: unhardcode 'class'?
	}

	protected static function getKeys() {
		return array();
	}

	protected static function addGeneratedKeys( array &$keys ) {
		$keys[ 'primary' ] = DTKey::getFieldDefinition( array( 'id', 'live' ) );
	}


	public function handleArea( array $data, Template $template ) {
		echo get_class( $this );
	}

	public function getDirectUrl( RCPage $page, array $params = NULL, $rewriteOwningRecord = NULL, $rewriteTitlePrefix = NULL, $rewriteTitleSuffix = NULL ) {
		// TODO: handle rewriteOwningRecord being different than what we got in db 
		// (in that case we'd want an additional join, as multiple owning Records may have the same rewrite => url mapping)

		if ( $params === NULL ) {
			$params = array();
		}

		$params[ 'class' ] = get_class( $this );
		$params[ 'element' ] = $this->{self::FIELDNAME_PRIMARY};

		$url = DTUrlRewrite::provideRewrite( $this->storage, $page, $params, $rewriteOwningRecord, $rewriteTitlePrefix, $rewriteTitleSuffix );

		return $url;
	}

	/**
	 * get nice download url for a file
	 *
	 * $rec has to be a record with a file reference and a DTUrlRewrite field
	 */
	public function getDownloadUrl( $page, IRecord $rec = NULL, RCFile $file = NULL ) {
		if ( $rec === NULL ) {
			$rec = $this;
		}

		if ( $file === NULL ) {
			$fileFieldName = $rec->getDataTypeFieldName( 'DTFileRecordReference' );
			$file = $rec->{$fileFieldName};
		}

		list( $fn, $ext ) = $file->getFilenameAndExtForUrl();

		$url = $this->getDirectUrl( $page, array( 'file' => $file->{Record::FIELDNAME_PRIMARY} ), $rec, $fn, $ext );

		return $url;
	}

	protected function handleFileDownload( array $data, Template $template, $referencesPath ) {
		if ( $data[ 'isDirectLink' ] && ( $filePrimary = RequestInfo::getCurrent()->getQueryParam( 'file' ) ) && ( $fileRecord = $this->storage->selectFirstRecord( 'RCFile', array( 'fields' => '*', 'where' => array( self::FIELDNAME_PRIMARY, '=', array( $filePrimary ) ) ) ) ) ) {
			$referencedRecords = $this->collect( $referencesPath );

			if ( in_array( $fileRecord, $referencedRecords, true ) ) {
				$template->cancelOutput();

				$this->storage->sendFile( $fileRecord, $fileRecord->getNiceDownloadFilename() );

				return true;
			}
		}

		return false;
	}

	public function getHTMLID() {
		return strtolower( substr( get_class( $this ), 2 ) );
	}

	protected function getPager( $recordClass, $queryStruct, $itemsPerPage = NULL, $identifier = NULL ) {
		if ( $identifier === NULL ) { // autogenerate identifier
			// TODO: make sure identifiers don't collide (e.g. same elementRecord should be able to have multiple pagers)
			$identifier = array( 'page', $this->id );
		}

		return new Pager( $this->storage, $recordClass, $queryStruct, $identifier, $itemsPerPage );
	}

	public function getChildren() {
		return NULL;
	}

	public function getSearchableContent() {
		return NULL;
	}

	public function getContainingPages() {
		// FIXME: using queries should be better for performance
		// FIXME: this does not check for RCRTEArea or the like

		$areas = array();
		$elementInAreas = $this->storage->selectRecords('RCElementInArea', array('fields' => array('area.*'), 'where' => array('element', '=', array($this->{Record::FIELDNAME_PRIMARY}), 'AND', 'class', '=', array(get_called_class()))));

		foreach($elementInAreas as $element){
			$areas[] = $element->area;
		}
		
		$areasVisited = array();

		$pages = array();

		while ( $area = array_pop( $areas ) ) {
			if ( in_array($area, $areasVisited, true) ) // skip already visited to prevent inclusion loops
				continue; 

			$areaPages = array();

			$pageAreas = $this->storage->selectRecords('RCPageArea', array('fields' => array('page.*'), 'where' => array('area', '=', array($area->{Record::FIELDNAME_PRIMARY}))));

			foreach($pageAreas as $pageArea){
				$areaPages[] = $pageArea->page;
			}

			if ($areaPages) {
				$pages = array_merge( $pages, $areaPages );
			}

			$parentAreas = array();

			$parentElementInAreas = $this->storage->selectRecords('RCElementInArea', array('fields' => array('area.*'), 'where' => array('element', '=', array($area->{Record::FIELDNAME_PRIMARY}), 'AND', 'class', '=', array('RCArea'))));

			foreach($parentElementInAreas as $parentElementInArea){
				$parentAreas[] = $parentElementInArea->area;
			}

			if ($parentAreas) {
				$areas = array_merge( $areas, $parentAreas );
			}

			$areasVisited[] = $area;
		}

		return $pages;
	}

	public function duplicate(){
		$values = array();
		$fields = $this->getOwnFieldDefinitions();

		foreach($fields as $fieldName => $fieldDef){
			if(in_array($fieldDef['dataType'], array('DTSteroidPrimary', 'DTSteroidID', 'DTMTime', 'DTCTime', 'DTPubStartDateTime', 'DTPubEndDateTime'))){
				$values[$fieldName] = NULL;
			} else {
				$values[$fieldName] = $this->{$fieldName};
			}
		}

		$class = get_called_class();

		$newElement = $class::get( $this->storage, $values, false );

		$formFields = $this->getFormFields($this->storage);

		foreach($formFields as $fieldName => $formField){
			if(is_subclass_of($formField['dataType'], 'BaseDTForeignReference')){
				$foreignFieldName = $this->fields[ $fieldName ]->getForeignFieldName();
				$foreignRecordClass = $this->fields[ $fieldName ]->getRecordClass();

				$foreignRecs = $this->{$fieldName};
				$foreignRecValues = array();

				foreach($foreignRecs as $foreignRec){
					$values = $foreignRec->getValues();
					$newValues = array();

					foreach($values as $colName => $value){
						$newValues[str_replace('_primary', '', $colName)] = $value;
					}

					$newValues[$foreignFieldName] = $newElement;
					$foreignRecValues[] = $foreignRecordClass::get($this->storage, $newValues, false);
				}

				$newElement->{$fieldName} = $foreignRecValues;
			}
		}

		return $newElement;
	}
}
