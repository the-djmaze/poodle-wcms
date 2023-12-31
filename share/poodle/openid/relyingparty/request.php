<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\OpenID\RelyingParty;

class Request_Exception extends \Exception {}

class Request extends \Poodle\OpenID\RelyingParty
{
	protected
		$anonymous = false;

	const
		MODE_ASSOCIATE  = 'associate',            // http://openid.net/specs/openid-authentication-2_0.html#rfc.section.8
		MODE_CHECKID_I  = 'checkid_immediate',    // http://openid.net/specs/openid-authentication-2_0.html#rfc.section.9
		MODE_CHECKID_S  = 'checkid_setup',        // http://openid.net/specs/openid-authentication-2_0.html#rfc.section.9
		MODE_CHECK_AUTH = 'check_authentication'; // http://openid.net/specs/openid-authentication-2_0.html#rfc.section.11.4.2.1

	function __construct(\Poodle\OpenID\Provider\Endpoint $endpoint)
	{
		$this->endpoint = $endpoint;
		$this->message  = new \Poodle\OpenID\Message($endpoint->preferredNamespace());
	}

	public function addNamespace($uri, $alias)
	{
		if ($this->endpoint->supportsType($uri))
		{
			return $this->message->addNamespace($uri, $alias);
		}
		if (\Poodle\OpenID\Extensions\SREG::NS_1_1 === $uri) {
			return $this->addNamespace(\Poodle\OpenID\Extensions\SREG::NS_1_0, $alias);
		}
		if (\Poodle\OpenID\Extensions\SREG::NS_1_0 === $uri && !$this->endpoint->supportsType(\Poodle\OpenID\Extensions\AX::NS_1_0) && $this->endpoint->isOpenIDv1()) {
			return $this->message->addNamespace($uri, $alias);
		}
	}

}
