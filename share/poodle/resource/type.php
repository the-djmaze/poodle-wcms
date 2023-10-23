<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Type implements \Poodle\FieldTypes, \JsonSerializable
{

	const
		// TBL->resource_types.resource_type_flags
		FLAG_HIDDEN     = 1,
		FLAG_NO_DATA    = 2,
		FLAG_ADMIN_MENU = 4, // show in seperate list in admin leftside
		FLAG_NO_L10N    = 8, // untranslatable resource

		FIELD_FLAG_L10N      = 1,
		FIELD_FLAG_ATTR_FUNC = 2;

	protected
		$id       = 0,
		$name     = '',
		$label    = '',
		$flags    = 0,
		$class    = '',
		$cssclass = '',
		$wysiwyg_cfg = '',
		$bodylayout_id = 0,
		$fields;

	public function __construct($id = null)
	{
		if ($id && (is_int($id) || ctype_digit($id))) {
			$id = static::getBy('id', $id);
		}
		if (is_array($id)) {
			foreach ($id as $k => $v) {
				$this->$k = is_int($this->$k) ? (int)$v : (string)$v;
			}
			if ($this->id && $this->label) {
				$this->label = \Poodle::getKernel()->OUT->L10N->dbget($this->label);
			}
		}
	}

	public function __get($k)
	{
		if (!isset($this->$k) && 'fields' === $k) {
			$this->fields = self::getFields($this->id);
		}
		return $this->$k;
	}

	public function jsonSerialize()
	{
		return array(
			'id' => $this->id,
//			'name' => $this->name,
//			'label' => $this->label,
			'flags' => $this->flags,
//			'class' => $this->class,
//			'cssclass' => $this->cssclass,
			'wysiwyg_cfg' => $this->wysiwyg_cfg,
			'bodylayout_id' => $this->bodylayout_id,
			'fields' => $this->__get('fields'),
		);
	}

	public static function getFields(int $id, bool $call_func=true) : array
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$r = array();
		$fields = $SQL->query("SELECT
			rtf_name name,
			rtf_label label,
			rtf_type type,
			rtf_flags flags,
			rtf_attributes attributes
		FROM {$SQL->TBL->resource_types_fields}
		WHERE resource_type_id={$id}
		ORDER BY rtf_sortorder ASC");
		while ($field = $fields->fetch_assoc()) {
			$field['label'] = $K->OUT->L10N->dbget($field['label']);
			$field['type']  = (int)$field['type'];
			$field['flags'] = (int)$field['flags'];
			$attr = $field['attributes'] ? json_decode($field['attributes'], true) : array();
			if (!is_array($attr)) {
				$attr = unserialize($field['attributes']);
				$attr = is_array($attr) ? $attr : array();
			}
			$field['attributes'] = $attr;
			if ($call_func && $field['flags'] & self::FIELD_FLAG_ATTR_FUNC) {
				if (is_callable($field['attributes']['get'])) {
					$field['attributes'] = call_user_func($field['attributes']['get']);
				} else {
					unset($field['attributes']['get']);
					unset($field['attributes']['set']);
				}
			}
			$r[] = $field;
		}
		return $r;
	}

	public static function getByName(string $name)  : array { return new static(static::getBy('name', $name)); }
	public static function getByClass(string $name) : array { return new static(static::getBy('class', $name)); }
	protected static function getBy(string $field, string $value) : array
	{
		$SQL = \Poodle::getKernel()->SQL;
		$row = $SQL->uFetchAssoc("SELECT
			resource_type_id id,
			resource_type_name name,
			resource_type_label label,
			resource_type_flags flags,
			resource_type_class class,
			resource_type_cssclass cssclass,
			resource_type_wysiwyg_cfg wysiwyg_cfg,
			resource_bodylayout_id bodylayout_id
		FROM {$SQL->TBL->resource_types}
		WHERE resource_type_{$field} = {$SQL->quote($value)}");
		if (!$row) {
			throw new \Exception("Resource type {$field}={$value} not found");
		}
		return $row;
	}

}
