<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\TPL;

class Context
{
	public
		$attrs = array();

	protected
		$tpl_file;

	private
		$parent,
		$repeat,
		$_scope;

	function __construct(Context $parent=null, $scope='root')
	{
		$this->parent = $parent;
		$this->_scope = $scope;
	}

	function __set($name, $value)
	{
		if (!preg_match('#^[a-z_][a-z0-9_]*#i', $name)) {
			throw new \InvalidArgumentException('Template variable error \''.$name.'\' has an incorrect format, must be of: [a-z_][a-z0-9_]*');
		}
		if (property_exists($this, $name) && !array_key_exists($name, (array)$this)) {
			throw new \Exception('Template variable error \''.$name.'\' is not a public property and therefore may not be set');
		}
		$this->$name = $value;
	}

	# no need to check isset($this->$name), PHP does that before calling __isset()
	function __isset($name)
	{
		return $this->parent
			? isset($this->parent->$name)
			: null !== static::getBuiltinProp($name);
//		return defined($name);
	}

	function __get($name)
	{
		if ('repeat' === $name && !$this->repeat) {
			$this->repeat = new ContextRepeaters($this);
		}
		if (property_exists($this, $name)) {
			return $this->$name;
		}
		$v = static::getBuiltinProp($name);
		if (null !== $v) { return $v; }
		if ($this->__isset($name)) { return $this->parent->$name; }
		\Poodle\Debugger::error(E_USER_NOTICE, "Unable to find variable '{$name}' in current scope ({$this->getScope()})", $this->tpl_file, 0);
		return null;
	}

	public function getScope()
	{
		$p = $this;
		$scope = $p->_scope;
		while ($p = $p->parent) {
			$scope = $p->_scope.'/'.$scope;
		}
		return $scope;
	}

	public function getTopContext() : self
	{
		return $this->parent ? $this->parent->getTopContext() : $this;
	}

	public function toString(string $filename, ?self $ctx=null) : ?string
	{
		if ($this->parent) {
			return $this->parent->toString($filename, $ctx?:$this);
		}
	}

	public function new_context_repeat(string $var, $exp) : self
	{
		$ctx = new self($this, $var);
		$ctx->__get('repeat')->append($var, $exp);
		return $ctx;
	}

	protected static function getBuiltinProp($name)
	{
		if ('KERNEL'    === $name || 'root' === $name) { return \Poodle::getKernel(); }
		if ('IDENTITY'  === $name || 'user' === $name) { return \Poodle::getKernel()->IDENTITY; }
		if ('SERVER'    === $name) { return $_SERVER; }
		if ('RESOURCE'  === $name) { return \Poodle::getKernel()->RESOURCE; }
		if ('REQUEST'   === $name) { return array('GET'=>$_GET,'POST'=>isset($_POST)?$_POST:null); }
		if ('CONFIG'    === $name) { return \Poodle::getKernel()->CFG; }
		if ('URI_BASE'  === $name) { return \Poodle::$URI_BASE; }
		if ('URI_MEDIA' === $name) { return \Poodle::$URI_MEDIA; }
		if ('SQL'       === $name) { return \Poodle::getKernel()->SQL; }
		if ('cookieconsent' === $name) { return POODLE_BACKEND || !empty($_COOKIE['consent']); }
	}
}

class ContextRepeaters
{
	private $ctx;

	function __construct(Context $ctx)
	{
		$this->ctx = $ctx;
	}

	function __get($name)
	{
		if ($this->ctx->parent) {
			return $this->ctx->parent->repeat->$name;
		}
	}

	function __set($name, ContextVarIterator $value)
	{
		$this->$name = $value;
	}

	function __isset($name)
	{
		if ($this->ctx->parent) {
			return isset($this->ctx->parent->repeat->$name);
		}
	}

	public function append($name, $data)
	{
		$this->$name = new ContextVarIterator($this->ctx, $data);
	}
}

class ContextVarIterator implements \Iterator
{
	protected
		$index,         # repetition number, starting from zero.
		$start,         # true for the starting repetition (index 0).
		$end,           # true for the ending, or final, repetition.
		$length = null, # length of the sequence, which will be the total number of repetitions.
		# count reps with lower-case letters: "a" - "z", "aa" - "az", "ba" - "bz", ..., "za" - "zz", "aaa" - "aaz", and so forth.
		$letter,
		$Letter,        # upper-case version of letter
		$roman,         # lower-case version of Roman numerals
		$Roman,         # upper-case version of Roman numerals

		$iterator,
		$current,
		$key,
		$valid,
		$ctx;

	function __construct(Context $ctx, $source)
	{
		$this->ctx = $ctx;
		if (is_array($source))                          { $this->iterator = new \ArrayIterator($source); }
		else if ($source instanceof \IteratorAggregate) { $this->iterator = $source->getIterator(); }
		else if ($source instanceof \Iterator)          { $this->iterator = $source; }
		else if ($source instanceof \Traversable)       { $this->iterator = new \IteratorIterator($source); }
		else if ($source instanceof \stdClass)          { $this->iterator = new \ArrayIterator((array)$source); }
		else                                            { $this->iterator = new \ArrayIterator(array()); }

		if ($this->iterator instanceof \Countable)      { $this->length = count($this->iterator); }
	}

	function __destruct()
	{
		$this->ctx = null;
	}

	# repetition number, starting from one.
	public function number() : int
	{
		return 1 + $this->index;
	}

	# true for even-indexed repetitions (0, 2, 4, ...).
	public function even() : bool
	{
		return 0 === ($this->index % 2);
	}

	# true for odd-indexed repetitions (1, 3, 5, ...).
	public function odd() : bool
	{
		return 1 === ($this->index % 2);
	}

	/**
	 * Iterator
	 */

	protected function fetch()
	{
		if ($this->valid = $this->iterator->valid()) {
			$this->current = $this->iterator->current();
			$this->key     = $this->iterator->key();
			# Prefetch next
			$this->iterator->next();
			$this->end = !$this->iterator->valid();
		} else {
			$this->current = null;
			$this->key     = 0;
		}
		if ($this->end && null === $this->length) {
			$this->length = $this->valid ? 1 + $this->index : 0;
		}
	}
	public function current() { return $this->current; }
	public function key()     { return $this->key; }
	public function next()    { $this->fetch(); ++$this->index; }
	public function rewind()
	{
		$this->iterator->rewind();
		$this->index = 0;
		$this->fetch();
	}
	public function valid() { return $this->valid; }

	/**
	 * TAL
	 */

	function __get($key)
	{
		switch ($key)
		{
		case 'key':
		case 'index':
		case 'end':
		case 'length': return $this->$key;
		case 'start':  return 0 === $this->index;
		case 'letter': return $this->int2letter(1 + $this->index);
		case 'Letter': return strtoupper($this->int2letter(1 + $this->index));
		case 'roman':  return strtolower($this->int2roman(1 + $this->index));
		case 'Roman':  return $this->int2roman(1 + $this->index);
		}
		\Poodle\Debugger::error(E_USER_NOTICE, "Undefined repeat key '{$key}' in current scope ({$this->ctx->getScope()})", $this->ctx->tpl_file, 0);
	}

	protected function int2letter(int $int) : string
	{
		static $alpha = 'abcdefghijklmnopqrstuvwxyz';
		$result = '';
		while ($int--) {
			$result = $alpha[$int % 26] . $letters;
			$int = floor($int / 26);
		}
		return $result;
	}

	protected function int2roman(int $int) : string
	{
		static $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
		$result = '';
		foreach ($map as $roman => $n) {
			while ($int >= $n) {
				$int -= $n;
				$result .= $roman;
				break;
			}
		}
		return $result;
	}

}
