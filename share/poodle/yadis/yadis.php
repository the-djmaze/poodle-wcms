<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://yadis.org/
	http://en.wikipedia.org/wiki/Yadis
*/

namespace Poodle\Yadis;

const
	CONTENT_TYPE = 'application/xrds+xml', // XRDS (yadis) content type
	XRDS_HEADER  = 'X-XRDS-Location';      // Yadis header

/**
 * @param string $uri The URI on which to perform Yadis discovery.
 * @return mixed $obj An instance of \Poodle\Yadis\DiscoveryResult or NULL.
 */
function discover(string $uri, \Poodle\HTTP\Request $request=null)
{
	if (!$request) {
		$request = request();
	}
	// If the fetcher doesn't support SSL, we can't discover on a HTTPS URL.
	if (\Poodle\URI::isHTTPS($uri) && !$request->supportsSSL()) {
		return null;
	}
	$headers  = array('Accept: '.CONTENT_TYPE.', application/xhtml+xml; q=0.6, text/html; q=0.3');
	$response = $request->get($uri, $headers);
	if ($response && !$response->failed && !$response->xrds_uri) {
		// Try to discover the location of the XRDS Document
		$uri = $response->getHeader(XRDS_HEADER);
		if (!$uri && preg_match('#<meta[^>]+http-equiv=["\']X-(?:XRDS|YADIS)-Location["\'][^>]*>#si', $response->body, $m)) {
			$uri = trim(preg_replace('#^.+content=["\'](.+)?["\'].+$#Dsi', '$1', $m[0]));
		}
		if ($uri) {
			$request_uri = $response->request_uri;
			$response    = $request->get($uri, $headers);
			if ($response && !$response->failed) {
				$response->request_uri = $request_uri;
			}
		}
	}
	return $response;
}

function request()
{
	return \Poodle\HTTP\Request::factory('Poodle\\Yadis\\DiscoveryResult');
}

/**
 * Contains the result of performing Yadis discovery on a URI.
 */

class DiscoveryResult extends \Poodle\HTTP\Response
{
	public
		$XRDS;           // Instance of a \Poodle\Yadis\XRDS document parser
	protected
		$content_type,   // The content-type header
		$failed = false, // Did the discovery fail?
		$xrds_uri;       // The URI from which the response text was returned (not set when there was no XRDS document found)

	function __construct(string $request_uri, $status = null, $headers = null, $body = null)
	{
		parent::__construct($request_uri, $status, $headers, $body);
		$this->failed = ($status != 200 && $status != 206);
		$this->content_type = strtolower(preg_replace('#^([^;]+);.*$#Ds','$1',$this->getHeader('content-type')));
		if ($this->isXRDS()) {
			$this->xrds_uri = $final_uri;
		}
	}

	/**
	 * Returns the list of service objects as described by the XRDS document,
	 * if this yadis object represents a successful Yadis discovery.
	 *
	 * @return array $services An array of {@link \Poodle\Yadis\XRDS_Service} objects
	 */
	public function services(iterable $filters = null, int $filter_mode = XRDS::MATCH_ANY) : array
	{
		if (!$this->XRDS && $this->isXRDS()) {
			$this->XRDS = XRDS::parseXML($this->body);
		}
		return $this->XRDS ? $this->XRDS->services($filters, $filter_mode) : array();
	}

	public function isXRDS() : bool
	{
		// If the body should be an XRDS document
		return (CONTENT_TYPE == $this->content_type || ($this->xrds_uri && $this->final_uri != $this->xrds_uri));
	}
}
