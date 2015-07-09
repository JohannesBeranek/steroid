<?

require_once STROOT . '/page/class.RCPage.php';
require_once STROOT . '/storage/class.RBStorage.php';
require_once STROOT . '/util/class.ClassFinder.php';



class UTPage extends PHPUnit_Framework_TestCase {

	static $dependencies = array();

	public function testCopy(){
		throw new Exception('This is not really a unit test so you better not use this');

		$storage = self::getStorage( Config::loadNamed( LOCALROOT . '/localconf.ini.php', 'localconf' ));

		$tx = $storage->startTransaction();

		try{
			$newParent = RCPage::get( $storage, array( Record::FIELDNAME_PRIMARY => 33557986 ), Record::TRY_TO_LOAD );
			$page      = RCPage::get( $storage, array( Record::FIELDNAME_PRIMARY => 33637050 ), Record::TRY_TO_LOAD );

//			$records = ClassFinder::getAll('RC', true);
//
//			foreach($records as $recordName => $recordInfo){
//				if($recordName::BACKEND_TYPE === Record::BACKEND_TYPE_WIDGET){
//					var_dump($recordName);
//					var_dump($recordName::getForeignReferences());
//				}
//			}

			$copy = $page->duplicate( $newParent );

			$copy->save();

			$tx->commit();
		} catch(Exception $e){
			$tx->rollback();

			throw $e;
		}
	}

	protected static function getStorage( $config ) {
		require_once STROOT . '/storage/class.RBStorage.php';

		$dbConfig        = $config->getSection( 'DB' );
		$filestoreConfig = $config->getSection( 'filestore' );

		$DB = new RBStorage(
			$dbConfig[ 'host' ], $dbConfig[ 'username' ], $dbConfig[ 'password' ], $dbConfig[ 'database' ],
			( $filestoreConfig !== null && isset( $filestoreConfig[ 'path' ] ) ) ? $filestoreConfig[ 'path' ] : null,
			isset( $dbConfig[ 'default_engine' ] ) ? $dbConfig[ 'default_engine' ] : null,
			isset( $dbConfig[ 'default_charset' ] ) ? $dbConfig[ 'default_charset' ] : null,
			isset( $dbConfig[ 'default_collation' ] ) ? $dbConfig[ 'default_collation' ] : null
		);

		$DB->init();

		return $DB;
	}
}