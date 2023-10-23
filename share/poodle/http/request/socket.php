<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\HTTP\Request;

class Socket extends \Poodle\HTTP\Request
{
	public function supportsSSL()
	{
		return function_exists('openssl_open');
	}

	protected function __doRequest(string &$method, string &$request_url, &$body, array $extra_headers)
	{
		$parts = parse_url($request_url);

		// Set a default port.
		$port = 0;
		if (array_key_exists('port', $parts)) {
			$port = $parts['port'];
		} else if ('http' === $parts['scheme'] || 'https' === $parts['scheme']) {
			$parts['port'] = self::getSchemePort($parts['scheme']);
		} else {
			throw new \RuntimeException("Scheme '{$parts['scheme']}' unsupported");
		}

		if (!array_key_exists('path', $parts)) {
			$parts['path'] = '/';
		}

		$headers = array(
			"{$method} {$parts['path']}".(isset($parts['query']) ? "?{$parts['query']}" : '')." HTTP/1.1",
			"Host: ".$parts['host'].($port ? ":".$port : ''),
			"User-Agent: {$this->user_agent}",
			'Connection: Close',
		);
		if ($extra_headers) {
			$headers = array_merge($headers, $extra_headers);
		}
		$headers = implode("\r\n", $headers);
		if (!is_null($body)) {
			if (!stripos($headers,'Content-Type')) {
				$headers .= "\r\nContent-Type: application/x-www-form-urlencoded";
			}
			$headers .= "\r\nContent-Length: ".strlen($body);
		}

		$context = stream_context_create();
		if ('https' === $parts['scheme']) {
			$parts['host'] = 'ssl://'.$parts['host'];
			stream_context_set_option($context, 'ssl', 'verify_peer_name', true);
			if ($this->verify_peer || $this->ca_bundle) {
				stream_context_set_option($context, 'ssl', 'verify_peer', true);
				if ($this->ca_bundle) {
					if (is_dir($this->ca_bundle) || (is_link($this->ca_bundle) && is_dir(readlink($this->ca_bundle)))) {
						stream_context_set_option($context, 'ssl', 'capath', $this->ca_bundle);
					} else {
						stream_context_set_option($context, 'ssl', 'cafile', $this->ca_bundle);
					}
				}
			} else {
				stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
			}
		} else {
			$parts['host'] = 'tcp://'.$parts['host'];
		}

		$errno = 0;
		$errstr = '';

		$sock = stream_socket_client("{$parts['host']}:{$parts['port']}", $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
		if (false === $sock) {
			throw new \RuntimeException($errstr);
		}

		stream_set_timeout($sock, $this->timeout);

		fwrite($sock, $headers . "\r\n\r\n");
		if (!is_null($body)) {
			fwrite($sock, $body);
		}

		# Read all headers
		$chunked = false;
		$response_headers = array();
		$data = rtrim(fgets($sock, 1024)); # read line
		while (strlen($data)) {
			$response_headers[] = $data;
			$chunked |= preg_match('#Transfer-Encoding:.*chunked#i',$data);
			$data = rtrim(fgets($sock, 1024)); # read next line
		}

		$code = explode(' ', $response_headers[0]);
		$code = (int)$code[1];

		# Read body
		$body = '';
		if (is_resource($this->stream)) {
			while (!feof($sock)) {
				if ($chunked) {
					$chunk = hexdec(trim(fgets($sock, 8)));
					if (!$chunk) { break; }
					while ($chunk > 0) {
						$tmp = fread($sock, $chunk);
						fwrite($this->stream, $tmp);
						$chunk -= strlen($tmp);
					}
				} else {
					fwrite($this->stream, fread($sock, 1024));
				}
			}
		} else {
			$max_bytes = $this->max_response_kb * 1024;
			while (!feof($sock) && strlen($body) < $max_bytes) {
				if ($chunked) {
					$chunk = hexdec(trim(fgets($sock, 8)));
					if (!$chunk) { break; }
					while ($chunk > 0) {
						$tmp = fread($sock, $chunk);
						$body .= $tmp;
						$chunk -= strlen($tmp);
					}
				} else {
					$body .= fread($sock, 1024);
				}
			}
		}

		fclose($sock);

		return new $this->result_class($request_url, $code, self::parseHeaders($response_headers), $body);
	}

}
