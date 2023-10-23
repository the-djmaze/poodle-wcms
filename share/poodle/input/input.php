<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Input extends \ArrayIterator
{

	public function append($i) { throw new \BadMethodCallException(); }

	public static function fixEOL(string $str, string $eol="\n") : string
	{
		return str_replace("\r\n", $eol, $str);
	}

	public static function fixSpaces(string $str) : string
	{
		return preg_replace('#\\p{Zs}#u', ' ', $str);
	}

	/**
	 * http://tools.ietf.org/html/rfc5321#section-4.1.2
	 * While the definition for Local-part is relatively permissive, for
	 * maximum interoperability, a host that expects to receive mail SHOULD
	 * avoid defining mailboxes where the Local-part requires (or uses) the
	 * Quoted-string form or where the Local-part is case-sensitive.
	 */
	public static function lcEmail(string $str) : string
	{
		return mb_strtolower(trim($str));
	}

	public static function asDate($v) : ?\Poodle\Date
	{
		if (preg_match('#^([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01]))#', $v, $m)) {
			return new \Poodle\Date($m[1]);
		}
		return null;
	}

	public static function asDateFromMonth($v) : ?\Poodle\Date
	{
		if (preg_match('#^([0-9]{4}-(0[1-9]|1[0-2]))#', $v, $m)) {
			return new \Poodle\Date($m[1]);
		}
		return null;
	}

	public static function asDateFromWeek($v) : ?\Poodle\Date
	{
		if (preg_match('#^([0-9]{4}-W([0-4][0-9]|5[0-3]))#', $v, $m)) {
			return new \Poodle\Date($m[1]);
		}
		return null;
	}

	// $local=true uses current date_default_timezone
	public static function asDateTime($v, $local=false)
	{
		if (preg_match('#^([0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])[T ]([01][0-9]|2[0-3]):[0-5][0-9](:([0-5][0-9]|60))?)#', $v, $m)) {
			return $local ? new \DateTime($m[1]) : new \Poodle\DateTimeFloating($m[1]);
		}
		return null;
	}

	public static function asTime($v) : ?\Poodle\Time
	{
		if (preg_match('#(([01][0-9]|2[0-3]):[0-5][0-9](:([0-5][0-9]|60)(\\.[0-9]+)?)?)#', $v, $m)) {
			return new \Poodle\Time('1970-01-01 '.$m[1]);
		}
		return null;
	}

	public static function str2float($value, $def=null) : ?float
	{
		return preg_match('#-?[0-9]+(\\.[0-9]+)?$#D', $value) ? (float)$value : $def;
	}

	public static function strip($value, $def='') : ?string
	{
		if (!is_string($value)) { return null; }
		$value = self::fixEOL(trim(self::fixSpaces(strip_tags($value))));
		return strlen($value) ? $value : $def;
	}

	public static function validateEmail($v)
	{
//		filter_var($v, FILTER_VALIDATE_EMAIL)
		return $v ? \Poodle\Security::checkEmail($v) : null;
	}

	public static function validateURI($v)
	{
		return \Poodle\Security::checkURI($v) ?: null;
	}
/*
	protected function a2c($v)
	{
		$c = get_class($this);
		return is_array($v) ? new $c($v) : $v;
	}
	public function offsetGet($i) { return $this->a2c(parent::offsetGet($i)); }
	public function current() { return $this->a2c(parent::current()); }
*/
}
