<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#stanzas
*/

namespace Poodle\XMPP;

abstract class Request
{
	const
		STANZA = null; // stanza-kind MUST be one of message, presence, or iq.

	protected
		$id,
		$type, // get, set, result, error
		$to,
		$from,
		$value,
		$lang;

	function __get($k)
	{
		if (property_exists($this, $k)) {
			return $this->$k;
		}
	}

	function __set($k, $v)
	{
		if (property_exists($this, $k)) {
			$this->$k = $v;
		}
	}

	/**
	 * https://xmpp.org/rfcs/rfc6120.html#stanzas-error
	 */
	public function error(\Poodle\XMPP\Client $client, XMLNode $error)
	{
//		$client->stanza_error($error->parent);
		$errors = array();
		foreach ($error->children as $child) {
			$errors[] = ('text' === $child->name) ? $child->value : $child->name;
		}
		throw new \Exception("XMPP {$error->parent->name} {$this->id} {$error['type']}: ".implode(', ', $errors));
	}

	public function result(\Poodle\XMPP\Client $client, XMLNode $result)
	{
		throw new \Exception("XMPP {$result->parent->name} {$this->id} unprocessed result: ".print_r($result,1));
	}

	public function getId()
	{
		if (!$this->id) {
			$this->id = 'poodle_xmpp_' . uniqid();
		}
		return $this->id;
	}

	public function getValue()
	{
		return $this->value;
	}

	public function __toString()
	{
		$name = static::STANZA;
		$msg = "<{$name} id=\"{$this->getId()}\"";
		if ($this->type) {
			$msg .= " type=\"{$this->type}\"";
		}
		if ($this->to) {
			$msg .= " to=\"{$this->to}\"";
		}
		if ($this->from) {
			$msg .= " from=\"{$this->from}\"";
		}
		if ($this->lang) {
			$msg .= " xml:lang=\"{$this->lang}\"";
		}
		$value = $this->getValue();
		if (strlen($value)) {
			$msg .= ">{$value}</{$name}>";
		} else {
			$msg .= "/>";
		}
		return $msg;
	}

	public static function encode($data)
	{
		return htmlspecialchars($data, ENT_NOQUOTES | ENT_XML1, 'UTF-8');
	}

	public static function quote()
	{
		return htmlspecialchars($data, ENT_QUOTES | ENT_XML1, 'UTF-8');
	}

}
