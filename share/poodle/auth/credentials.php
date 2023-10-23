<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth;

class Credentials
{
	protected
		$identity_id, // Integer or SASL authzid
		$claimed_id,  // String  or SASL authcid
		$passphrase,
		$algo,
		$info,

		$hash_passphrase = true,
		$hash_claimed_id = true;

	function __construct($identity, $claimed_id, $passphrase = null)
	{
		if ($identity instanceof \Poodle\Identity) {
			$identity = $identity->id;
		}
		$this->identity_id = (int) $identity;
		$this->claimed_id  = $claimed_id;
		$this->passphrase  = $passphrase;
	}

	function __get($k)
	{
		if ('info' === $k && !$this->info && $info = parse_url($this->claimed_id)) {
			return empty($info['host']) ? null : $info['host'];
		}
		if (property_exists($this, $k)) {
			return $this->$k;
		}
	}

	function __set($k, $v)
	{
		if (property_exists($this, $k)) {
			if ($v && !$this->$k && ('hash_passphrase' === $k || 'hash_claimed_id' === $k)) {
				throw new \Exception(substr($k,5) . ' already hashed or not allowed');
			}
			$this->$k = $v;
		}
	}

	public function hashClaimedID()
	{
		if ($this->hash_claimed_id && $this->claimed_id) {
			$this->claimed_id = \Poodle\Auth::secureClaimedID($this->claimed_id);
			$this->hash_claimed_id = false;
		}
	}

	public function hashPassphrase()
	{
		if ($this->hash_passphrase && $this->passphrase) {
			$this->passphrase = \Poodle\Auth::hashPassphrase($this->passphrase, $this->algo);
			$this->hash_passphrase = false;
		}
	}

}
