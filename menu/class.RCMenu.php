<?php
/**
 * @package stlocal\ext\person
 */

require_once STROOT . '/storage/interface.IRBStorage.php';

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
require_once __DIR__ . '/class.RCMenuItem.php';
require_once __DIR__ . '/class.DTMenuItemForeignReference.php';

require_once __DIR__ . '/class.BaseMenuItem.php';

/**
 * @package stlocal\ext\person
 */
class RCMenu extends BaseMenuItem {

	const BACKEND_TYPE = Record::BACKEND_TYPE_CONFIG;

	// Record
	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( 'id', 'live', 'language' ) )
		);
	}

	// Record
	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTSteroidPrimary::getFieldDefinition(),
			'id' => DTSteroidID::getFieldDefinition(),
			'live' => DTSteroidLive::getFieldDefinition(),
			'language' => DTSteroidLanguage::getFieldDefinition(),
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),

			'title' => DTString::getFieldDefinition( 127 ),

			'domainGroup' => DTSteroidDomainGroup::getFieldDefinition( true ),
			'defaultMenu' => DTRecordReference::getFieldDefinition( 'RCDefaultMenu', false ),
			'root' => DTRecordReference::getFieldDefinition( 'RCPage' ),
			'menu:RCMenuItem' => DTMenuItemForeignReference::getFieldDefinition()
		);
	}

	// IRecord
	public static function getEditableFormFields() {
		return array(
			'title',
			'defaultMenu',
			'root',
			'menu:RCMenuItem'
		);
	}

	// Record
	protected static function isFilterableField( User $user, $fieldName, $fieldDef ) {
		return parent::isFilterableField( $user, $fieldName, $fieldDef ) && $fieldDef[ 'dataType' ] != 'DTMenuItemForeignReference';
	}

	public function buildMenu( RCPage $currentPage, $isSubMenu = false ) {
		$menuItems = self::constructItemHierarchy( $this->{'menu:RCMenuItem'} );


		if ( $this->root || $isSubMenu ) { // in case root is set, add page menu items
			$this->addAdditionalSubItems( $menuItems, $currentPage, $isSubMenu );
		}

		if ( $menuItems ) {
			usort( $menuItems, array( $this, 'sortMenuItems' ) );
		}

		// template rendering needs to check showInMenu!

		return $menuItems;
	}


	public static function getClosest( $page, array $menuItems ) {
		$closest = NULL;

		foreach ( $menuItems as $item ) {
			if ( ( $itemPage = $item->page ) && ( $distance = $page->getDistance( $itemPage ) ) !== false && ( !isset( $shortestDistance ) || $distance < $shortestDistance ) ) {
				$shortestDistance = $distance;
				$closest = $item;
			}
		}

		return $closest;
	}

	final protected function getChildItems() {
		static $items;

		if ( $items === NULL ) {

			// TODO: caching!
			$items = $this->{'menu:RCMenuItem'};

			foreach ( $items as $k => $item ) {
				if ( $item->parent ) unset( $items[ $k ] );
			}
		}

		return $items;
	}

	final protected function getPageForSub( $isSubMenu = false, RCPage $currentPage ) {
		if ( $this->root ) {
			return $this->root;
		} else if ( $isSubMenu ) {
			if ( count( $this->{'menu:RCMenuItem'} ) ) { // if we have menu items, we don't want this page's subpages
				return NULL;
			} else {
				return $currentPage; // dynamic submenu mode where children of current page are displayed
			}
		}
	}

	final protected function hasAdditionalSubItems() {
		return (bool)$this->root;
	}

	// TODO: does this actually help?
	final private static function constructItemHierarchy( array $items ) {
		$ret = array();

		foreach ( $items as $item ) {
			if ( !$item->parent ) {
				$ret[ ] = $item;
			} else {
				$children = array();

				foreach ( $items as $item2 ) {
					if ( $item2->parent === $item ) {
						$children[ ] = $item2;
					}
				}

				if ( !empty( $children ) ) {
					$item->{'parent:RCMenuItem'} = $children;
				}
			}
		}

		return $ret;
	}
}