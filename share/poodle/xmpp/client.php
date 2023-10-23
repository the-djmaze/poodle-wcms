<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html
*/

namespace Poodle\XMPP;

class Client extends Stream
{
	const
		NS = 'jabber:client';

	protected
		$jid,
		$requests = array();

	function __construct($config)
	{
		parent::__construct($config);

		$this->extensions[static::NS] = $this;
		foreach (glob(__DIR__ . '/extensions/*.php') as $ext) {
			$ext = basename($ext, '.php');
			$class = "Poodle\\XMPP\\Extensions\\{$ext}";
			$this->extensions[constant("{$class}::NS")] = new $class($this);
		}
	}

	protected function getRequestObject(XMLNode $node)
	{
		$obj = null;
		$id = isset($node['id']) ? $node['id'] : null;
		if ($id && isset($this->requests[$id])) {
			$obj = $this->requests[$id];
			unset($this->requests[$id]);
		}
		return $obj;
	}

	/**
	 * https://xmpp.org/rfcs/rfc6121.html#iq
	 */
	protected function iq(XMLNode $node)
	{
		$rq = $this->getRequestObject($node);
		if ($rq) {
			// result, error
			$rq->{$node['type']}(
				$this,
				isset($node->children[0]) ? $node->children[0] : new XMLNode
			);
		} else if ($obj = $this->getNodeExtension($node->children[0])) {
//			$fn = $node->children[0]->name;
			$fn = "iq_{$node['type']}"; // iq_get, iq_set, iq_result, iq_error
			$obj->$fn($node);
		} else {
/*
			if ('error' === $node['type']) {
				$this->iq_error($node);
			}

			$type = $node['type'];
			if ('result' === $node['type']) {
			}
			foreach ($node->children as $cnode) {
				$this->cacheEventNode($cnode);
			}
*/
		}
	}

	protected function iq_error(XMLNode $node)
	{
		$this->stanza_error($node);
	}

	/**
	 * https://xmpp.org/rfcs/rfc6121.html#message
	 */
	protected function message(XMLNode $node)
	{
		if (!isset($node['type'])) {
			$node['type'] = 'normal';
		}
		$rq = $this->getRequestObject($node);
		if ('error' === $node['type']) {
			$this->stanza_error($node);
		}
//		$obj->message($node);
	}

	/**
	 * https://xmpp.org/rfcs/rfc6121.html#presence
	 */
	protected function presence(XMLNode $node)
	{
		$rq = $this->getRequestObject($node);
		// https://xmpp.org/rfcs/rfc6121.html#sub-request-handle
		if (isset($node['type'])) {
			if ('subscribe' === $node['type']) {
				$this->send(\Poodle\XMPP\Request\Presence::newSubscribeResponse($node['from'], true));
			}
			if ('error' === $node['type']) {
				$this->stanza_error($node);
			}
		}
	}

	/**
	 * https://xmpp.org/rfcs/rfc6120.html#stanzas-error
	 */
	protected function stanza_error(XMLNode $node)
	{
		$id = isset($node['id']) ? $node['id'] : null;
		$error = $node->getChildByName('error');
		$errors = array();
		foreach ($error->children as $child) {
			$errors[] = ('text' === $child->name) ? $child->value : $child->name;
		}
		throw new \Exception("XMPP {$node->name} {$id} {$error['type']}: ".implode(', ', $errors));
	}

	public function getJid()
	{
		return $this->jid;
	}

	public function setJid($jid)
	{
		$this->jid = (string) $jid;
		return $this->log("set jid: {$jid}", static::LOG_DEBUG);
	}

	public function send($string, $priority = false)
	{
		if ($string instanceof Request && !$priority) {
			$this->requests[$string->getId()] = $string;
		}
		return parent::send($string, $priority);
	}

}
