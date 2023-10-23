<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Array param options are:
		lifetime, path, domain, secure, httponly, samesite
		or:
		expires, path, domain, secure, httponly, samesite
*/

namespace Poodle\HTTP;

abstract class Cookie
{
	const
		OPT_SECURE    = 1,
		OPT_HTTPONLY  = 2,
		OPT_SS_STRICT = 4,
		OPT_SS_LAX    = 8; // allows cross-origin request on <a>, <link> and <form get>

	public static function setStrict($name, $value, $maxage = 0, $path = '', $domain = '')
	{
		static::set($name, $value, $maxage, $path, $domain, empty($_SERVER['HTTPS']) ? 4 : 5);
	}

	public static function set($name, $value, $maxage = 0, $path = '', $domain = '', $options = 0)
	{
		$options = static::getOptions($maxage, $path, $domain, $options);
		setcookie($name, $value, $options);
	}

	public static function getAsHeader($name, $value, array $options = array())
	{
		if (!$name || false !== strpbrk($name, "=,; \t\r\n\013\014")) {
			throw new \InvalidArgumentException('Invalid cookie name');
		}
		if (strlen($value)) {
			// %x21 / %x23-2B / %x2D-3A / %x3C-5B / %x5D-7E
			$parms = array($name.'='.rawurlencode($value));
			if (isset($options['expires'])) {
				$parms[] = 'expires='.gmdate('D, d-M-Y H:i:s T', $options['expires']);
				$parms[] = 'Max-Age='.intval($options['expires'] - time());
			} else if (!empty($options['lifetime'])) {
				$parms[] = 'expires='.gmdate('D, d-M-Y H:i:s T', time() + $options['lifetime']);
				$parms[] = 'Max-Age='.intval($options['lifetime']);
			}
		} else {
			$parms = array($name.'=deleted');
			$parms[] = 'expires='.gmdate('D, d-M-Y H:i:s T', 1);
			$parms[] = 'Max-Age=0';
		}
		if (empty($options['path'])) {
			$parms[] = 'Path=/';
		} else {
			if (!is_string($options['path'])) {
				throw new \InvalidArgumentException('Cookie $options[path] is not a string');
			}
			if (false !== strpbrk($options['path'], "=,; \t\r\n\013\014")) {
				throw new \InvalidArgumentException('Invalid cookie path');
			}
			$parms[] = 'Path='.$options['path'];
		}
		if (!empty($options['domain'])) {
			if (false !== strpbrk($options['domain'], "=,; \t\r\n\013\014")) {
				throw new \InvalidArgumentException('Invalid cookie domain');
			}
			$parms[] = 'Domain='.$options['domain'];
		}
		if (!empty($options['secure'])) {
			$parms[] = 'Secure';
		}
		if (!empty($options['httponly'])) {
			$parms[] = 'HttpOnly';
		}
		if (!empty($options['samesite'])) {
			$parms[] = 'SameSite='.$options['samesite'];
		}
		return 'Set-Cookie: '.implode('; ', $parms);
	}

	public static function remove($name, $path = '', $domain = '', $options = 0)
	{
		static::removeHeader($name);
		if (isset($_COOKIE[$name])) {
			if (is_array($path)) {
				$path['expires'] = 0;
				static::set($name, null, $path);
			} else {
				static::set($name, null, 0, $path, $domain, $options);
			}
		}
	}

	public static function removeHeader($name)
	{
		$removed = false;
		if (!headers_sent()) {
			$cookies = array();
			foreach (headers_list() as $header) {
				if (stripos($header, 'Set-Cookie:') !== false) {
					if (explode('=', trim(explode(':', $header)[1]))[0] === $name) {
						$removed = true;
					} else {
						$cookies[] = $header;
					}
				}
			}
			if ($removed) {
				header_remove('Set-Cookie');
				foreach ($cookies as $cookie) {
					header($cookie, false);
				}
			}
		}
		return $removed;
	}

	public static function replace($name, $value, array $options = array())
	{
		if (static::removeHeader($name)) {
			header(static::getAsHeader($name, $value, $options), false);
		}
	}

	protected static function getOptions($maxage, $path = '', $domain = '', $opt = 0)
	{
		if (is_array($maxage)) {
			$options = $maxage;
			// session cookie param lifetime?
			if (isset($options['lifetime'])) {
				if (!empty($options['lifetime'])) {
					$options['expires'] = time() + $options['lifetime'];
				}
				unset($options['lifetime']);
			}
		} else {
			$options = array();
			if ($maxage) {
				$options['expires'] = time() + max(0, $maxage);
			}
		}
		if ($path) {
			$options['path'] = $path;
		}
		if ($domain) {
			$options['domain'] = $domain;
		}
		if ($opt & static::OPT_SECURE) {
			$options['secure'] = true;
		}
		if ($opt & static::OPT_HTTPONLY) {
			$options['httponly'] = true;
		}
		if ($opt & static::OPT_SS_STRICT) {
			$options['samesite'] = 'Strict';
		} else if ($opt & static::OPT_SS_LAX) {
			$options['samesite'] = 'Lax';
		}
		return $options;
	}

}
