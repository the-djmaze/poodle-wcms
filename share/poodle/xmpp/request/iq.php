<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#stanzas-semantics-iq
*/

namespace Poodle\XMPP\Request;

class IQ extends \Poodle\XMPP\Request
{

	const
		STANZA = 'iq',

		TYPE_GET = 'get',
		TYPE_SET = 'set';

	public function __construct($type, $value = '', $to = '')
	{
		$this->type  = $type;
		$this->value = $value;
		$this->to    = $to;
	}

}
