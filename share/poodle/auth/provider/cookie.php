<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Provider;

class Cookie extends \Poodle\Auth\Provider
{

	protected static function getConfig()
	{
		$cfg = \Poodle::getKernel()->CFG;
		$ac  = $cfg->auth_cookie;
		if (!$ac || !$ac->name) {
			$cfg->set('auth_cookie', 'name', 'member');
			$cfg->set('auth_cookie', 'allow', 0);
			$ac = $cfg->auth_cookie;
		}
		if (!$ac->cryptkey) {
			$cfg->set('auth_cookie', 'cryptkey', sha1(mt_rand().microtime()));
		}
		if (!$ac->compression) {
			$cfg->set('auth_cookie', 'compression', null);
		}
		if (!$ac->cipher) {
			$ciphers = \Poodle\Crypt\Symmetric::listCiphers();
			$cfg->set('auth_cookie', 'cipher', $ciphers[array_rand($ciphers)]);
		}
		// If no timeout defined or timeout invalid: set to 180 days
		if (1 > $ac->timeout) {
			$cfg->set('auth_cookie', 'timeout', 180);
		}
		if (!isset($ac->ip_protection)) {
			$cfg->set('auth_cookie', 'ip_protection', 1);
		}
		if (!isset($ac->samesite)) {
			$cfg->set('auth_cookie', 'samesite', 'Strict');
		}
		if ('asymmetric' === $ac->cipher && empty($ac->cryptkeypair)) {
			$cfg->set('auth_cookie', 'cryptkeypair', \Poodle::dataToJSON(\Poodle\Crypt\Asymmetric::createKeyPair(
				null,
				array(
					'digest_alg' => 'sha256',
					'private_key_bits' => 2048
				)
			)));
		}
		return $ac;
	}

	public function getAction($credentials=array()) {}

	public function authenticate($credentials)
	{
		$ac = static::getConfig();

		# Check if cookie exists
		if (!isset($credentials[$ac->name])) {
			return new \Poodle\Auth\Result\Error(self::ERR_FAILURE, 'No Cookie found');
		}

		try {
			$cookie = explode('.', $credentials[$ac->name], 2);
			if (!is_array($cookie) || 2 != count($cookie)) {
				throw new \Exception('Cookie wrong format');
			}
			$c_salt = \Poodle\Base64::urlDecode($cookie[0]);
			if (!($cookie = \Poodle\Base64::urlDecode($cookie[1]))) {
				throw new \Exception('Cookie decoding failed');
			}

			# Decrypt and validate cookie
			$cookie = json_decode(static::getCryptor($c_salt)->decrypt($cookie), true);
			if (!is_array($cookie) || 3 != count($cookie)) {
				throw new \Exception('Cookie decryption failed');
			}

			# Validate identity_id
			$cookie[0] = (int)$cookie[0];
			if (1 > $cookie[0]) {
				throw new \Exception('credential \'identity_id\' failed', self::ERR_CREDENTIAL_INVALID);
			}

			# Validate client IP
			if ($ac->ip_protection && $_SERVER['REMOTE_ADDR'] !== $cookie[2]) {
				throw new \Exception('credential \'IP\' failed', self::ERR_CREDENTIAL_INVALID);
			}

			# Cookie is correct so check the data
			# Lookup user in the database
			$user = \Poodle\Identity\Search::byID($cookie[0]);
			if (!$user) {
				throw new \Exception('A database record with the supplied identity_id ('.$cookie[0].') could not be found.', self::ERR_IDENTITY_NOT_FOUND);
			}
		} catch (\Exception $e) {
			static::remove();
			return new \Poodle\Auth\Result\Error($e->getCode() ?: self::ERR_FAILURE, $e->getMessage());
		}

		if (\Poodle::getKernel()->SESSION) {
			\Poodle::getKernel()->SESSION->setTimeout($cookie[1]);
		}

		return new \Poodle\Auth\Result\Success($user);
	}

	public static function set()
	{
		$K  = \Poodle::getKernel();
		$ID = $K->IDENTITY;
		if (0 < $ID->id) {
			$ac = static::getConfig();
			if ($ac->allow) {
				$c_salt = random_bytes(8);
				$data = \Poodle::dataToJSON(array(str_pad($ID->id,10,'0',STR_PAD_LEFT), $K->SESSION->timeout(), $_SERVER['REMOTE_ADDR']));
				$data = \Poodle\Base64::urlEncode($c_salt) . '.' . \Poodle\Base64::urlEncode(static::getCryptor($c_salt)->encrypt($data));
				\Poodle\HTTP\Cookie::set($ac->name, $data, array(
					'expires'  => time() + ($ac->timeout * 86400),
					'httponly' => true,
					'secure'   => !empty($_SERVER['HTTPS']),
					'samesite' => $ac->samesite
				));
			}
		}
	}

	public static function remove()
	{
		$ac = static::getConfig();
		\Poodle\HTTP\Cookie::remove($ac->name);
	}

	protected static function getCryptor($c_salt)
	{
		$ac = static::getConfig();

		if ('asymmetric' === $ac->cipher) {
			$key = json_decode($ac->cryptkeypair);
			return new \Poodle\Crypt\Asymmetric(array(
				'public_key'  => $key->public,
				'private_key' => $key->private,
				'compression' => $ac->compression,
			));
		}

		return new \Poodle\Crypt\Symmetric(array(
			'cipher'      => $ac->cipher,
			'passphrase'  => sha1($c_salt . $ac->cryptkey, true),
			'compression' => $ac->compression,
		));
	}
}
