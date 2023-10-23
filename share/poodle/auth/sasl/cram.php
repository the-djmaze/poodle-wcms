<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\SASL;

class Cram extends \Poodle\Auth\SASL
{

	function __construct(string $algo)
	{
		$algo = \strtolower($algo);
		if (!\in_array($algo, \hash_algos())) {
			throw new \Exception("Unsupported SASL CRAM algorithm: {$algo}");
		}
		$this->algo = $algo;
	}

	public function authenticate(string $authcid, string $passphrase, ?string $challenge = null) : string
	{
		return $this->encode($authcid . ' ' . \hash_hmac($this->algo, $this->decode($challenge), $passphrase));
	}

	public static function isSupported(string $param) : bool
	{
		return \in_array(\strtolower($param), \hash_algos());
	}

}
