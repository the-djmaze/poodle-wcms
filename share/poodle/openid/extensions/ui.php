<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://code.google.com/apis/accounts/docs/OpenID.html
*/

namespace Poodle\OpenID\Extensions;

class UI extends \Poodle\OpenID\Message_Fields
{
	const
		NS_1_0 = 'http://specs.openid.net/extensions/ui/1.0';

	protected
		$valid_keys = array(
			# request
			'mode', // popup | x-has-session
			'icon'  // true
			# response
		);

	function __construct($uri, $alias=null)
	{
		parent::__construct(self::NS_1_0, $alias?$alias:'ui');
	}
}

\Poodle\OpenID\Message::registerNamespaceClass(UI::NS_1_0, 'Poodle\\OpenID\\Extensions\\UI');
