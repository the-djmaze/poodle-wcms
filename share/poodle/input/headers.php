<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Input;

class Headers extends \Poodle\Input
{

	// Accept-Encoding: gzip, deflate, br
	public static function AcceptEncoding($encoding)
	{
		return (!empty($_SERVER['HTTP_ACCEPT_ENCODING']))
		 && false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], $encoding);
//		 && in_array($encoding, preg_split('/[^a-z]+/', $_SERVER['HTTP_ACCEPT_ENCODING']));
	}

	// Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
	public static function Accept($mime)
	{
		return (!empty($_SERVER['HTTP_ACCEPT']))
		 && false !== stripos($_SERVER['HTTP_ACCEPT'], '*/*')
		 && false !== stripos($_SERVER['HTTP_ACCEPT'], $mime);
	}

}
