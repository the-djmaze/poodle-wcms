<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class Config implements \ArrayAccess /*, \Serializable*/
{
	protected
		$refresh_cache = false;

	# loads the global configuration system
	function __construct($__set_state=false)
	{
		if ($__set_state) { return; }
		$K = \Poodle::getKernel();
		// cfg_type
		$result = $K->SQL->query('SELECT cfg_section, cfg_key, cfg_value FROM '.$K->SQL->TBL->config);
		if (!$result) { \Poodle\URI::redirect('/setup/'); }
		while ($row = $result->fetch_row()) {
			if (!isset($this->{$row[0]})) {
				$this->{$row[0]} = new Config_Section();
			}
			$this->{$row[0]}->{$row[1]} = $row[2];
		}
		$result->free();
		# Cache everything
		$K->CACHE->set(__CLASS__, $this);
		$this->init();
	}

	public static function load()
	{
		$c = \Poodle::getKernel()->CACHE->get(__CLASS__);
		return ($c ? $c : new static());
	}

	public static function removeFromCache()
	{
		\Poodle::getKernel()->CACHE->delete(__CLASS__);
	}

	protected function init()
	{
		if (!$this->site->timezone) {
			$this->set('site', 'timezone', 'UTC');
		}
		if (empty($_COOKIE['PoodleTimezone'])) {
			date_default_timezone_set($this->site->timezone);
		}
		// Temporary config untill we improve the debug options in admin
		\Poodle::$DEBUG = (int)$this->debug->poodle_level;
		\Poodle::getKernel()->addEventListener('shutdown', array($this,'onShutdown'));
	}

	public function onShutdown()
	{
		if ($this->refresh_cache) {
			self::removeFromCache();
			$this->refresh_cache = false;
		}
	}

	# when class gets destroyed check for changes and delete cache when needed
	function __destruct()
	{
		$this->onShutdown();
	}

	# retrieve section key value
	public function get(string $section, string $key)
	{
		return isset($this->$section->$key) ? $this->$section->$key : null;
	}

	# set value for section key
	public function set(string $section, string $key, $value)
	{
		if (!$section || !$key) { return false; }
		if ($value instanceof \DateTime) { $value = $value->getTimestamp(); }
		if (!isset($this->$section->$key)) {
			# section key doesn't exist so we create it
			\Poodle\LOG::notice(\Poodle\LOG::CREATE, 'Poodle\\Config->'.$section.'->'.$key.' did not exist.');
			$this->add($section, $key, $value);
			return;
		}
		// issue: 0=='value' results in true
		if ($value instanceof \DateTime) { $value = $value->getTimestamp(); }
		if (is_bool($value)) { $value = (int)$value; }
		if ($value === $this->$section->$key) { return; }
		$this->$section->$key = $value;
		\Poodle::getKernel()->SQL->TBL->config->update(
			array('cfg_value'=>$value),
			array('cfg_section'=>$section, 'cfg_key'=>$key));
		$this->refresh_cache = true;
	}

	# create a new section key with value
	public function add(string $section, string $key, $value)
	{
		if (isset($this->$section->$key)) { return; }
		if (!isset($this->$section)) { $this->$section = new Config_Section(); }
		if ($value instanceof \DateTime) { $value = $value->getTimestamp(); }
		if (is_bool($value)) { $value = (int)$value; }
		$this->$section->$key = $value;
		\Poodle::getKernel()->SQL->TBL->config->insert(array(
			'cfg_section' => $section,
			'cfg_key'     => $key,
			'cfg_value'   => $value
		));
		$this->refresh_cache = true;
	}

	# destroy section key or whole section
	public function delete(string $section, string $key=null)
	{
		if (!is_object($this->$section)) { return; }
		if (!$key) {
			$this->$section = null;
		} else {
			if (!isset($this->$section->$key)) { return; }
			$this->$section->$key = null;
		}
		$SQL = \Poodle::getKernel()->SQL;
		$SQL->query('DELETE FROM '.$SQL->TBL->config." WHERE cfg_section='$section'".($key ? " AND cfg_key='$key'" : ''));
		$this->refresh_cache = true;
	}

	// PHP >= 7.4
	public function __serialize() : array
	{
		return \get_object_public_vars($this);
	}

	public function __unserialize(array $data) : void
	{
		foreach ($data as $k => $v) {
			if ('*' !== $k[1]) {
				$this->$k = $v;
			} else {
				self::removeFromCache();
			}
		}
		$this->init();
	}

	// PHP < 7.4
	public function __sleep()
	{
		return \array_keys(\get_object_public_vars($this));
	}

	public function __wakeup()
	{
		$this->init();
	}
//	public function serialize() : string
//	public function unserialize(string $serialized) : void

	# ArrayAccess
	public function offsetExists($k)  { return property_exists($this, $k); }
	public function offsetGet($k)     { return $this->$k; }
	public function offsetSet($k, $v) {}
	public function offsetUnset($k)   {}
}

class Config_Section extends \ArrayIterator
{
	public function __serialize() : array
	{
		return $this->getArrayCopy();
	}
	public function __unserialize($data)
	{
		if (isset($data[1]) && \is_array($data[1])) {
			$data = $data[1];
		}
		foreach ($data as $k => $v) {
			$this->offsetSet($k, $v);
		}
	}

	function __get($k)     { return $this->offsetGet($k); }
	function __isset($k)   { return $this->offsetExists($k); }
	function __unset($k)   { $this->offsetUnset($k); }
	function __set($k, $v) { $this->offsetSet($k, $v); }
}
