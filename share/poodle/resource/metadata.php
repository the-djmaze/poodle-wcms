<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

//class Metadata extends \ArrayIterator
class Metadata implements \ArrayAccess
{
	protected
		$data = array(),
		$resource;

	protected static
		$types = null,
		$types_grouped = null;

	function __construct(\Poodle\Resource $resource)
	{
		$this->resource = $resource;
		$this->data = array( 0=>array(), $resource->l10n_id=>array() );

		$SQL = \Poodle::getKernel()->SQL;
		$q = "SELECT
			CASE WHEN rtf_flags & 1 THEN {$resource->l10n_id} ELSE 0 END l10n_id,
			rtf_name name,
			'' value,
			0 namespace_id
		FROM {$SQL->TBL->resource_types_fields}
		WHERE resource_type_id IN (0,{$resource->type_id})";
		if ($resource->id) {
			$q .= "
			UNION SELECT
				l10n_id,
				resource_meta_name name,
				resource_meta_value value,
				resource_namespace_id namespace_id
			FROM {$SQL->TBL->resources_metadata}
			WHERE resource_id={$resource->id}
			  AND (l10n_id=0 OR l10n_id={$resource->l10n_id})";
		}
		$qr = $SQL->query($q);
		while ($r = $qr->fetch_row()) {
			$l10n_id = (int)$r[0];
			$this->data[$l10n_id][$r[1]] = $r[2];
		}
	}

	public function offsetExists($offset)
	{
		return array_key_exists($offset,$this->data[0]) || array_key_exists($offset,$this->data[$this->resource->l10n_id]);
	}
	public function offsetGet($offset)
	{
		if (array_key_exists($offset,$this->data[$this->resource->l10n_id])) {
			return $this->data[$this->resource->l10n_id][$offset];
		}
		if (array_key_exists($offset,$this->data[0])) {
			return $this->data[0][$offset];
		}
		return null;
	}
	public function offsetSet($o, $v) { throw new \BadMethodCallException(); }
	public function offsetUnset($o)   { throw new \BadMethodCallException(); }

	public function set($l10n_id, $data)
	{
		// TODO: process all data properly instead of just wiping all data
		// This must be base on the current resource_type_id
		$this->data[$l10n_id] = $data;
	}

	public function append($l10n_id, $key, $value)
	{
		$this->data[$l10n_id][$key] = $value;
	}

	public function getMergedArrayCopy()
	{
		return array_merge($this->data[0], $this->data[$this->resource->l10n_id]);
	}

	public function save()
	{
		if (!$this->resource->id) { return false; }

		if ('all' === $this->data[0]['meta-robots']) {
			unset($this->data[0]['meta-robots']);
		}

		$SQL = \Poodle::getKernel()->SQL;
		$tbl = $SQL->TBL->resources_metadata;
/*
		TODO: process all data properly instead of just wiping all data
		This must be base on the current resource_type_id
			\Poodle\FieldTypes::FIELD_TYPE_*
*/
		$fields = Type::getFields(0, false);
		if ($this->resource->type_id) {
			$fields = array_merge($fields, Type::getFields($this->resource->type_id, false));
		}
		foreach ($fields as $field) {
			$l10n_id = ($field['flags'] & Type::FIELD_FLAG_L10N) ? $this->resource->l10n_id : 0;

			$n = $field['name'];
			$v = isset($this->data[$l10n_id][$n]) ? $this->data[$l10n_id][$n] : null;
			$v = is_array($v) ? implode(',',$v) : $v;
			if ($v && !empty($field['attributes']['maxlength'])) {
				$v = mb_substr($v,0,$field['attributes']['maxlength']);
			}
			if (!$v && Type::FIELD_TYPE_FILE == $field['type']) {
				continue;
			}
			if (Type::FIELD_TYPE_CHECKBOX == $field['type']) {
				$v = !!$v;
				if ($v && isset($field['attributes']['value'])) {
					$v = $v ? $field['attributes']['value'] : null;
				}
			}

			$row = array(
				'resource_id' => $this->resource->id,
				'l10n_id' => $l10n_id,
				'resource_meta_name' => $n
			);
			$tbl->delete($row);
			if (strlen($v)) {
				$row['resource_meta_value'] = $v;
				$tbl->insert($row);
			}
		}
	}
}
