<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail;

class Address
{
	protected
		$address = '',
		$name    = '';

	function __construct($address, string $name = '')
	{
		$this->setName($name);
		$this->setAddress($address);
	}

	protected function setAddress($v)
	{
		if ($v instanceof Address) {
			$this->address = $v->address;
			$this->name    = $v->name;
			return;
		}
		if (preg_match('/^(.*)<([^<>]+@[^@<>]+)>$/D', $v, $m)) {
			$this->setName(\Poodle\Mail::decodeHeader($m[1]));
			$v = $m[2];
		}
		$v = \Poodle\Mail::removeCRLF($v);
		\Poodle\Security::checkEmail($v,0);
		$this->address = $v;
	}

	protected function setName(string $v)
	{
		$this->name = \Poodle\Mail::removeCRLF($v);
	}

	function __get($k)
	{
		if (property_exists($this, $k)) { return $this->$k; }
		trigger_error('Undefined property '.get_class($this).'->'.$k);
	}

	function __set($k, $v)
	{
		if ('address'   === $k) { $this->setAddress($v); }
		else if ('name' === $k) { $this->setName($v); }
		else { trigger_error('Undefined property '.get_class($this).'->'.$k); }
	}

	function __toString()
	{
		return $this->name ? "{$this->name} <{$this->address}>" : "<{$this->address}>";
	}

	public function asEncodedString($charset = 'UTF-8', $encoding = 'Q', $phrase = true)
	{
		# RFC 6530 SMTPUTF8
		if ($this->name) {
			return \Poodle\Mail::encodeHeader('', $this->name, $phrase, $encoding, $charset)
			 ." <{$this->address}>";
//			 .' <'.\Poodle\Mail::encodeHeader('', $this->address, true).'>';
		}
		return "<{$this->address}>";
	}

}
