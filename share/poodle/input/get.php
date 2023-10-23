<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Input;

class GET extends \Poodle\Input
{
	# ArrayAccess
	public function offsetGet($k) { return ($this->offsetExists($k) ? parent::offsetGet($k) : null); }

	# Poodle
	public function keys (...$args) { return array_keys($args ? self::_get($args) : $this->getArrayCopy()); }
	public function exist(...$args) { return !is_null(self::_get($args)); }
	public function bit  (...$args) { $v = self::_get($args); return \preg_match('#^[01]$#', $v) ? $v : null; }
	public function bool (...$args) { $v = self::_get($args); return (bool) \filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE); }
	public function float(...$args) { return self::str2float(self::_get($args)); }
	public function int  (...$args) { $v = self::_get($args); return (\preg_match('#^-?\d+$#', $v) ? (int)$v : null); }
	public function map  (...$args) {
		$v = self::_get($args);
		return \is_array($v) ? new static($v) : null;
	}
	public function raw  (...$args) { return self::_get($args); }
	public function txt  (...$args) { return self::strip(self::_get($args)); }
	public function text (...$args) { return self::strip(self::_get($args)); }
	public function uint (...$args) { $v = self::_get($args); return (\ctype_digit((string)$v) ? (int)$v : null); }

	# HTML5 form fields
	public function date(...$args)          { return self::asDate(self::_get($args)); }
	public function datetime(...$args)      { return self::asDateTime(self::_get($args)); }
	public function datetime_local(...$args){ return self::asDateTime(self::_get($args), true); }
	public function email(...$args)         { $v = self::_get($args); return (true===self::validateEmail($v) ? self::lcEmail($v) : null); }
	public function month(...$args)         { return self::asDateFromMonth(self::_get($args)); }
	public function week(...$args)          { return self::asDateFromWeek(self::_get($args)); }
	public function time(...$args)          { return self::asTime(self::_get($args)); }
	public function color(...$args)         { $v = self::_get($args); return (\preg_match('/^#([0-9A-F]{3}|[0-9A-F]{6}|rgba?\(\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})(?:\s*,\s*(0(?:\.[0-9]*)?))?\s*\)|hsla?\(\s*([0-2]?[0-9]{1,2}|3[0-5][0-9]|360)\s*,\s*([0-9]{1,2}|100)%\s*,\s*([0-9]{1,2}|100)%(?:\s*,\s*(0(?:\.[0-9]*)?))?\s*\))$/i', $v) ? $v : null); }
	public function number(...$args)        { $v = self::_get($args); return (false!==\Poodle\Math::getValid($v) ? new \Poodle\Number($v) : null); }
	public function range(...$args)         { $v = self::_get($args); return (false!==\Poodle\Math::getValid($v) ? new \Poodle\Number($v) : null); }
	public function tel(...$args)           { return self::_get($args); }
	public function url(...$args)           { $v = self::_get($args); return (true===self::validateURI($v) ? $v : null); }

	protected function _get($args)
	{
		if (!$args) { return null; }
		$c = \count($args);
		$v = $this;
		for ($i=0; $i<$c; ++$i) {
			$k = $args[$i];
			if (!\is_string($k) && !\is_int($k)) {
				throw new \InvalidArgumentException("Parameter {$i} is not a string or integer. Type is: ".\gettype($k));
			}
			if (!isset($v[$k])) {
				return null;
			}
			$v = $v[$k];
		}
		return $v;
	}

	public function __toString() { return \Poodle\URI::buildQuery($this); }
}
