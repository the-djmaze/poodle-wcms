<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab\Cron;

class DayOfWeekField extends AbstractField
{
	private static $days = array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT');

	public function isMatchedDate(\DateTime $date) : bool
	{
		// Convert text to number
		$value = \strtr(\str_ireplace(static::$days, \range(0,6), $this->value), '7', '0');

		foreach (\explode(',', $value) as $part) {
			// Is it the last weekday of the month?
			if (\strpos($part, 'L')) {
				$last = $date->format('t');
				$tdate = clone $date;
				$tdate->setDate($date->format('Y'), $date->format('n'), $last);
				$ldow = (int) $tdate->format('w');
				$dow = (int) \substr($part, 0, -1);
				$last -= ($ldow < $dow ? 7 : 0) + $ldow - $dow;
				if ($date->format('j') == $last) {
					return true;
				}
			}

			// Is it the Nth weekday of the month?
			else if (\strpos($part, '#')) {
				list($dow, $nth) = \explode('#', $part);
				if ($date->format('w') == $dow
				 && ceil($date->format('j') / 7) == $nth) {
					return true;
				}
			}

			// Handle day of the week values
			else {
				$part = str_replace('-0', '-7', $part);
				$format = (false === \strpos($part, '7')) ? 'w' : 'N';
				if (static::hasMatch($date->format($format), $part)) {
					return true;
				}
			}
		}
		return false;
	}

	public function nextMatchDate(\DateTime $date) : bool
	{
		$interval = new \DateInterval('P1D');
		$i = 1 + \min(6, $date->format('t') - $date->format('j'));
		while (--$i) {
			$date->add($interval);
			if ($this->isMatchedDate($date)) {
				$date->setTime(0, 0, 0);
				return true;
			}
		}
		return false;
	}

	protected function validate(string $value) : bool
	{
		/*
		*	any value
		,	value list separator
		-	range of values
		/	step values
		L	last
		#	Nth (first, second, third, fourth) we don't allow fifth as that is not always true
		0-7
		SUN-SAT
		*/
		$day = '([0-7]|SUN|MON|TUE|WED|THU|FRI|SAT)';
		return (bool) \preg_match("@^("
			."(\\*|({$day}-)?{$day})(/[2346])?"
			."|(({$day}-)?{$day},)+({$day}-)?{$day}"
			."|{$day}L"
			."|{$day}#[1-4]"
		.")$@Di", $value);
	}
}
