<?php

require_once __DIR__ . '/class.ImagickCLIUtil.php';

class ImagickDrawCLI {
	protected $options;

	protected $fontName;
	protected $fontSize;

	public function __construct() {
		$this->options = array();
	}

	public function setFont( $font_name ) {
		$this->fontName = $font_name;

		array_push( $this->options, '-font', $font_name );
	}

	public function getFont() {
		return $this->fontName;
	}

	public function setFontSize( $pointsize ) {
		$this->fontSize = $pointsize;

		array_push( $this->options, '-pointsize', $pointsize );
	}

	public function getFontSize() {
		return $this->fontSize;
	}

	// this function is not officially documented on php.net yet
	public function setTextKerning( $kerning ) {
		array_push( $this->options, '-kerning', $kerning );
	}

	public function setFontWeight( $font_weight ) {
		array_push( $this->options, '-weight', $font_weight );
	}

	public function setFillColor( $color ) {
		array_push( $this->options, '-fill', $color );
	}

	public function setStrokeAntialias( $stroke_antialias ) {
		// noop - there is no stroke antialias in convert documentation
	}
	
	public function setTextAntialias( $antiAlias ) {
		if ($antiAlias) {
			array_push( $this->options, '-antialias' ); // enable antialiasing
		} else {
			array_push( $this->options, '+antialias' ); // disable antialiasing
		}
	}

	public function annotation( $x, $y, $text ) {
		array_push($this->options, '-draw', 'text ' . ImagickCLIUtil::shortFloat($x) . ',' . ImagickCLIUtil::shortFloat($y) . ' "' . str_replace( '"', '\"', $text ) . '"' );
	}

	public function destroy() {
		// noop
	}


	/**
	* Used internally for ImagickCLI::draw command
	*/
	public function getCommands() {
		return $this->options;
	}
}