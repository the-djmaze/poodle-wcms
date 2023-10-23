<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Use in:
	$result = $SQL->query("SELECT * FROM {$SQL->TBL->examples}");
	$result->setFetchObjectParams('TableRow', array($SQL->TBL->examples, 'example_id'));
	$result->fetch_object();
*/

namespace Poodle\SQL;

class TableRow implements \ArrayAccess
{
	protected
		$_table = null,
		$_id_field = '',
		$_fields = array(),
		$_fields_map = array(
		// 'prop_name' => 'field_name'
		);

	private static
		$_tables_defaults = array(),
		$_tables_serials = array();

	public function __construct(Table $table = null, $id_field = '')
	{
		if ($table instanceof Table) {
			$this->_table = $table;
			$this->_id_field = $id_field;
			if (!$this->_fields) {
				// generate fields with DEFAULT
				static::initTableRowDefaults();
				$name = (string)$table;
				$this->_fields = static::$_tables_defaults[$name];
//				if (!$this->_id_field) {
//					$this->_id_field = static::$_tables_serials[$name];
//				}
			}
		}
	}

	protected function getFieldName($key)
	{
		return isset($this->_fields_map[$key]) ? $this->_fields_map[$key] : $key;
	}

	protected function initTableRowDefaults()
	{
		if ($this->_table) {
			$name = (string)$this->_table;
			if (!isset(static::$_tables_defaults[$name])) {
				static::$_tables_serials[$name] = '';
				static::$_tables_defaults[$name] = array();
				foreach ($this->_table->listColumns(false) as $field => $data) {
					static::$_tables_defaults[$name][$field] = $row['default'];
					if (false !== strpos($row['type'], 'SERIAL')) {
						static::$_tables_serials[$name] = $field;
					}
				}
			}
		}
	}

	public function __isset($key)
	{
		return array_key_exists($this->getFieldName($key), $this->_fields);
	}

	public function __get($key)
	{
		$key = $this->getFieldName($key);
		if (array_key_exists($key, $this->_fields)) {
			return $this->_fields[$key];
		}
		throw new \Exception("Undefined property {$key}");
	}

	public function __set($key, $value)
	{
		$key = $this->getFieldName($key);
		// Check if called by db fetch_object before __construct
		// PHP 5.6.21 and PHP 7.0.6 mysqli_fetch_object() sets the properties
		// of the object AFTER calling the object constructor, not BEFORE
		if ($this->_table && ($key == $this->_id_field || !array_key_exists($key, $this->_fields))) {
			$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
			if (false === strpos($bt[1]['function'], 'fetch_object')) {
				throw new \Exception("Not allowed to set {$key}");
			}
		}
		$this->_fields[$key] = $value;
	}

	public function __unset($key)
	{
	}

	// ArrayAccess
	public function offsetGet($key)         { return $this->__get($key); }
	public function offsetExists($key)      { return $this->__isset($key); }
	public function offsetSet($key, $value) { $this->__set($key, $value); }
	public function offsetUnset($key)       { $this->__unset($key); }

	public function delete()
	{
		if ($this->_table && $this->_id_field && $id = $this->_fields[$this->_id_field]) {
			$this->_table->delete(array($this->_id_field => $id));
			return true;
		}
		return false;
	}

	public function save()
	{
		if ($this->_table && $this->_id_field) {
			$data = $this->_fields;
//			$data = array_diff_assoc($this->_fields, $this->_fields_orig);
//			$data = array_intersect_key($this->_fields, $this->_fields_changed);
			$id = $data[$this->_id_field];
			unset($data[$this->_id_field]);
			if ($id) {
				$this->_table->update($data, array($this->_id_field => $id));
			} else {
				$this->_fields[$this->_id_field] = $this->_table->insert($data, $this->_id_field);
			}
			return true;
		}
		return false;
	}

}
