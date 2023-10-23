<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Config;

abstract class File
{

	public static function get($filename=null, $raw=false)
	{
		if (!$filename) { $filename = __DIR__.DIRECTORY_SEPARATOR.'config.def'; }
		return $raw ? file_get_contents($filename) : self::parse(file_get_contents($filename));
	}

	public static function parse($data)
	{
//		$tokens = token_get_all($data);

		preg_match_all('#\\$this->_readonly_data\[\'([a-z_]+)\'\](?:\[\'([a-z_]+)\'\])?(?:\[\'([a-z_]+)\'\])?\s*=\s*\'(.*)\'#', $data, $match, PREG_SET_ORDER);
		$config = array();
		foreach ($match as $v)
		{
			if ($v[3]) {
				$config[$v[1]][$v[2]][$v[3]] = $v[4];
			} else if ($v[2]) {
				$config[$v[1]][$v[2]] = $v[4];
			} else {
				$config[$v[1]] = $v[4];
			}
		}
		return $config;
	}

}
