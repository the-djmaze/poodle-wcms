<?php

namespace Poodle\SQL;

class ValueRaw /* extends SplString */
{
	public $value;

	function __construct($value)
	{
		$this->value = $value;
	}

	function __toString()
	{
		return $this->value;
	}
}
