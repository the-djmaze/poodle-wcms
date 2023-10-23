<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/extensions/xep-0198.html
*/

namespace Poodle\XMPP\Extensions;

use \Poodle\XMPP\XMLNode;

class SM extends \Poodle\XMPP\Extension
{
	const
		NS = 'urn:xmpp:sm:*';

	public function sm(XMLNode $node)
	{
		if ('features' === $node->parent->name) {
			if ('urn:xmpp:sm:2' === $node->ns) {
//				$this->client->send('<enable xmlns="urn:xmpp:sm:2"/>', $this);
			}
			if ('urn:xmpp:sm:3' === $node->ns) {
//				$this->client->send('<enable xmlns="urn:xmpp:sm:3"/>', $this);
			}
		}
	}

	public function enabled(XMLNode $node)
	{
		$node['id']; // some-long-sm-id
		$node->attributeIsTrue('resume');
	}

	public function failed(XMLNode $node)
	{
		throw new \Exception($node->children[0]->name);
	}

}
