<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#sasl
*/

namespace Poodle\XMPP\Extensions;

use \Poodle\XMPP\XMLNode;

class SASL extends \Poodle\XMPP\Extension
{
	const
		NS = 'urn:ietf:params:xml:ns:xmpp-sasl';

	protected
		$SASL;

	/**
	 * 6.4.1 https://xmpp.org/rfcs/rfc6120.html#sasl-process-stream
	 * 6.4.2 https://xmpp.org/rfcs/rfc6120.html#sasl-process-neg-initiate
	 */
	public function mechanisms(XMLNode $node)
	{
		// Try to authenticate
		foreach ($node->children as $mechanism) {
			switch ($mechanism->value)
			{
			case 'PLAIN':
			case 'SCRAM-SHA-1':
				$this->client->log("AUTH {$mechanism->value} {$this->client->getUserAddress()}", \Poodle\XMPP\Client::LOG_NOTICE);
				$this->SASL = \Poodle\Auth\SASL::factory($mechanism->value);
				$this->SASL->base64 = true;
				break;
			case 'SCRAM-SHA-1-PLUS':
				break;
			}

			if ($this->SASL) {
				$this->client->send('<auth xmlns="'.static::NS .'" mechanism="'.$mechanism->value.'">'
					. $this->SASL->authenticate($this->client->getUsername(), $this->client->getPassphrase())
					. '</auth>'
					, true);
				break;
			}
		}
	}

	/**
	 * 6.4.3 https://xmpp.org/rfcs/rfc6120.html#sasl-process-neg-challengeresponse
	 */
	public function challenge(XMLNode $node)
	{
		$this->client->send('<response xmlns="urn:ietf:params:xml:ns:xmpp-sasl">'
			. $this->SASL->challenge($node->value)
			. '</response>', true);
	}

	/**
	 * 6.4.4 https://xmpp.org/rfcs/rfc6120.html#sasl-process-neg-abort
	 */
	public function abort(XMLNode $node)
	{
		$this->client->send('<abort xmlns="urn:ietf:params:xml:ns:xmpp-sasl"/>', true);
	}

	/**
	 * 6.4.5 https://xmpp.org/rfcs/rfc6120.html#sasl-process-neg-failure
	 * 6.5   https://xmpp.org/rfcs/rfc6120.html#sasl-errors
	 */
	public function failure(XMLNode $node)
	{
		$errors = array();
		foreach ($node->children as $child) {
			$errors[] = ('text' === $child->name) ? $child->value : $child->name;
		}
		throw new \Exception('SASL failure: '.implode(', ', $errors));
	}

	/**
	 * 6.4.6 https://xmpp.org/rfcs/rfc6120.html#sasl-process-neg-success
	 */
	public function success(XMLNode $node)
	{
		if (false === $this->SASL->verify($node->value)) {
			throw new \Exception('Invalid serverSignature');
		}
		$this->client->start();
	}

}
