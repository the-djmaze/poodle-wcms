<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crypt;

class Symmetric extends \Poodle\Crypt
{
	protected
		$cipher,
		$passphrase;

	/**
	 * options:
	 *     cipher
	 *     passphrase
	 *     compression
	 */
	function __construct(array $options = array())
	{
		parent::__construct($options);
		if (!$this->passphrase) {
			throw new \InvalidArgumentException('Salt not set or empty');
		}
		if ($this->cipher && !\function_exists('openssl_encrypt')) {
			throw new \Exception('OpenSSL not installed');
		}
	}

	public static function listCiphers() : array
	{
		$list = array();
		if (\function_exists('openssl_get_cipher_methods')) {
			$list = \openssl_get_cipher_methods();
			$list = \array_diff($list, \array_map('strtoupper',$list));
			// ECB = insecure, GCM not supported
			$list = \array_filter($list, function($v){return !(\strpos($v,'-ecb') || \strpos($v,'-gcm'));});
			\natcasesort($list);
		}
		if (\function_exists('xxtea_encrypt')) {
			$list[] = 'xxtea';
		}
		return $list;
	}

	public function encrypt(string &$data) : string
	{
		# Handle optional compression
		$encrypted = $this->compressor($data);

		if ('xxtea' === $this->cipher) {
			return \xxtea_encrypt($data, $this->passphrase);
		}

		if ($this->cipher && 'scramble' !== $this->cipher) {
			# Get the size of the appropriate local initialization vector
			$ivsz = \openssl_cipher_iv_length($this->cipher);
			# Generate an initialization vector
			$iv = \random_bytes($ivsz);
			# Perform encryption and prepend the IV to the data stream, CBC tamper protected
			return $iv . \openssl_encrypt($encrypted, $this->cipher, $this->passphrase, OPENSSL_RAW_DATA, $iv);
		}

		# Scramble data
		$datasize  = \strlen($encrypted);
		$strongkey = $this->hardenPassphrase();
		$keysize   = \strlen($strongkey);
		$di = $ki  = -1;
		while (++$di < $datasize) {
			if (++$ki >= $keysize) { $ki = 0; }
			$encrypted[$di] = \chr((\ord($encrypted[$di]) + \ord($strongkey[$ki])) % 256);
		}
		return $encrypted;
	}

	public function decrypt(string &$encrypted) : string
	{
		# Decrypt data
		if ('xxtea' === $this->cipher) {
			$data = \xxtea_decrypt($encrypted, $this->passphrase);
		} else if ($this->cipher && 'scramble' !== $this->cipher) {
			# Get the size of the appropriate local initialization vector
			$ivsz = \openssl_cipher_iv_length($this->cipher);
			# Recover the initialization vector
			$iv = \substr($encrypted, 0, $ivsz);
			# Recover the data block and perform decryption
			$data = \openssl_decrypt(\substr($encrypted, $ivsz), $this->cipher, $this->passphrase, OPENSSL_RAW_DATA, $iv);
		} else {
			# Descramble data
			$strongkey = $this->hardenPassphrase();
			$keysize   = \strlen($strongkey);
			$datasize  = \strlen($encrypted);
			$data      = $encrypted;
			$di = $ki  = -1;
			while (++$di < $datasize) {
				if (++$ki >= $keysize) { $ki = 0; }
				$work = (\ord($encrypted[$di]) - \ord($strongkey[$ki]));
				$data[$di] = \chr($work < 0 ? $work + 256 : $work);
			}
		}
		# handle optional decompression
		return $this->compressor($data, true);
	}

	protected function hardenPassphrase() : string
	{
		return \Poodle\Hash::string('sha256', $this->passphrase . $this->passphrase, true);
	}

}
