<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crypt;

function defineSodiumConstants($prefix)
{
	foreach (array(
//		'LIBRARY_VERSION',
//		'LIBRARY_MAJOR_VERSION',
//		'LIBRARY_MINOR_VERSION',
		'CRYPTO_AEAD_AES256GCM_KEYBYTES',
		'CRYPTO_AEAD_AES256GCM_NSECBYTES',
		'CRYPTO_AEAD_AES256GCM_NPUBBYTES',
		'CRYPTO_AEAD_AES256GCM_ABYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_KEYBYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_NSECBYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_NPUBBYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_ABYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_IETF_KEYBYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_IETF_NSECBYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_IETF_NPUBBYTES',
		'CRYPTO_AEAD_CHACHA20POLY1305_IETF_ABYTES',
		'CRYPTO_AUTH_BYTES',
		'CRYPTO_AUTH_KEYBYTES',
		'CRYPTO_BOX_SEALBYTES',
		'CRYPTO_BOX_SECRETKEYBYTES',
		'CRYPTO_BOX_PUBLICKEYBYTES',
		'CRYPTO_BOX_KEYPAIRBYTES',
		'CRYPTO_BOX_MACBYTES',
		'CRYPTO_BOX_NONCEBYTES',
		'CRYPTO_BOX_SEEDBYTES',
//		'CRYPTO_KDF_BYTES_MIN',
//		'CRYPTO_KDF_BYTES_MAX',
//		'CRYPTO_KDF_CONTEXTBYTES',
//		'CRYPTO_KDF_KEYBYTES',
//		'CRYPTO_KX_SEEDBYTES',
//		'CRYPTO_KX_SESSIONKEYBYTES',
		'CRYPTO_KX_PUBLICKEYBYTES',
		'CRYPTO_KX_SECRETKEYBYTES',
//		'CRYPTO_KX_KEYPAIRBYTES',
		'CRYPTO_GENERICHASH_BYTES',
		'CRYPTO_GENERICHASH_BYTES_MIN',
		'CRYPTO_GENERICHASH_BYTES_MAX',
		'CRYPTO_GENERICHASH_KEYBYTES',
		'CRYPTO_GENERICHASH_KEYBYTES_MIN',
		'CRYPTO_GENERICHASH_KEYBYTES_MAX',
//		'CRYPTO_PWHASH_ALG_ARGON2I13',
//		'CRYPTO_PWHASH_ALG_ARGON2ID13',
//		'CRYPTO_PWHASH_ALG_DEFAULT',
		'CRYPTO_PWHASH_SALTBYTES',
		'CRYPTO_PWHASH_STRPREFIX',
		'CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE',
		'CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE',
		'CRYPTO_PWHASH_OPSLIMIT_MODERATE',
		'CRYPTO_PWHASH_MEMLIMIT_MODERATE',
		'CRYPTO_PWHASH_OPSLIMIT_SENSITIVE',
		'CRYPTO_PWHASH_MEMLIMIT_SENSITIVE',
		'CRYPTO_PWHASH_SCRYPTSALSA208SHA256_SALTBYTES',
		'CRYPTO_PWHASH_SCRYPTSALSA208SHA256_STRPREFIX',
		'CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_INTERACTIVE',
		'CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_INTERACTIVE',
		'CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_SENSITIVE',
		'CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_SENSITIVE',
		'CRYPTO_SCALARMULT_BYTES',
		'CRYPTO_SCALARMULT_SCALARBYTES',
		'CRYPTO_SHORTHASH_BYTES',
		'CRYPTO_SHORTHASH_KEYBYTES',
		'CRYPTO_SECRETBOX_KEYBYTES',
		'CRYPTO_SECRETBOX_MACBYTES',
		'CRYPTO_SECRETBOX_NONCEBYTES',
		'CRYPTO_SIGN_BYTES',
		'CRYPTO_SIGN_SEEDBYTES',
		'CRYPTO_SIGN_PUBLICKEYBYTES',
		'CRYPTO_SIGN_SECRETKEYBYTES',
		'CRYPTO_SIGN_KEYPAIRBYTES',
		'CRYPTO_STREAM_NONCEBYTES',
		'CRYPTO_STREAM_KEYBYTES',
	) as $const) {
		\define("SODIUM_{$const}", $prefix ? \constant("{$prefix}{$const}") : 0);
	}
}

//if (extension_loaded('sodium')) {
if (\is_callable('sodium_crypto_box')) {
	// PHP 7.2.0
	class Sodium
	{
		public static function __callStatic(string $name, array $arguments)
		{
			// get_extension_funcs('sodium')
			return \call_user_func_array("sodium_crypto_{$name}", $arguments);
		}
		public static function isCallable(string $name) : bool
		{
			return \is_callable("sodium_crypto_{$name}");
		}
	}

//} else if (extension_loaded('libsodium')) {
} else if (is_callable('\\Sodium\\crypto_box')) {
	// PECL
	class Sodium
	{
		public static function __callStatic(string $name, array $arguments)
		{
			// get_extension_funcs('libsodium')
			return \call_user_func_array("Sodium\\crypto_{$name}", $arguments);
		}
		public static function isCallable(string $name) : bool
		{
			return \is_callable("Sodium\\crypto_{$name}");
		}
	}
	defineSodiumConstants('Sodium\\');

} else if (class_exists('ParagonIE_Sodium_Compat')) {
	class Sodium
	{
		public static function __callStatic(string $name, array $arguments)
		{
			return \call_user_func_array("ParagonIE_Sodium_Compat::crypto_{$name}", $arguments);
		}
		public static function isCallable(string $name) : bool
		{
			return \is_callable("ParagonIE_Sodium_Compat::crypto_{$name}");
		}
	}
	defineSodiumConstants('ParagonIE_Sodium_Compat::');

} else {
	class Sodium
	{
		public static function __callStatic(string $name, array $arguments)
		{
			throw new \Error('Sodium extension not installed. Use PHP 7.2 or PECL version.');
		}
		public static function isCallable(string $name) : bool
		{
			return false;
		}
	}
	defineSodiumConstants(false);
}

/*
function Sodium\randombytes_buf($length)
function Sodium\randombytes_random16()
function Sodium\randombytes_uniform($integer)
function Sodium\bin2hex($string)
function Sodium\compare($string)
function Sodium\hex2bin($string_1, $string_2)
function Sodium\increment($string)
function Sodium\add($string_1, $string_2)
function Sodium\library_version_major()
function Sodium\library_version_minor()
function Sodium\memcmp($string_1, $string_2)
function Sodium\memzero(&$reference, $length)
function Sodium\version_string()
*/
