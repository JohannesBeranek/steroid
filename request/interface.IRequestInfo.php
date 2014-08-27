<?php

interface IRequestInfo {
	public function getRequestTime();
	public function isHTTPS();
	public function getRequestMethod();
	public function getRequestURI();
	public function getProtocol();
	public function getHTTPHost();
	public function getPagePath();
	public function getQueryString();
	
	/**
	 * Get param in query
	 * 
	 * @param string $name
	 * 
	 * @return mixed value of queried parameter or NULL if value is not set
	 */
	public function getQueryParam( $name, $unsetValue = NULL );
	
	/**
	 * Get param in post data
	 *
	 * @param string $name
	 *
	 * @return mixed value of queried parameter or NULL if value is not set
	 */
	public function getPostParam( $name, $unsetValue = NULL );

	public function getPost();
	public function getQueryParams();
	
	/**
	 * Merges passed params into query params of requestInfo object
	 * 
	 * @param array $params
	 */
	public function addQueryParams( array $params );
	
	public function getGPParam( $name, $unsetValue = NULL );
	public function getPGParam( $name, $unsetValue = NULL );
	
	/**
	 * Get file info array by name
	 *
	 * @param string $name
	 *
	 * @return mixed value of queried parameter or NULL if value is not set
	 */
	public function getFileInfo( $name, $unsetValue = NULL );
	
	/**
	 * Get server info by name
	 *
	 * @param string $name
	 *
	 * @return mixed value of queried parameter or NULL if value is not set
	 */
	public function getServerInfo( $name, $unsetValue = NULL );
	
	public function rewrite( $newUrl );
	
	/**
	 * get domain record
	 * 
	 * @return null|RCDomain
	 */
	public function getDomainRecord();
	
	public function setDomainRecord( RCDomain $domainRecord );
	
	/**
	 * get domain group record
	 * 
	 * @return null|RCDomainGroup
	 */
	public function getDomainGroupRecord();
	
}