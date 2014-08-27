<?php
/**
 * @package steroid\template
 */

require_once STROOT . '/file/class.Filename.php';

require_once STROOT . '/gfx/class.GFX.php';
require_once STROOT . '/util/class.Pager.php';
require_once STROOT . '/util/class.Debug.php';
require_once STROOT . '/html/class.HtmlUtil.php';

require_once STROOT . '/res/class.Res.php';

require_once STROOT . '/language/class.RCFrontendLocalization.php';


/**
 * @package steroid\template
 */
class Template {
	const FN_STRIP_HTML_WHITESPACES = 'fn_strip_html_whitespaces';
	const FN_STRIP_HTML_WHITESPACES_AGGRESSIVE = 'fn_strip_html_whitespaces_aggressive';
	const FN_STRIP_SCRIPT_LINEBREAKS = 'fn_strip_script_linebreaks';
	const FN_STRIP_STYLE_LINEBREAKS = 'fn_strip_style_linebreaks';

	/**
	 * Do not alter from template
	 *
	 * @var bool
	 */
	protected $cancelOutputFlag;

	/**
	 * Don't touch this
	 *
	 * @var bool[]
	 */
	private $requiredFiles = array();

	/**
	 * Do not alter from template
	 *
	 * @var string
	 *
	 */
	protected $mainTemplateFile;

	/**
	 * Do not try to use this directly
	 *
	 * @var array
	 *
	 */
	protected $fileContexts;


	/**
	 * May be used as seen fit in template
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * May be modified in templates for e.g. dynamic element reordering
	 * 
	 * @var array
	 */
	protected $currentOutputArray;

	/**
	 * May be used together with $currentOutputArray
	 *
	 * @var array
	 */
	protected $currentOutputMeta;

	/**
	* Used for prerendering
	*
	* @var array
	*/
	protected $currentInput;


	/**
	 * Do not alter from template
	 *
	 * @var array
	 */
	protected $localDataStack;

	/**
	 * Do not alter from template
	 *
	 * @var array
	 *
	 */
	protected $res;

	/**
	 * Do not alter from template
	 *
	 * @var array
	 *
	 */
	protected $areas;


	/**
	 * Do not alter from template
	 *
	 * @var array
	 *
	 */
	protected $elementsForAreas;


	/**
	 * May be used from template
	 *
	 * @var IRBStorage
	 */
	protected $storage;

	/**
	 * May be used from template
	 *
	 * @var GFX
	 */
	protected static $gfx;


	/**
	 * May be used from template
	 *
	 * Deferred output using $this->putString( $name ), where $name is the key in this array
	 *
	 * e.g.:
	 * $this->putString('pageTitle');
	 * ...
	 * $this->string['pageTitle'] = 'example';
	 * ...
	 * $this->putString('pageTitle');
	 * ---> both putString calls will result in 'example' being print at that position
	 *
	 * string table persists across different files!
	 *
	 * @var array
	 */
	protected $string = array();


	protected $outputWidgetTimes;

	/**
	 * @param string $filename filename of template
	 * @param string $sourceDirectory pass __DIR__ if you want to use a path relative to your script, otherwise a relative path will be based on WEBROOT
	 */
	public function __construct( IRBStorage $storage, $filename, $sourceDirectory = NULL ) {
		$this->storage = $storage;
		$this->mainTemplateFile = Filename::webize( $filename, $sourceDirectory );

		if ( !self::$gfx ) { // don't use a new gfx instance for every template
			self::$gfx = new GFX( new FileCache(), $storage );
			self::$gfx->setMode( GFX::MODE_TAG );
			self::$gfx->setSkipGenerate( true );
		}

		$section = Config::section('web');

		$this->outputWidgetTimes = (!empty($section['enableDebugParameter'])) && isset($_GET['wt']) && ($_GET['wt'] === '1');
	}

	protected function prepareForParsing( $data, $elementsForAreas ) {
		$this->fileContexts = array();
		$this->data = $data;
		$this->data[ 't' ] = new ArrayObject(); // magic template object to pass stuff between templates
		$this->elementsForAreas = $elementsForAreas;
		$this->res = array();
		$this->localDataStack = array();
	}

	protected function pushContext( $filename ) {
		// add newest context at start so current context always has index 0
		$newFileContext = array( 'namedPartStack' => array(), 'dir' => pathinfo( $filename, PATHINFO_DIRNAME ), 'output' => array(), 'contextStack' => array() );

		array_unshift( $this->fileContexts, $newFileContext );
		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ] =& $this->fileContexts[ 0 ];
	}

	protected function popContext() {
		if ( count( $this->fileContexts ) <= 1 ) return; // do not pop last context

		$oldContext = array_shift( $this->fileContexts );

		if ( $oldContext[ 'output' ] ) {
			$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ] = array_merge( $this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ], $oldContext[ 'output' ] ); // TODO: contextStack ?
		}
	}

	protected function _getOutput( $filename, $addedParams = NULL ) {
		if ( $filename === '' || $filename === NULL ) {
			throw new Exception( '_getOutput called with empty filename.' );
		}

		$this->pushContext( $filename );

		// when extending, localData comes last in precedence, thus we extract it first
		foreach ( $this->localDataStack as $ld ) {
			extract( $ld );
		}

		if ( $this->data ) {
			extract( $this->data ); // put variables in local scope
		}

		if ( $addedParams ) {
			extract( $addedParams );
		}

		ob_start();

		try {
			$returnValue = ( include $filename );

			if ( $returnValue === false ) {
				ob_clean();

				$this->cancelOutputFlag = true;

				return false;
			}
		} catch ( Exception $e ) {
			ob_clean();

			if ($e instanceof TemplatePassThroughException) {
				throw $e;
			} else {
				throw new TemplateException( 'Exception requiring template file "' . $filename . '"', 0, $e );
			}
		}

		$this->endAll();

		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		$this->popContext();
	}

	protected function buildOutput( $input, $limitToPart = NULL, $returnArray = false ) {
		if ($input instanceof DebugProtection) {
			$input = $input->getValue();
		}


		$output = array();
		$this->currentOutputArray = &$output;

		$currentOutputMeta = array();
		$this->currentOutputMeta = &$currentOutputMeta;

		$this->currentInput = &$input;


		// filter js files which will be included in head from normal js include, so we don't double include - TODO: there's probably a php function to do this kind of filtering by key
		if ( !empty( $this->res[ 'js' ] ) ) {
			foreach ( $this->res as $t => $data ) {
				if ( strlen( $t ) > 2 && substr( $t, 0, 2 ) === 'js' ) {
					foreach ( $data as $f => $val ) {
						if ( isset( $this->res[ 'js' ][ $f ] ) ) {
							unset( $this->res[ $t ][ $f ] );
						}
					}
				}

			}
		}

		reset($input);
		while ( list($name, $outputPart) = each($input) ) {

			if ( is_array( $outputPart ) ) {
				if ( !$limitToPart || ( $limitToPart == $name ) || $outputPart[ 'action' ] === 'output' ) {
					// DEBUG
					unset($partTime);


					if (!isset($outputPart['action'])) {
						throw new Exception( Debug::getStringRepresentation( $outputPart ) );
					}

					switch ( $outputPart[ 'action' ] ) {
						case 'element':
							$this->lastLocalDataStack = $this->localDataStack;
							$this->localDataStack = $outputPart[ 'data' ];

							$this->fileContexts = array();
							$this->pushContext( rtrim( $outputPart[ 'dir' ], '/' ) . '/' );

							// DEBUG
							$startTime = microtime(true);

							// handleArea may call $template->cancelOutput() to set $this->cancelOutputFlag or just return false to cancel output
							if ( ( $outputPart[ 'element' ]->handleArea( $this->data, $this ) === false && ( $this->cancelOutputFlag = true ) ) || $this->cancelOutputFlag ) {
								return false;
							}

							$partTime = microtime(true) - $startTime;


							$this->popContext();

							// set elementMeta before $outputPart gets overriden
							$meta = array( 'type' => 'element', 'originalData' => $outputPart['originalData'] );

							$outputPart = $this->buildOutput( new DebugProtection( $this->fileContexts[ 0 ][ 'output' ] ), $limitToPart, true );

							$this->localDataStack = $this->lastLocalDataStack;
							
							break;
						case 'output':
							$outputPart = $this->buildOutput( new DebugProtection( $outputPart[ 'output' ] ), ( $limitToPart === true || ( $limitToPart !== NULL && $limitToPart === $name ) ) ? true : $limitToPart, true );
							
							$meta = array( 'type' => 'output' );

							break;
						case 'expanded':
						case 'string':
						case 'res':
						case 'callback':
							// these are here so we don't fall into default continue
							$meta = array( 'type' => $outputPart[ 'action' ] );
							break;
						default:
							continue 2;
					}

					if ( $outputPart === false ) {
						return false;
					}

					// reset references
					$this->currentOutputArray = &$output;
					$this->currentOutputMeta = &$currentOutputMeta;
					$this->currentInput = &$input;

					$output[] = $outputPart;
					end($output); // set array pointer to end
					$meta['outputIndex'] = key($output);
					$currentOutputMeta[] = $meta;


//
					if (isset($partTime) && $this->outputWidgetTimes) {
						$output[] = "Time: " . ($partTime * 1000) . "ms";
						$currentOutputMeta[] = array( 'type' => 'debug' );
					}
					
				}
			} else {
				if ( $limitToPart === NULL || $limitToPart === $name || $limitToPart === true ) {
					$output[] = $outputPart;
					$currentOutputMeta[] = array( 'type' => 'rendered' );
				}
			}
		}

		if ( $returnArray ) {
			return $output;
		}

		return $this->expandOutput( $output );
	}

	final protected function expandOutput( array &$output ) {
		$ret = '';

		static $mode;

		if ( $mode === NULL ) {
			$mode = ST::getMode();
		}

		foreach ( $output as $outputPart ) {
			if ( is_array( $outputPart ) ) {
				if ( isset( $outputPart[ 'action' ] ) ) {
					switch ( $outputPart[ 'action' ] ) {
						case 'expanded':
							$ret .= $this->expandOutput( $outputPart['output'] );
							break;
						case 'debug':
							$ret .= Debug::getStringRepresentation( $outputPart['value'] );
							break;
						case 'string':
							$name = $outputPart[ 'name' ];

							if ( array_key_exists( $name, $this->string ) ) {
								$ret .= empty( $outputPart[ 'escape' ] ) ? $this->string[ $name ] : htmlspecialchars( $this->string[ $name ], $outputPart[ 'escape' ] === true ? ENT_COMPAT : $outputPart[ 'escape' ], "UTF-8" );
							}
							break;
						case 'res':
							$type = $outputPart[ 'type' ];

							if ( empty( $this->res[ $type ] ) ) break;

							// TODO: content type / content length / compression negotiation when delivering

							if ( strlen( $type ) > 2 && substr( $type, 0, 2 ) === 'js' ) {
								$typeStr = 'js';
							} else {
								$typeStr = $type;
							}

							switch ( $typeStr ) {
								case 'css':
									$types = array();

									foreach ( $this->res[ 'css' ] as $cssRes ) {
										if ( preg_match( '/^(http(s)?:|:)?\/\//', $cssRes[ 'filename' ] ) ) { // external resource: http://, https://, ://, //
											$index = count( $types );
										} else {
											$index = empty( $cssRes[ 'options' ] ) ? 'default' : json_encode( $cssRes[ 'options' ] );
										}

										if ( !array_key_exists( $index, $types ) ) {
											$types[ $index ] = array( 'files' => array(), 'options' => $cssRes[ 'options' ] );
										}

										$types[ $index ][ 'files' ][ ] = $cssRes[ 'filename' ];
									}

									foreach ( $types as $index => $type ) {
										$attributes = '';

										if ( ctype_digit( $index ) ) {
											$href = $cssRes[ 'filename' ][ 0 ];
										} else {
											if ( $mode == ST::MODE_PRODUCTION ) {
												$href = '/res?j=' . Res::createJob( $type[ 'files' ], $typeStr, $this->storage );
											}
										}

										if ( $type[ 'options' ][ 'media' ] ) {
											$attributes .= 'media="' . htmlspecialchars( $type[ 'options' ][ 'media' ] ) . '" ';
										}

										// uncompressed in dev mode
										if ( !ctype_digit( $index ) && $mode === ST::MODE_DEVELOPMENT ) {
											$newres = '';

											foreach ( $type[ 'files' ] as $f ) {
												$href = '/res?file=' . rawurlencode( $f );

												$newres .= '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars( $href, ENT_COMPAT, "UTF-8" ) . '" ' . $attributes . '/>';
											}
										} else {
											$newres = '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars( $href, ENT_COMPAT, "UTF-8" ) . '" ' . $attributes . '/>';


										}

										if ( isset( $type[ 'options' ][ 'condition' ] ) ) {
											$newres = '<!--[if ' . $type[ 'options' ][ 'condition' ] . ']>' . $newres . '<![endif]-->';
										}

										$ret .= $newres;
									}
									break;
								case 'js':
									$files = array();

									foreach ( $this->res[ $type ] as $jsRes ) {
										if ( preg_match( '/^(http(s)?:)?\/\//', $jsRes[ 'filename' ] ) ) { // external resource: http://, https://, //
											$ret .= '<script type="text/javascript" src="' . $jsRes[ 'filename' ] . '"></script>';
										} else {
											if ( $mode === ST::MODE_DEVELOPMENT ) {
												$ret .= '<script type="text/javascript" src="/res?file=' . rawurlencode( $jsRes[ 'filename' ] ) . '"></script>';
											} else {
												$files[ ] = $jsRes[ 'filename' ];
											}
										}
									}

									if ( $files ) {
										$src = '/res?j=' . Res::createJob( $files, $typeStr, $this->storage );

										$ret .= '<script type="text/javascript" src="' . htmlspecialchars( $src, ENT_COMPAT, "UTF-8" ) . '"></script>';
									}
									break;
							}
							break;
						case 'callback':
							$callback = $outputPart[ 'callback' ];

							if ( is_callable( $callback ) ) {
								$callback( $ret );
							} else {
								// TODO: caching with internal functions?
								$start = microtime( true );
								switch ( $callback ) {
									case self::FN_STRIP_HTML_WHITESPACES:
										$ret = preg_replace( '/>\s+</', '> <', $ret );
										$ret = preg_replace( '/(?|(<(?:script|style)[^>]*?>)\s+|\s+(<\/(?:script|style)>))/', '$1', $ret );
										break;
									case self::FN_STRIP_HTML_WHITESPACES_AGGRESSIVE: // this might change spacing, but at the same time remove problems with floats
										$ret = preg_replace( '/>\s+</', '><', $ret );
										$ret = preg_replace( '/(?|(<(?:script|style)[^>]*?>)\s+|\s+(<\/(?:script|style)>))/', '$1', $ret );
										break;
									case self::FN_STRIP_SCRIPT_LINEBREAKS:
										$ret = preg_replace_callback( '/(<script[^>]*?>)(.+?)<\/script>/s', function ( $matches ) {
											return $matches[ 1 ] . str_replace( array( "\r", "\n" ), '', $matches[ 2 ] ) . '</script>';
										}, $ret );
										break;
									case self::FN_STRIP_STYLE_LINEBREAKS:
										$ret = preg_replace_callback( '/(<style[^>]*?>)(.+?)<\/style>/s', function ( $matches ) {
											return $matches[ 1 ] . str_replace( array( "\r", "\n" ), '', $matches[ 2 ] ) . '</style>';
										}, $ret );
										break;
									default:
										throw new Exception( 'Unknown callback type passed:' . Debug::getStringRepresentation( $callback ) );
								}

							}
							break;
					}
				} else {
					$ret .= $this->expandOutput( $outputPart );
				}
			} else {
				$ret .= $outputPart;
			}
		}

		return $ret;
	}

	public function cancelOutput() {
		$this->cancelOutputFlag = true;
	}

	public function getOutput( $data, $elementsForAreas, $limitToPart = NULL ) {
		do {
			$this->prepareForParsing( $data, $elementsForAreas );

			try {				
				if ( $this->_getOutput( $this->mainTemplateFile ) === false || $this->cancelOutputFlag ) {
					return false;
				}
				
				$done = true;
			} catch( RestartWithDifferentTemplateException $ex ) {
				$file = $ex->getFile();
				
				$this->mainTemplateFile = Filename::webize( $ex->getTemplateName(), dirname( $file ) );			
			}
		} while (!isset($done));

		$output = $this->buildOutput( new DebugProtection( $this->fileContexts[ 0 ][ 'output' ] ), $limitToPart );

		if ( $output === false ) {
			return false;
		}

		return $output;
	}


// functions from here on are for use inside a template
	/**
	 *
	 * @param string $filename
	 */
	protected function path( $filename ) {
		return Filename::webize( $filename, $this->fileContexts[ 0 ][ 'dir' ] );
	}

	/**
	 * Extend template from given other template
	 *
	 * @param string $filename
	 */
	protected function extend( $filename, $addedParams = NULL ) {
		$this->_getOutput( $this->path( $filename ), $addedParams );
	}


	/**
	 * Include (require) given template/file
	 *
	 * e.g.
	 * $this->put('footer.php');
	 *
	 * Also callable from outside
	 *
	 * @param string $filename
	 */
	public function put( $filename, array $localData = NULL ) {
		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		$filename = Filename::webize( $filename, $this->fileContexts[ 0 ][ 'dir' ] );

		$this->pushContext( $filename );

		if ( $this->data ) {
			extract( $this->data ); // put variables in local scope
		}

		$hasLocalData = !empty( $localData );

		if ( $hasLocalData ) {
			$this->localDataStack[ ] = $localData;
		}

		foreach ( $this->localDataStack as $ld ) {
			extract( $ld );
		}

		ob_start();

		try {
			$returnValue = ( include $filename );

			if ( $returnValue === false ) {
				ob_clean();

				$this->cancelOutput();

				return false;
			}
		} catch ( Exception $e ) {
			ob_clean();

			if ($e instanceof TemplatePassThroughException) {
				throw $e;
			} else {
				throw new TemplateException( 'Exception putting file "' . $filename . '"', 0, $e );
			}
		}

		if ( $hasLocalData ) {
			array_pop( $this->localDataStack );
		}

		$this->endAll();

		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		// restructure, so we don't merge/override parts
		$this->fileContexts[ 0 ][ 'output' ] = array(
			array(
				'action' => 'output',
				'dontOverride' => true,
				'output' => $this->fileContexts[ 0 ][ 'output' ]
			)
		);

		$this->popContext();

		ob_start();
	}

	/**
	 * Start a part which may be overridden by a template extending the current template by starting a part named the same
	 *
	 * @param string|null $name
	 */
	protected function start( $name = NULL ) {
		static $nameCounter = 0;

		if ( $name === NULL ) { // autogenerate new name in case none was given ; this way you can use start without worrying about part being overriden
			$name = '__internal__' . ( ++$nameCounter );
		}

		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		$newContext = array( 'action' => 'output', 'output' => array(), 'namedPartStack' => array() );
		$stacks =& $this->fileContexts[ 0 ][ 'contextStack' ];

		$find = function ( &$context ) use ( $name, &$find, $newContext, &$stacks ) {
			if ( !is_array( $context ) || !isset( $context[ 'output' ] ) || !empty( $context[ 'dontOverride' ] ) ) return false;

			if ( isset( $context[ 'output' ][ $name ] ) && empty( $context[ 'output' ][ $name ][ 'dontOverride' ] ) ) {
				array_unshift( $context[ 'namedPartStack' ], $name );
				$context[ 'output' ][ $name ] = $newContext;
				array_unshift( $stacks, '' );
				$stacks[ 0 ] =& $context[ 'output' ][ $name ];
				return true;
			}


			foreach ( $context[ 'output' ] as $k => $c ) {
				if ( $find( $context[ 'output' ][ $k ] ) ) {
					return true;
				}
			}

			return false;
		};

		$found = $find( $this->fileContexts[ 0 ] );

		if ( !$found ) {
			array_unshift( $this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'namedPartStack' ], $name );
			$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ $name ] = $newContext;
			array_unshift( $this->fileContexts[ 0 ][ 'contextStack' ], '' );
			$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ] =& $this->fileContexts[ 0 ][ 'contextStack' ][ 1 ][ 'output' ][ $name ];

		}


		ob_start();
	}

	/**
	 * This moves a named part on the same contextual level to where you call this method.
	 *
	 * You can always use this function, as it checks for existence of a part with the given name before doing anything.
	 * Be aware however, that the named part is erased at its original location, and thus this operation is more like cut & paste
	 * than copy & paste.
	 *
	 * Named parts from different files aren't yet supported.
	 *
	 * @param string $name Name of part to insert
	 */
	protected function insert( $name ) {
		if ( isset( $this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ $name ] ) ) {

			$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

			$part = $this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ $name ];

			unset( $this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ $name ] );
			$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ $name ] = $part;

			ob_start();
		}
	}

	// EXPERIMENTAL, used by RTE
	protected function insertOutput( $output ) {
		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = array( 'action' => 'expanded', 'output' => $output ); 
	
		ob_start();
	}

	// EXPERIMENTAL, used by RTE
	protected function cutNextElement() {
		if (isset($this->currentInput)) {

			$i = 0;

			while(list($name, $outputPart) = each($this->currentInput)) {
				if (is_array($outputPart) && $outputPart['action'] === 'element') {
					unset( $this->currentInput[$name] );

					while ($i > 0) {
						prev();
						$i--;
					}

					return $outputPart;
				}

			}
		}
	

		return NULL;
	}



	/**
	 * Places the contents (element outputs) of an area
	 *
	 * Element output is actually only generated/placed after the whole template chain is evaluated, so its possible to move areas around etc
	 * without elements being called multiple times
	 */
	protected function area( $name ) {
		if ( !empty( $this->elementsForAreas[ $name ] ) ) {
			foreach ( $this->elementsForAreas[ $name ] as $element ) {
				$this->placeElement( $element );
			}
		}
	}

	public function placeElement( $element ) {
		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		$data = $this->localDataStack;

		if (isset($element['data'])) {
			$data = array_merge($data, $element['data']);
			unset($element['data']);
		}

		// needed for variables directly in $element (e.g. 'columns', 'element', ...)
		$data[] = $element;

		// this needs to be done with placeholder so we don't render areas more than once
		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = array( 'action' => 'element', 'element' => $element[ 'element' ], 'data' => $data, 'originalData' => $element, 'dir' => $this->fileContexts[ 0 ][ 'dir' ] );

		ob_start();
	}


	public function getPreviousElementMeta( $cut = false ) {
		if (empty($this->currentOutputMeta) || empty($this->currentOutputArray)) {
			return NULL;
		}


		$lastElement = end($this->currentOutputMeta);

		while ($meta = prev($this->currentOutputMeta)) {
			if ($meta['type'] === 'element') {
				if ( $cut ) {
					unset($this->currentOutputMeta[key($this->currentOutputMeta)]);
				}

				return $meta;
			}
		}

		return NULL;
	}

	/**
	 * End last started part - still open parts will be ended at end of file automatically
	 *
	 * @param $callbacks callable|callable[]|string|string[] pass callable or any of Template::FN_* (as is, or multiple as an array)
	 */
	protected function end( $callbacks = NULL ) {
		if ( empty( $this->fileContexts[ 0 ][ 'contextStack' ][ 1 ][ 'namedPartStack' ] ) ) return; // we actually need to look at the namedPartStack one context level above

		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		// Validate callback as soon as it is passed, otherwise tracking down errors would get more complicated
		if ( $callbacks !== NULL ) {
			if ( is_callable( $callbacks ) ) { // catch possibility of passing a callable array
				$callbacks = array( $callbacks );
			}

			foreach ( (array)$callbacks as $callback ) {
				$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = array( 'action' => 'callback', 'callback' => $callback );
			}
		}


		array_shift( $this->fileContexts[ 0 ][ 'contextStack' ] );

		ob_start(); // TODO: save on ob_start/end calls
	}

	/**
	 * End all currently started parts
	 *
	 * Shouldn't be used/needed under normal circumstances
	 *
	 */
	protected function endAll() {
		while ( !empty( $this->fileContexts[ 0 ][ 'contextStack' ][ 1 ][ 'namedPartStack' ] ) ) {
			$this->end();
		}
	}

	/**
	 * Add resource, optionally providing type which will else be deducted from file extension
	 *
	 * @param string      $filename
	 * @param array|null  $options only used for media queries at the moment
	 * @param string|null $type currently supported types are: js css
	 */
	protected function res( $filename, array $options = NULL, $type = NULL ) {
		$isExternal = preg_match( '/^(http(s)?:)?\/\//', $filename );

		if ( !$isExternal ) {
			$filename = Filename::webize( $filename, $this->fileContexts[ 0 ][ 'dir' ] );

			if ( !is_readable( $filename ) ) {
				throw new RuntimeException( 'Unable to read res "' . $filename . '"' );
			}
		}

		if ( $type === NULL ) {
			$type = pathinfo( $filename, PATHINFO_EXTENSION );
		}

		if ( $type === 'scss' || $type === 'less' ) {
			$type = 'css'; // it's still css
		}

		if ( $type !== 'css' && ( strlen( $type ) < 2 || substr( $type, 0, 2 ) !== 'js' ) ) {
			throw new LogicException( 'Invalid res type: "' . $type . '", only "js[...]" and "css" are supported at the moment.' );
		}

		if ( !array_key_exists( $type, $this->res ) ) {
			$this->res[ $type ] = array();
		}

		// by using the filename as key again we don't double add files
		$this->res[ $type ][ $filename ] = array(
			'filename' => $isExternal ? $filename : Filename::webpathize( $filename ),
			'options' => $options
		);
	}

	/**
	 * Print inclusion of all resources of given type
	 *
	 * @param string $type
	 */
	protected function putRes( $type ) {
		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = array( 'action' => 'res', 'type' => $type );

		ob_start();
	}

	/**
	 * Print string with given name
	 *
	 * @param string $name
	 */
	protected function putString( $name, $escape = true ) {
		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = ob_get_clean();

		$this->fileContexts[ 0 ][ 'contextStack' ][ 0 ][ 'output' ][ ] = array( 'action' => 'string', 'name' => $name, 'escape' => $escape );

		ob_start();
	}

	/**
	 * Print localized string
	 *
	 * @param string              $key
	 * @param boolean|string|null $fallback
	 * @param boolean|string|null $fallback
	 * @param boolean             $autoCreate
	 */
	protected function ll( $key, $values = NULL, $fallback = NULL, $preloadPrefix = NULL, $autoCreate = true, $escapePreSubstitution = false ) {
		if ( $preloadPrefix === NULL ) {
			$preloadPrefix = strstr( $key, '.', true );
		}

		if ( $preloadPrefix !== false ) {
			$table = RCFrontendLocalization::loadTranslationTable( $this->storage, $preloadPrefix );

			$translation = isset( $table[ $key ] ) ? $table[ $key ] : false;
		} else {
			$translation = RCFrontendLocalization::getTranslation( $this->storage, $key );
		}

		if ( $translation === false || $translation === NULL ) { // might be NULL ("not yet translated") in db
			if ( $translation === false && $autoCreate && isset( $this->data[ 'page' ] ) && ( $page = $this->data[ 'page' ] ) ) {
				$storageFilter = $this->storage->unregisterFilter( UHPage::FILTER_IDENTIFIER ); // temporarly unregister storage filter, so we can get preview language and create preview record

				$userRecord = User::getCLIUserRecord( $this->storage );

				$translationRecordData = array(
					'live' => DTSteroidLive::LIVE_STATUS_PREVIEW,
					'language' => $page->language->getFamilyMember( array( 'live' => DTSteroidLive::LIVE_STATUS_PREVIEW ) ), // FIXME: can't get preview version in live state because of filter
					'key' => $key
				);

				$val = $fallback === false ? NULL : $fallback;
				$variables = empty( $values ) ? NULL : ( '$' . implode( ', $', array_keys( $values ) ) );

				if ( !$page->live ) {
					$translationRecordData[ 'value' ] = $val;
					$translationRecordData[ 'creator' ] = $userRecord;
					$translationRecordData[ 'variables' ] = $variables;
				}

				$translationRecord = RCFrontendLocalization::get( $this->storage, $translationRecordData, $page->live ? Record::TRY_TO_LOAD : false );

				if ( $page->live && !$translationRecord->exists() ) {
					$translationRecord->value = $val;
					$translationRecord->creator = $userRecord;
					$translationRecord->variables = $variables;

					$translationRecord->save();
				} elseif ( !$page->live ) {
					$translationRecord->save();
				}


				if ( $page->live ) {
					$translationRecord = RCFrontendLocalization::get( $this->storage, array(
						'live' => DTSteroidLive::LIVE_STATUS_LIVE,
						'id' => $translationRecord->id, // same id as preview record!
						'language' => $page->language,
						'creator' => $userRecord,
						'key' => $key,
						'variables' => $variables,
						// if we have an existing translation record which is not live, we still take fallback instead of preview value 
						// otherwise we'd be publishing the record without user consent, which we don't want
						'value' => $val
					), false );

					$translationRecord->save();
				}

				// done, reregister filter
				$this->storage->registerFilter( $storageFilter, UHPage::FILTER_IDENTIFIER );
			}

			if ( $fallback === NULL ) { // $fallback may also be false, in which case this function returns false correctly
				$fallback = '[' . $key . ']';
			}

			$translation = $fallback;
		}

		if ( $escapePreSubstitution !== false ) {
			if ($escapePreSubstitution !== ENT_COMPAT && $escapePreSubstitution !== ENT_NOQUOTES) {
				$escapePreSubstitution = ENT_COMPAT; // assume user doesn't know what he's doing
			}
			
			$translation = htmlspecialchars( $translation, $escapePreSubstitution, "UTF-8" );
		}

		if ( !empty( $values ) && is_string( $translation ) ) {
			$translation = preg_replace_callback( '/(?<!\\\)\$([a-zA-Z_][a-zA-Z0-9_-]*)/', function ( $matches ) use ( $values ) {
				if ( isset( $values[ $matches[ 1 ] ] ) ) {
					return $values[ $matches[ 1 ] ];
				}

				return $matches[ 0 ];
			}, $translation );
		}

		return $translation;
	}

	/**
	 * Redirect to given url
	 *
	 * @param string  $url
	 * @param integer $code = 303 (use 307, if you want to preserve original method, e.g. make browser POST again if original method was POST)
	 *
	 * @return false returns false so you can do return $this->redirect(...) to prevent rendering of the remaining template
	 */
	public function redirect( $url, $code = 303 ) {
		// avoid http response splitting vulerability ( php header function should do this itself starting php 5.1.2, but it's always better to be sure than sorry )
		$url = str_replace( array( "\0", "\r", "\n" ), '', $url );
		
		// avoid XSS with redirect
		if (preg_match( '/^(javascript:|data:)/', $url )) {
			throw new Exception( 'Dangerous redirect url' );
		}
		
		$this->cancelOutput();
		
		header( ' ', true, $code );
		header( 'Location: ' . $url );
		
		return false;
	}
}



class TemplateException extends Exception {
}

class TemplatePassThroughException extends Exception {
	
}

class RestartWithDifferentTemplateException extends TemplatePassThroughException {
	protected $templateName;
	
	public function __construct( $templateName ) {
		$this->templateName = $templateName;
	}
	
	public function getTemplateName() {
		return $this->templateName;
	}
}