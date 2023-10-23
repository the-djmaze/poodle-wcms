<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#tls
*/

namespace Poodle\XMPP\Extensions;

use \Poodle\XMPP\XMLNode;

class TLS extends \Poodle\XMPP\Extension
{
	const
		NS = 'urn:ietf:params:xml:ns:xmpp-tls';

	/**
	 * 5.4.2.1 https://xmpp.org/rfcs/rfc6120.html#tls-process-initiate-command
	 */
	public function starttls(XMLNode $node)
	{
		$this->client->send("<starttls xmlns='urn:ietf:params:xml:ns:xmpp-tls'><required /></starttls>", true);
	}

	/**
	 * 5.4.2.2 https://xmpp.org/rfcs/rfc6120.html#tls-process-initiate-failure
	 */
	public function failure(XMLNode $node)
	{
		$this->client->disconnect(false);
		throw new \Exception('XMPP tls failed');
	}

	/**
	 * 5.4.2.3 https://xmpp.org/rfcs/rfc6120.html#tls-process-initiate-proceed
	 */
	public function proceed(XMLNode $node)
	{
		$this->client->log('Switching to TLS', \Poodle\XMPP\Client::LOG_NOTICE);
		$this->client->setCrypto();
	}
}
