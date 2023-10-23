<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Result;

class Form
{
	protected
		$fields = array(),
		$action = null,
		$submit = false,
		$css_class = null;

	public function __construct($fields, $action = null, $css_class = null, $submit=false)
	{
		if ('?' === $action[0]) {
			$action = $_SERVER['REQUEST_PATH'].$action;
		}
		foreach ($fields as &$field) {
			if (!isset($field['inputmode'])) {
				$field['inputmode'] = null;
			}
			if (!isset($field['pattern'])) {
				$field['pattern'] = null;
			}
			if (!isset($field['value'])) {
				$field['value'] = null;
			}
		}
		$this->fields = $fields;
		$this->action = $action;
		$this->submit = $submit;
		$this->css_class = $css_class;
	}

	public function __get($key)
	{
		return (property_exists($this, $key) ? $this->$key : null);
	}
}
