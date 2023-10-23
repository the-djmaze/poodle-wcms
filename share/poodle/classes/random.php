<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Random
{

	public static function bytes($num_bytes)
	{
		return random_bytes($num_bytes);
	}

	/**
	 * Produce a string of length random bytes, chosen from chars.
	 * If $chars is null, the resulting string contains [A-Za-z0-9-_].
	 *
	 * @param integer $length The length of the resulting randomly-generated string
	 * @param string $chars A string of characters from which to choose to build the new string
	 * @return string $result A string of randomly-chosen characters from $chars
	 */
	public static function string($length, $chars = null)
	{
		if (!is_string($chars) || !strlen($chars)) {
			return substr(Base64::urlEncode(random_bytes($length)), 0, $length);
		}

		$popsize = strlen($chars);
		if ($popsize > 256) {
			throw new \InvalidArgumentException('More than 256 characters supplied.');
		}

		$str = random_bytes($length);
		while ($length--) {
			$str[$length] = $chars[ord($str[$length]) % $popsize];
		}

		return $str;
	}

}
