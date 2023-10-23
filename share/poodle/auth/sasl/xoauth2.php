<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\SASL;

class XOAUTH2 extends \Poodle\Auth\SASL
{

	public function authenticate(string $username, string $token, ?string $authzid = null) : string
	{
		return $this->encode("user={$username}\x01auth=Bearer {$token}\x01\x01");
	}

	public static function isSupported(string $param) : bool
	{
		return false;
	}

}
