<?php
/**
 * @package stlocal\ext\person
 */

require_once STROOT . '/storage/record/class.Record.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTSteroidID.php';
require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/language/class.DTSteroidLanguage.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/file/class.DTImageRecordReference.php';
require_once STROOT . '/storage/record/class.DTRecordClassSelect.php';
require_once __DIR__ . '/class.DTMenuItemForeignReference.php';
require_once __DIR__ . '/class.DTMenuItemParent.php';
require_once __DIR__ . '/class.DTMenuItemChildren.php';

require_once __DIR__ . '/class.BaseMenuItem.php';

/**
 * @package stlocal\ext\person
 */
class RCMenuItem extends BaseMenuItem {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'id', 'live', 'language' ) ),
			'k_page' => DTKey::getFieldDefinition( array( 'page' ) ),
			'k_parent' => DTKey::getFieldDefinition( array( 'parent' ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			Record::FIELDNAME_SORTING => DTSteroidSorting::getFieldDefinition(),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'language' => DTSteroidLanguage::getFieldDefinition(),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'menu' => DTRecordReference::getFieldDefinition( 'RCMenu', true ),
			'parent' => DTMenuItemParent::getFieldDefinition(),
			'title' => DTString::getFieldDefinition( 127, false, NULL, true ),
			'showInMenu' => DTBool::getFieldDefinition( true ),
			'page' => DTRecordReference::getFieldDefinition( 'RCPage', false ),
			'subItemsFromPage' => DTBool::getFieldDefinition( true ),
			'url' => DTString::getFieldDefinition( 255, false, NULL, true ),
			'pagesFromRecordClass' => DTRecordClassSelect::getFieldDefinition( array( array( 'RCPage', 'pageTypeFilter' ) ), true ),
			'icon' => DTImageRecordReference::getFieldDefinition( false ),
			'alignRight' => DTBool::getFieldDefinition()
		);
	}

	public static function getEditableFormFields() {
		return array(
			'menu',
			'parent',
			'title',
			'showInMenu',
			'page',
			'subItemsFromPage',
			'url',
			'pagesFromRecordClass',
			'icon',
			'alignRight',
			'parent:RCMenuItem',
			'sorting'
		);
	}

	public static function getTitleFields() {
		return array(
			'title',
			'page'
		);
	}

	final protected function getChildItems() {
		return $this->{'parent:RCMenuItem'};
	}

	final protected function getPageForSub( $isSubMenu = false, RCPage $currentPage ) {
		return $this->subItemsFromPage ? $this->page : NULL;
	}

	final protected function hasAdditionalSubItems() {
		return ( $this->subItemsFromPage && $this->page ) || $this->pagesFromRecordClass;
	}

	protected function getPageItems( RCPage $currentPage, $isSubMenu = false ) {
		$pages = parent::getPageItems( $currentPage );

		if ( $recordClassString = $this->pagesFromRecordClass ) {
			$recordClasses = explode( ',', $recordClassString );

			foreach ( $recordClasses as $rc ) {
				$pageField = $rc::getDataTypeFieldName( 'DTSteroidPage' );

				$rcDefaultSorting = $rc::getDefaultSorting();

				$queryStruct = array( 'fields' => array( $pageField . '.*' ), 'orderBy' => $rcDefaultSorting );

				if ( $domainGroupField = $rc::getDataTypeFieldName( 'DTSteroidDomainGroup' ) ) {
					$queryStruct[ 'where' ] = array( $domainGroupField, '=', '%1$s' ); // "domainGroup" is constant
					$queryStruct[ 'vals' ] = array( $currentPage->domainGroup );
				}

				// cache has to be cleared if one of the following is changed for a used recordClass:
				// - defaultSorting
				// - fieldname for DTSteroidPage
				// - existence of DTSteroidDomainGroup in recordClass fieldDefinitions
				$queryStruct[ 'name' ] = 'RCMenuItem_getPageItems_dynamic_' . $rc;

				$records = $this->storage->selectRecords( $rc, $queryStruct );

				$rcPages = array();

				foreach ( $records as $record ) {
					$rcPages[ ] = $record->{$pageField};
				}

				$pages = !empty( $pages ) ? array_merge( $pages, $rcPages ) : $rcPages;
			}
		}

		return $pages;
	}

	protected function saveInit() {
		if ( !$this->fields[ 'menu' ]->getValue() && ( $parent = $this->fields[ 'parent' ]->getValue() ) && ( $menu = $parent->getFieldValue( 'menu' ) ) ) {
			$this->menu = $menu;
		}

		parent::saveInit();
	}


	public function getFormValues( array $fields ) {
		return parent::getFormValues( array_merge( array( 'menu' ), array_diff( $fields, array( 'parent:RCMenuItem' ) ) ) ); // save the children!
	}

	public function getTitle() {
		$title = ( $this->page && ( $this->title === '' || $this->title === NULL ) )
				? $this->page->title
				: $this->title;

		return $title === NULL ? '' : $title;
	}

	public function notifyReferenceRemoved( IRecord $originRecord, $reflectingFieldName, $triggeringFunction ) {
		if ( get_class( $originRecord ) == 'RCPage' && $reflectingFieldName == 'page' && $triggeringFunction == 'setValue' ) { // menuItem is being removed from within page editor, so delete it
			$this->delete();
			return;
		}

		parent::notifyReferenceRemoved( $originRecord, $reflectingFieldName, $triggeringFunction );
	}
}