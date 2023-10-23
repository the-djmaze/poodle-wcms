<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class JSON
{

	public static function encode($data, $options = 0, $depth = 512)
	{
		return json_encode($data, $options | JSON_THROW_ON_ERROR, $depth);
	}

	public static function decode($data, $options = 0, $depth = 512)
	{
		if ($options & JSON_BIGINT_AS_STRING && defined('JSON_C_VERSION') && PHP_INT_SIZE > 4) {
			/**
			 * When large ints should be treated as strings, not all servers support that.
			 * So we must manually detect large ints and convert them to strings.
			 */
			$data = preg_replace('/:\s*(-?\d{'.(strlen(PHP_INT_MAX)-1).',})/', ': "$1"', $data);
			$options ^= JSON_BIGINT_AS_STRING;
		}
		return json_decode($data, $options & JSON_OBJECT_AS_ARRAY, $depth, $options | JSON_THROW_ON_ERROR);
	}

}
