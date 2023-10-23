<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Encrypt
		1. load public keys using addPublicKey()
		2. encrypt($data)
			1. Generates a random envelope_key
			2. Encrypts data symmetrically with AES256 using the envelope_key
			3. Encrypts envelope_key with the public keys
			4. Returns the encrypted data and the encrypted envelope_keys

	Decrypt
		1. load private key using setPrivateKey()
		2. decrypt($sealed_data, $envelope_key)
			1. Decrypts envelope_key with the private key
			2. Decrypts data using the envelope_key
*/

namespace Poodle\Crypt;

class Seal
{
	protected
		$method       = 'AES256',
		$iv           = null,
		$public_keys  = array(),
		$priv_key_id  = null,
		$envelope_key = null;

	function __destruct()
	{
		foreach ($this->public_keys as $public_key) {
			openssl_pkey_free($public_key);
		}
		if ($this->priv_key_id) {
			openssl_pkey_free($this->priv_key_id);
		}
	}

	public function addPublicKey($id, $key)
	{
		if (isset($this->public_keys[$id])) {
			throw new \Exception("Public Key {$id} already in use");
		}
		static::getErrors();
		$resource = openssl_pkey_get_public($key);
		if (!$resource) {
			throw new \Exception("Failed to get Public Key for {$id}: " . static::getErrors());
		}
		$this->public_keys[$id] = $resource;
	}

	public function decrypt($sealed_data)
	{
		$data = null;
		static::getErrors();
		if (!openssl_open($sealed_data, $data, $this->envelope_key, $this->priv_key_id, $this->method, $this->iv)) {
			throw new \Exception("Failed to unseal data: " . static::getErrors());
		}
		return $data;
	}

	public function encrypt($data)
	{
		$iv = random_bytes(32);
		static::getErrors();
		if (!openssl_seal($data, $sealed_data, $env_keys, array_values($this->public_keys), $this->method, $iv)) {
			throw new \Exception("Failed to seal data: " . static::getErrors());
		}

		$envelope_keys = array();
		foreach (array_keys($this->public_keys) as $i => $id) {
			$envelope_keys[$id] = $env_keys[$i];
		}

		return (object) array(
			'data' => $sealed_data,
			'iv'   => $iv,
			'keys' => $envelope_keys
		);
	}

	public function setEnvelopeKey($envelope_key)
	{
		$this->envelope_key = $envelope_key;
	}

	public function setIV($iv)
	{
		$this->iv = $iv;
	}

	public function setPrivateKey($key, $passphrase = null)
	{
		if ($this->priv_key_id) {
			openssl_pkey_free($this->priv_key_id);
		}
		static::getErrors();
		$this->priv_key_id = openssl_pkey_get_private($key, $passphrase);
		if (!$this->priv_key_id) {
			throw new \Exception("Failed to set Private Key: " . static::getErrors());
		}
	}

	protected static function getErrors()
	{
		$errors = array();
		while ($msg = openssl_error_string()) {
			$errors[] = $msg;
		}
		return implode("\n", $errors);
	}

}
