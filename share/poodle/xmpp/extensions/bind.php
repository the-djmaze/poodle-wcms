<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#bind
*/

namespace Poodle\XMPP\Extensions;

use \Poodle\XMPP\XMLNode;

class Bind extends \Poodle\XMPP\Extension
{
	const
		NS = 'urn:ietf:params:xml:ns:xmpp-bind';

	public function bind(XMLNode $node)
	{
		if ('features' === $node->parent->name) {
			if ($node->getChildByName('required')) {
				$resource = $this->client->getResource();
				$resource = $resource ? "<resource>{$resource}</resource>" : "<resource/>";
				$this->client->send(
					new \Poodle\XMPP\Request\IQ('set', '<bind xmlns="'.static::NS .'">'.$resource.'</bind>'),
					true
				);
			}
		}
	}

	public function iq_result(XMLNode $node)
	{
		if ($cnode = $node->getChildByName('jid')) {
			$this->client->setJid($cnode->value);
		}
	}

}
