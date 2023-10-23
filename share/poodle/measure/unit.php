<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Measure;

abstract class Unit extends \Poodle\Number
{
	protected
		$prefix,
		$postfix; // Like velocity

	protected static
		// SI metric area, length, volume, weight, Ampere, Volt, Watt
		$metric_prefixes = array(
			// symbol => power
			'Y'  => 24,
			'Z'  => 21,
			'E'  => 18,
			'P'  => 15,
			'T'  => 12,
			'G'  => 9,
			'M'  => 6,
			'k'  => 3,
			'h'  => 2,
			'da' => 1,
//			'' => 0,
			'd'  => −1,
			'c'  => −2,
			'm'  => −3,
			'μ'  => −6,
			'n'  => −9,
			'p'  => −12,
			'f'  => −15,
			'a'  => −18,
			'z'  => −21,
			'y'  => −24,
		);

	function __toString()
	{
		if ($this->prefix) {
			$unit = clone $this;
			$unit->div((new \Poodle\Number(10))->pow(static::$metric_prefixes[$this->prefix]));
			$v = $unit->value;
		} else {
			$v = $this->value;
		}
		return "{$v} {$this->prefix}" . $this::BASE . ($this->postfix ? "/{$this->postfix}" : '');
	}

	public static function detect($v)
	{
		if (preg_match('/^([0-9\\.]+)\\s*([KMGTPEZY])?(I?B)(?:\\s*/\\s*([a-z]))?$/D', strtoupper($v), $m)) {
			$unit = new Bytes($m[1]);
			$e = strpos(' KMGTPEZY', $m[2]);
			if (0 < $e) {
				$unit->mul(
					(new \Poodle\Number(('I' === $m[3][0]) ? 1024 : 1000))->pow($e)
				);
				if ('I' === $m[3][0]) {
					$unit->prefix = $m[2] . 'i';
				} else {
					$unit->prefix = $m[2];
				}
			}
			if (!empty($m[4])) {
				$unit->postfix = $m[4];
			}
			return $unit;
		}

		if (preg_match('/^([0-9\\.]+)\\s*([yzafpnμmcdhkMGTPEZY]|da)?(g|m[²2³3]?|L|l|a|A|V|W)(?:\\s*/\\s*([a-z]))?$/D', $v, $m)) {
			switch ($m[3])
			{
			case 'a':
			case 'm²':
			case 'm2':
				$unit = new Ares($m[1]);
				if ('m' === $m[3][0]) {
					$unit->mul(100);
				}
				break;

			case 'A':
				$unit = new Ampere($m[1]);
				break;

			case 'B':
				$unit = new Bytes($m[1]);
				break;

			case 'g':
				$unit = new Grams($m[1]);
				break;

			case 'L':
			case 'l':
			case 'm³':
			case 'm3':
				$unit = new Litres($m[1]);
				if ('m' === $m[3][0]) {
					$unit->mul('0.001');
				}
				break;

			case 'm':
				$unit = new Metres($m[1]);
				break;

			case 'V':
				$unit = new Volt($m[1]);
				break;

			case 'W':
				$unit = new Watt($m[1]);
				break;
			}

			if ($m[2]) {
				$unit->prefix = $m[2];
				$unit->mul((new \Poodle\Number(10))->pow(static::$metric_prefixes[$m[2]]));
			}

			if (!empty($m[4])) {
				$unit->postfix = $m[4];
			}

			return $unit;
		}

		// Maritime
		if (preg_match('/^([0-9\\.]+)\\s*(ftm|M|NM|Nm|nmi)$/D', $v, $m)) {
			$unit = new Metres($m[1]);
			switch ($m[2])
			{
			case 'ftm': $unit->mul('1.8288'); break; // Fathoms
			default:    $unit->mul('1852');   break; // miles
			}
			return $unit;
		}

		// Imperial length
		if (preg_match('/^([0-9\\.]+)\\s*(th|in|ft|yd|ch|fur|mi|lea)$/D', $v, $m)) {
			$unit = new Metres($m[1]);
			switch ($m[2])
			{
			case 'th':  $unit->mul('0.0000254'); break;
			case 'in':  $unit->mul('0.0254'); break;
			case 'ft':  $unit->mul('0.3048'); break;
			case 'yd':  $unit->mul('0.9144'); break;
			case 'ch':  $unit->mul('20.1168'); break;
			case 'fur': $unit->mul('201.168'); break;
			case 'mi':  $unit->mul('1609.344'); break;
			case 'lea': $unit->mul('4828.032'); break;
			}
			return $unit;
		}

		// Imperial Volume
		if (preg_match('/^([0-9\\.]+)\\s*(fl oz|gi|pt|qt|gal)$/D', $v, $m)) {
			$unit = new Litres($m[1]);
			switch ($m[2])
			{
			case 'fl oz': $unit->mul('0.0284130625'); break;
			case 'gi': $unit->mul('0.142065312'); break;
			case 'pt': $unit->mul('0.56826125'); break;
			case 'qt': $unit->mul('1.1365225'); break;
			case 'gal': $unit->mul('4.54609'); break;
			}
			return $unit;
		}
	}

}
