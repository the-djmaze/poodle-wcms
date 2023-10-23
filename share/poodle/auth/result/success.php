<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Result;

class Success
{
	protected
		$user;

	public function __construct($user)
	{
		$this->user = $user;
	}

	public function __get($key)
	{
		if ('user' === $key) return $this->user;
		return null;
	}
}
