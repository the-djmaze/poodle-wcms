<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab\Cron;

class MinutesField extends AbstractField
{
	public function isMatchedDate(\DateTime $date) : bool
	{
		return static::hasMatch($date->format('i'), $this->value);
	}

	public function nextMatchDate(\DateTime $date) : bool
	{
		$interval = new \DateInterval('PT1M');
		$i = 60 - $date->format('i');
		while (--$i) {
			$date->add($interval);
			if ($this->isMatchedDate($date)) {
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
		/	step values = (2|3|4|5|6|10|12|15|20|30)
		0-59
		*/
		return (bool) \preg_match('@^(([1-5]?[0-9],)+[1-5]?[0-9]|(\\*|([1-5]?[0-9]-)?[1-5]?[0-9])(/([2-6]|1[025]|[23]0)?)?)$@D', $value);
	}
}
