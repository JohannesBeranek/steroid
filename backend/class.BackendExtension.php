<?php
/**
 * @package steroid\backend
 */

abstract class BackendExtension {
	const HAS_RESPONSE = true;

	public static function handleRequest( RBStorage $storage, IRequestInfo $requestInfo, $method = NULL ) {
		throw new LogicException( 'Unknown method "' . $method . '" for extension "' . get_called_class() . '"' );
	}
}