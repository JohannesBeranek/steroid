<?php

require_once STROOT . '/util/class.Config.php';
require_once STROOT . '/cache/class.Cache.php';
require_once STROOT . '/file/class.Filename.php';

class ClassFinder {
	// FIXME: move to base classes?
	const CLASSTYPE_RECORD = 'RC';
	const CLASSTYPE_DATATYPE = 'DT';
	const CLASSTYPE_UNITTEST = 'UT';
	const CLASSTYPE_URLHANDLER = 'UH';
	const CLASSTYPE_CLIHANDLER = 'CH';
	const CLASSTYPE_WIZARD = 'WZ';
	const CLASSTYPE_BACKEND_EXTENSION = 'BX';
	const CLASSTYPE_LOGIN_EXTENSION = 'LE'; // BACKEND login extensions only!
	const CLASSTYPE_EMAIL_PROVIDER = 'EP';
	const CLASSTYPE_AUTHENTICATOR = 'AC';

	const CLASSFILE_KEY_FULLPATH = 'fullPath';
	const CLASSFILE_KEY_FILENAME = 'fileName';
	const CLASSFILE_KEY_CLASSNAME = 'className';

	protected static $classes = array();
	protected static $required = array();

	protected static $cache;

	public static function clearCache() {
		if ( !self::$cache ) self::initCache();
		if ( self::$cache === false ) return;

		// TODO: populate this array dynamically (e.g. using reflection)
		// when adding a new type, make sure to add it to this array as well!
		$types = array( self::CLASSTYPE_AUTHENTICATOR, self::CLASSTYPE_CLIHANDLER, self::CLASSTYPE_DATATYPE, self::CLASSTYPE_RECORD, self::CLASSTYPE_UNITTEST, self::CLASSTYPE_URLHANDLER, self::CLASSTYPE_WIZARD, self::CLASSTYPE_BACKEND_EXTENSION, self::CLASSTYPE_LOGIN_EXTENSION );

		foreach ( $types as $type ) {
			self::$cache->delete( self::convertKey( $type ) );
		}
	}

	protected static function initCache() {
		if ( ( $config = Config::get( 'localconf' ) ) && ( $conf = $config->getSection( 'classfinder' ) ) && isset( $conf[ 'cache' ] ) && $conf[ 'cache' ] ) {
			self::$cache = Cache::getBestMatch( $conf[ 'cache' ] );
		} else {
			self::$cache = false;
		}
	}

	public static $classFileItemKeys = array(
		self::CLASSFILE_KEY_FULLPATH,
		self::CLASSFILE_KEY_FILENAME,
		self::CLASSFILE_KEY_CLASSNAME
	);

	protected static function convertKey( $key ) {
		return 'ClassFinder.' . $key . '.cache';
	}

	protected static function getFromCache( $key ) {
		if ( self::$cache === NULL ) self::initCache();
		if ( self::$cache === false ) return false;

		$key = self::convertKey( $key );
		$str = self::$cache->get( $key );

		if ( $str === false ) {
			return false;
		}

		parse_str( $str, $data );

		return $data;
	}

	protected static function setInCache( $key, $data ) {
		if ( self::$cache === NULL ) self::initCache();
		if ( self::$cache === false ) return;

		$key = self::convertKey( $key );
		self::$cache->set( $key, http_build_query( $data ) );
	}

	public static function getAll( $type, $andRequire = false, $include = NULL, $exclude = NULL ) {
		if ( !isset( self::$classes[ $type ] ) ) {
			if ( ( $files = self::getFromCache( $type ) ) === false ) {
				$matches = self::getFiles( self::regexByType( $type ), $include, $exclude );

				$files = array();

				foreach ( $matches as $file ) {
					$class = array_combine( self::$classFileItemKeys, $file );
					$files[ $class[ self::CLASSFILE_KEY_CLASSNAME ] ] = $class;
				}

				self::setInCache( $type, $files );
			}

			self::$classes[ $type ] = $files;
		}

		if ( $andRequire && !isset( self::$required[ $type ] ) ) {
			foreach ( self::$classes[ $type ] as $class ) {
				require_once $class[ self::CLASSFILE_KEY_FULLPATH ];

				if ( !class_exists( $class[ self::CLASSFILE_KEY_CLASSNAME ] ) ) {
					throw new LogicException( 'Class "' . $class[ self::CLASSFILE_KEY_CLASSNAME ] . '" was not defined after including "' . $class[ self::CLASSFILE_KEY_FULLPATH ] . '"' );
				}
			}

			self::$required[ $type ] = true;
		}

		return self::$classes[ $type ];
	}

	/**
	 * Find and optionally require classfile(s)
	 *
	 * @param string|string[] $classNames may be the name of a class, or an array of classNames
	 * @param bool            $andRequire = false
	 * @param string|string[] $include = NULL directories to include
	 * @param string|string[] $exclude = NULL directories to exclude
	 *
	 * @return string[]
	 */
	public static function find( $classNames, $andRequire = false, $include = NULL, $exclude = NULL ) {
		if ( empty( $classNames ) ) {
			throw new InvalidArgumentException( '$classNames must not be empty' );
		}

		$types = array();

		foreach ( (array)$classNames as $className ) {
			$types[ substr( $className, 0, 2 ) ][ ] = $className; // must be one of "RC", "DT" or "UT"
		}

		$files = array();

		foreach ( $types as $type => $className ) {
			if ( class_exists( $className[ 0 ], false ) ) {
				$files[ ] = array( self::CLASSFILE_KEY_CLASSNAME => $className[ 0 ] );
				continue;
			}

			self::getAll( $type, false, $include, $exclude );

			if ( !isset( self::$classes[ $type ][ $className[ 0 ] ] ) ) {
				throw new ClassNotFoundException( $className[ 0 ] . ' does not exist' );
			} else if ( $andRequire ) {
				require_once self::$classes[ $type ][ $className[ 0 ] ][ self::CLASSFILE_KEY_FULLPATH ];
			}

			$files[ ] = self::$classes[ $type ][ $className[ 0 ] ];
		}

		return $files;
	}

	protected static function getFiles( $regex, $include = NULL, $exclude = NULL ) {
		if ( $include === NULL ) {
			$include = array( STROOT, LOCALROOT );
		}

		if ( $exclude === NULL ) {
			$exclude = array( STROOT . '/res', LOCALROOT . '/res' );
		}

		$matches = new AppendIterator();

		foreach ( $include as $dir ) {
			$directory = new RecursiveDirectoryIterator( $dir );
			$filtered = new ClassFinderIterator( $directory, $exclude );
			$iterator = new RecursiveIteratorIterator( $filtered );
			$newMatches = new RegexIterator( $iterator, $regex, RegexIterator::GET_MATCH );

			if ( $newMatches ) {
				$matches->append( $newMatches );
			}
		}

		if ( empty( $matches ) ) {
			throw new ClassNotFoundException( 'No classes found' );
		}

		return $matches;
	}

	protected static function regexByType( $type ) {
		return '#^(?:[A-Z]:)?(?:/(?!\.svn)[^/]+)+/(class\.(' . $type . '.+?)\.php)$#Di';
	}

	protected static function regexByClass( array $classNames ) {

		$regex = '#^(?:[A-Z]:)?(?:/(?!\.svn)[^/]+)+/(class\.(';

		foreach ( $classNames as $idx => $className ) {
			$regex .= $className . ( ( $idx < ( count( $classNames ) - 1 ) ) ? '|' : '' );
		}

		$regex .= ')\.php)$#Di';

		return $regex;
	}

	public static function getClassLocation( $className ) {
		if ( empty( $className ) ) {
			throw new InvalidArgumentException( '$recordClass must be set' );
		}

		$path = '';

		$type = substr( $className, 0, 2 );

		if ( !isset( self::$classes[ $type ] ) || !isset( self::$classes[ $type ][ $className ] ) ) {
			if ( !class_exists( $className ) ) {
				self::find( array( $className ), true );
			}

			$reflection = new ReflectionClass( $className );
			$path = $reflection->getFileName();
		} else {
			$path = self::$classes[ $type ][ $className ][ self::CLASSFILE_KEY_FULLPATH ];
		}

		$path = Filename::webpathize( $path );
		$path = explode( '/', $path );

		array_pop( $path );

		$path = implode( '/', $path );

		$path = ltrim( $path, '/' );

		return $path;
	}
}

class ClassNotFoundException extends Exception {
}

class ClassFinderIterator extends RecursiveFilterIterator {
	protected $exclude;

	public function __construct( RecursiveDirectoryIterator $recursiveIterator, array $exclude ) {
		$this->exclude = $exclude;
		parent::__construct( $recursiveIterator );
	}

	public function accept() {
		$current = $this->current();
		// $this->hasChildren() || 
		return ( !$current->isDir() || ( !in_array( $current->getPath(), $this->exclude ) && substr( $current->getFilename(), 0, 1 ) != '.' ) );
	}

	public function getChildren() {
		return new self( $this->getInnerIterator()->getChildren(), $this->exclude );
	}

}
