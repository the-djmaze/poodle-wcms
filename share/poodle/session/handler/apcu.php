<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	APC is not the best approach (it will fail eventualy)
	Yet, it is an alternative when memcache and tmpfs are not available.
*/

namespace Poodle\Session\Handler;

class APCu extends \Poodle\Session\Handler
{
	protected
		$prefix;

	function __construct($config)
	{
		if (!function_exists('apcu_store')) {
			throw new \Exception('APCu not loaded');
		}
		$this->prefix = $_SERVER['HTTP_HOST'].\Poodle::$URI_BASE.'/sessions/';
	}

	#
	# Handler functions
	#

	public function destroy($id)
	{
		parent::destroy($id);

		$ids = apcu_exists($this->prefix) ? apcu_fetch($this->prefix) : array();
		unset($ids[$id]);
		apcu_store($this->prefix, $ids, 0);

		return apcu_delete($this->prefix . $id);
	}

	public function gc($maxlifetime)
	{
		parent::gc($id);

		$ids = apcu_exists($this->prefix) ? apcu_fetch($this->prefix) : array();
		$count = count($ids);
		foreach ($ids as $id => $timeout) {
			if (time() > $timeout) {
				unset($ids[$id]);
				apcu_delete($this->prefix . $id);
			}
		}
		apcu_store($this->prefix, $ids, 0);
		return $count - count($ids);
	}

	public function read($id)
	{
		$key = $this->prefix . $id;
		return apcu_exists($key) ? apcu_fetch($key) : '';
	}

	public function write($id, $data)
	{
		parent::write($id, $data);

		$ids = apcu_exists($this->prefix) ? apcu_fetch($this->prefix) : array();
		$ids[$id] = time()+$this->timeout;
		apcu_store($this->prefix, $ids, 0);

		return apcu_store($this->prefix . $id, $data, $this->timeout);
	}

}
