<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://step2.googlecode.com/svn/spec/openid_oauth_extension/latest/openid_oauth_extension.html
*/

namespace Poodle\OpenID\Extensions;

class OAUTH extends \Poodle\OpenID\Message_Fields
{
	const
		NS_1_0 = 'http://specs.openid.net/extensions/oauth/1.0';

	protected
		$valid_keys = array(
			# request
			'consumer', 'scope',
			# response
			'request_token'
		);

	function __construct($uri, $alias=null)
	{
		parent::__construct(self::NS_1_0, $alias?$alias:'oauth');
	}
}

\Poodle\OpenID\Message::registerNamespaceClass(OAUTH::NS_1_0, 'Poodle\\OpenID\\Extensions\\OAUTH');
