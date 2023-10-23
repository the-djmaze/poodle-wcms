<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Math;

abstract class BcMath extends Base implements MathInterface
{
	const ENGINE = 'bcmath';

	public static function add($l, $r, $d=14) { return static::doMath('add', $l, $r, $d); }
	public static function cmp($l, $r, $d=14) { return bccomp(static::getValid($l), static::getValid($r), $d); }
	public static function div($l, $r, $d=14) { return static::doMath('div', $l, $r, $d); }
	public static function mod($l, $m)        { return bcmod(static::getValid($l), static::getValid($m)); }
	public static function mul($l, $r, $d=14) { return static::doMath('mul', $l, $r, $d); }
	public static function pow($l, $r, $d=14) { return static::doMath('pow', $l, $r, $d); }
	public static function powmod($l, $r, $m, $d=14) { return static::doMath('powmod', $l, $r, $m, $d); }
	public static function sqrt($o, $d=14)    { return static::doMath('sqrt', $o, $d); }
	public static function sub($l, $r, $d=14) { return static::doMath('sub', $l, $r, $d); }

	protected static function doMath($fn, ...$args)
	{
		$fn = 'bc'.$fn;
		foreach ($args as $i => $v) { $args[$i] = static::getValid($v); }
		return static::getValid($fn(...$args));
	}
}
