<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://openid.net/specs/openid-simple-registration-extension-1_1-01.html
*/

namespace Poodle\OpenID\Extensions;

class SREG extends \Poodle\OpenID\Message_Fields
{
	const
		NS_1_0 = 'http://openid.net/sreg/1.0',
		NS_1_1 = 'http://openid.net/extensions/sreg/1.1';

	protected
		$valid_keys = array(
			# request
			'required', 'optional', 'policy_url',
			# response
			'country','dob','email','fullname','gender',
			'language','nickname','postcode','timezone'
		);

	function __construct($uri, $alias=null)
	{
		if (self::NS_1_0 !== $uri && self::NS_1_1 !== $uri) $uri = self::NS_1_0;
		parent::__construct($uri, $alias?$alias:'sreg');
	}
}

\Poodle\OpenID\Message::registerNamespaceClass(SREG::NS_1_0, 'Poodle\\OpenID\\Extensions\\SREG');
\Poodle\OpenID\Message::registerNamespaceClass(SREG::NS_1_1, 'Poodle\\OpenID\\Extensions\\SREG');
