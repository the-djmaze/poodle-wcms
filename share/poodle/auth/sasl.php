<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth;

abstract class SASL
{
	public
		$base64 = false;

	abstract public function authenticate(string $authcid, string $passphrase, ?string $authzid = null);

	public function challenge(string $challenge) : string
	{
		return null;
	}

	public function verify(string $data) : bool
	{
		return false;
	}

	final public static function factory(string $type)
	{
		if (\preg_match('/^([A-Z2]+)(?:-(.+))?$/Di', $type, $m)) {
			$class = __CLASS__ . "\\{$m[1]}";
			if (\class_exists($class)) {
				return new $class($m[2] ?? '');
			}
		}
		throw new \Exception("Unsupported SASL mechanism type: {$type}");
	}

	public static function isSupported(string $type) : bool
	{
		if (\preg_match('/^([A-Z2]+)(?:-(.+))?$/Di', $type, $m)) {
			$class = __CLASS__ . "\\{$m[1]}";
			return \class_exists($class) && $class::isSupported($m[2] ?? '');
		}
		return false;
	}

	final protected function decode(string $data) : string
	{
		return $this->base64 ? \base64_decode($data) : $data;
	}

	final protected function encode(string $data) : string
	{
		return $this->base64 ? \base64_encode($data) : $data;
	}

}
