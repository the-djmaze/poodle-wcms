<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Base64
{

	public static function encode($data, $chunklen = null, $end = "\r\n")
	{
		return 0 < $chunklen
			? chunk_split(base64_encode($data), $chunklen, $end)
			: base64_encode($data);
	}

	public static function decode($data, $strict = false)
	{
		return base64_decode($data, $strict);
	}

	/*
	 * RFC 4648 §4 'Table 2: The "URL and Filename safe" Base 64 Alphabet'
	 *   - instead of +
	 *   _ instead of /
	 *   No padded =
	 */
	public static function urlEncode($data)
	{
		if (is_array($data)) {
			return array_map(static::class . '::' . __FUNCTION__, $data);
		}
		return strtr(rtrim(base64_encode($data),'='), '+/', '-_');
	}

	public static function urlDecode($data, $strict = false)
	{
		if (is_array($data)) {
			return array_map(static::class . '::' . __FUNCTION__, $data);
		}
		return base64_decode(strtr($data, '-_', '+/'), $strict);
	}

	public static function urlVerify($data)
	{
		return (bool) preg_match('/^[a-zA-Z0-9_-]+$/D', $data);
	}

	public static function verify($data)
	{
		return (bool) preg_match('/^[a-zA-Z0-9+/]+=*$/D', $data);
	}

}
