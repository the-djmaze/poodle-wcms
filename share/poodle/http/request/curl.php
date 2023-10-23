<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\HTTP\Request;

class CURL extends \Poodle\HTTP\Request
{
	function __construct($result_class=null)
	{
		parent::__construct($result_class);
		$this->reset();
	}

	public function supportsSSL()
	{
		$v = curl_version();
		if (is_array($v)) {
			return in_array('https', $v['protocols']);
		}
		return is_string($v) ? !!preg_match('/OpenSSL/i', $v) : false;
	}

	protected function __doRequest(string &$method, string &$request_url, &$body, array $extra_headers)
	{
		$this->reset();

		$c = curl_init();
		if (false === $c) {
			throw new \RuntimeException("Could not initialize CURL for URL '{$request_url}'");
		}

		$cv = curl_version();
		// php.net/curl_setopt
		curl_setopt_array($c, array(
			CURLOPT_USERAGENT      => $this->user_agent.' '.(is_array($cv) ? 'curl/'.$cv['version'] : $cv),
			CURLOPT_CONNECTTIMEOUT => $this->timeout,
			CURLOPT_TIMEOUT        => $this->timeout,
			CURLOPT_URL            => $request_url,
			CURLOPT_HEADERFUNCTION => array($this, 'fetchHeader'),
			CURLOPT_WRITEFUNCTION  => array($this, is_resource($this->stream) ? 'streamData' : 'fetchData'),
			CURLOPT_SSL_VERIFYPEER => ($this->verify_peer || $this->ca_bundle),
//				CURLOPT_FOLLOWLOCATION => false,       // follow redirects
//				CURLOPT_MAXREDIRS      => 0,           // stop after 0 redirects
		));
//			curl_setopt($c, CURLOPT_ENCODING , 'gzip');
		if (defined('CURLOPT_NOSIGNAL')) {
			curl_setopt($c, CURLOPT_NOSIGNAL, true);
		}
		if ($this->ca_bundle) {
			curl_setopt($c, CURLOPT_CAINFO, $this->ca_bundle);
		}
		if ($extra_headers) {
			curl_setopt($c, CURLOPT_HTTPHEADER, $extra_headers);
		}
		if ('HEAD' === $method) {
			curl_setopt($c, CURLOPT_NOBODY, true);
		} else if ('GET' !== $method) {
			if ('POST' === $method) {
				curl_setopt($c, CURLOPT_POST, true);
			} else {
				curl_setopt($c, CURLOPT_CUSTOMREQUEST, $method);
			}
			if (!is_null($body)) {
				curl_setopt($c, CURLOPT_POSTFIELDS, $body);
			}
		}

		curl_exec($c);

		$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
		if (!$code) {
			$msg = "Error ".curl_errno($c).": ".curl_error($c)." for {$request_url}";
			curl_close($c);
			throw new \RuntimeException($msg);
		}
		curl_close($c);

		$result = new $this->result_class($request_url, $code, self::parseHeaders($this->headers), $this->data);
		$this->reset();
		return $result;
	}

	protected function reset()
	{
		$this->headers = array();
		$this->data = '';
	}

	protected function fetchHeader($ch, $header)
	{
		array_push($this->headers, rtrim($header));
		return strlen($header);
	}

	protected function fetchData($ch, $data)
	{
		$data = substr($data, 0, min(strlen($data), ($this->max_response_kb*1024) - strlen($this->data)));
		$this->data .= $data;
		return strlen($data);
	}

	protected function streamData($ch, $data)
	{
		return fwrite($this->stream, $data);
	}

}
