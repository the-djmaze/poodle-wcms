<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Result;

class Error extends \Exception /* extends \ErrorException */
{
	public function __construct($number = 0, $message = '', \Exception $previous = NULL)
	{
		parent::__construct($message, $number, $previous);
	}
}
