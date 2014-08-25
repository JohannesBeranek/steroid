<?php
/**
 *
 * @package package_name
 */

require_once STROOT . '/urlhandler/interface.IURLHandler.php';

require_once STROOT . '/storage/class.RBStorageDataTypeValueFilter.php';

require_once STROOT . '/datatype/class.DTSteroidLive.php';
require_once STROOT . '/language/class.DTSteroidLanguage.php';

require_once STROOT . '/page/class.RCPage.php';

require_once __DIR__ . '/interface.IClassRequestHandler.php';

/**
 *
 * @package package_name
 */
class UHPage implements IURLHandler {
	const FILTER_IDENTIFIER = 'frontend';

	private static $classRequestHandler = array();


	protected $currentPage;

	protected $storageFilter;


	public static function registerClassRequestHandler( IClassRequestHandler $handler, $command ) {
		self::$classRequestHandler[ $command ] = $handler; // TODO: should this override existing commands?
	}

//	const CLASSREQUEST_TYPE_LOCAL_EXT = 'lx';

	public function handleURL( IRequestInfo $requestInfo, RCUrl $url, IRBStorage $storage ) {
		/*
		$this->currentPage = $storage->selectFirstRecord( 'RCPage', array( 
			'fields' => array( '*', 'language.locale', 'language.iso639', 'template.filename', 'domainGroup.domainGroup:RCDomain.*' ), 
			'where' => array( 'page:RCPageUrl.url', '=', '%1$s' ),
			'vals' => array( $url ),
			'name' => 'UHPage_currentPage'
		) );
		*/
		// JB 23.1.2014 temporary fix for no-primary-url-found bug till $filteredJoinData in RBStorage can be reenabled
		$currentPagePrimaryData = $storage->selectFirst( 'RCPage', array( 
			'fields' => array( 'primary' ), 
			'where' => array( 'page:RCPageUrl.url', '=', '%1$s' ),
			'vals' => array( $url ),
			'name' => 'UHPage_currentPagePrimaryData'
		) );

		$this->currentPage = RCPage::get( $storage, array( 'primary' => $currentPagePrimaryData['primary'] ), true );

		$this->currentPage->load( array( '*', 'language.locale', 'language.iso639', 'template.filename', 'domainGroup.domainGroup:RCDomain.*'  ) );

		// make sure filter criteria are loaded, otherwise we would get endless recursion
		// potentially benefits performance as well by preventing fields to be loaded separately by lazy loading
		//	$this->currentPage->load();

		if ( ( $newPage = RCPage::getTargetPage( $this->currentPage ) ) !== $this->currentPage ) {
			$targetLocation = $this->currentPage->getUrlForPage( $newPage, true, true );

			header( 'Location: ' . $targetLocation );

			return self::RETURN_CODE_HANDLED;
		}

		// default content-type, may be overriden by calling header(...) again
		header( 'Content-Type: text/html; charset=utf-8' );

		// set locale
		$locale = $this->currentPage->language->locale;

		if ( $locale ) {
			if ( !setlocale( LC_ALL, $locale ) ) {
				// mac has different locales, so we try those as well, if setting locale fails
				// (this is just to make testing locally on macs easier)
				$ret = setlocale( LC_ALL, str_ireplace( 'utf8', 'UTF-8', $locale ) );
			}
		}

		$this->storageFilter = new RBStorageDataTypeValueFilter( array(
			'DTSteroidLive' => $this->currentPage->live,
			'DTSteroidLanguage' => $this->currentPage->language->{Record::FIELDNAME_PRIMARY}
		) );

		$storage->registerFilter( $this->storageFilter, self::FILTER_IDENTIFIER );

		if ( ( $classRequest = $requestInfo->getQueryParam( 'classRequest' ) ) && isset( self::$classRequestHandler[ $classRequest ] ) ) {
			$handler = self::$classRequestHandler[ $classRequest ];

			/* @var IClassRequestHandler $handler */
			return $handler->handleClassRequest( $storage, $this->currentPage, $classRequest, $requestInfo );
		}

		/*
				if ($classRequest = $requestInfo->getQueryParam('classRequest')) {
					$classRequestParts = explode(':', $classRequest, 2);

					if (count($classRequestParts) === 2 && strlen($classRequestParts[0]) === 2) { // class request type has to always have strlen of 2
						switch ($classRequestParts[0]) {
							case self::CLASSREQUEST_TYPE_LOCAL_EXT: // lx:elasticsearch/class.ElasticSearch.php
								if (substr($classRequestParts[1], -4) !== '.php') {
									throw new SecurityException('Invalid class request (does not end on .php), might be security related.');
								}

								$path = Filename::resolvePath($classRequestParts[1]); // also does security related filtering

								$fullPath = STLOCAL . '/' . ltrim($path, '/');

								if (is_readable($fullPath) && is_file($fullPath)) { // TODO: support symlinks in a secure fashion
									// TODO
								} else {
									throw new SecurityException('Invalid class request (resolved path invalid), might be security related.');
								}
							break;
							default:
								throw new SecurityException('Invalid class request (unknown class request type), might be security related.');
						}
					} else {
						throw new SecurityException('Invalid class request (wrong format), might be security related.');
					}
				}
		*/


		$templateRecord = $this->currentPage->template;


		$context = array( 'page' => $this->currentPage, 'request' => $requestInfo, 'url' => $url, 'storage' => $storage );

//		$areaJoins = $this->currentPage->{'page:RCPageArea'};
		// saves some queries (about 4 per page area), and makes query cacheable
		$areaJoins = $storage->selectRecords( 'RCPageArea', array( 'fields' => '*', 'where' => array( 'page', '=', '%1$s' ) ), NULL, NULL, NULL, array( $this->currentPage ), 'UHPage_pageArea' );

		if ( ( $elementPrimary = intval( $requestInfo->getQueryParam( 'element' ) ) ) && ( $elementClass = $requestInfo->getQueryParam( 'class' ) ) ) { // AJAX request to an element
			$elements = array();

			foreach ( $areaJoins as $areaJoin ) {
				$elements[ ] = new ArrayObject( array( 'element' => $areaJoin->area, 'columns' => $areaJoin->columns, 'key' => $areaJoin->{'key'} ), ArrayObject::ARRAY_AS_PROPS ); // TODO: more data?
			}

			while ( $elJoin = array_shift( $elements ) ) {
				if ( isset( $elJoin->{'key'} ) ) {
					$columnKey = $elJoin->{'key'};
				}

				// TODO: optimize by checking element value instead of grabbing whole element
				if ( $elJoin->element instanceof $elementClass && $elJoin->element->{Record::FIELDNAME_PRIMARY} == $elementPrimary ) {
					if ( $elJoin->hidden ) {
						throw new Exception( 'Element request for hidden element.' );
					}

					$elementJoin = $elJoin;
					break;
				}

				if ( $elJoin instanceof IRecord && $elJoin->hidden ) continue; // skip hidden elements

				$children = $elJoin->element->getChildren();

				if ( $children ) {
					$elements = array_merge( $children, $elements );
				}
			}


			if ( !isset( $elementJoin ) ) {
				// this might also happen in case element got removed from page after user loaded page, and afterward user triggers request to now deleted element - in this case we don't have a security problem
				throw new SecurityException( 'Invalid elementPrimary ' . $elementPrimary . ' on url ' . $url->url . ' for page passed, MAY be security relevant.' );
			}

			$template = new Template( $storage, STROOT . '/template/element.php' );

			$indexedAreas = array( 'element' => array( array( 'element' => $elementJoin->element->load(), 'columns' => $elementJoin->columns, 'columnKey' => $columnKey ) ) );

			$context[ 'isDirectLink' ] = true;

			$output = $template->getOutput( $context, $indexedAreas, $requestInfo->getQueryParam( 'part' ) );

		} else {
			$template = $templateRecord->getTemplateInstance();

			$indexedAreas = array();

			foreach ( $areaJoins as $areaJoin ) {
				$k = $areaJoin->key;

				if ( !isset( $indexedAreas[ $k ] ) ) {
					$indexedAreas[ $k ] = array();
				}

				$indexedAreas[ $k ][ ] = array( 'element' => $areaJoin->area, 'columns' => $areaJoin->columns, 'columnKey' => $k ); // TODO: more data?
			}

			$context[ 'isDirectLink' ] = false;

			if ( $part = $requestInfo->getQueryParam( 'part' ) ) {
				$output = $template->getOutput( $context, $indexedAreas, $part );
			} else {
				$output = $template->getOutput( $context, $indexedAreas );
			}
		}

		if ( $output ) {
			$section = Config::section('web');

			if (!empty($section['enableDebugParameter'])) {
				if ( !empty( $_GET[ 'qc' ] ) ) {
					$output .= 'Query count: ' . $storage->getQueryCount() . ' queries<br>';
				}

				if ( !empty( $_GET[ 'qt' ] ) ) {
					$output .= 'Query time: ' . ( $storage->getQueryTime() * 1000 ) . 'ms<br>';
				}

				if ( !empty( $_GET[ 'tt' ] ) && isset( $_SERVER[ 'REQUEST_TIME_FLOAT' ] ) ) {
					$output .= 'Total time: ' . ( ( microtime( true ) - $_SERVER[ 'REQUEST_TIME_FLOAT' ] ) * 1000 ) . 'ms<br>';
				}

				if ( !empty( $_GET[ 'qbt' ] ) ) {
					$output .= 'Query build time: ' . ( $storage->getQueryBuildTime() * 1000 ) . 'ms<br>';
				}
			}


			$language = $this->currentPage->language;
			header( 'Content-Language: ' . $language->iso639 );

// output "filter" demo
			/*
						if (($leet = $requestInfo->getQueryParam('leet')) && $leet === 'haxorz') {
							$repl = array(
										'A' => '/-\\', 'B' => '|3', 'C' => '¢', 'D' => '|)', 'E' => '£', 'F' => 'ƒ', 'G' => '6', 'H' => '|-|',
										'I' => '|', 'J' => '_|', 'K' => '|{', 'L' => '|_', 'M' => '/\\/\\', 'N' => '|\\|', 'O' => '0', 'P' => '|°', 'Q' => '0_',
										'R' => '|2', 'S' => '5', 'T' => '7', 'U' => '|_|', 'V' => '\\/', 'W' => '\\/\\/', 'X' => '}{', 'Y' => '`/', 'Z' => 'z'
									) ;
							$from = array_keys($repl);
							$to = array_values($repl);

							$output = preg_replace_callback('/(>)([^<>]+)(<)/', function($matches) use ($from, $to) {
								return '>' . str_ireplace( $from, $to, $matches[2]	) . '<';
							}, $output);
						}
			*/
			$acceptGzip = false;
			$acceptHeader = $requestInfo->getServerInfo( 'HTTP_ACCEPT_ENCODING' );

			// TODO: cache gzencoded output ?
			if ( $acceptHeader && strpos( $acceptHeader, 'gzip' ) !== false ) {
				$acceptGzip = true;
				header( 'Content-Encoding: gzip' );

				$output = gzencode( $output, 9 );
			}

			echo $output;
		}

		return self::RETURN_CODE_HANDLED;
	}


}