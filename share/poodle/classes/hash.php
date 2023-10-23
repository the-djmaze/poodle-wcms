<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Hash
{
	const
		BCRYPT_DEFAULT_WORK_FACTOR = 10;

	public static
		$PBKDF2_HASH_ALGORITHM = 'sha256',
		$PBKDF2_ITERATIONS = 1000,
		$PBKDF2_SALT_BYTES = 24,
		$PBKDF2_HASH_BYTES = 24;

	public static function algos() : array
	{
		static $algos;
		if (!$algos) {
			$algos = array('bcrypt','pbkdf2');
			if (\is_callable('scrypt') || \is_callable('sodium_crypto_pwhash_scryptsalsa208sha256_str') || \is_callable('\\Sodium\\crypto_pwhash_scryptsalsa208sha256_str')) {
				$algos[] = 'scrypt';
			}
			if (\defined('PASSWORD_ARGON2I') || \is_callable('sodium_crypto_pwhash_str') || \is_callable('\\Sodium\\crypto_pwhash_str')) {
				$algos[] = 'argon2i';
			}
			if (\defined('PASSWORD_ARGON2ID')) {
				$algos[] = 'argon2id';
			}
			if (\is_callable('blake2') || \is_callable('sodium_crypto_generichash') || \is_callable('\\Sodium\\crypto_generichash')) {
				$algos[] = 'blake2';
			}
			$algos += \hash_algos();
			\sort($algos);
		}
		return $algos;
	}

	public static function available(string $algo) : bool
	{
		return (\in_array($algo, self::algos()) || \function_exists($algo));
	}

	public static function file(string $algo, string $filename, $raw=false) : ?string
	{
		if ('blake2' === $algo) {
			return static::blake2_file($filename, $raw);
		}
		if (\in_array($algo, \hash_algos())) {
			return \hash_file($algo, $filename, $raw);
		}
		$algo = $algo.'_file';
		if (\function_exists($algo)) {
			return $algo($filename, $raw);
		}
		return null;
	}

	public static function string(string $algo, string $string, $raw=false) : ?string
	{
		switch ($algo)
		{
		case 'none':
		case '':
			return $string;

		case 'bcrypt':
			return static::bcrypt($string);

		case 'blake2':
			return static::blake2($string, $raw);

		case 'pbkdf2':
			// format: algorithm$iterations$salt$hash
			$salt = \base64_encode(\random_bytes(static::$PBKDF2_SALT_BYTES));
			return static::$PBKDF2_HASH_ALGORITHM
				.'$'.static::$PBKDF2_ITERATIONS
				.'$'.$salt
				.'$'.\base64_encode(\hash_pbkdf2(
					static::$PBKDF2_HASH_ALGORITHM,
					$string,
					$salt,
					static::$PBKDF2_ITERATIONS,
					static::$PBKDF2_HASH_BYTES,
					true
				));

		case 'scrypt':
			try {
				return \Poodle\Crypt\Sodium::pwhash_scryptsalsa208sha256_str(
					$string,
					SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_INTERACTIVE,
					SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_INTERACTIVE
				);
			} catch (\Throwable $e) {}
			return Scrypt::hash($string, Scrypt::OPSLIMIT_INTERACTIVE, Scrypt::MEMLIMIT_INTERACTIVE);

		case 'argon2i':
			if (\defined('PASSWORD_ARGON2I')) {
				return \password_hash($string, PASSWORD_ARGON2I);
			}
			try {
				return \Poodle\Crypt\Sodium::pwhash_str(
					$string,
					SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
					SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
				);
			} catch (\Throwable $e) {}
			break;

		case 'argon2id':
			if (\defined('PASSWORD_ARGON2ID')) {
				return \password_hash($string, PASSWORD_ARGON2ID);
			}
			break;

		default: # sha1, md5, etc.
			if (\in_array($algo, \hash_algos())) {
				return \hash($algo, $string, $raw);
			}
			if (\function_exists($algo)) {
				return $algo($string, $raw);
			}
		}
		return null;
	}

	public static function bcrypt(string $string, int $work_factor = 0) : string
	{
		if (\strlen($string) > 72) {
			\trigger_error('bcrypt $string truncated to 72 characters', E_USER_WARNING);
		}
		if ($work_factor < 4 || $work_factor > 31) {
			$work_factor = self::BCRYPT_DEFAULT_WORK_FACTOR;
		}
		return \password_hash($string, PASSWORD_BCRYPT, array('cost'=>$work_factor));
	}

	public static function blake2(string $string, bool $raw = false, int $size = 64, ?string $key = null) : ?string
	{
		try {
			$hash = \Poodle\Crypt\Sodium::generichash($string, $key, $size);
			return $raw ? $hash : \bin2hex($hash);
		} catch (\Throwable $e) {}
		if (\is_callable('blake2')) {
			return \blake2($string, $size, $key, $raw);
		}
		\trigger_error('BLAKE2 hashing not available', E_USER_WARNING);
		return null;
	}

	public static function blake2_file(string $filename, bool $raw = false, int $size = 64, ?string $key = null) : ?string
	{
		if (64 == $size && \is_callable('blake2_file')) {
			return \blake2_file($filename, $raw);
		}
		$hash = null;
		if ($fp = \fopen($filename, 'rb')) {
			$data = \fread($fp, 4096);
			try {
				$state = \Poodle\Crypt\Sodium::generichash_init($key, $size);
				while (\strlen($data)) {
					\Poodle\Crypt\Sodium::generichash_update($state, $data);
					$data = \fread($fp, 4096);
				}
				$hash = \Poodle\Crypt\Sodium::generichash_final($state, $size);
			} catch (\Throwable $e) {
				\trigger_error('BLAKE2 file hashing not available', E_USER_WARNING);
			}
			\fclose($fp);
		} else {
			\trigger_error('BLAKE2 file hashing failed', E_USER_WARNING);
		}
		return ($raw || !$hash) ? $hash : \bin2hex($hash);
	}

	public static function verify(string $algo, string $string, string $stored_hash, bool $raw=false) : bool
	{
		$hash = false;
		switch ($algo)
		{
		case 'none':
		case '':
			$hash = $string;
			break;

		case 'bcrypt':
	 		return \password_verify($string, $stored_hash);

		case 'blake2':
			$size = \strlen($string);
			if (!$raw) {
				$size /= 2;
			}
			$hash = static::blake2($string, $raw, $size);
			break;

		case 'pbkdf2':
			$params = \explode('$', $stored_hash);
			if (\count($params) !== 4) {
				return false;
			}
			$stored_hash = \base64_decode($params[3]);
			$hash = \hash_pbkdf2($params[0], $string, $params[2], $params[1], \strlen($stored_hash), true);
			break;

		case 'scrypt':
			try {
				return \Poodle\Crypt\Sodium::pwhash_scryptsalsa208sha256_str_verify($stored_hash, $string);
			} catch (\Throwable $e) {}
			return Scrypt::verify($stored_hash, $string);

		case 'argon2i':
			if (\defined('PASSWORD_ARGON2I')) {
				return \password_verify($string, $stored_hash);
			}
			try {
				return \Poodle\Crypt\Sodium::pwhash_str_verify($stored_hash, $string);
			} catch (\Throwable $e) {
			}
			return false;

		case 'argon2id':
			if (\defined('PASSWORD_ARGON2ID')) {
				return \password_verify($string, $stored_hash);
			}
			return false;

		default: # sha1, md5, etc.
			if (\in_array($algo, \hash_algos())) {
				$hash = \hash($algo, $string, $raw);
			} else if (\function_exists($algo)) {
				$hash = $algo($string, $raw);
			}
		}

		return \hash_equals($stored_hash, $hash);
	}

	public static function hmac(string $algo, string $data, string $key, bool $raw=false) : ?string
	{
		if (\in_array($algo, \hash_algos())) {
			return \hash_hmac($algo, $data, $key, $raw);
		}
		// PHP compiled with --disable-hash
		if (\function_exists($algo)) {
			if (\strlen($key) > 64) { $key = $algo($key, true); }
			$key  = \str_pad($key, 64, "\x00");
			$ipad = \str_repeat("\x36", 64);
			$opad = \str_repeat("\x5c", 64);
			return $algo(($key ^ $opad) . $algo(($key ^ $ipad) . $data, true), $raw);
		}
		return null;
	}

	public static function hmac_file(string $algo, string $filename, string $key, bool $raw=false) : ?string
	{
		return \in_array($algo, \hash_algos())
			? \hash_hmac_file($algo, $filename, $key, $raw)
			: null;
	}

	public static function sha1(string $string, bool $raw=false) : string { return self::string('sha1', $string, $raw); }
	public static function md5(string $string,  bool $raw=false) : string { return self::string('md5',  $string, $raw); }

	public static function balloon(string $passphrase, string $salt, int $space_cost = 16, int $time_cost = 20, int $delta = 4, string $algo = 'sha256') : string
	{
		// md5, sha1, sha224, sha256, sha384, sha512

		// Step 1. Expand input into buffer.
		$buf = array(\hash($algo, "0{$passphrase}{$salt}", true));
		for ($s = 1; $s < $space_cost; ++$s) {
			$buf[] = \hash($algo, $s . $buf[$s - 1], true);
		}
		$cnt = \count($buf);

		// Step 2. Mix buffer contents.
		for ($t = 0; $t < $time_cost; ++$t) {
			for ($s = 0; $s < $space_cost; ++$s) {
				// Step 2a. Hash last and current blocks.
				$buf[$s] = \hash($algo, $cnt++ . $buf[($s ? $s : $space_cost) - 1] . $buf[$s], true);
				// Step 2b. Hash in pseudorandomly chosen blocks.
				for ($d = 0; $d < $delta; ++$d) {
					$other = \hexdec(\hash($algo, "{$cnt}{$salt}{$t}{$s}{$d}")) % $space_cost;
					++$cnt;
					$buf[$s] = \hash($algo, "{$cnt}{$buf[$s]}{$buf[$other]}", true);
					++$cnt;
				}
			}
		}

		return \array_pop($buf);
	}

}
