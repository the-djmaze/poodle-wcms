<?php

namespace Poodle\SQL;

class Error extends \Error
{
	const
		NO_EXTENSION  = 1,
		NO_CONNECTION = 2,
		NO_DATABASE   = 3;

	# Redefine the exception so message isn't optional
	function __construct($message, $code=0)
	{
		parent::__construct($message, $code);
	}

//	function __toString() {}

}
