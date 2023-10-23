<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Base32
{
	const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
	protected static $map = array(
		'A' => 0, // ord 65
		'B' => 1,
		'C' => 2,
		'D' => 3,
		'E' => 4,
		'F' => 5,
		'G' => 6,
		'H' => 7,
		'I' => 8,
		'J' => 9,
		'K' => 10,
		'L' => 11,
		'M' => 12,
		'N' => 13,
		'O' => 14,
		'P' => 15,
		'Q' => 16,
		'R' => 17,
		'S' => 18,
		'T' => 19,
		'U' => 20,
		'V' => 21,
		'W' => 22,
		'X' => 23,
		'Y' => 24,
		'Z' => 25, // ord 90
		'2' => 26, // ord 50
		'3' => 27,
		'4' => 28,
		'5' => 29,
		'6' => 30,
		'7' => 31  // ord 55
	);

	public static function random(int $length) : string
	{
		$secret = '';
		while (0 < $length--) {
			$secret .= static::CHARS[random_int(0,31)];
		}
		return $secret;
	}

	public static function encode(string $data, bool $padding = false, int $chunklen = 0, string $end = "\r\n") : string
	{
		$sl = is_string($data) ? strlen($data) : 0;

		$base32 = '';
		$remainder = 0;
		$remainderSize = 0;

		for ($i = 0; $i < $sl; ++$i) {
			$remainder = ($remainder << 8) | ord($data[$i]);
			$remainderSize += 8;
			while ($remainderSize > 4) {
				$remainderSize -= 5;
				$base32 .= static::CHARS[($remainder & (31 << $remainderSize)) >> $remainderSize];
			}
		}

		if ($remainderSize > 0) {
			// remainderSize < 5:
			$base32 .= static::CHARS[($remainder << (5 - $remainderSize)) & 31];
		}

		if ($padding) {
			$base32 .= str_repeat('=', (8 - ceil(($sl % 5) * 8 / 5)) % 8);
		}

		return 0 < $chunklen
			? chunk_split($base32, $chunklen, $end)
			: $base32;
	}

	public static function decode(string $data, bool $strict = false) : ?string
	{
		$data = strtoupper(rtrim($data, "=\x20\t\n\r\0\x0B"));
		$dataSize = strlen($data);
		$buf = 0;
		$bufSize = 0;
		$res = '';
		$charMap = static::$map;
		for ($i = 0; $i < $dataSize; ++$i) {
			$c = $data[$i];
			if (isset($charMap[$c])) {
				$buf = ($buf << 5) | $charMap[$c];
				$bufSize += 5;
				if ($bufSize > 7) {
					$bufSize -= 8;
					$res .= chr(($buf & (0xff << $bufSize)) >> $bufSize);
				}
			} else if ($strict && false === strpos(" \r\n\t", $c)) {
				trigger_error('Base32 string contains unexpected char #'.ord($c)." at offset {$i}");
				return null;
			}
		}
		return $res;
	}

	public static function verify(string $data) : bool
	{
		return (bool) preg_match('/^[A-Z2-7 \\r\\n\\t]+=*$/D', $data);
	}

}
