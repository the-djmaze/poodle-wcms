<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\HTTP;

class PathInfo implements \ArrayAccess, \Countable, \Iterator
{
	protected $path;
	function __construct($path)
	{
		$this->path = explode('/', substr($path, 1));
//		$this->path = explode('/', trim($path, '/'));
	}
	function __toString()     { return '/'.implode('/',$this->path); }
	# Iterator
	public function key()     { return key($this->path); }
	public function current() { return current($this->path); }
	public function next()    { return next($this->path); }
	public function rewind()  { return reset($this->path); }
	public function valid()   { return (null !== key($this->path)); }
	# ArrayAccess
	public function offsetExists($i) { return array_key_exists($i, $this->path); }
	public function offsetGet($i)    { return $this->offsetExists($i) ? $this->path[$i] : null; }
	public function offsetSet($i,$v) {}
	public function offsetUnset($i)  {}
	# Countable
	public function count()   { return count($this->path); }

	public function getArrayCopy() { return $this->path; }
}
/*
 * This should have a smaller memory footprint and be faster
 * But somehow it is slower on my i5-3570
 * Because creation is slower and performance gets noticed at 1.000+ elements
class PathInfo extends \SplFixedArray
{
	function __construct($path)
	{
		$path = explode('/', substr($path, 1));
		parent::__construct(count($path));
		foreach ($path as $i => $v) {
			parent::offsetSet($i, $v);
		}
	}
	function __toString()            { return '/'.implode('/',$this); }
	public function setSize($s)      { throw new \BadMethodCallException(); }
	public function offsetSet($i,$v) { throw new \BadMethodCallException(); }
	public function offsetUnset($i)  { throw new \BadMethodCallException(); }

	public function getArrayCopy()   { return $this->toArray(); }
}
*/
