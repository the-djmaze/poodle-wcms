<?php

namespace Poodle\Crontab\Cron;

interface FieldInterface
{
	/**
	 * Check if the respective value of a DateTime field satisfies a CRON exp
	 */
	public function isMatchedDate(\DateTime $date) : bool;

	/**
	 * When a CRON expression is not satisfied, this method is used to increment
	 * a DateTime object by the unit of the cron field
	 */
	public function nextMatchDate(\DateTime $date) : bool;

	public function setValue(string $value) : void;
}
