<?php
/**
 * @package steroid\html
 */
 
 
require_once __DIR__ . '/ganon.php';
 
/**
 * @package steroid\html
 */
class HtmlUtil {
	protected static $lastInvalidMessage;
	
	/**
	 * Tags which may not be closed in html < 5 and may be not closed in html 5 
	 *
	 * @var array
	 */
	protected static $htmlTagsNeedNoClose = array(
		'area', 'base', 'basefont', 'br', 'col', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param'
	);
	
	
	protected static $commentRegex = '/<!(?:--(?:[^-]*|-[^-]+)*--\s*)*>/';
	
	/**
	 * return passed htmlString with comments stripped out
	 * 
	 * Passed string does not need to be valid html
	 * 
	 * @param string $htmlString
	 * @return string
	 */
	public static function stripComments( $htmlString ) {
		return preg_replace(self::$commentRegex, '', $htmlString);
	}
	
	public static function getLastInvalidMessage() {
		return self::$lastInvalidMessage;
	}
	
	public static function getAndClearLastInvalidMessage() {
		$ret = self::$lastInvalidMessage;
		self::$lastInvalidMessage = NULL;
		return $ret;
	}
	
	/**
	 * Checks vor validity of htmlString according to html5 rules 
	 * 
	 * Disregards comments
	 * Uses whitelist for filtering
	 * 
	 * Example for $allowed:
	 * 
	 * array(
	 * 	'img' => array( 'src' ),
	 * 	'a' => array( 'href' ),
	 * 	'strong', 'em', 'b', 'i', 'br'
	 * )
	 * 
	 * @param string $htmlString
	 * @param array $allowed
	 * @return bool
	 */
	public static function isValid( $htmlString, array $allowed ) {
		if (empty($htmlString)) {
			return true;
		}
		
		// don't allow comments - if your content may have comments, strip them beforehand with stripComments function
		if (preg_match(self::$commentRegex, $htmlString )) {
			self::$lastInvalidMessage = 'Comment found';
			return false;
		}
		
		// (?|(a)|(b)) => (?|...) is called branch reset operator ; without this, unmatched groups of | are included as empty array entries!
		// TODO: attributes
		$namePattern = '[a-zA-Z_:][-a-zA-Z0-9_:.]*';
		$attributePattern = '(?:' . $namePattern . '|' . $namePattern . '=(?:"[^"]*"|(?<!")[^\s>\/]*))';
		
		$pattern = '/<\s*(?|(' . $namePattern . ')\s*((?:\s*' . $attributePattern . ')*)(\/)?|(\/)\s*(' . $namePattern . '))\s*>/i';
		$matches = array();
		
		$matchCount = preg_match_all($pattern, $htmlString, $matches, PREG_SET_ORDER);
	
		$stack = array();
		
		foreach ($matches as $match) {
			if ($match[1] == '/') { // closing
				if (empty($stack)) { // closing tag without opening tag left
					self::$lastInvalidMessage = 'Closing tag without opening tag found: "' . $match[2] . '"';
					return false;
				}
				
				$tag = strtolower($match[2]);
				
				// validate if tag was opened before
				while( $openingTag = array_pop($stack) ) {
					if ($openingTag == $tag) {
						break;
					} else {
						if (!in_array($tag, $stack)) {
							self::$lastInvalidMessage = 'No matching opening tag for closing tag: "' . $tag .'"';
							return false;
						} elseif (!in_array($openingTag, self::$htmlTagsNeedNoClose)) {
							self::$lastInvalidMessage = 'Tag was not closed: "' . $openingTag .'"';
							return false;
						}
					}
				}

			} else { // opening / self-closing
				$tag = strtolower($match[1]);

				// validate if tag is allowed
				if (!array_key_exists($tag, $allowed) && !in_array($tag, $allowed, true)) {
					self::$lastInvalidMessage = 'Disallowed tag: "' . $tag . '"';
					return false;
				}
								
				// parse attributes
				if (count($match) > 2 && ($attributeString = trim($match[2])) != '') {
					$allowedAttributes = array_key_exists($tag, $allowed) ? $allowed[$tag] : NULL;
					
					if (empty($allowedAttributes)) { // got attribute string, but no allowed attributes -> invalid
						self::$lastInvalidMessage = 'No allowed attributes for tag given: "' . $tag .'", attributeString: "' . $attributeString . '"';
						return false;
					}
						
					$attributePattern = '/([^ =]+)(?:\s*=\s*"([^"]*)")?/i';	
					$attributes = array();
					
					preg_match_all($attributePattern, $attributeString, $attributes, PREG_SET_ORDER);
					
					foreach ($attributes as $attribute) {
						if (!array_key_exists($attribute[1], $allowedAttributes) && !in_array($attribute[1], $allowedAttributes)) {
							self::$lastInvalidMessage = 'Attribute "' . $attribute[1] . '" not allowed in tag "' . $tag;
							return false;
						}
						
						// check for specific value
						if (count($attribute) > 2 && array_key_exists($attribute[1], $allowedAttributes) && !in_array($attribute[2], $allowedAttributes[$attribute[1]])) {
							self::$lastInvalidMessage = 'Value "' . $attribute[2] . '" not allowed for attribute "' . $attribute[1] . '" in tag "' . $tag . '"';
							return false;
						}
					}
				}
				
				if (end($match) == '/') { // self-closing
					// TODO
				} else {
					$stack[] = $tag;
				}
			}
		}
		
		// check if we still have unclosed tags which need closing
		foreach ($stack as $tag) {
			if (!in_array($tag, self::$htmlTagsNeedNoClose)) {
				self::$lastInvalidMessage = 'Unclosed tag: "' . $tag . '"';
				return false;
			}
		}
		
		return true;
	}

	// not yet tested
	public static function repair( $html ) {
		$html = str_get_dom( $html );
		
		return (string)$html;
	}

	/**
	 * Filters given html string by passed list (or CSS rules if $useSelectors = true)
	 * 
	 * Uses ganon for filtering to be more robust against invalid html
	 * ganon is not the fastest solution, but flexible, easy and rather solid/stable, so this is actually made for smaller html snippets/pages ( < 100K )
	 * 
	 */
	public static function filter( $htmlString, array $allowed, $keepChildren = true, $removeAttributes = true, $useSelectors = false ) {
		$html = str_get_dom( $htmlString );
		
		$selectors = array();

		// $html may already be the root node
		$root = $html->root ? $html->root : $html;

		$nodes = $root->children;

		while( $node = array_shift($nodes) ) {
			// for debugging
			// if ($node->indent() >= 0) {
			//	echo str_repeat("\t", $node->indent()) . $node->getTag() . "\n";
			//}

			//if ($node->isText() || $node instanceof HTML_NODE_CDATA) {
			//	echo "Text: " . $node->toString() . "\n\n";
			//} 
			// ---

			if ($node->isText())
				continue;

			if ($useSelectors) {
				foreach ($allowed as $rule) {
					$res = $node->select($rule, false, false, true);
				
					if ($res) {
						break;
					}
				}
			} else {
				$res = NULL;
				$tag = strtolower($node->getTag());
				

				$ns = $node->getNamespace();

				if ($ns === '') { // for now, just disallow everything with namespace
					foreach ($allowed as $k => $v) {
						if (is_array($v)) { // array: allow tag, check attributes according to array
							if ($k === $tag) {
								foreach ($node->attributes as $attributeName => $attributeValue) { // check allowed attributes
									if (!in_array($attributeName, $v, true)) {
										if ($removeAttributes) {
											// just remove disallowed attributes
											$node->deleteAttribute( $attributeName );
										} else {
											// kick whole tag
											break 2;
										}
										
									}
								}

								$res = true;
								break;
							}
						} else { // string: allow tag without attributes
							if ($v === NULL) {
								$v = $k;
							}

							if ($v === $tag) {
								if (empty($node->attributes) && !$removeAttributes) {
									$res = true;
								} else if ($removeAttributes) {
									$node->attributes = array();
									$node->attributes_ns = array();
									$res = true;
								}

								break;
							} 
						}
					}
				}
			}

			if (!empty($res)) {
				if ($children = $node->children) {
					$nodes = array_merge($children, $nodes);
				}
			} else {
				if ($keepChildren && ($children = $node->children)) {
					$nodes = array_merge($children, $nodes);
				}

				$node->detach($keepChildren);
			}
		}
		
		return (string)$html;
	}

	// FIXME: make sure html entities aren't truncated
	// TODO: visual vs actual count (e.g. how to count html entities)
	public static function htrunc( $string, $chars, $inWord = false, $killBreaks = false, $linkTarget = NULL, $countTags = false, $linkImages = true, $appendix = NULL ) {
		if ($appendix === NULL) {
			$appendix = ' &hellip;';
		}

		if ($linkImages) {
			// convert images into links
			$ret = preg_replace( '/<img [^>]*src="([^"]*)"[^>]*>/u', '<a href="$1">$1</a>', $string );
		} else {
			$ret = $string;
		}

		if ( $linkTarget !== null ) {
			if ( $linkTarget === true ) {
				$ret = preg_replace( '/<a [^>]*href="([^"]*)"[^>]*target="([^"]*)">/u', '<a href="$1" target="$2">', $ret );
				$ret = preg_replace( '/<a [^>]*target="([^"]*)"[^>]*href="([^"]*)">/u', '<a href="$2" target="$1">', $ret );
				// TODO: fix a tags without target attribute
			} else {
				$ret = preg_replace( '/<a [^>]*href="([^"]*)"[^>]*>/u', '<a href="$1" target="' . $linkTarget . '">', $ret );
			}
		}

		$allowedTags = array( 'a', 'b', 'strong', 'em', 'i', 'u', 's' );

		if ( !$killBreaks ) {
			$allowedTags[ ] = 'br';
		}

		$ret = preg_replace_callback( '/<\/?([^ >\/]+)\S*?>/u', function( $matches ) use ( $allowedTags ) {
			$tag = strtolower( $matches[ 1 ] );

			if ( in_array( $tag, $allowedTags ) ) {
				return $matches[ 0 ];
			}

			return '';
		}, $ret );

		// remove now empty tags - should leave breaks intact
		$ret = preg_replace( '/<([^ >]+).*?>\s*<\/\1>/', '', $ret );

		$tagStack = array();
		$len = mb_strlen( $ret );

		// short circuit
		// also prevents ($i - 1) < 0
		if ($len === 0) {
			return $ret;
		}

		if ( $countTags ) {
			$lastChar = $chars - 1;
		}

		$c = 0;

		for ( $i = 0; $i < $len; $i++ ) {
			if ( mb_substr($ret, $i, 1) === '<' ) { // skip tag
				$tagStart = $i;

				if ( mb_substr($ret, $i + 1, 1)  === '/' ) {
					// closing tag
					array_pop( $tagStack );

					$isEndTag = true;
				} else {
					// opening tag
					preg_match( '/([^ \/>]+)[^\/>]*(\/>|>)/u', $ret, $m, PREG_OFFSET_CAPTURE, $i + 1 );
					if ( $m[ 2 ][ 0 ] !== '/>' ) {
						$tag = $m[ 1 ][ 0 ];

						if (!in_array(strtolower($tag), self::$htmlTagsNeedNoClose, true)) {

							$tagStack[ ] = $tag;

							if ( $countTags ) {
								$lastChar -= 3 + mb_strlen($tag); // 3 chars for "</...>"
							}
						}
					}

					$isEndTag = false;
				}

				$i = mb_strpos( $ret, '>', $i + 1 );


				if ( $countTags ) {
					if ( $isEndTag ) {
						$lastChar += $i - $tagStart + 1;
					}

					if ( $i >= ( $lastChar ) ) {
						break;
					}
				}

				continue;
			}

			if ( !$countTags ) {
				$c++;

				if ( $c === $chars ) {
					break;
				}
			} else if ( $i >= ( $lastChar ) ) {
				break;
			}
		}


		if ( $i < ( $len - 1 ) ) {
			// easy version - trim hit space
			if ( mb_substr($ret, $i - 1, 1) === ' ' || mb_substr($ret, $i, 1) === ' ' || $inWord ) {
				$ret = rtrim( mb_substr( $ret, 0, $i ) );

			} else {
				while ( --$i && mb_substr( $ret, $i, 1) !== ' ' ) {
					if ( mb_substr($ret, $i, 1) == '<' ) {
						if ( mb_substr($ret, $i + 1, 1) !== '/' ) {
							array_pop( $tagStack );
						} else {
							preg_match( '/([^ \/>]+)[^\/>]*(\/>|>)/u', $ret, $m, PREG_OFFSET_CAPTURE, $i );
							if ( $m[ 2 ][ 0 ] !== '/>' ) {
								$tag = $m[ 1 ][ 0 ];
								$tagStack[ ] = $tag;
							}
						}
					}
				}

				$ret = rtrim( mb_substr( $ret, 0, $i ) );
			}

			while ( $tag = array_pop( $tagStack ) ) {
				$ret .= '</' . $tag . '>';
			}

			if ($appendix !== false && $appendix !== '') {
				$ret .= $appendix;
			}
		}


		return $ret;
	}
}
 