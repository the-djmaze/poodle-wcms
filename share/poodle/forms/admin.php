<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Forms;

class Admin extends \Poodle\Resource\Admin
{
	public
		$id    = null,
		$title = 'Forms',
		$form  = array(),
		$forms = array(),
		$allowed_methods = array('GET','HEAD','POST');

	protected
		$def_form = array(
			'id'           => 0,
			'name'         => '',
			'email'        => false,
			'emailaddress' => '',
			'store_db'     => false,
			'result_uri'   => '',
			'active'       => true,
			'send_email_resource' => 0
		);

	function __construct(array $data=array())
	{
		parent::__construct($data);

		if (isset(\Poodle::$PATH[1]) && ctype_digit(\Poodle::$PATH[1])) {
			$this->id = (int)\Poodle::$PATH[1];
		}
	}

	protected function closeRequest($msg = null, $query = '')
	{
		parent::closeRequest($msg, \Poodle\URI::admin("/poodle_forms/{$this->id}/{$query}"));
	}

	public static function getFormOptions()
	{
		$SQL = \Poodle::getKernel()->SQL;
		$qr = $SQL->query("SELECT
			form_id,
			form_name
		FROM {$SQL->TBL->forms}
		WHERE form_active=1");
		$o = array();
		while ($r = $qr->fetch_row()) {
			$o[] = array(
				'value' => $r[0],
				'label' => $r[1]
			);
		}
		return array('options' => $o);
	}

	protected function getSortOrder($field_id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$r = $SQL->uFetchRow("SELECT
			ffield_sortorder
		FROM {$SQL->TBL->forms_fields}
		WHERE form_id = {$this->id}
		  AND ffield_id = ".(int)$field_id);
		return $r ? (int)$r[0] : false;
	}

	public function GET()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;

		if (!is_null($this->id)) {
			$ffields = array();
			$rfields = array();
			if (0 < $this->id) {
				$form = $SQL->uFetchAssoc("SELECT
					form_id id,
					form_name name,
					form_email email,
					form_emailaddress emailaddress,
					form_store_db store_db,
					form_result_uri result_uri,
					form_send_email_resource send_email_resource,
					form_active active
				FROM {$SQL->TBL->forms}
				WHERE form_id={$this->id}");
				if (!$form) {
					\Poodle\Report::error(404);
				}

				$qr = $SQL->query("SELECT
					ffield_id id,
					ffield_sortorder sortorder,
					ffield_type type,
					ffield_label label,
					ffield_value value,
					ffield_required required,
					ffield_active active
				FROM {$SQL->TBL->forms_fields}
				WHERE form_id={$this->id}
				  AND ffield_active=1
				ORDER BY ffield_sortorder");
				while ($r = $qr->fetch_assoc()) {
					$r['multiple'] = ('radio' === $r['type'] || 'select' === $r['type']);
					if ($r['multiple']) {
						$r['value'] = json_decode($r['value'], true);
					}
					$ffields[] = $r;
					if ('submit' != $r['type']) {
						$rfields[] = array(
							'id' => $r['id'],
							'label' => $r['label']
						);
					}
				}
			} else {
				$form = $this->def_form;
			}

			$form['result_count'] = $SQL->TBL->forms_postdata->count("form_id={$this->id}");

			if (isset(\Poodle::$PATH[2]) && 'result' === \Poodle::$PATH[2]) {
				if ('csv' === $K->mlf) {
					$fp = fopen('php://output', 'w');
					$result = array('time');
					foreach ($rfields as $f) {
						$result[] = $f['label'];
					}
					fputcsv($fp, $result, ';', '"');
					$qr = $SQL->query("SELECT
						fpost_time,
						fpost_data
					FROM {$SQL->TBL->forms_postdata}
					WHERE form_id={$this->id}");
					while ($r = $qr->fetch_row()) {
						$data = json_decode($r[1], true);
						$result = array(date('Y-m-d H:i:s', $r[0]));
						foreach ($rfields as $f) {
							$result[] = isset($data[$f['id']]) ? $data[$f['id']]['value'] : null;
						}
						fputcsv($fp, $result, ';', '"');
					}
					fclose($fp);
					return;
				}

				$limit  = 50;
				$offset = (int)$_GET->uint('offset');
				$form['fields'] = $rfields;
				$form['result'] = new \ArrayIterator;
				$qr = $SQL->query("SELECT
					fpost_id,
					fpost_time,
					fpost_data
				FROM {$SQL->TBL->forms_postdata}
				WHERE form_id={$this->id}
				LIMIT {$limit}
				OFFSET {$offset}");
				while ($r = $qr->fetch_row()) {
					$data = json_decode($r[2], true);
					$result = array(
						'id' => (int) $r[0],
						'date' => $OUT->L10N->date('DATE_T', (int) $r[1]),
						'fields' => array()
					);
					foreach ($rfields as $f) {
						$result['fields'][] = array(
							'value' => isset($data[$f['id']]) ? $data[$f['id']]['value'] : null
						);
					}
					$form['result']->append($result);
				}
				$form['result']->pagination = new \Poodle\Pagination(
					$_SERVER['REQUEST_PATH'].'?offset=${offset}',
					$form['result_count'], $offset, $limit);
				$this->form = $form;
				$OUT->head
					->addCSS('poodle_forms_admin')
					->addScript('poodle_forms_admin');
				$OUT->display('poodle/forms/admin/result');
				return;
			}

			$form['emailaddresses'] = explode(',',$form['emailaddress']);
			$form['fields'] = $ffields;
			$this->form = $form;
			if ($this->id) {
				$this->title = $OUT->L10N['Form'].': '.$form['name'];
				$OUT->crumbs->append($form['name'], '/admin/poodle_forms/'.$this->id);
			} else {
				$this->title = $OUT->L10N['Add form'];
				$OUT->crumbs->append($this->title);
			}

			$resources = array();
			$result = $SQL->query("SELECT
				resource_id id,
				resource_uri uri
			FROM {$SQL->TBL->resources}
			WHERE NOT resource_flags & ".\Poodle\Resource::FLAG_SUB_LOCKED."
			ORDER BY resource_uri");
			while ($row = $result->fetch_assoc())
			{
				// Gecko supports background-image
				$text = \Poodle\Tree::convertURI($row['uri']);
				$row['id'] = (int)$row['id'];
				$row['text'] = $text ?: '[home]';
				$row['class'] = $text ? 'lvl'.(substr_count($row['uri'],'/')-1) : null;
				$resources[] = $row;
			}
			$OUT->resources = $resources;

			$OUT->emailresources = $SQL->query("SELECT
				resource_id id,
				resource_uri uri
			FROM {$SQL->TBL->resources}
			WHERE resource_type_id=9
			ORDER BY resource_uri");
		}

		$this->forms = $SQL->query("SELECT form_id id, form_name name FROM {$SQL->TBL->forms} WHERE form_active=1");

		$OUT->head
			->addCSS('poodle_forms_admin')
			->addScript('poodle_forms_admin');
		$OUT->display('poodle/forms/admin/index');
	}

	public function HEAD()
	{
	}

	public function POST()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		if (!is_null($this->id)) {
			try {
				if (isset(\Poodle::$PATH[2]) && 'result' === \Poodle::$PATH[2]) {
					if (isset($_POST['delete'])) {
						$ids = implode(',', $SQL->prepareValues($_POST['del']));
						if ($ids) {
							$SQL->TBL->forms_postdata->delete("form_id={$this->id} AND fpost_id IN ({$ids})");
						}
						$this->closeRequest('Result(s) deleted', "result");
					}
					\Poodle\Report::error(409, $e->getMessage());
				}

				$f = $_POST->map('form');
				if (!empty($f)) {
					$mailtos = array();
					foreach ($f['emailaddresses'] as $i => $v) {
						try {
							if ($v && \Poodle\Input::validateEmail($v)) {
								$mailtos[] = $v;
							}
						} catch (\Throwable $e) {
						}
					}
					if ($f->bool('email') && !$mailtos) {
						\Poodle\Report::error(412,'Invalid Email address');
					}

					$form = array();
					foreach ($this->def_form as $k => $v) {
						$form['form_'.$k] = $f[$k];
					}
					$form['form_send_email_resource'] = (int)$f->uint('send_email_resource');
					$form['form_active'] = $f->bool('active');
					$form['form_emailaddress'] = implode(',', $mailtos);

					unset($form['form_id']);
					if ($this->id) {

						if (isset($_POST['copy_form'])) {
							$form['form_name'] = 'copy: '.$form['form_name'];
							try {
								$new_id = $SQL->TBL->forms->insert($form,'form_id');
							} catch (\Throwable $e) {
								throw new \Exception($K->L10N['ERROR: Form name not unique']);
							}
							$result = $SQL->query("SELECT ffield_sortorder, ffield_type, ffield_label, ffield_value, ffield_required, ffield_active
								FROM {$SQL->TBL->forms_fields}
								WHERE form_id={$this->id} AND ffield_active>0");
							foreach ($result as $field) {
								$field['form_id'] = $new_id;
								$SQL->TBL->forms_fields->insert($field);
							}
							$this->id = $new_id;
							$this->closeRequest('Form copied');
						}

						$SQL->TBL->forms->update($form,array('form_id'=>$this->id));
					} else {
						$this->id = $SQL->TBL->forms->insert($form,'form_id');
					}
					$this->closeRequest('Form Saved');
				}

				if (!empty($_POST['formfields'])) {
					$tbl = $SQL->TBL->forms_fields;
					foreach ($_POST['formfields'] as $field_id => $data) {
						$value = $data['value'];
						if (is_array($value)) {
							unset($value[count($value) - 1]);
							$value = \Poodle::dataToJSON($value);
						}
						$tbl->update(array(
							'ffield_label' => $data['label'],
							'ffield_value' => (string) $value,
							'ffield_required' => !empty($data['required'])
							), array('form_id' => $this->id, 'ffield_id' => $field_id));
					}
				}

				if (!empty($_POST['move_up']) && 1 === count($_POST['move_up'])) {
					$ids = array_map('intval', array_keys($_POST['move_up']));
					$sortorder = $this->getSortOrder($ids[0]);
					if (!$sortorder) {
						\Poodle\Report::error(409, "Can't move form field");
					}
					--$sortorder;
					$SQL->exec("UPDATE {$SQL->TBL->forms_fields}
					SET ffield_sortorder = ffield_sortorder + 1
					WHERE form_id = {$this->id}
					  AND ffield_sortorder = {$sortorder}");
					$SQL->exec("UPDATE {$SQL->TBL->forms_fields}
					SET ffield_sortorder = {$sortorder}
					WHERE ffield_id = {$ids[0]}");
					$this->closeRequest('Moved', '?fields');
				}

				if (!empty($_POST['move_down']) && 1 === count($_POST['move_down'])) {
					$ids = array_map('intval', array_keys($_POST['move_down']));
					$sortorder = $this->getSortOrder($ids[0]);
					$m = $SQL->uFetchRow("SELECT MAX(ffield_sortorder) FROM {$SQL->TBL->forms_fields} WHERE form_id={$this->id}");
					if ($sortorder >= $m[0]) {
						\Poodle\Report::error(409, "Can't move form field");
					}
					++$sortorder;
					$SQL->exec("UPDATE {$SQL->TBL->forms_fields}
					SET ffield_sortorder = ffield_sortorder - 1
					WHERE form_id = {$this->id}
					  AND ffield_sortorder = {$sortorder}");
					$SQL->exec("UPDATE {$SQL->TBL->forms_fields}
					SET ffield_sortorder = {$sortorder}
					WHERE ffield_id = {$ids[0]}");
					$this->closeRequest('Moved', '?fields');
				}

				if (!empty($_POST['remove']) && 1 === count($_POST['remove'])) {
					$ids = array_map('intval', array_keys($_POST['remove']));
					$sortorder = $this->getSortOrder($ids[0]);
					if (!$sortorder) {
						\Poodle\Report::error(409, "Can't remove form field");
					}
					$affected_rows = $SQL->exec("UPDATE {$SQL->TBL->forms_fields}
					SET ffield_sortorder = ffield_sortorder - 1
					WHERE form_id = {$this->id}
					  AND ffield_sortorder > {$sortorder}");
					$SQL->exec("UPDATE {$SQL->TBL->forms_fields} SET
						ffield_active = 0,
						ffield_sortorder = ffield_sortorder + {$affected_rows}
					WHERE form_id = {$this->id}
					  AND ffield_id = {$ids[0]}");
					$this->closeRequest('Removed', '?fields');
				}

				if (!empty($_POST['add'])) {
					list($type) = $_POST->keys('add');
					if (!in_array($type, array('text','email','textarea','checkbox','radio','select','submit'))) {
						throw new \Exception('Given type not allowed');
					}

					$tbl = $SQL->TBL->forms_fields;
					$tbl->insert(array(
						'form_id' => $this->id,
						'ffield_sortorder' => $tbl->count("form_id={$this->id}"),
						'ffield_type'     => $type,
						'ffield_label'    => '',
						'ffield_value'    => '',
						'ffield_required' => 0,
						'ffield_active'   => 1
					));

					$this->closeRequest('Form field added', '?fields');
				}

				if (!empty($_POST['formfields'])) {
					$this->closeRequest('Form fields saved', '?fields');
				}

				print_r($_POST);
			}
			catch (\Throwable $e)
			{
				\Poodle\Report::error(409, $e->getMessage());
			}
		}
	}

}
