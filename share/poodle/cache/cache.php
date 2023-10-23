<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Cache implements \ArrayAccess
{

	/**
	 * $uri examples:
	 *     'apc:'
	 *     'apcu:'
	 *     'file://'.$config['general']['cache_dir']
	 *     'memcached://$host(:$port)(?servers[]=$host2)'
	 *     'memcached:/path/to/memcached.sock
	 *     'none:'
	 */
	public static function factory($uri = null)
	{
		$cfg = parse_url(strtr($uri, '\\', '/'));
		if (empty($cfg['scheme'])) {
			if ($uri) {
				trigger_error(__CLASS__ . ' invalid uri, using none', E_USER_NOTICE);
			}
			$cfg = array('scheme'=>'none');
		}
		$class = 'Poodle\\Cache\\Adapter\\'.$cfg['scheme'];
		unset($cfg['scheme']);
		if (isset($cfg['query'])) {
			parse_str($cfg['query'], $arr);
			unset($cfg['query']);
			$cfg = array_merge($cfg, $arr);
		}
		return new $class($cfg);
	}

	protected static function fixKey($key)
	{
		// change namespace separator
		return strtr($key, '\\', '/');
	}

	# ArrayAccess
	public function offsetExists($k)  { return $this->exists($k); }
	public function offsetGet($k)     { return $this->get($k); }
	public function offsetSet($k, $v) { $this->set($k, $v); }
	public function offsetUnset($k)   { $this->delete($k); }
}
