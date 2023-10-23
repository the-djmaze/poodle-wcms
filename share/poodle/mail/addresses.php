<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail;

class Addresses extends \ArrayIterator
{
	public function append($address, $name='')
	{
		if ($address instanceof Address) {
			parent::append($address);
		} else {
			parent::append(new Address($address, $name));
		}
	}

	public function offsetSet($i, $v)
	{
		if ($v instanceof Address) {
			parent::offsetSet($i, $v);
		} else {
			trigger_error('Value is not a \Poodle\Mail\Address');
		}
	}

	function __toString()
	{
		$result = array();
		foreach ($this as $address) {
			$result[] = $address->__toString();
		}
		return implode("\n", $result);
	}

	public function asEncodedString($charset = 'UTF-8', $encoding = 'Q', $phrase = true)
	{
		$result = array();
		foreach ($this as $address) {
			$result[] = $address->asEncodedString($charset, $encoding, $phrase);
		}
		return implode(', ', $result);
	}
}
