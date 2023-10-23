<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://tools.ietf.org/html/rfc3986
*/

namespace Poodle;

abstract class URI
{

	public static function resolve($uri='', $query=null)
	{
		if ($uri && '/' === $uri[0] && (empty($uri[1]) || '/' !== $uri[1])) {
			$admin_base = preg_replace('#/[^/]+\\.[^/\\.]+$#D', '', \Poodle::$URI_ADMIN);
			$uri = preg_replace('#^('.\Poodle::$URI_ADMIN.'|'.$admin_base.')#', '/admin', $uri);
			$uri = preg_replace('#^('.\Poodle::$URI_INDEX.'|'.\Poodle::$URI_BASE.')#', '', $uri);

			$path = parse_url($uri);
			if (is_file(substr($path['path'],1))) {
				return \Poodle::$URI_BASE.$uri;
			}

			$base = null;
			$s = substr($uri,0,7);
			if ('/admin/' === $s) {
				$uri = substr($uri,6);
				$base = \Poodle::$URI_ADMIN;
			} else
			if ('/media/' === $s) {
				$uri = substr($uri,6);
				$base = \Poodle::$URI_MEDIA;
			}
			return self::index($uri, $query, $base);
		}
		return $uri;
	}

	public static function index($uri='', $query=null, $base=null)
	{
		if ('/admin/'===substr($uri,0,7)) {
			$uri = substr($uri,6);
			$base = \Poodle::$URI_ADMIN;
		}
		if (!is_string($base)) { $base = \Poodle::$URI_INDEX; }

		if (!is_string($uri)) { $uri = $base; }
		else if (strlen($uri) && '/' === $uri[0]) {
			$uri = rtrim($base,'/').($base?preg_replace("#^{$base}#",'/',$uri):$uri);
		} else if ('//' !== substr($uri, 0, 2) && !strpos($uri, '://')) {
			if (strlen($uri)) {
				$uri = rtrim($_SERVER['PHP_SELF'],'/').'/'.$uri;
			} else {
				$uri = $_SERVER['PHP_SELF'];
			}
		}

		preg_match('/^(.*)?(#.*)?$/D',$uri,$m);
		$uri = $m[1];

		if ($query = self::parseQuery($query)) {
			$uri = self::appendArgs($uri, $query);
		}
		$uri .= isset($m[2])?$m[2]:'';

		return str_replace('&amp;', '&', $uri);
	}

	public static function l10n($uri='', $query=null, $language=null)
	{
		static $lng = null;
		if (!$language) {
			if (null === $lng) {
				$K = \Poodle::getKernel();
				if ($K && !empty($K->L10N)) {
					$lng = $K->L10N->multilingual ? $K->L10N->lng : false;
				}
			}
			$language = $lng;
		}
		return self::index($uri, $query, $language ? \Poodle::$URI_INDEX.$language.'/' : null);
	}

	public static function admin($uri='', $query=null)
	{
		return self::index($uri, $query, \Poodle::$URI_ADMIN);
	}

	public static function media($uri='', $query=null)
	{
		return self::index($uri, $query, \Poodle::$URI_MEDIA);
	}

	public static function scheme($uri)
	{
		return preg_match('/^[a-zA-Z]([a-zA-Z0-9\\+\\-\\.]+):/', $uri, $m)
			? strtolower($m[1])
			: null;
	}

	public static function unparse($parsed_url)
	{
		$url = '//';
		if (!empty($parsed_url['scheme']))   { $url = $parsed_url['scheme'] . '://'; }
		if (!empty($parsed_url['user']) || !empty($parsed_url['pass'])) {
			$url .= RFC_3986::authority_userinfo($parsed_url['user'], $parsed_url['pass']);
		}
		if (!empty($parsed_url['host']))     { $url .= $parsed_url['host']; }
		if (!empty($parsed_url['port']))     { $url .= ':'.$parsed_url['port']; }
		if (!empty($parsed_url['path']))     { $url .= $parsed_url['path']; }
		if (!empty($parsed_url['query']))    { $url .= '?'.$parsed_url['query']; }
		if (!empty($parsed_url['fragment'])) { $url .= '#'.$parsed_url['fragment']; }
		return $url;
	}

	public static function buildQuery($data, $enc=PHP_QUERY_RFC3986)
	{
		return http_build_query($data, '', '&', $enc);
	}

	/**
	 * Workaround, because parse_str() converts dots and spaces in variable
	 * names to underscores, as noted on http://php.net/parse_str
	 * and on http://php.net/manual/en/language.variables.external.php
	 */
	public static function parseQuery($str)
	{
		if (is_array($str)) { return $str; }
		// rawurldecode() ?
		$data = array();
		if (is_string($str))
		{
			$parts = explode('&', $str);
			foreach ($parts as $part)
			{
				$pair = explode('=', $part, 2);
				$pair[1] = isset($pair[1]) ? urldecode($pair[1]) : '';
				$p = strpos($pair[0], '[');
				if ($p && preg_match_all('/\\[([^\\[\\]]*)\\]/', $pair[0], $m)) {
					$m = $m[1];
					$v = $pair[1];
					$i = count($m);
					while ($i--) {
						$v = strlen($m[$i]) ? array(urldecode($m[$i]) => $v) : array($v);
					}
					$key = urldecode(substr($pair[0], 0, $p));
					if (!isset($data[$key])) {
						$data[$key] = $v;
					} else {
						$data[$key] = array_merge_recursive($data[$key], $v);
					}
				} else {
					$data[urldecode($pair[0])] = $pair[1];
				}
			}
		}
		return $data;
	}

	public static function login($check=false)
	{
		$https = \Poodle::getKernel()->CFG->auth->https;
		$uri = self::abs();
		if (empty($_SERVER['HTTPS']) && $https && ($https > 1 || POODLE_BACKEND)) {
			$uri = str_replace('http://', 'https://', $uri);
//			$uri = 'https://'.self::host().self::index('login', null, POODLE_BACKEND);
			if ($check) {
				self::redirect($uri, 301);
			}
		}
		return $uri;
	}

	public static function shrink($uri, $len=35)
	{
		$uri = preg_replace('#^([a-z]+?:)?//#i', '', $uri);
		return (strlen($uri) > $len) ? substr($uri,0,round($len*2/3)).'...'.substr($uri,3-round($len/3)) : $uri;
	}

	public static function refresh($uri='', $time=3)
	{
		# Not a HTTP spec but some browsers support it
		header('Refresh: '.(int)$time.'; url='.self::abs($uri));
		\Poodle\Debugger::trigger('HTTP 1.x specs have no "Refresh" header. Some browsers may not support it!', __DIR__, E_USER_NOTICE);
	}

	public static function redirect($uri='', $code=303)
	{
		$code = (int)$code;
		if (!$uri) { $uri = \Poodle::$URI_INDEX; }
		if (!$code) { $code = 303; }

		$uri = self::resolve($uri);

		if (XMLHTTPRequest) {
			\Poodle\HTTP\Status::set(202);
			header('Content-Type: application/json');
			echo json_encode(array(
				'status' => '302',
				'location' => $uri
			));
		} else {
			\Poodle\HTTP\Headers::setLocation($uri, $code);
			echo $uri;
		}
		exit;
	}

	public static function abs_base()
	{
		return self::abs(\Poodle::$URI_BASE);
	}

	/* get absolute uri */
	public static function abs($uri='', $scheme=null)
	{
		if (!$scheme) { $scheme = (empty($_SERVER['HTTPS']) ? 'http' : 'https'); }
		if ('//' === substr($uri, 0, 2)) { $uri = $scheme.':'.$uri; }
		if (!strpos($uri, '://')) {
			$port = empty($_SERVER['HTTPS']) ? 80 : 443;
			if ($port != $_SERVER['SERVER_PORT']) {
				$uri = $_SERVER['SERVER_PORT'].$uri;
			}
			$uri = $scheme.'://'.self::host().$uri;
		}
		return $uri;
	}

	public static function host($strip_lng=false)
	{
		static $host, $host_s;
		if (!$host) {
			$host = $_SERVER['HTTP_HOST'];
//			$host .= $_SERVER['SERVER_PORT'];
			$host_s = (preg_match('#^([a-z]{2})(-[a-z]{2})?\\.#D', $host, $match) ? substr($host, strlen($match[0])) : $host);
		}
		return $strip_lng ? $host_s : $host;
	}

	public static function by_ip()
	{
		static $by_ip = null;
		if (is_null($by_ip)) {
			# IPv6 like http://[::1]/
			$by_ip = (bool)preg_match('#^([0-9\\.]+|\\[[0-9a-f:]+\\])$#i', self::host());
		}
		return $by_ip;
	}

	public static function path()
	{
//		return $_SERVER['REQUEST_PATH'];
		return substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?')?:strlen($_SERVER['REQUEST_URI']));
	}

	public static function appendArgs($uri, array $args)
	{
		$uri_q = parse_url($uri, PHP_URL_QUERY);
		if ($uri_q) { $args = array_merge(self::parseQuery($uri_q), $args); }
		$uri = preg_replace('#\\?.*#','',$uri);
		if ($args) { $uri .= '?'.self::buildQuery($args); }
		return $uri;
	}

	public static function isHTTPS($uri) { return 'https:' === substr($uri, 0, 6); }

}
