<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crypt;

class Asymmetric extends \Poodle\Crypt
{
	public
		$public_key,
		$private_key,
		$padding = OPENSSL_PKCS1_OAEP_PADDING;

	/**
	 * options:
	 *     public_key
	 *     private_key
	 *     padding
	 *     passphrase
	 *     compression
	 */
	function __construct(array $options = array())
	{
		parent::__construct($options);
		if ($this->private_key) {
			static::getErrors();
			$this->private_key = openssl_pkey_get_private(
				$this->private_key,
				isset($options['passphrase']) ? $options['passphrase'] : null
			);
			if (!$this->private_key) {
				throw new \Exception("Failed to open private key: " . static::getErrors());
			}
		}
	}

	function __destruct()
	{
		if ($this->private_key) {
			openssl_pkey_free($this->private_key);
		}
	}

	public static function createKeyPair(?string $passphrase = null, array $options = array()) : array
	{
		$options = array_merge(
			array(
				'digest_alg' => 'sha512',
				'private_key_bits' => 4096,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			),
			$options
		);
		static::getErrors();
		// Create the private and public key
		$res = openssl_pkey_new($options);
		if (!$res) {
			throw new \Exception("Create new key failed: " . static::getErrors());
		}
		try {
			// Extract the private key from $res to $privKey
			if (!openssl_pkey_export($res, $privKey, $passphrase)) {
				throw new \Exception ("Export private key failed: " . static::getErrors());
			}
			// Extract the public key from $res to $pubKey
			$pubKey = openssl_pkey_get_details($res);
			if (!$pubKey) {
				throw new \Exception ("Export public key failed: " . static::getErrors());
			}
		} finally {
			openssl_pkey_free($res);
		}
		return (object) array('private' => $privKey, 'public' => $pubKey['key']);
	}

	public static function changeKeyPassphrase($private_key, ?string $old_passphrase = null, ?string $new_passphrase = null)
	{
		static::getErrors();
		$res = openssl_pkey_get_private($private_key, $old_passphrase);
		if (!$res) {
			throw new \Exception("Loading private key failed: " . static::getErrors());
		}
		try {
			if (!openssl_pkey_export($res, $private_key, $new_passphrase)) {
				throw new \Exception ("Passphrase change failed: " . static::getErrors());
			}
		} finally {
			openssl_pkey_free($res);
		}
		return $private_key;
	}

	public function encrypt(string &$data) : string
	{
		if (!is_string($data)) {
			throw new \InvalidArgumentException('Parameter 1 must be string');
		}
		# Encrypt data
		static::getErrors();
		if (!openssl_public_encrypt($this->compressor($data), $encrypted, $this->public_key, $this->padding)) {
			throw new \Exception("Failed to encrypt data: " . static::getErrors());
		}
		return $encrypted;
	}

	public function decrypt(string &$encrypted) : string
	{
		# Decrypt data
		static::getErrors();
		if (!openssl_private_decrypt($encrypted, $data, $this->private_key, $this->padding)) {
			throw new \Exception("Failed to decrypt data: " . static::getErrors());
		}
		# Handle optional decompression
		return $this->compressor($data, true);
	}

	protected static function getErrors() : string
	{
		$errors = array();
		while ($msg = openssl_error_string()) {
			$errors[] = $msg;
		}
		return implode("\n", $errors);
	}

}
