<?php
/**
 * Main class for Steroid Web
 * @package steroid\web
 */

require_once STROOT . '/class.ST.php';
require_once STROOT . '/request/interface.IRequestInfo.php';
require_once STROOT . '/request/class.RequestInfo.php';
require_once STROOT . '/urlhandler/interface.IURLHandler.php';
require_once STROOT . '/util/class.Responder.php';
require_once STROOT . '/user/class.User.php';
require_once STROOT . '/util/class.StringFilter.php';
require_once STROOT . '/file/class.Filename.php';

require_once STROOT . '/cache/class.Cache.php';

require_once STROOT . '/gfx/class.GFX.php';
require_once STROOT . '/cache/class.FileCache.php';

require_once STROOT . '/res/class.Res.php';

require_once STROOT . '/domaingroup/class.RCDomainGroup.php';
require_once STROOT . '/datatype/class.DTSteroidReturnCode.php';
require_once STROOT . '/preview/class.RCPreviewSecret.php';

require_once STROOT . '/url/class.UrlUtil.php';

/**
 * Main class for Steroid Web
 * @package steroid\web
 */
class STWeb extends ST {

	const LIVEMODE_PREVIEW = 0;
	const LIVEMODE_LIVE = 1;


	/** @var IRequestInfo */
	protected $requestInfo;

	/** @var int */
	protected $returnCode;


	/** @var User */
	protected $user;

	/** @var ICache */
	protected $cache;

	public static function forceHTTPS() {
		if ( !RequestInfo::getCurrent()->getServerInfo( RequestInfo::PROXY_SAFE_IS_HTTPS ) ) {
			if ( ( $webSection = Config::section( 'web' ) ) && !empty( $webSection[ 'disableHTTPS' ] ) ) {
				// Log::write( 'omitting https redirect due to disableHTTPS set in config "web" section' ); // may be enabled for debugging purposes (otherwise keep it disabled to avoid spamming the log)
			} else {
				// finding an appropriate domain is done later, as even if we got no domain with SSL support, we just err out
				throw new RequireHTTPSException();
			}
		}
	}


	/**
	 * @param Config   $conf
	 * @param IStorage $storage
	 * @param array    $context
	 */
	public function __construct( Config $conf, IRBStorage $storage, IRequestInfo $requestInfo = NULL ) {
		parent::__construct( $conf, $storage );

		$this->requestInfo = $requestInfo === NULL ? RequestInfo::requestInfoFromContext() : $requestInfo;

		Log::setRequestInfo( $this->requestInfo );

		if ( ( $config = $this->config->getSection( 'web' ) ) && isset( $config[ 'cache' ] ) ) {
			$cacheType = $config[ 'cache' ];
		} else {
			$cacheType = null; // let Cache class decide
		}

		$this->cache = Cache::getBestMatch( $cacheType );
	}

	/**
	 * @return int
	 */
	public function __invoke() {
		$pagePath = $this->requestInfo->getPagePath();

		try {
			switch ( $pagePath ) {
				case '/monit': // Monitoring
					echo gethostname() . ": OK";
					break;
				case '/res': // Resources (css, js, ...)
					$this->handleResourceRequest();
					break;
				case '/gfx': // GFX (Images)
					$this->storage->init();

					$this->requestInfo->setDomainRecord( $this->getDomainRecordFromRequest() );
					$domainGroupRecord = $this->requestInfo->getDomainGroupRecord();

					GFX::handleRequest( $this->requestInfo, new FileCache(), $this->storage );
					break;
				case '/apc': // APC Management
					$this->storage->init();

					// only allow apc for existing domainGroup records (also makes it possible to serve correct 404 page in case of access denied)
					$this->requestInfo->setDomainRecord( $this->getDomainRecordFromRequest() );
					$domainGroupRecord = $this->requestInfo->getDomainGroupRecord();

					User::init( $this->config, $this->requestInfo, $this->storage );
					$this->user = User::getCurrent( $this->config, $this->requestInfo, $this->storage );

					if ( $this->user->record && $this->user->record->is_backendAllowed ) {
						define( 'USE_AUTHENTICATION', 0 ); // otherwise we can't see User Cache Entries
						return STROOT . '/apc.php';
					} else {
						throw new Exception( 'User not allowed to access apc status.' );
					}
					break;
				case '/phpinfo': // phpinfo()
					$this->storage->init();

					// only allow phpinfo for existing domainGroup records (also makes it possible to serve correct 404 page in case of access denied)
					$this->requestInfo->setDomainRecord( $this->getDomainRecordFromRequest() );
					$domainGroupRecord = $this->requestInfo->getDomainGroupRecord();

					User::init( $this->config, $this->requestInfo, $this->storage );
					$this->user = User::getCurrent( $this->config, $this->requestInfo, $this->storage );

					if ( $this->user->record && $this->user->record->is_backendAllowed ) {
						if ( defined( 'HHVM_VERSION' ) ) {
							printf( "HHVM Version: %s<br>\n", HHVM_VERSION );
						}

						phpinfo();
					} else {
						throw new Exception( 'User not allowed to access phpinfo().' );
					}
					break;
				case '/favicon.ico': // favicon
					$this->storage->init();

					$this->requestInfo->setDomainRecord( $this->getDomainRecordFromRequest() );
					$domainGroupRecord = $this->requestInfo->getDomainGroupRecord();

					if ( $domainGroupRecord->favicon ) {
						Responder::sendFile( $domainGroupRecord->favicon->getFullFilename(), $this->requestInfo->getServerInfo( 'HTTP_IF_MODIFIED_SINCE' ), $this->requestInfo->getServerInfo( 'HTTP_RANGE' ) );
					} else {
						Responder::sendHSTSHeader();

						Responder::sendReturnCodeHeader( 404 );
					}
					break;
				case '/robots.txt':
					$this->storage->init();

					$domainRecord = $this->getDomainRecordFromRequest();

					if ( $domainRecord->disableTracking ) {
						Responder::sendString( "User-agent: *\nDisallow: /", "text/plain" );
					} else {
						Responder::sendHSTSHeader();

						Responder::sendReturnCodeHeader( 404 );
					}
					break;
					break;
				default:
					if ( $this->isStaticRes( $pagePath ) ) { // static resource
						Responder::sendFile( WEBROOT . $pagePath, $this->requestInfo->getServerInfo( 'HTTP_IF_MODIFIED_SINCE' ), $this->requestInfo->getServerInfo( 'HTTP_RANGE' ) );
					} else { // dynamic served url
						$this->storage->init();

						User::init( $this->config, $this->requestInfo, $this->storage );
						$this->user = User::getCurrent( $this->config, $this->requestInfo, $this->storage );

						$domainRecord = $this->getDomainRecordFromRequest();

						if ( $domainRecord->redirectToUrl ) {
							Responder::sendHSTSHeader();

							Responder::sendReturnCodeHeader( 302 );

							Responder::sendLocationHeader( UrlUtil::getRedirectUrl( $domainRecord->redirectToUrl ) );
							
							return;
						} else if ( $domainRecord->redirectToPage ) {
							// TODO: STWeb should not need to know about live states
							$targetPage = $domainRecord->redirectToPage->getFamilyMember( array( 'live' => 1 ) );
							
							if ( $targetPage && $targetPage->live ) {
								Responder::sendHSTSHeader();

								Responder::sendReturnCodeHeader( 302 );

								Responder::sendLocationHeader( 'Location: ' . $targetPage->getUrlForPage( RCPage::ABSOLUTE_NO_PROTOCOL ) );
								
								return;
							}

						} else if ( ! RequestInfo::getCurrent()->getServerInfo( RequestInfo::PROXY_SAFE_IS_HTTPS ) && !Config::key('web', 'disableHTTPS') && 
							( ( ( $domainMatch = Config::key( 'web', 'preferHTTPS') ) && preg_match( $domainMatch, $domainRecord->domain ) ) || ( ( $hstsMatchList = Config::key( 'web', 'domainsHSTS' ) ) && Match::multiFN( $domainRecord->domain, $hstsMatchList ) ) ) ) {
							$this->redirectToHTTPS();
							
							return;
						}

						$this->requestInfo->setDomainRecord( $domainRecord );
						$domainGroupRecord = $this->requestInfo->getDomainGroupRecord();

						do {
							$urlRecord = $this->getURLRecordFromRequest( $domainGroupRecord );

							$urlHandlerReturn = $this->handleUrlRecord( $urlRecord );

						} while ( $urlHandlerReturn === IURLHandler::RETURN_CODE_REEVALUATE_PAGEPATH );
					}
			}
		} catch ( BadRequestException $e ) {
			Log::write( $e );
			$this->handleBadRequest();
		} catch ( InvalidRangeException $e ) {
			Log::write( $e );
			$this->handleBadRequest();
		} catch ( InvalidHTTPHostException $e ) { // getDomainRecordFromRequest fail
			Log::write( $e );
			$this->handleDomainNotFound();
		} catch ( UnknownDomainException $e ) { // getDomainRecordFromRequest fail
			Log::write( $e );
			$this->handleDomainNotFound();
		} catch ( InvalidURLException $e ) { // getURLRecordFromRequest fail
			// no logging here - we don't want to spam the log
			$this->handlePageNotFound( $domainGroupRecord );
		} catch ( UnknownURLException $e ) { // getURLRecordFromRequest fail
			// no logging here - we don't want to spam the log
			$this->handlePageNotFound( $domainGroupRecord );
		} catch ( InvalidURLHandlerException $e ) { // getURLHandler fail
			Log::write( $e );
			$this->handlePageNotFound( $domainGroupRecord );
		} catch ( RequireHTTPSException $e ) { // may be triggered everywhere ; this tells us that we should redirect to https (leaving everything else unchanged)

			// TODO: make sure canonical header uses https in this case

			$this->redirectToHTTPS();
		} catch ( Exception $e ) { // catch other exceptions which may be unknown to us
			$ex = $e;
			while ( $ex = $ex->getPrevious() ) {
				if ( $ex instanceof RequireHTTPSException ) {
					$this->redirectToHTTPS();
					return 0;
				}
			}

			Log::write( $e );

			if ( isset( $domainGroupRecord ) ) { // TODO: should actually use code 500
				$this->handlePageNotFound( $domainGroupRecord );
			} else {
				$this->handleDomainNotFound();
			}
		}

		return 0;
	}

	final protected function redirectToHTTPS() {
		$domainRecord = $this->requestInfo->getDomainRecord();

		if ( !$domainRecord ) {
			$host = $this->requestInfo->getHTTPHost();
		} else {
			$targetDomain = $domainRecord;

			if ( $domainRecord->noSSL || strpos( $domainRecord->domain, '*' ) !== false ) {
				$domainGroup = $domainRecord->domainGroup;

				$primaryDomain = $domainGroup->getPrimaryDomain();

				if ( $primaryDomain->noSSL ) {
					$domains = $domainGroup->{'domainGroup:RCDomain'};

					foreach ( $domains as $domain ) {
						if ( $domain === $domainRecord ) continue;

						if ( !$domain->noSSL ) {
							$targetDomain = $domain;
							break;
						}
					}
				} else {
					$targetDomain = $primaryDomain;
				}
			}

			$host = $targetDomain->domain;
		}

		// no need for sendHSTSHeader here, as we checked that we aren't on https before, otherwise we wouldn't be redirecting to https

		$redirect = "https://" . $host . $this->requestInfo->getRequestURI();
		Responder::sendReturnCodeHeader( 307 ); // 307 is needed so POST will be used again if original request was POST

		Responder::sendLocationHeader( $redirect );
	}

	protected function handleUrlRecord( RCUrl $urlRecord ) {
		do {
			// TODO: add support for canonical http header for urls with returnCode 418 AND non-primary domains!
			// Link: <http://www.example.com/downloads/white-paper.pdf>; rel="canonical"

			// TODO: handle redirect returnCodes of urlRecord
			$urlHandler = $this->getURLHandler( $urlRecord );

			$urlHandlerReturn = $urlHandler->handleURL( $this->requestInfo, $urlRecord, $this->storage );
		} while ( ( $urlHandlerReturn instanceof RCUrl ) && ( $urlRecord = $urlHandlerReturn ) );

		return $urlHandlerReturn;
	}

	protected function handleBadRequest() {
		Responder::sendHSTSHeader();

		Responder::sendReturnCodeHeader( 400 );

		// TODO
	}

	protected function handleDomainNotFound() {
		Responder::sendReturnCodeHeader( 404 );

	}

	protected function handlePageNotFound( RCDomainGroup $domainGroup ) {
		static $skip;

		if ( $skip === NULL ) {
			$skip = true; // prevent recursion when handling page not found

			Responder::sendHSTSHeader();

			Responder::sendReturnCodeHeader( 404 );

			try {
				if ( ( $page = $domainGroup->notFoundPage )
						&& ( $urlRecord = $this->storage->selectFirstRecord( 'RCUrl', array( 'where' => array( 'url:RCPageUrl.page', '=', '%1$s', 'AND', 'returnCode', '=', array( DTSteroidReturnCode::RETURN_CODE_PRIMARY ) ), 'vals' => array( $page ), 'name' => 'STWeb_pageNotFound' ) ) )
				) {
					$this->handleUrlRecord( $urlRecord );
				}
			} catch ( RequireHTTPSException $e ) { // may be triggered everywhere ; this tells us that we should redirect to https (leaving everything else unchanged)
				// [Johannes Beranek 05.05.2014] Somethin on notFoundPage might also trigger RequireHTTPSException
				// TODO: make sure canonical header uses https in this case

				$this->redirectToHTTPS();
			} catch ( Exception $e ) {
				Log::write( $e );
			}
		}
	}



	/**
	 * @return int
	 */
	protected function getLiveValue() {
		$previewSecret = $this->requestInfo->getQueryParam( RCPreviewSecret::URL_PARAM );

		// TODO: permissions ?

		if ( RCPreviewSecret::validate( $this->storage, $previewSecret ) ) {
			return self::LIVEMODE_PREVIEW;
		}

		return self::LIVEMODE_LIVE;
	}


	/**
	 * @throws InvalidHTTPHostException, UnknownDomainException
	 * @return DomainRecord
	 */
	protected function getDomainRecordFromRequest() {
		// local caching of variables
		$httpHost = $this->requestInfo->getHTTPHost();

		if ( $httpHost === NULL || $httpHost == '' ) {
			throw new InvalidHTTPHostException( 'Invalid HTTP Host' );
		}

		$domainParts = explode( '.', $httpHost );

		require_once STROOT . '/domain/class.RCDomain.php';

		$domainQueryStruct = array(
			'fields' => array(
				'disableTracking',
				'domain',
				'domainGroup.*',
				'redirectToUrl',
				'redirectToPage'
			),
			'where' => array(
				'domain',
				'=',
				'%1$s'
			)
		);

		// domain check: sub.domain.com
		$domainRecord = $this->storage->selectFirstRecord( 'RCDomain', $domainQueryStruct, NULL, NULL, array( $httpHost ), 'STWeb_domainRecord_dt' );

		// domain check: *.domain.com, *.com
		while ( !$domainRecord && count( $domainParts ) > 1 ) {
			array_shift( $domainParts );
			$domainMatch = '*.' . implode( '.', $domainParts );

			// reuses cached query, as only the value changed
			$domainRecord = $this->storage->selectFirstRecord( 'RCDomain', $domainQueryStruct, NULL, NULL, array( $domainMatch ), 'STWeb_domainRecord_dt' );
		}

		if ( !$domainRecord ) {
			throw new UnknownDomainException( $httpHost );
		}

		return $domainRecord;
	}

	/**
	 * @throws InvalidURLException, UnknownURLException
	 * @return UrlRecord
	 */
	protected function getURLRecordFromRequest( RCDomainGroup $domainGroup ) {
		// local caching of variables
		$pagePath = $this->requestInfo->getPagePath();

		if ( $pagePath === NULL || $pagePath == '' ) {
			throw new InvalidURLException();
		}

		if ( $pagePath[ 0 ] !== '/' ) {
			$pagePath = '/' . $pagePath;
		}

		while ( $pagePath !== '/' && $pagePath[ strlen( $pagePath ) - 1 ] === '/' ) {
			$pagePath = substr( $pagePath, 0, -1 );
		}

		require_once STROOT . '/url/class.RCUrl.php';
		// TODO: permissions

		$urlRecord = $this->storage->selectFirstRecord( 'RCUrl', array(
			'fields' => array(
				'*',
				'urlHandler' => array( 'fields' => '*' )
			),
			'where' => array(
				'url',
				'=',
				'%1$s',
				'AND',
				'domainGroup',
				'=',
				'%2$s',
				'AND',
				'live',
				'=',
				'%3$s'
			)
		), NULL, NULL, array( $pagePath, $domainGroup, $this->getLiveValue() ), 'STWeb_url' );

		if ( $urlRecord === NULL ) {
			throw new UnknownURLException( 'URL "' . $pagePath . '" does not exist' );
		}

		return $urlRecord;
	}

	protected function getURLHandler( $urlRecord ) {
		$urlHandlerRecord = $urlRecord->urlHandler;

		if ( $urlHandlerRecord === NULL ) {
			throw new InvalidURLHandlerException();
		}

		$urlHandlerClassName = StringFilter::filterClassName( $urlHandlerRecord->className );
		$urlHandlerFileName = WEBROOT . $urlHandlerRecord->filename;

		require_once $urlHandlerFileName;

		if ( !class_exists( $urlHandlerClassName, false ) || !in_array( 'IURLHandler', class_implements( $urlHandlerClassName, false ) ) ) {
			throw new InvalidURLHandlerException();
		}

		$urlHandler = new $urlHandlerClassName();

		return $urlHandler;
	}

	protected function handleResourceRequest() {
		if ( $job = $this->requestInfo->getQueryParam( 'j' ) ) {
			$this->storage->init();

			Res::serveJob( $this->storage, $job, $this->cache, $this->requestInfo );
		} else if ( $file = $this->requestInfo->getQueryParam( 'file' ) ) { // request for unaltered, uncompressed resource
			$file = Filename::resolvePath( $file );

			if ( !$this->isRes( $file ) ) {
				throw new SecurityException( 'Filename with forbidden path passed to res handler: "' . $file . '"' );
			}

			// TODO: additionally check file extension for .js and .css

			Res::serveFile( $file, $this->requestInfo );
		}
	}


	// only allows res/css and res/js for security reasons
	protected function isRes( $path ) { // TODO: replace strpos with something more efficient + check file extensions
		return (
				strpos( $path, '/' . STDIRNAME . '/res/css/' ) === 0
				|| strpos( $path, '/' . STDIRNAME . '/res/js/' ) === 0
				|| strpos( $path, '/' . LOCALDIRNAME . '/res/css/' ) === 0
				|| strpos( $path, '/' . LOCALDIRNAME . '/res/js/' ) === 0
				|| preg_match( '/^\/' . preg_quote( LOCALDIRNAME . '/ext/', '/' ) . '[^\/]+\/res\/css\//', $path ) === 1
				|| preg_match( '/^\/' . preg_quote( LOCALDIRNAME . '/ext/', '/' ) . '[^\/]+\/res\/js\//', $path ) === 1
		);
	}

	protected function isStaticRes( $path ) { // TODO: replace strpos with something more efficient
		return (
				strpos( $path, '/' . STDIRNAME . '/res/static/' ) === 0
				|| strpos( $path, '/' . LOCALDIRNAME . '/res/static/' ) === 0
				|| preg_match( '/^\/' . preg_quote( LOCALDIRNAME . '/ext/', '/' ) . '[^\/]+\/res\/static\//', $path ) === 1
		);
	}
}

/**
 *
 * @author codeon_johannes
 *
 */
class InvalidHTTPHostException extends RuntimeException {
}

/**
 *
 * @author codeon_johannes
 *
 */
class UnknownDomainException extends RuntimeException {
	/** @var string */
	protected $domain;

	/**
	 *
	 * @param string $message
	 * @param string $domain
	 */
	public function __construct( $domain ) {
		$this->domain = $domain;
		parent::__construct( 'Unknown domain: "' . $domain . '"' );
	}

	/**
	 *
	 * @return string
	 */
	public function getDomain() {
		return $this->domain;
	}
}

/**
 * @package steroid\web
 */
class InvalidURLException extends Exception {
}

/**
 * @package steroid\web
 */
class UnknownURLException extends Exception {
}

/**
 * @package steroid\web
 */
class InvalidURLHandlerException extends Exception {
}

/**
 * @package steroid\web
 */
class RequireHTTPSException extends Exception {
}
