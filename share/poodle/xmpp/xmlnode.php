<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\XMPP;

class XMLNode implements \ArrayAccess
{
	public
		$name,
		$ns,
		$attributes,
		$parent   = null,
		$value    = null,
		$children = array();

	public function getChildByName($name)
	{
		foreach ($this->children as $node) {
			if ($name === $node->name || $node = $node->getChildByName($name)) {
				return $node;
			}
		}
	}

	public function attributeIsTrue($name)
	{
		return !empty($this->attributes[$name]) && 'false' !== $this->attributes[$name];
	}

	public function offsetExists($k)  { return array_key_exists($k, $this->attributes); }
	public function offsetGet($k)
	{
		if (!isset($this->attributes[$k])) {
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
			trigger_error("Undefined XMLNode attribute: {$k} on {$bt[0]['file']}#{$bt[0]['line']}");
			return;
		}
		return $this->attributes[$k];
	}
	public function offsetSet($k, $v) { $this->attributes[$k] = $v; }
	public function offsetUnset($k)   { unset($this->attributes[$k]); }
}
