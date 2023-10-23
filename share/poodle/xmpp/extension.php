<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\XMPP;

abstract class Extension
{
	const
		NS = null;

	protected
		$client;

	function __construct(\Poodle\XMPP\Client $client)
	{
		$this->client = $client;
	}
}
