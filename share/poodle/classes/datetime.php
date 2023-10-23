<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class DateTime extends \DateTime implements \JsonSerializable
{
	const
		STRING_FORMAT = 'Y-m-d H:i:s e',
		NUMBER_FORMAT = 'YmdHis',
		JSON_FORMAT   = 'Y-m-d\\TH:i:s.u\\Z',
		// Format types
		GREGORIAN   = 0,
		SOLAR_HIJRI = 1,
		UTC         = 2,
		LOCAL       = 4;

	public $format;

	function __construct($time = null, $timezone = null)
	{
		if ($time instanceof \DateTime) {
			$time = '@' . $time->getTimestamp();
		} else if (is_numeric($time) && 2147483647 >= $time) {
			$time = '@' . $time;
		}
		if ($time && '@' === $time[0]) {
			parent::__construct($time);
			self::setTimezone($timezone ?: 'UTC');
		} else if ($timezone) {
			parent::__construct($time, static::createTimeZone($timezone));
		} else {
			parent::__construct($time);
		}
	}

	public static function createFromFormat($format, $time, $timezone = null)
	{
		$timezone = static::createTimeZone($timezone);
		return new static(parent::createFromFormat($format, $time, $timezone), $timezone);
	}

	protected static function createTimeZone($timezone = null)
	{
		return $timezone instanceof \DateTimeZone ? $timezone : new \DateTimeZone($timezone ?: date_default_timezone_get());
	}

	public function diffTime(\DateTimeInterface $datetime2, $absolute = false)
	{
		return new TimeInterval(parent::diff($datetime2, $absolute));
	}

	public function setTimezone($timezone)
	{
		return parent::setTimezone(static::createTimeZone($timezone));
	}

	public function __toString()
	{
		return $this->format($this->format ?: static::STRING_FORMAT);
	}

	public function jsonSerialize()
	{
		return $this->format(static::JSON_FORMAT, static::UTC);
	}

	public function getTransitionsBetween(\DateTimeInterface $date)
	{
		$st = $this->getTimestamp();
		$et = $date->getTimestamp();
		if ($st > $et) {
			$et = $st;
			$st = $date->getTimestamp();
		}
		$transitions = $this->getTimezone()->getTransitions($st, $et);
		return empty($transitions[1]) ? false : array_slice($transitions, 1);
	}

	public function asNumber($microseconds = false)
	{
		return new Number($this->format(static::NUMBER_FORMAT . ($microseconds ? '.u' : ''), static::UTC));
	}

	// As 'yyyymmddhhiiss.u', example '20170831235958'
	public static function createFromNumber($time)
	{
		if (preg_match('/^([0-9]{1,14})(\\.[0-9]*)?/', $time, $m)) {
			$time = str_pad($m[1], 14, '0') . '.' . (isset($m[2]) ? $m[2] : '0');
			return static::createFromFormat('YmdHis.u', $time, 'UTC');
		}
	}

	public function format($format, $type = 0)
	{
		if ($type & static::UTC || $type & static::LOCAL) {
			$dt = clone $this;
			$dt->setTimezone($type & static::LOCAL ? null : 'UTC');
			return ($type & 1) ? $dt->formatSolarHijri($format) : $dt->format($format);
		}
		return ($type & 1) ? $this->formatSolarHijri($format) : parent::format($format);
	}

	protected static
		// jan, feb, mar, apr, may, jun, jul, aug, sep, okt, nov, dec
		$G_DAYS = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31),
		// far, ord, kho, tir, mor, sha, meh, aba, aza, dey, bah, esf
		$SH_DAYS = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29),
		$SH_MONTHS = array('Farvardin','Ordibehesht','Khordad','Tir','Mordad','Shahrivar','Mehr','Aban','Azar','Dey','Bahman','Esfand');

	public function formatSolarHijri($format)
	{
		$year  = parent::format('Y');
		$month = parent::format('m') - 1;
		$days  = parent::format('d');

		// add leap day?
		if (1 < $month && parent::format('L')) {
			++$days;
		}
		// add previous months
		while ($month--) {
			$days += static::$G_DAYS[$month];
		}

		$year -= 622;
		if ($days > 79) {
			$days -= 79;
			$month = 0;
			++$year;
			$day = $days;
		} else {
			$month = 9;
			$days += (3 === $year % 4 ? 11 : 10);
			$day = $days;
			$m = $month;
			while (1) {
				$days += static::$SH_DAYS[--$m];
				if (1 > $m) {
					break;
				}
			}
		}

		while (1) {
			$mdays = static::$SH_DAYS[$month++];
			if ($day > $mdays && 12 > $month) {
				$day -= $mdays;
			} else {
				break;
			}
		}

		return parent::format(preg_replace_callback('/(\\\\*)([djmnFMYyz])/', function($m)use($year,$month,$day,$days){
			if (0 === strlen($m[1]) % 2) {
				switch ($m[2])
				{
				case 'd': return $m[1] . str_pad($day, 2, '0', STR_PAD_LEFT);
				case 'j': return $m[1] . $day;
				case 'm': return $m[1] . str_pad($month, 2, '0', STR_PAD_LEFT);
				case 'n': return $m[1] . $month;
				case 'F': return $m[1] . addcslashes(static::$SH_MONTHS[$month-1], 'a..zA..Z');
				case 'M': return $m[1] . addcslashes(substr(static::$SH_MONTHS[$month-1], 0, 3), 'a..zA..Z');
				case 'Y': return $m[1] . $year;
				case 'y': return $m[1] . substr($year, -2);
				case 'z': return $m[1] . $days;
				}
			}
			return $m[0];
		}, $format));
	}

	public function alter($mod)
	{
		if (!($mod instanceof \DateInterval)) {
			$mod = \DateInterval::createFromDateString($mod);
		}
		$dt = clone $this;
		if (($mod->y || $mod->m) && 0 < ($days = $this->format('j')-28)) {
			// Prevent month shifting, like:
			// jan 30 +1 month = mar 2 => set to feb 28/29
			// jan 31 +3 month = may 1 => set to apr 30
			// feb 29 +1 year  = mar 1 => set to feb 28
			$dt->sub(new \DateInterval("P{$days}D"));
			$m = $mod->m + (12 * $mod->y);
			$i = new \DateInterval('P'.abs($m).'M');
			$i->invert = ($m < 0 || $mod->invert) ? 1 : 0;
			$dt->add($i);
			$days = min($days, $dt->format('t') - 28);
			if (0 < $days) {
				$dt->add(new \DateInterval("P{$days}D"));
			}
			$mod->m = $mod->y = 0;
		}
		return $dt->add($mod);
	}

	public function getCSSClasses()
	{
		return 'day ' . mb_strtolower($this->format('l')) . ($this->isToday() ? ' today' : '');
	}

	public function isDST()
	{
		return (bool) $this->format('I');
	}

	public function isToday()
	{
		return $this->isSameDay(new \DateTime());
	}

	public function isSameDay(\DateTimeInterface $datetime)
	{
		return gmdate('Ymd', $this->getTimestamp()) == gmdate('Ymd', $datetime->getTimestamp());
	}

}

/**
 * Date, Time and Floating have no use for DateTimeZone
 * Therefore we set UTC
 */

class DateTimeFloating extends DateTime
{
	const
		STRING_FORMAT = 'Y-m-d H:i:s',
		JSON_FORMAT = 'Y-m-d\\TH:i:s'; // ISO 8601 without UTC difference

	function __construct($time = null)
	{
		parent::__construct($time, 'UTC');
	}

	public static function createFromFormat($format, $time, $timezone = null)
	{
		return parent::createFromFormat($format, $time, 'UTC');
	}

	public function format($format, $type = 0)
	{
		return parent::format($format);
	}

	public function getTimezone()
	{
		return false;
	}

	public function setTimezone($timezone)
	{
		return false;
	}

	public function isDST()
	{
		return false;
	}
}

class Date extends DateTimeFloating
{
	const
		STRING_FORMAT = 'Y-m-d',
		NUMBER_FORMAT = 'Ymd',
		JSON_FORMAT   = 'Y-m-d';

	function __construct($time = null)
	{
		parent::__construct($time);
		parent::setTime(0, 0);
	}

	public function add($interval)
	{
		parent::add($interval);
		return parent::setTime(0, 0);
	}

	public function modify($modify)
	{
		parent::modify($modify);
		return parent::setTime(0, 0);
	}

	public function sub($interval)
	{
		parent::sub($interval);
		return parent::setTime(0, 0);
	}

	public function setTime($hour, $minute, $second = 0, $microseconds = 0)
	{
		return $this;
	}

	public function getTimestamp()
	{
		parent::setTime(0, 0);
		return parent::getTimestamp();
	}

	public function setTimestamp($unixtimestamp)
	{
		parent::setTimestamp($unixtimestamp);
		return parent::setTime(0, 0);
	}

	public function asNumber($microseconds = false)
	{
		return parent::asNumber(false);
	}

	// As 'yyyymmdd', example '20170831'
	public static function createFromNumber($time)
	{
		if (preg_match('/^([0-9]{4}(0[1-9]|1[012])([012][1-9]|30|31]))/', $time, $m)) {
			$time = str_pad($m[1], 14, '0', STR_PAD_RIGHT) . '.0';
			return static::createFromFormat('YmdHis.u', $time);
		}
	}
}

class Week extends Date
{
	const
		STRING_FORMAT = 'Y-\\WW',
		NUMBER_FORMAT = 'YW',
		JSON_FORMAT   = 'Y-\\WW';

	function __construct($time = null)
	{
		parent::__construct($time);
		$this->setFirstDayOfWeek();
	}

	public function add($interval)
	{
		parent::add($interval);
		return $this->setFirstDayOfWeek();
	}

	public function modify($modify)
	{
		parent::modify($modify);
		return $this->setFirstDayOfWeek();
	}

	public function sub($interval)
	{
		parent::sub($interval);
		return $this->setFirstDayOfWeek();
	}

	public function setTimestamp($unixtimestamp)
	{
		parent::setTimestamp($unixtimestamp);
		return $this->setFirstDayOfWeek();
	}

	protected function setFirstDayOfWeek()
	{
		return parent::modify('-'.($this->format('N') - 1).' days');
	}

	// As 'yyyyww', example '201752'
	public static function createFromNumber($time)
	{
		if (preg_match('/^([0-9]{4}([0-4][0-9]|5[0-3]))/', $time, $m)) {
			return static::createFromFormat('YW', $m[1]);
		}
	}
}

class Month extends Date
{
	const
		STRING_FORMAT = 'Y-m',
		NUMBER_FORMAT = 'Ym',
		JSON_FORMAT   = 'Y-m';

	function __construct($time = null)
	{
		parent::__construct($time);
		$this->setFirstDayOfMonth();
	}

	public function add($interval)
	{
		parent::add($interval);
		return $this->setFirstDayOfMonth();
	}

	public function modify($modify)
	{
		parent::modify($modify);
		return $this->setFirstDayOfMonth();
	}

	public function sub($interval)
	{
		parent::sub($interval);
		return $this->setFirstDayOfMonth();
	}

	public function setTimestamp($unixtimestamp)
	{
		parent::setTimestamp($unixtimestamp);
		return $this->setFirstDayOfMonth();
	}

	protected function setFirstDayOfMonth()
	{
		return parent::modify('-'.($this->format('j') - 1).' days');
	}

	// As 'yyyymm', example '201712'
	public static function createFromNumber($time)
	{
		if (preg_match('/^([0-9]{4}(0[1-9]|1[012]))/', $time, $m)) {
			return static::createFromFormat('Ymd', "{$m[1]}01");
		}
	}
}

class Time extends DateTimeFloating
{
	const
		STRING_FORMAT = 'H:i:s',
		NUMBER_FORMAT = 'His',
		JSON_FORMAT   = 'H:i:s';

	function __construct($time = null)
	{
		parent::__construct($time);
		parent::setDate(1970, 1, 1);
	}

	public function add($interval)
	{
		parent::add($interval);
		return parent::setDate(1970, 1, 1);
	}

	public function modify($modify)
	{
		parent::modify($modify);
		return parent::setDate(1970, 1, 1);
	}

	public function sub($interval)
	{
		parent::sub($interval);
		return parent::setDate(1970, 1, 1);
	}

	public function setDate($year, $month, $day)
	{
		return $this;
	}

	public function setISODate($year, $week, $day = 1)
	{
		return $this;
	}

	public function getTimestamp()
	{
		parent::setDate(1970, 1, 1);
		return parent::getTimestamp();
	}

	public function setTimestamp($unixtimestamp)
	{
		parent::setTimestamp($unixtimestamp);
		return parent::setDate(1970, 1, 1);
	}

	public function diff($time, $absolute = false)
	{
		$t = (($time->format('H') * 3600) + ($time->format('i') * 60) + $time->format('s'))
		 - (($this->format('H') * 3600) + ($this->format('i') * 60) + $this->format('s'));
		$s = abs($t);
		$i = new TimeInterval('PT'.floor($s / 3600).'H'.floor(($s / 60) % 60).'M'.floor($s % 60).'S');
		$i->f = round(($time->format('u') - $this->format('u')) / 1000000, 6);
		if (0 > $t) {
			$i->f = -$i->f;
			if (!$absolute) {
				$i->invert = 1;
			}
		}
		return $i;
	}

	public function diffTime(\DateTimeInterface $time2, $absolute = false)
	{
		return $this->diff($time2, $absolute);
	}

	// As 'hhmmss.u', example '235958'
	public static function createFromNumber($time)
	{
		if (preg_match('/^([0-9]{1,14})(\\.[0-9]*)?/', $time, $m)) {
			$time = str_pad($m[1], 14, '0', STR_PAD_LEFT) . '.' . (isset($m[2]) ? $m[2] : '0');
			return static::createFromFormat('YmdHis.u', '19700101'.substr($time,8));
		}
	}

	public function getCSSClasses()
	{
		return 'time';
	}

	public function isToday()
	{
		return false;
	}

	public function isSameDay(\DateTimeInterface $datetime)
	{
		return false;
	}

	public function isSameTime(Time $time, $minutes = true, $seconds = true)
	{
		$f = 'H' . ($minutes || $seconds ? 'i' : '') . ($seconds ? 's' : '');
		return gmdate($f, $this->getTimestamp()) == gmdate($f, $time->getTimestamp());
	}
}

class Timestamp extends DateTime
{
	public function __toString()
	{
		return $this->getTimestamp();
	}

	public function jsonSerialize()
	{
		return $this->getTimestamp();
	}
}

class TimeInterval extends \DateInterval
{
	public function __construct($interval)
	{
		parent::__construct('PT0S');
		if (!($interval instanceof \DateInterval)) {
			$interval = new \DateInterval($interval);
		}
		$this->h = $interval->h;
		$this->i = $interval->i;
		$this->s = $interval->s;
		$this->f = $interval->f;
		$this->invert = $interval->invert;
	}

	public static function createFromDateString($time)
	{
		return new self(parent::createFromDateString($time));
	}
}
