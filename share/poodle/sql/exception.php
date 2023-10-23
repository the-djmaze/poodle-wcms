<?php

namespace Poodle\SQL;

class Exception extends \Exception
{

	protected $query;

	# Redefine the exception so message isn't optional
	function __construct($message, $code=0, $query=null)
	{
		parent::__construct($message, $code);
		$this->query = $query;
	}

	final function getQuery() { return $this->query; }

//	function __toString() {}

}
