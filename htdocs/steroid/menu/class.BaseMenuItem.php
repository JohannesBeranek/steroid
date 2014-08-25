<?php

require_once STROOT . '/storage/record/class.Record.php';

abstract class BaseMenuItem extends Record {
	abstract protected function getChildItems();

	abstract protected function getPageForSub( $isSubMenu = false, RCPage $currentPage );

	abstract protected function hasAdditionalSubItems();

	final public function unfold( RCPage $currentPage ) {
		$menuItems = $this->getChildItems();
// FIXME: pagesFromRecordClass		
		$this->addAdditionalSubItems( $menuItems, $currentPage );

		// filter !showInMenu
		foreach ( $menuItems as $k => $item ) {
			if ( !$item->showInMenu ) {
				unset( $menuItems[ $k ] );
			}
		}

		if ( $menuItems ) {
			usort( $menuItems, array( $this, 'sortMenuItems' ) );
		}

		return $menuItems;
	}

	final protected static function sortMenuItems( $a, $b ) {
		return ( $a->sorting - $b->sorting ) ? : strcmp( $a->getTitle(), $b->getTitle() );
	}

	protected function addAdditionalSubItems( array &$menuItems, RCPage $currentPage, $isSubMenu = false ) {
		$pagesAlreadyIn = array();

		foreach ( $menuItems as $menuItem ) {
			if ( $page = $menuItem->page ) $pagesAlreadyIn[ ] = $page;
		}

		$pages = $this->getPageItems( $currentPage, $isSubMenu );

		if ( $pages ) {
			// remove pages already in menuItems	
			foreach ( $pages as $k => $page ) {
				if ( in_array( $page, $pagesAlreadyIn, true ) ) {
					unset( $pages[ $k ] ); // as we just unset and do no resorting, keys stay the same (important for index calculation)
				}
			}

			/*
			static $menuItemFormFields;

			if ( $menuItemFormFields === NULL ) {
				$menuItemFormFields = array_keys( RCMenuItem::getFormFields( $this->storage ) );
			}
			*/

			foreach ( $pages as $idx => $page ) {
				$pageMenuItem = RCMenuItem::get( $this->storage, array(
					'page' => $page,
					'sorting' => ( $idx + 1 ) * 256,
					'showInMenu' => true,
					'title' => NULL,
					'subItemsFromPage' => true,
					'url' => NULL,
					'parent:RCMenuItem' => array(),
					'pagesFromRecordClass' => ''
				), false );

				$menuItems[ ] = $pageMenuItem;
			}
		}
	}

	protected function getPageItems( RCPage $currentPage, $isSubMenu = false ) {
		if ( $parentPage = $this->getPageForSub( $isSubMenu, $currentPage ) ) {
			// still need to select all pages to get index correct :(
			$pages = $this->storage->selectRecords( 'RCPage', array(
				'fields' => '*',
				'where' => array( 'parent', '=', array( $parentPage ), 'AND', 'pageType', '=', array( 'RCPage' ) ),
				'orderBy' => RCPage::getDefaultSorting()
			) );
		}


		return isset( $pages ) ? $pages : NULL;
	}
}

?>