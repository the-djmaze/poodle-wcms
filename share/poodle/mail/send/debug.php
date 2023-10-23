<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail\Send;

class Debug extends \Poodle\Mail\Send
{

	# Sends mail using the PHP mail() function.
	public function send()
	{
		$this->prepare($header, $body, self::HEADER_ADD_TO | self::HEADER_ADD_BCC);
		echo htmlentities($header).'<br/><br/>';
		echo htmlentities($body);
		return true;
	}

	public function close() {}

}
