<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\HTTP;

class Response
{
	public
		$request_uri, # The URI that was passed to the fetcher
		$final_uri;   # The result of following redirects from the request_uri
	protected
		$status,      # The HTTP status code returned from the final_uri
		$headers,     # The headers returned from the final_uri
		$body;        # The body returned from the final_uri

	function __construct($request_uri, $status = null, $headers = null, $body = null)
	{
		$this->request_uri = $request_uri;
		$this->final_uri   = $request_uri;
		$this->status      = (int)$status;
		$this->headers     = is_array($headers) ? $headers : array();
		if (function_exists('gzinflate') && isset($this->headers['content-encoding'])
		 && (false !== stripos($this->headers['content-encoding'], 'gzip'))) {
			$this->body = gzinflate(substr($body,10,-4));
		} else {
			$this->body = $body;
		}
	}

	function __get($k)
	{
		return property_exists($this, $k) ? $this->$k : null;
	}

	public function getHeader($names)
	{
		$names = is_array($names) ? $names : array($names);
		foreach ($names as $n) {
			$n = strtolower($n);
			if (isset($this->headers[$n])) {
				return $this->headers[$n];
			}
		}
		return null;
	}

	public function getRedirectLocation()
	{
		if ($location = $this->getHeader('location')) {
			$uri = is_array($location) ? $location[0] : $location;
			if (!preg_match('#^[a-z][a-z0-9\\+\\.\\-]+://[^/]+#i', $uri)) {
				// no host
				preg_match('#^([a-z][a-z0-9\\+\\.\\-]+://[^/]+)(/[^\\?\\#]*)#i', $this->final_uri, $url);
				if ('/' === $uri[0]) {
					// absolute path
					$uri = $url[1] . $uri;
				} else {
					// relative path
					$rpos = strrpos($url[2], '/');
					$uri  = $url[1] . substr($url[2], 0, $rpos+1) . $uri;
				}
			}
			return $uri;
		}
		return false;
	}

}
