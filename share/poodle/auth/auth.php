<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Auth
{

	const
		ERR_FAILURE            = 1,
		ERR_IDENTITY_NOT_FOUND = 2, # Failure due to identity not being found.
		ERR_IDENTITY_AMBIGUOUS = 3, # Failure due to identity being ambiguous.
		ERR_CREDENTIAL_INVALID = 4, # Failure due to invalid credential being supplied.
		ERR_UNKNOWN            = 5; # Failure due to unknown reasons.

	public static function secureClaimedID($id)
	{
		return \Poodle\Hash::string('sha256', \mb_strtolower(\mb_substr($id,0,4096)));
	}

	public static function algos()
	{
		$hash = array('bcrypt');
		if (\Poodle\Hash::available('argon2i')) {
			$hash[] = 'argon2i';
		}
		if (\Poodle\Hash::available('argon2id')) {
			$hash[] = 'argon2id';
		}
		return \array_unique(\array_merge(
			array(\Poodle::getKernel()->CFG->auth->default_pass_hash_algo),
			$hash
		));
	}

	protected static function getAlgo($algo)
	{
		$algos = \array_unique(\array_merge(array($algo), static::algos()));
		foreach ($algos as $algo) {
			if ($algo && \Poodle\Hash::available($algo)) {
				return $algo;
			}
		}
		// Not secure but always better then none at all
		return 'sha1';
	}

	public static function generatePassphrase($length=12, $chars='')
	{
		if ($chars)  { $chars = \is_array($chars) ? $chars : \str_split($chars); }
		if (!$chars) { $chars = \range(33, 126); }
		$pass = '';
		\shuffle($chars);
		$l = \count($chars)-1;
		for ($x=0; $x<$length; ++$x) {
			$c = $chars[\mt_rand(0, $l)];
			$pass .= \is_int($c) ? \chr($c) : $c;
		}
		return $pass;
	}

	public static function hashPassphrase($passphrase, $algo=null)
	{
		if ($passphrase && 1024 >= \strlen($passphrase)) {
			$algo = self::getAlgo($algo);
			return $algo.':'.\Poodle\Hash::string($algo, $passphrase);
		}
		return null;
	}

	public static function verifyPassphrase($plain, $hash)
	{
		// No plain passphrase given or deny very long passphrase
		if (!$plain || 1024 < \strlen($plain)) {
			return false;
		}

		// Verify given passphrase
		list($algo, $passphrase) = \explode(':', $hash, 2);
		return ($algo && $passphrase
		 && (\Poodle\Hash::available($algo)
			? \Poodle\Hash::verify($algo, $plain, $passphrase)
			: (\class_exists($algo) && $algo::verify($plain, $passphrase))));
	}

	public static function update($provider, \Poodle\Auth\Credentials $credentials)
	{
		if (!($provider instanceof \Poodle\Auth\Provider)) {
			$provider = \Poodle\Auth\Provider::getById($provider);
		}
		if (!$provider) {
			throw new \Exception('Poodle\Auth::update invalid provider');
		}
		return $provider->updateAuthentication($credentials);
	}

	// Called by \Poodle\Identity->delete()
	public static function onIdentityDelete(\Poodle\Events\Event $event)
	{
		if ($event->target instanceof \Dragonfly\Identity) {
			\Dragonfly::getKernel()->SQL->TBL->auth_identities->delete(array(
				'identity_id' => $event->target->id
			));
		}
	}

}
