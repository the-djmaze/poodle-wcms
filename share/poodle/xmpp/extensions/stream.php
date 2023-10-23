<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#streams
*/

namespace Poodle\XMPP\Extensions;

use \Poodle\XMPP\XMLNode;

class Stream extends \Poodle\XMPP\Extension
{
	const
		NS = 'http://etherx.jabber.org/streams';

	protected
		$features = array();

	/**
	 * 4.3.2 https://xmpp.org/rfcs/rfc6120.html#streams-negotiation-features
	 */
	public function features(XMLNode $node)
	{
		$this->features = array();
		foreach ($node->children as $cnode) {
			$this->features[$cnode->ns] = $cnode->attributes;
			$this->client->cacheEventNode(
				$cnode,
				('mechanisms' === $cnode->name || $cnode->getChildByName('required'))
			);
		}
	}

	/**
	 * 4.9 https://xmpp.org/rfcs/rfc6120.html#streams-error
	 */
	public function error(XMLNode $node)
	{
//		$this->client->stanza_error($node->parent);
		$errors = array();
		foreach ($node->children as $child) {
			$errors[] = ('text' === $child->name) ? $child->value : $child->name;
		}
		throw new \Exception('XMPP stream error: '.implode(', ', $errors));
	}

}
