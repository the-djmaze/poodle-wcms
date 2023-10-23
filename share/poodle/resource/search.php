<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Search
{
	public static function latestOf($resource_type, $limit=5, $offset=0, $class=null)
	{
		trigger_error(__CLASS__ . '::latestOf() use ::latestOfType()', E_USER_DEPRECATED);
		return static::latestOfType($resource_type, $limit, $offset, $class);
	}

	public static function latestOfType($resource_type, $limit=5, $offset=0, $class=null)
	{
		$K   = \Poodle::getKernel();
		$SQL = $K->SQL;
		$def_class = 'Poodle\\Resource\\SearchResult';

		if (!ctype_digit((string)$resource_type)) {
			$id = $SQL->uFetchRow("SELECT resource_type_id FROM {$SQL->TBL->resource_types} WHERE resource_type_name=".$SQL->quote($resource_type));
			$resource_type = $id ? $id[0] : 0;
		}
		$resource_type = (int)$resource_type;
		$limit   = max(1, (int)$limit);
		$offset  = max(0, (int)$offset);

		$where = "ptime<=UNIX_TIMESTAMP()
		  AND (etime=0 OR etime>UNIX_TIMESTAMP())
		  AND type_id={$resource_type}
		  AND l10n_id IN (0,{$K->L10N->id})";

		$result = $SQL->uFetchAll("SELECT
			id,
			uri,
			parent_id,
			type_id,
			ctime,
			ptime,
			etime,
			flags,
			creator_identity_id,
			mtime,
			status,
			modifier_identity_id,
			l10n_id,
			title,
			body,
			type_name,
			type_class,
			type_flags,
			user_nickname as nickname,
			user_givenname as givenname,
			user_surname as surname
		FROM {$SQL->TBL->view_latest_resources_data}
		LEFT JOIN {$SQL->TBL->users} ON (identity_id=creator_identity_id)
		WHERE {$where}
		ORDER BY ptime DESC
		LIMIT {$limit} OFFSET {$offset}");

		if (!$class || ($class !== $def_class && !is_subclass_of($class, $def_class))) {
			$class = $def_class;
		}
		return new $class($result, $limit, $offset, $where);
	}
}

class SearchResult extends \ArrayIterator
{
	protected
		$limit,
		$offset,
		$where,
		$foundRows = null, /* Only MySQL supports SQL_CALC_FOUND_ROWS */
		$pagination = null;

	function __construct($result, $limit, $offset, $where)
	{
		parent::__construct($result);
		$this->limit  = $limit;
		$this->offset = $offset;
		$this->where  = $where;
	}

	function __get($k)
	{
		if ('foundRows' === $k && null === $this->foundRows) {
			if ($this->offset + $this->count() < $this->limit) {
				$this->foundRows = $this->count();
			} else {
				$this->foundRows = \Poodle::getKernel()->SQL->TBL->view_latest_resources_data->count($this->where);
			}
		}
		if ('pagination' === $k && !$this->pagination) {
			$this->pagination = new \Poodle\Pagination(
				null,
				$this->__get('foundRows'),
				$this->offset, $this->limit);
		}

		return $this->$k;
	}

	public function append($v) {}
	public function offsetGet($k)     { return new \Poodle\Resource\Basic(parent::offsetGet($k)); }
	public function offsetSet($k, $v) { }
	public function offsetUnset($k)   { }
	public function current() { return new \Poodle\Resource\Basic(parent::current()); }
}
