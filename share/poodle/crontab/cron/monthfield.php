<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab\Cron;

class MonthField extends AbstractField
{
	private static
		$months = array(1=>'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC');

	public function isMatchedDate(\DateTime $date) : bool
	{
		// Convert text to number
		return static::hasMatch($date->format('n'), \str_ireplace(static::$months, \range(1,12), $this->value));
	}

	public function nextMatchDate(\DateTime $date) : bool
	{
		$date->setDate($date->format('Y'), $date->format('n'), 1);
		$interval = new \DateInterval('P1M');
		$i = 13;
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
		/	step values = (2|3|4|6)
		1-12
		*/
		$month = '([1-9]|1[012]|JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)';
		return (bool) \preg_match("@^(({$month},)+{$month}|(\\*|({$month}-)?{$month})(/[2346])?)$@Di", $value);
	}
}
