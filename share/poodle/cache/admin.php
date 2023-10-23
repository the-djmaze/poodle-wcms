<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Cache;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Cache',
		$allowed_methods = array('GET','POST');

	public function GET()
	{
		$K = \Poodle::getKernel();
		if (isset($_GET['clear'])) {
			$K->CACHE->clear();
			\Poodle::closeRequest($K->L10N['Cache cleared'], 200, \Poodle\URI::admin('/poodle_cache/'));
		}

		if (!$K->CACHE->isWritable()) {
			\Poodle\Notify::error($K->OUT->L10N['Cache not writable']);
		}
		$K->OUT->display('poodle/cache/index');
	}

}
