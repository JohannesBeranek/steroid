<?php

interface IIncludeCache {
	function doInclude( $key );
	function doIncludeOnce( $key );
	function doRequire( $key );
	function doRequireOnce( $key );
}
