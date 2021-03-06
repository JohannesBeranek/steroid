<?php
/**
 * @package steroid\urlhandler
 */

require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTSmallInt.php';

require_once STROOT . '/util/class.ClassFinder.php';
require_once STROOT . '/file/class.Filename.php';

/**
 * @package steroid\urlhandler
 */
class RCUrlHandler extends Record {
	const BACKEND_TYPE = Record::BACKEND_TYPE_SYSTEM;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			self::FIELDNAME_PRIMARY => DTSmallInt::getFieldDefinition( true, true, NULL, false ),
			'title' => DTString::getFieldDefinition( 127 ),
			'className' => DTString::getFieldDefinition( 127 ),
			'filename' => DTString::getFieldDefinition( 127 )
		);
	}

	public static function getStaticRecords( RBStorage $storage ) {
		$urlHandlers = ClassFinder::getAll( ClassFinder::CLASSTYPE_URLHANDLER );

		$records = array();

		foreach ( $urlHandlers as $name => $urlHandler ) {
			$existing = $storage->selectFirstRecord( 'RCUrlHandler', array( 'where' => array( 'className', '=', array( $name ) ) ) );

			if ( !$existing ) {
				$records[ ] = array(
					'title' => $name,
					'className' => $name,
					'filename' => Filename::getPathWithoutWebroot( Filename::getPathInsideWebrootWithLocalDir( $urlHandler[ 'fullPath' ] ) )
				);
			}
		}

		return $records;
	}
}
