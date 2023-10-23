<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab\Cron;

abstract class AbstractField implements FieldInterface
{
	protected
		$value;

	function __construct(string $value)
	{
		$this->setValue($value);
	}

	function __toString()
	{
		return $this->value;
	}

	public function setValue(string $value) : void
	{
		if (!$this->validate($value)) {
			throw new \InvalidArgumentException('Invalid CRON ' . \get_class_basename(static::class) . ' value ' . $value);
		}
		$this->value = $value;
	}

	/**
	 * Validates a CRON expression for a given field
	 */
	abstract protected function validate(string $value) : bool;

	/**
	 * Check to see if datetime part has a match with the field value
	 */
	protected static function hasMatch(int $dtPart, string $value) : bool
	{
		if ('*' === $value || $dtPart == $value) {
			return true;
		}

		foreach (\explode(',', $value) as $part) {
			if ($part === (string) $dtPart) {
				return true;
			}

			// Value has a step (start[-end]/step)
			if (\strpos($part, '/')) {
				list($range, $step) = \explode('/', $part, 2);
				if ('*' === $range) {
					if (0 === $dtPart % $step) {
						return true;
					}
				} else {
					$range = \explode('-', $range, 2);
					$start = (int) $range[0];
					$end = isset($range[1]) ? (int) $range[1] : $dtPart;
					if ($dtPart >= $start && $dtPart <= $end && $dtPart % $step === $start % $step) {
						return true;
					}
				}
			}

			// Value within a range
			else if (\strpos($part, '-')) {
				list($start, $end) = \explode('-', $part, 2);
				if ($dtPart >= $start && $dtPart <= $end) {
					return true;
				}
			}
		}

		return false;
	}
}
