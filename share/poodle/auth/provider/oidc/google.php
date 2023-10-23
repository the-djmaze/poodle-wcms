<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://developers.google.com/accounts/docs/OpenIDConnect

	https://console.developers.google.com/
	Google doesn't like testing with local IP's, use a domain name
	Error: invalid_request device_id and device_name are required for private IP: 192.168.1.20

	Access Not Configured. The API (Google+ API) is not enabled for your project.
	Please use the Google Developers Console to update your configuration.
	Free quota: 10,000 requests/day
*/

namespace Poodle\Auth\Provider\OIDC;

class Google extends \Poodle\Auth\Provider\OpenIDConnect
{
	const
		ISSUER_URI = 'https://accounts.google.com';
}
