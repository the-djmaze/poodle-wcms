<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\XMPP\Extensions;

use \Poodle\XMPP\XMLNode;

class Session extends \Poodle\XMPP\Extension
{
	const
		NS = 'urn:ietf:params:xml:ns:xmpp-session';

	public function session(XMLNode $node)
	{
		if ('features' === $node->parent->name) {
			if (!$node->getChildByName('optional')) {
				$this->client->send(
					new \Poodle\XMPP\Request\IQ('set', '<session xmlns="'.static::NS .'"/>'),
					true
				);
			}
		}
	}

}
