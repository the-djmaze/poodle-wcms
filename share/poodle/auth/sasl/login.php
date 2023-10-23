<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\SASL;

class Login extends \Poodle\Auth\SASL
{
	protected
		$passphrase;

	public function authenticate(string $username, string $passphrase, ?string $challenge = null) : string
	{
		if ($challenge && 'Username:' !== $this->decode($challenge)) {
			throw new \Exception("Invalid response: {$challenge}");
		}
		$this->passphrase = $passphrase;
		return $this->encode($username);
	}

	public function challenge(string $challenge) : string
	{
		if ($challenge && 'Password:' !== $this->decode($challenge)) {
			throw new \Exception("invalid response: {$challenge}");
		}
		return $this->encode($this->passphrase);
	}

	public static function isSupported(string $param) : bool
	{
		return true;
	}

}
