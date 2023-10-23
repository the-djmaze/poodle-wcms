<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Idea based on previous implementation of https://github.com/mtdowling/cron-expression
*/

namespace Poodle\Crontab\Cron;

class Expression
{
	const
		MINUTE  = 0,
		HOUR    = 1,
		DAY     = 2,
		MONTH   = 3,
		WEEKDAY = 4;

	/**
	 * @var array CRON expression fields
	 */
	private $fields = array();

	/**
	 * @var array Order in which to test of cron parts
	 */
	private static $order = array(self::MONTH, self::DAY, self::WEEKDAY, self::HOUR, self::MINUTE);

	private static $macros = array(
		'@yearly'   => '0 0 1 1 *',
		'@annually' => '0 0 1 1 *',
		'@monthly'  => '0 0 1 * *',
		'@weekly'   => '0 0 * * 0',
		'@daily'    => '0 0 * * *',
		'@midnight' => '0 0 * * *',
		'@hourly'   => '0 * * * *'
	);

	/**
	 * @throws InvalidArgumentException if not a valid CRON expression
	 */
	function __construct(string $expression)
	{
		$fields = \explode(' ', isset(static::$macros[$expression]) ? static::$macros[$expression] : $expression);
		if (\count($fields) < 5) {
			throw new \InvalidArgumentException($expression . ' is not a valid CRON expression');
		}

		$this->fields[0] = new MinutesField($fields[0]);
		$this->fields[1] = new HoursField($fields[1]);
		$this->fields[2] = new DayOfMonthField($fields[2]);
		$this->fields[3] = new MonthField($fields[3]);
		$this->fields[4] = new DayOfWeekField($fields[4]);
	}

	/**
	 * Get a field of the CRON expression
	 *
	 * @throws InvalidArgumentException if the position or value is invalid
	 */
	public function getField(int $position) : FieldInterface
	{
		if (0 > $position || 4 < $position) {
			throw new \InvalidArgumentException($position . ' is not a valid position');
		}
		return $this->fields[$position];
	}

	/**
	 * Set a field of the CRON expression
	 *
	 * @throws InvalidArgumentException if the position or value is invalid
	 */
	public function setField(int $position, string $value) : self
	{
		$this->getField($position)->setValue($value);
		return $this;
	}

	/**
	 * Get a next run date relative to the current date or a specific date
	 *
	 * @param string|DateTime $currentDate (optional) Relative calculation date
	 *
	 * @throws RuntimeExpression on too many iterations
	 */
	public function getNextRunDate($currentDate = 'now') : \DateTime
	{
		if ($currentDate instanceof \DateTime) {
			$nextRun = clone $currentDate;
		} else {
			$nextRun = new \DateTime($currentDate ?: 'now');
		}
		$nextRun->setTimezone(new \DateTimeZone(\date_default_timezone_get()));
		$nextRun->setTime($nextRun->format('H'), $nextRun->format('i'), 0);

		// Hop to next matched minute
		$this->fields[0]->nextMatchDate($nextRun);

		// Set a limit of 4 years to skip an (almost) impossible date
		$i = 4;
		while (--$i) {
			foreach (self::$order as $fi => $position) {
				// Get the field object to validate this part
				$field = $this->getField($position);
				// If the field has no match, then start all over next month
				if (!$field->isMatchedDate($nextRun) && (
					!$field->nextMatchDate($nextRun)
					|| (self::WEEKDAY === $position && !$this->getField(self::DAY)->isMatchedDate($nextRun))
				)) {
					$nextRun->setDate($nextRun->format('Y'), $nextRun->format('n')+1, 1);
					$nextRun->setTime(0, 0, 0);
					continue 2;
				}
			}
			return $nextRun;
		}

		throw new \RuntimeException('CRON expression not matched within 4 years');
	}

	public function __toString()
	{
		return \implode(' ', $this->fields);
	}
}
