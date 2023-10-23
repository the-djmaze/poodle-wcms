<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab\Cron;

class HoursField extends AbstractField
{
	public function isMatchedDate(\DateTime $date) : bool
	{
		return static::hasMatch($date->format('H'), $this->value);
	}

	public function nextMatchDate(\DateTime $date) : bool
	{
		$interval = new \DateInterval('PT1H');
		$i = 24 - $date->format('G');
		while (--$i) {
			$date->add($interval);
			if ($this->isMatchedDate($date)) {
				$date->setTime($date->format('G'), 0, 0);
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
		/	step values = (2|3|4|6|8|12)
		0-23
		*/
		$hour = '(1?[0-9]|2[0-3])';
		return (bool) \preg_match("@^(({$hour},)+{$hour}|(\\*|({$hour}-)?{$hour})(/([23468]|12))?)$@D", $value);
	}
}
