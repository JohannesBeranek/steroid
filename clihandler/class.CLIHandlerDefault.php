<?php
/**
 *
 * @package steroid\cli
 */

require_once __DIR__ . '/class.CLIHandler.php';

/**
 *
 * @package steroid\cli
 *
 */
class CLIHandlerDefault extends CLIHandler {
	public function performCommand( $called, $command, array $params ) {
		$availableCommands = $this->getAvailableCommands();

		$closestMatchPercent = 0;
		$closestMatch = NULL;

		foreach ( $availableCommands as $aCommand ) {
			similar_text( $aCommand, $command, $p );

			if ( $p > $closestMatchPercent ) {
				$closestMatchPercent = $p;
				$closestMatch = $aCommand;
			}
		}

		$out = $this->getUsageText( $called, $command, $params );

		if ( $closestMatch !== NULL ) {
			$out .= "\nDid you mean \"" . $closestMatch . "\"?\n";
		}

		$this->notifyError( $out );

		return EXIT_FAILURE;
	}

	public function getUsageText( $called, $command, array $params ) {
		$availableCommands = $this->getAvailableCommands();

		return $this->formatUsageArguments( array(
			ST::PRODUCT_NAME . ' CLI commands:' => $availableCommands
		) );
	}

	public function compgen( $called, $command, array $params ) {
		$availableCommands = $this->getAvailableCommands();

		return implode( ' ', $availableCommands );
	}


}


?>