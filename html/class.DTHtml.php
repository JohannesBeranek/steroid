<?php
/**
 * @package steroid\html
 */

require_once STROOT . '/datatype/class.BaseDTText.php';
require_once STROOT . '/html/class.HtmlUtil.php';

/**
 * @package steroid\html
 */
class DTHtml extends BaseDTText {
	public static $defaultAllowed = array(
		'sup', 'sub', 'br' => array( 'class' ), 'ul', 'li',
		'ol' => array( 'start' ),
		'h1' => array( 'class' ), 'h2' => array( 'class' ), 'h3' => array( 'class' ), 'h4' => array( 'class' ), 'h5' => array( 'class' ), 'u', 'b', 'strong', 'em',
		'p' => array( 'class', 'id' ),
		'strike',
		'i', 'div' => array( 'class', 'id' ),
		'span' => array( 'class' ),
		'img' => array( 'src', 'alt' ),
		'a' => array( 'href', 'title', 'target', 'id', 'name' ),
		'table' => array( 'border', 'class', 'cellpadding', 'cellspacing', 'align' ),
		'tbody', 'tr', 'th', 'td', 'caption', 'thead',
		'blockquote', 'cite' => array( 'title' )
	);

	/**
	 * @param int|null        $maxLen
	 * @param bool            $nullable
	 * @param array|true|null $allowed pass true to allow everything
	 */
	public static function getFieldDefinition( $maxLen = NULL, $nullable = false, $allowed = NULL ) {
		if ( $maxLen === NULL ) {
			$maxLen = 65535;
		}

		if ( $allowed === NULL || $allowed === false ) {
			$allowed = static::$defaultAllowed;
		}

		return array(
			'dataType' => get_called_class(),
			'maxLen' => $maxLen,
			'isFixed' => false,
			'default' => NULL,
			'nullable' => (bool)$nullable,
			'allowed' => $allowed
		);
	}

	public function setValue( $data = NULL, $loaded = false, $path = NULL, array &$dirtyTracking = NULL ) {
//		if (!$loaded && ($this->config[ 'allowed' ] !== true  &&  !HtmlUtil::isValid( $data, $this->config[ 'allowed' ] ) ) ) {
//			throw new InvalidValueForFieldException( 'Invalid data, message: ' . HtmlUtil::getLastInvalidMessage() );
//		}

		parent::setValue( $data, $loaded, $path, $dirtyTracking );
	}
}