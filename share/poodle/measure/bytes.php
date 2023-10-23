<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Measure;

class Bytes extends Unit
{
	const BASE = 'B';

	function __toString()
	{
		$v = $this->value;
		if ($this->prefix) {
			$e = strpos(' KMGTPEZY', $this->prefix[0]);
			if (0 < $e) {
				if (isset($this->prefix[1]) && 'i' === $this->prefix[1]) {
					$n = new \Poodle\Number(1024);
				} else {
					$n = new \Poodle\Number(1000);
				}
				$unit = clone $this;
				$v = $unit->div($n->pow($e))->value;
			}
		}
		return "{$v} {$this->prefix}" . $this::BASE . ($this->postfix ? "/{$this->postfix}" : '');
	}
}
