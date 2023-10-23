<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Cache\Adapter;

class None extends \Poodle\Cache implements \Poodle\Cache\Interfaces\Adapter
{

	function __construct(array $config)
	{
	}

	public function clear()
	{
	}

	public function delete($key)
	{
		return true;
	}

	public function exists($keys)
	{
		return false;
	}

	public function get($keys)
	{
		return false;
	}

	public function listAll()
	{
		return array();
	}

	public function mtime($key)
	{
		return false;
	}

	public function set($key, $var, $ttl=0)
	{
		return false;
	}

	public function isWritable()
	{
		return false;
	}

}
