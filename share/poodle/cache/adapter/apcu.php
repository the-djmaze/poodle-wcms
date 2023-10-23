<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Cache\Adapter;

class APCu extends \Poodle\Cache implements \Poodle\Cache\Interfaces\Adapter
{

	protected
		$prefix;

	function __construct(array $config)
	{
		if (!function_exists('apcu_store')) {
			throw new \Exception('APCu not loaded');
		}
		if (isset($config['key_prefix'])) {
			$this->prefix = $config['key_prefix'];
		} else {
			$this->prefix = $_SERVER['HTTP_HOST'].\Poodle::$URI_BASE.'/cache/';
		}
	}

	public function clear()
	{
		return apcu_clear_cache();
	}

	public function delete($key)
	{
		return apcu_delete($this->prefix . static::fixKey($key));
	}

	public function exists($keys)
	{
		if (is_string($keys)) {
			$keys = $this->prefix . static::fixKey($keys);
		} else if (is_array($keys)) {
			array_walk($keys, function(&$v, $i) {
				if (is_string($v)) {
					$v = $this->prefix . static::fixKey($v);
				} else {
					throw new \InvalidArgumentException('Cache->exists(): $keys['.$i.'] is of invalid type '.gettype($key));
				}
			});
		}
		return apcu_exists($keys);
	}

	public function get($keys)
	{
		if (is_string($keys)) {
			return apcu_fetch($this->prefix . static::fixKey($keys));
		}
		if (is_array($keys)) {
			$ret = array();
			foreach ($keys as $key) {
				if (is_string($key)) {
					$var = apcu_fetch($this->prefix . static::fixKey($key));
					if (false !== $var) {
						$ret[$key] = $var;
					}
				} else {
					throw new \InvalidArgumentException('Cache->get(): $keys['.$i.'] is of invalid type '.gettype($key));
				}
			}
			return $ret;
		}
		throw new \InvalidArgumentException('Cache->get(): $keys is of invalid type '.gettype($keys));
	}

	public function listAll()
	{
		$ret = array();
		$data = apcu_cache_info();
		if ($data) {
			foreach ($data['cache_list'] as $item) {
				if (0 === strpos($item['info'], $this->prefix)) {
					$ret[] = substr($item['info'], strlen($this->prefix));
				}
			}
		}
		return $ret;
	}

	public function mtime($key)
	{
		if ($data = apcu_cache_info()) {
			$key = $this->prefix . static::fixKey($key);
			foreach ($data['cache_list'] as $item) {
				if ($item['info'] === $key) {
					return $item['mtime'];
				}
			}
		}
		return false;
	}

	public function set($key, $var, $ttl=0)
	{
		return apcu_store($this->prefix . static::fixKey($key), $var, $ttl);
	}

	public function isWritable()
	{
		return true;
	}

}
