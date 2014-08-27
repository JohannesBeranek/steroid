<?php
/**
 * @package steroid\file
 */

require_once STROOT . '/storage/record/class.Record.php';
require_once STROOT . '/file/interface.IFileInfo.php';

require_once STROOT . '/datatype/class.DTKey.php';
require_once STROOT . '/datatype/class.DTString.php';
require_once STROOT . '/datatype/class.DTInt.php';
require_once STROOT . '/datatype/class.DTRecordReference.php';
require_once STROOT . '/user/class.DTSteroidCreator.php';
require_once STROOT . '/datatype/class.DTMTime.php';
require_once STROOT . '/datatype/class.DTCTime.php';
require_once STROOT . '/file/class.DTFile.php';
require_once STROOT . '/file/class.DTRenderConfig.php';

require_once STROOT . '/file/class.RCFileType.php';
require_once STROOT . '/file/class.DownloadFileInfo.php';

require_once STROOT . '/storage/interface.IRBStorage.php';
require_once STROOT . '/user/class.User.php';

require_once STROOT . '/url/class.UrlUtil.php';
require_once __DIR__ . '/class.VirtualFile.php';

require_once STROOT . '/user/class.RCUser.php';
require_once STROOT . '/user/class.User.php';

/**
 * @package steroid\file
 */
class RCFile extends Record implements IFileInfo {
	const ALLOW_CREATE_IN_SELECTION = 1;
	const BACKEND_TYPE = Record::BACKEND_TYPE_CONTENT;

	private static $gfx;

	protected static function getKeys() {
		return array(
			'primary' => DTKey::getFieldDefinition( array( self::FIELDNAME_PRIMARY ) )
		);
	}

	protected static function getFieldDefinitions() {
		return array(
			Record::FIELDNAME_PRIMARY => DTInt::getFieldDefinition( true, true, NULL, false ),
			'title' => DTString::getFieldDefinition( 127 ),
			'filename' => DTFile::getFieldDefinition(),
			'filetype' => DTRecordReference::getFieldDefinition( 'RCFileType', true, false ), // set automatically upon upload
			'downloadFilename' => DTString::getFieldDefinition( 127, false, NULL, true ), // FIXME: DEPRECATED
			'alt' => DTString::getFieldDefinition( 255, false, NULL, true ), // optional alt text, only applicable to images containing text
			'creator' => DTSteroidCreator::getFieldDefinition(),
			'mtime' => DTMTime::getFieldDefinition(),
			'ctime' => DTCTime::getFieldDefinition(),
			'comment' => DTText::getFieldDefinition( NULL, true ),
			'copyright' => DTString::getFieldDefinition( 255, false, NULL, true ),
			'lockToDomainGroup' => DTBool::getFieldDefinition(),
			'domainGroup' => DTSteroidDomainGroup::getFieldDefinition( false ), // not required, may be null if file comes from system (e.g. cli syncing global stuff)

			// only applies to images (and stuff renderable as image, like pdf)
			'renderConfig' => DTRenderConfig::getFieldDefinition( true ) // optional renderConfig which will be used by GFX
		);
	}

	protected static function getEditableFormFields() {
		return array(
			'title',
			'copyright',
			'comment',
			'alt',
			'lockToDomainGroup',
			'filename',
			'renderConfig'
		);
	}

	protected static function addPermissionsForReferencesNotInFormFields() {
		return array( 'filetype' );
	}

	public static function download( IRBStorage $storage, $url, RCFile $rec = NULL ) {
		$downloadFileInfo = new DownloadFileInfo( $url );

		try {
			$storage->downloadFile( $downloadFileInfo );
		} catch ( Exception $e ) {
			Log::write( $e );
			return NULL;
		}

		$filename = $downloadFileInfo->getStoredFilename();

		// adding filetype here doesn't work with async downloads
		$data = array( 'filename' => $filename, 'downloadFilename' => pathinfo( $filename, PATHINFO_BASENAME ) );

		if ( $rec !== NULL ) {
			$rec->setValues( $data );
		} else {
			$rec = RCFile::get( $storage, $data, false );
		}

		return $rec;
	}

	public static function create( IRBStorage $storage, $data, $filename, $mimeType = NULL, RCFile $rec = NULL, RCUser $creator = NULL ) {
		$f = new VirtualFile( $data, $mimeType, $filename );

		try {
			$storage->createFile( $f );
		} catch ( Exception $e ) {
			Log::write( $e );
			return NULL;
		}

		$filename = $f->getStoredFilename();

		if ( $creator === NULL ) {
			$creator = User::getCLIUserRecord( $storage );
		}

		// TODO: add filetype according to $mimeType
		$data = array( 'filename' => $filename, 'downloadFilename' => pathinfo( $filename, PATHINFO_BASENAME ), 'title' => $filename, 'creator' => $creator );

		if ( $mimeType !== NULL ) {
			$data[ 'filetype' ] = self::getFileTypeRecordFromMimeType( $storage, $mimeType );
		}

		if ( $rec !== NULL ) {
			$rec->setValues( $data );
		} else {
			$rec = RCFile::get( $storage, $data, false );
		}

		return $rec;
	}

	protected function beforeSave( $isUpdate, $isFirst ) {
		if ( $isFirst ) {
			if ( isset( $this->title ) && trim( $this->title ) == '' && isset( $this->downloadFilename ) ) {
				$this->title = $this->downloadFilename;
			}

			if ( ( !$isUpdate && !$this->fields[ 'filetype' ]->hasBeenSet() ) || $this->fields[ 'filename' ]->dirty ) {
				$filenameField = $this->beforeSaveFields[ 'filename' ];
				unset( $this->beforeSaveFields[ 'filename' ] );

				$filenameField->beforeSave( $isUpdate );

				$fullFileName = $this->getFullFilename();
				$this->storage->finishDownload( $fullFileName );

				$finfo = new finfo( FILEINFO_MIME_TYPE );
				$mimeType = $finfo->file( $fullFileName );

				$this->filetype = self::getFileTypeRecordFromMimeType( $this->storage, $mimeType );
			}
		}

		parent::beforeSave( $isUpdate, $isFirst );
	}

	protected static function getFileTypeRecordFromMimeType( $storage, $mimeType ) {
		$mimeCategory = strstr( $mimeType, '/', true );

		$type = $storage->selectFirstRecord( 'RCFileType', array( 'where' => array( 'mimeCategory', '=', array( $mimeCategory ), 'AND', 'mimeType', '=', array( $mimeType ) ) ) );

		if ( !$type ) {
			$type = RCFileType::get( $storage, array( 'mimeCategory' => $mimeCategory, 'mimeType' => $mimeType ), false );
		}

		return $type;
	}

	protected function _delete() {
		$this->load( array( 'filename' ) ); // make sure filename is loaded so it can later on be accessed for unlinking
		$this->storage->unlinkFile( $this );

		parent::_delete();
	}

	// IFileInfo
	public function getStoredFilename() {
		return $this->filename;
	}

	public function getTempFilename() {
		return null;
	}

	public function getUploadedFilename() {
		return null;
	}

	public function getDownloadFilename() {
		return $this->downloadFilename;
	}

	public function setStoredFilename( $filename ) {
		$this->filename = $filename;
	}

	public function getFullFilename() {
		return $this->storage->getStorageDirectory() . '/' . $this->getStoredFilename();
	}

	public function getMimeCategory() {
		return $this->filetype->mimeCategory;
	}

	public function getMimeType() {
		return $this->filetype->mimeType;
	}

	public function getMeta( $name ) {
		return isset( $this->fields[ $name ] ) ? $this->getFieldValue( $name ) : NULL;
	}

	public function getData() {
		return file_get_contents( $this->getFullFilename() );
	}

	// -----


	/**
	 * Returns "best" download filename
	 *
	 * In case no downloadFilename could be constructed, this function returns true, so the return value can be used
	 * for Storage::sendFile second parameter as well as for $forceDownload parameter of Responder class
	 */
	public function getNiceDownloadFilename() {
		if ( isset( $this->downloadFilename ) && trim( $this->downloadFilename ) !== '' ) {
			$downloadFilenameBase = $this->downloadFilename;
			$extension = pathinfo( $this->downloadFilename, PATHINFO_EXTENSION );
		}

		if ( !isset( $downloadFilenameBase ) ) {
			$downloadFilenameBase = basename( $this->filename );
		}

		if ( !isset( $extension ) || $extension === '' ) {
			$extension = pathinfo( $this->filename, PATHINFO_EXTENSION );
		}

		if ( $this->title !== NULL && trim( $this->title ) !== '' ) {
			$downloadFilename = $this->title;
		} else {
			$downloadFilename = pathinfo( $downloadFilenameBase, PATHINFO_FILENAME ); // PATHINFO_FILENAME : PHP >= 5.2.0
		}

		if ( $extension !== '' ) {
			$extension = preg_replace( '/[^a-z0-9\.].*/', '', strtolower( $extension ) );
			$downloadFilename .= '.' . $extension;
		}

		if ( $downloadFilename === '' ) $downloadFilename = true;

		return $downloadFilename;
	}


	final protected static function getGfx( $storage ) {
		if ( self::$gfx === NULL ) {
			self::$gfx = new GFX( new FileCache(), $storage );
			self::$gfx->setSkipGenerate( true );
			self::$gfx->setMode( GFX::MODE_SRC );
		}

		return self::$gfx;
	}

	public function getFormValues( array $fields ) {
		$ret = parent::getFormValues( $fields );


		if ( isset( $ret[ 'filename' ] ) ) {
			$ret[ 'filename' ] = array( 'filename' => $ret[ 'filename' ], 'primary' => $this->{Record::FIELDNAME_PRIMARY}, 'name' => $this->getNiceDownloadFilename() );

			// TODO: runtime check of supported mime types
			if ( $this->filetype->mimeCategory == 'image' && ( $file = $this->getFullFilename() ) && in_array( $this->filetype->mimeType, GFX::$supportedMimeTypes ) ) {
				$gfx = self::getGfx( $this->storage );

				$resizedImage = $gfx->resize( array( 'src' => $file, 'width' => 400, 'altformat' => 'jpg' ) );

				$ret[ 'filename' ][ 'cached' ] = $resizedImage;
			}
		}

		return $ret;
	}

	public function getFilenameAndExtForUrl() {
		$filename = $this->getNiceDownloadFilename();

		// as per request (some mail clients don't handle spaces and the like well)
		// $filename = rawurlencode($filename);

		$fn = pathinfo( $filename, PATHINFO_FILENAME );
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );

		$fn = UrlUtil::generateUrlPartFromString( $fn );

		if ( function_exists( 'mb_strtolower' ) ) {
			$ext = mb_strtolower( $ext, "UTF-8" );
		} else {
			$ext = strtolower( $ext );
		}

		if ( $ext === '' ) {
			$ext = NULL;
		} elseif ( $ext !== NULL ) {
			$ext = '.' . $ext;
		}

		return array( $fn, $ext );
	}

	public function listFormat( User $user, array $filter, $isSearchField = false ) {
		$ret = parent::listFormat( $user, $filter, $isSearchField );

		if ( isset( $this->filename ) && isset( $this->filetype ) && $this->filetype->mimeCategory === 'image' && in_array( $this->filetype->mimeType, GFX::$supportedMimeTypes ) ) {
			try {
				$img = self::getGfx( $this->storage )->convert( array( 'src' => $this, 'width' => 200, 'height' => 50, 'fit' => true, 'altformat' => 'jpg' ) );
			} catch ( Exception $e ) {
				Log::write( 'Corrupted image encountered, primary: ' . $this->primary, $e );
				$img = '/steroid/res/static/img/corrupted.jpg';
			}

			$ret[ 'filename' ] = array( 'filename' => $ret[ 'filename' ], 'cached' => $img );
		}

		return $ret;
	}

	protected static function getDisplayedListFields() {
		return array( 'title', 'filename', 'filetype', 'alt', 'domainGroup' );
	}

	protected static function getDisplayedFilterFields() {
		return array(
			'domainGroup', 'filetype'
		);
	}

	public static function modifySelect( array &$queryStruct, IRBStorage $storage, array &$userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass ) {
		parent::modifySelect( $queryStruct, $storage, $userFilters, $mainRecordClass, $recordClass, $requestFieldName, $requestingRecordClass );

		if ( $recordClass === 'RCFile' ) {
			$user = User::getCurrent();

			if ( !empty( $queryStruct[ 'where' ] ) ) {
				array_unshift( $queryStruct[ 'where' ], '(' );
				array_push( $queryStruct[ 'where' ], ')', 'AND' );
			} else {
				$queryStruct[ 'where' ] = array();
			}

			array_push( $queryStruct[ 'where' ], '(', 'lockToDomainGroup', '=', array( 0 ), 'OR', 'domainGroup', '=', array( $user->getSelectedDomainGroup() ), ')' );
		}

		if ( $requestingRecordClass != 'RCFile' && $requestingRecordClass != '' ) {
			$fieldDef = $requestingRecordClass::getFieldDefinition( $requestFieldName );

			if ( !isset( $fieldDef[ 'allowedCategories' ] ) ) {
				return;
			}

			$tmpQueryStruct = array( 'where' => array(
				'mimeCategory',
				'=',
				$fieldDef[ 'allowedCategories' ]
			) );

			if ( !empty( $fieldDef[ 'allowedTypes' ] ) ) {
				$tmpQueryStruct[ 'where' ][ ] = 'AND';
				$tmpQueryStruct[ 'where' ][ ] = 'mimeType';
				$tmpQueryStruct[ 'where' ][ ] = '=';
				$tmpQueryStruct[ 'where' ][ ] = $fieldDef[ 'allowedTypes' ];
			}

			$fileTypes = $storage->select( 'RCFileType', $tmpQueryStruct );

			$primaries = array();

			foreach ( $fileTypes as $fileType ) {
				$primaries[ ] = $fileType[ Record::FIELDNAME_PRIMARY ];
			}

			$queryStruct[ 'where' ][ ] = 'AND';
			$queryStruct[ 'where' ][ ] = 'filetype';
			$queryStruct[ 'where' ][ ] = '=';
			$queryStruct[ 'where' ][ ] = $primaries;
		}
	}

	public static function fillRequiredPermissions( array &$permissions, $titleOnly = false ) {
		parent::fillRequiredPermissions( $permissions, $titleOnly );

		if ( !isset( $permissions[ 'RCFileType' ] ) ) {
			$permissions[ 'RCFileType' ] = array(
				'mayWrite' => $permissions[ __CLASS__ ][ 'mayWrite' ],
				'isDependency' => 1,
				'restrictToOwn' => 0
			);
		}

		if ( !isset( $permissions[ 'RCFileCategory' ] ) ) {
			$permissions[ 'RCFileCategory' ] = array(
				'mayWrite' => $permissions[ __CLASS__ ][ 'mayWrite' ],
				'isDependency' => 1,
				'restrictToOwn' => 0
			);
		}

		if ( !isset( $permissions[ 'RCFileTypeFileCategory' ] ) ) {
			$permissions[ 'RCFileTypeFileCategory' ] = array(
				'mayWrite' => $permissions[ __CLASS__ ][ 'mayWrite' ],
				'isDependency' => 1,
				'restrictToOwn' => 0
			);
		}

		if ( !isset( $permissions[ 'RCVimeoVideo' ] ) ) { //TODO: move to where it belongs!
			$permissions[ 'RCVimeoVideo' ] = array(
				'mayWrite' => $permissions[ __CLASS__ ][ 'mayWrite' ],
				'isDependency' => 1,
				'restrictToOwn' => 0
			);
		}
	}
}
