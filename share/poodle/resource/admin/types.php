<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource\Admin;

class Types extends \Poodle\Resource\Admin implements \Poodle\FieldTypes
{
	public
		$title = 'Resource types',
		$allowed_methods = array('GET','POST');

	public function GET()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;

		if (isset(\Poodle::$PATH[1]) && strlen(\Poodle::$PATH[1]))
		{
			if (ctype_digit(\Poodle::$PATH[1])) {
				$type = self::getType(\Poodle::$PATH[1]);
				if (isset($_GET['export'])) {
					header('Cache-Control: no-store, no-cache, must-revalidate');
					header('Pragma: no-cache');
					header('Content-Transfer-Encoding: binary');
					\Poodle\HTTP\Headers::setContentDisposition('attachment', array('filename'=>"type-{$type['name']}.xml"));
					\Poodle\HTTP\Headers::setContentType('application/xml', array('name'=>"type-{$type['name']}.xml"));
					echo Type::export(\Poodle::$PATH[1]);
					return;
				}
				$OUT->edit_resource_type = true;
			} else if ('add' === \Poodle::$PATH[1]) {
				$type = array(
					'id' => 0,
					'name' => '',
					'label' => '',
					'label_l10n' => $K->L10N->get('Add'),
					'flags' => 0,
					'class' => '',
					'cssclass' => '',
					'bodylayout_id' => 0,
					'fields' => array()
				);
				$OUT->edit_resource_type = false;
			} else {
				\Poodle\Report::error(404);
			}
			$OUT->crumbs->append($type['label_l10n'], '/admin/poodle_resource_types/'.\Poodle::$PATH[1]);
			$OUT->head
				->addCSS('poodle_resource_type')
				->addScript('poodle_resource_type');
			$OUT->resource_type = $type;
			return $OUT->display('poodle/resource/admin/type');
		}

		$types = static::getList(0, isset($_GET['showhidden'])?0:1);
		unset($types[0]);

		$OUT->resource_types = $types;
		$OUT->display('poodle/resource/admin/types');
	}

	public function POST()
	{
		if (isset(\Poodle::$PATH[1]) && strlen(\Poodle::$PATH[1]))
		{
			if (isset($_GET['field']) && ctype_digit(\Poodle::$PATH[1])) {
				$tbl = \Poodle::getKernel()->SQL->TBL->resource_types_fields;
				$attribs = array();
				foreach ($_POST['rtf_attributes'] as $k => $v) {
					if (strlen($v)) { $attribs[$k] = $v; }
				}
				$data = array(
				'rtf_label' => $_POST->text('label'),
				'rtf_type' => $_POST->uint('type'),
				'rtf_attributes' => json_encode($attribs),
				'rtf_sortorder' => $_POST->uint('sortorder'),
				'rtf_flags' => (int)array_sum($_POST['flags']),
				);
				if ($_POST->text('name')) {
					$tbl->update($data, array(
						'resource_type_id' => \Poodle::$PATH[1],
						'rtf_name' => $_POST->text('name'),
					));
				} else {
					$data['resource_type_id'] = \Poodle::$PATH[1];
					$data['rtf_name'] = $_POST->text('new_name');
					$tbl->insert($data);
				}
				return $this->closeRequest('Field saved', '/admin/poodle_resource_types/'.\Poodle::$PATH[1].'#resource-type-fields');
			}

			$tbl = \Poodle::getKernel()->SQL->TBL->resource_types;
			$type = $_POST['resource_type'];
			if (empty($type['name']) || empty($type['label'])) {
				\Poodle\Report::error('Invalid data');
			}
			$data = array(
				'resource_type_flags'    => empty($type['flags'])    ?  0 : array_sum($type['flags']),
				'resource_type_class'    => empty($type['class'])    ? '' : substr(trim($type['class']),0,64),
//				'resource_type_cssclass' => empty($type['cssclass']) ? '' : substr(trim($type['cssclass']),0,64),
				'resource_bodylayout_id' => (int)$type['bodylayout_id'],
				'resource_type_label'    => substr(trim($type['label']),0,32),
			);
			if (ctype_digit(\Poodle::$PATH[1])) {
				$tbl->update($data, "resource_type_id=".\Poodle::$PATH[1]);
				$this->closeRequest(null, $_SERVER['REQUEST_URI']);
			} else if ('add' === \Poodle::$PATH[1]) {
				$data['resource_type_name'] = preg_replace('@[\\s&<>\'"#\\?]@','-',strtolower(substr(trim($type['name']),0,32)));
				$id = $tbl->insert($data, 'resource_type_id');
				$this->closeRequest(null, '/admin/poodle_resource_types/'.$id);
			}
		} elseif (isset($_FILES['resourcetype_xml'])) {
			echo Type::import($_FILES->getAsFileObject('resourcetype_xml')->tmp_name);
			$this->closeRequest('Resource type added/updated', '/admin/poodle_resource_types/'.$id);
		}
	}

	public static function getType($type_id=0)
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;

		if (0 == $type_id) {
			return array(
				'id' => 0,
				'name' => '',
				'label' => '['.$K->L10N->get('default').']',
				'label_l10n' => '['.$K->L10N->get('default').']',
				'flags' => 0,
				'class' => '',
				'cssclass' => '',
				'bodylayout_id' => 0,
				'fields' => self::getTypeFields(0)
			);
		}

		$row = $SQL->uFetchAssoc("SELECT
			resource_type_id id,
			resource_type_name name,
			resource_type_label label,
			resource_type_flags flags,
			resource_type_class class,
			resource_type_cssclass cssclass,
			resource_bodylayout_id bodylayout_id
		FROM {$SQL->TBL->resource_types}
		WHERE resource_type_id=".(int)$type_id);
		if ($row) {
			$row['label_l10n'] = $K->L10N->dbget($row['label']);
			$row['fields']     = self::getTypeFields($row['id']);
			return $row;
		}
	}

	public static function getTypeFields($id)
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;

		$attribs = array(
/*			'list'     => '',
			'maxlength' => '',
			'max'      => '',
			'min'      => '',
			'options'  => array(),
			'pattern'  => '',
			'required' => '',
			'step'     => '',
			'value'    => '',
			// FIELD_FLAG_ATTR_FUNC
			'get' => '',
			'set' => '',*/
		);

		$id  = (int)$id;
		$r   = array();
		$fields = $SQL->query("SELECT
			rtf_name name,
			rtf_label label,
			rtf_type type,
			rtf_flags flags,
			rtf_sortorder sortorder,
			rtf_attributes attributes
		FROM {$SQL->TBL->resource_types_fields}
		WHERE resource_type_id={$id}
		ORDER BY rtf_flags&1, rtf_sortorder ASC");
		while ($field = $fields->fetch_assoc()) {
//			$field['label_l10n'] = $K->OUT->L10N->dbget($field['label']);
			$field['type'] = (int)$field['type'];
			$field['flags'] = (int)$field['flags'];
			$field['sortorder'] = (int)$field['sortorder'];
			$field['type_name']  = static::getFieldTypeName($field['type']);
			$attr = $field['attributes'] ? json_decode($field['attributes'], true) : array();
			if (!is_array($attr)) {
				$attr = unserialize($field['attributes']);
				$attr = is_array($attr) ? $attr : array();
			}
			$field['attributes'] = array_merge($attribs, $attr);
			$r[] = $field;
		}
		return $r;
	}

	public static function getFieldTypeName($id)
	{
		static $types;
		if (!$types) {
			$rc = new \ReflectionClass('\\Poodle\\FieldTypes');
			$types = $rc->getConstants();
		}
		$type = array_search($id, $types);
		// FIELD_TYPE_*
		return $type ? substr($type,11) : 'UNKNOWN';
	}

	public static function getFixedList($type_id=0)
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$row = static::getDefaultType();
		$row['fields'] = \Poodle\Resource\Type::getFields(0);
		$types = array($row);
		if ($type_id) {
			$row = $SQL->uFetchAssoc('SELECT
				resource_type_id id,
				resource_type_name name,
				resource_type_label label,
				resource_type_flags flags,
				resource_type_class class,
				resource_type_cssclass cssclass,
				resource_type_wysiwyg_cfg wysiwyg_cfg,
				resource_bodylayout_id bodylayout_id
			FROM '.$SQL->TBL->resource_types.'
			WHERE resource_type_id='.(int)$type_id);
			if ($row) {
				$row['id'] = (int)$row['id'];
				$row['label'] = $K->OUT->L10N->dbget($row['label']);
				$row['flags'] = (int)$row['flags'];
				$row['bodylayout_id'] = (int)$row['bodylayout_id'];
//				$call = array($row['class'], 'getResourceTypeFields');
				$row['fields'] = array_merge($types[0]['fields'], \Poodle\Resource\Type::getFields($row['id']));
				unset($row['class']);
				$types = array(array_merge($types[0], $row));
			}
		}
		return $types;
	}

	public static function getList($type_id = 0, $not_flags = 1)
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$types = array();
		$result = $SQL->query('SELECT
			resource_type_id id,
			resource_type_name name,
			resource_type_label label,
			resource_type_flags flags,
			resource_type_class class,
			resource_type_cssclass cssclass,
			resource_type_wysiwyg_cfg wysiwyg_cfg,
			resource_bodylayout_id bodylayout_id
		FROM '.$SQL->TBL->resource_types.'
		WHERE resource_type_id = '.(int)$type_id.'
		   OR NOT resource_type_flags & '.(int)$not_flags);
		while ($row = $result->fetch_assoc()) {
//			$call = array($row['class'], 'getResourceTypeFields');
			$types[mb_strtolower($K->OUT->L10N->dbget($row['label']).$row['name']).$row['id']] = new \Poodle\Resource\Type($row);
		}
		ksort($types);
		array_unshift($types, new \Poodle\Resource\Type(static::getDefaultType()));
		return array_values($types);
	}

	protected static function getDefaultType()
	{
		return array(
			'id' => 0,
			'name' => 'default',
			'label' => '['.\Poodle::getKernel()->OUT->L10N->get('default').']',
			'flags' => 0,
			'cssclass' => null,
			'wysiwyg_cfg' => null,
			'bodylayout_id' => 0,
		);
	}

}
