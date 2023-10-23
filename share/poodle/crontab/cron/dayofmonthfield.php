<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab\Cron;

class DayOfMonthField extends AbstractField
{
	public function isMatchedDate(\DateTime $date) : bool
	{
		$day = (int) $date->format('j');

		// Check to see if this is the last day of the month
		if ('L' === $this->value) {
			return $day == $date->format('t');
		}

		// Check to see if this is the nearest workday to a particular value
		if (\strpos($this->value, 'W')) {
			// Date is in the weekend?
			if (5 < $date->format('N')) {
				return false;
			}

			$tday = (int) $this->value;
			if ($day !== $tday) {
				// Check if target day is in the weekend
				$tdow = (int) \DateTime::createFromFormat('Y-m-d', $date->format('Y-m-').\substr("0{$tday}", -2))->format('N');
				if (6 === $tdow) {
					// Saturday ? friday : monday
					$tday += (1 < $tday) ? -1 : 2;
				} else if (7 === $tdow) {
					// Sunday ? monday : friday
					$tday += ($date->format('t') > $tday) ? 1 : -2;
				}
			}

			return $day === $tday;
		}

		return static::hasMatch($day, $this->value);
	}

	public function nextMatchDate(\DateTime $date) : bool
	{
		$interval = new \DateInterval('P1D');
		$i = 1 + $date->format('t') - $date->format('j');
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
		*    any value
		,    value list separator
		-    range of values
		/    step values 2+
		1-31
		*/
		$day = '([1-9]|[12][0-9]|3[01])';
		return (bool) \preg_match("@^("
			."(\\*|({$day}-)?{$day})(/([2-9]|[12][0-9]))?"
			."|(({$day}-)?{$day},)+({$day}-)?{$day}"
			."|L"
			."|{$day}W"
		.")$@D", $value);
	}
}
