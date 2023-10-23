<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Forms;

class Form extends \Poodle\Resource\Basic
{

	public
		$allowed_methods = array('GET', 'HEAD', 'POST'),
		$form = array(
			'fields' => array(),
			'hasRequiredFields' => false
		);

	public function GET()
	{
		$K = \Poodle::getKernel();
		$K->OUT->L10N->load('forms');

		if ($this->body) {
			$K->OUT->display('resources/'.$this->id.'-body-'.$this->l10n_id, $this->body, $this->mtime);
		}

		$form_id = $this->getMetadata('form_id');
		if ($form_id) {
			$data = isset($_SESSION['FORMDATA']) ? $_SESSION['FORMDATA'] : null;
			$SQL = $K->SQL;
			$qr = $SQL->query("SELECT
				ffield_id id,
				ffield_type type,
				ffield_label label,
				ffield_value value,
				ffield_required required
			FROM {$SQL->TBL->forms_fields}
			WHERE form_id = {$form_id}
			  AND ffield_active = 1
			ORDER BY ffield_sortorder");
			while ($r = $qr->fetch_assoc()) {
				if ('select' === $r['type'] || 'radio' === $r['type']) {
					$r['options'] = json_decode($r['value'], true);
					$r['value'] = null;
				} else if ('checkbox' != $r['type'] && 'radio' != $r['type']) {
					$r['placeholder'] = $r['value'];
					$r['value'] = null;
				}
				$r['error'] = false;
				if (isset($data)) {
					if (isset($data[$r['id']])) {
						$r = array_merge($r, $data[$r['id']]);
					}
				}
				$this->form['fields'][] = $r;
				$this->form['hasRequiredFields'] |= !empty($r['required']);
			}
			$this->display('poodle/forms/form');
		}
	}

	public function POST()
	{
		unset($_SESSION['FORMDATA']);
		if (!empty($_POST['ffield'])) {
			$K = \Poodle::getKernel();
			$form_id = $this->getMetadata('form_id');
			if ($form_id) {
				$SQL = $K->SQL;
				$qr = $SQL->query("SELECT
					ffield_id,
					ffield_label,
					ffield_required,
					ffield_type
				FROM {$SQL->TBL->forms_fields}
				WHERE form_id = {$form_id}
				  AND ffield_active = 1
				  AND NOT ffield_type = 'submit'
				ORDER BY ffield_sortorder");
				$post = $_POST['ffield'];
				$data = array();
				$error = false;
				$sender_email = null;
				while ($r = $qr->fetch_row()) {
					$id = (int)$r[0];
					if (isset($post[$id]) && strlen($post[$id])) {
						$data[$id] = array(
							'label' => $r[1],
							'value' => $post[$id]
						);
					}
					if ($r[2] && (empty($post[$id]) || ('email' === $r[3] && true !== \Poodle\Input::validateEmail($post[$id])))) {
						$error |= true;
						$data[$id] = array(
							'error' => true,
							'value' => ''
						);
					}
					if ('email' === $r[3]) {
						$sender_email = $post[$id];
					}
				}
				if ($error) {
					$_SESSION['FORMDATA'] = $data;
					\Poodle\URI::redirect($_SERVER['REQUEST_PATH'].'?error='.time());
					return;
				}

				$form = $SQL->uFetchAssoc("SELECT
					form_name name,
					form_email email,
					form_emailaddress emailaddress,
					form_store_db store_db,
					form_result_uri result_uri,
					form_send_email_resource
				FROM {$SQL->TBL->forms}
				WHERE form_id={$form_id}");
				if ($form) {
					if ($form['store_db']) {
						$SQL->TBL->forms_postdata->insert(array(
							'form_id' => $form_id,
							'fpost_time' => time(),
							'fpost_data' => $data
						));
					}

					if ($form['email']) {
						$mailbody = '';
						foreach ($data as $field_id => $field) {
							$mailbody .= $field['label'] . ': ' .$field['value'] . "\n";
						}
						$mailtos = explode(',',$form['emailaddress']);
						$mail = \Poodle\Mail::sender();
						$mail->setFrom($mailtos[0]);
						if (\Poodle::$DEBUG) {
							$mail->addTo($K->IDENTITY->email);
							$mailbody .= "\n\Poodle::\$DEBUG is on, when off this email will be send to: {$form['emailaddress']}\n";
						} else {
							foreach ($mailtos as $mailto) {
								$mail->addTo($mailto);
							}
						}
						$mail->subject = $form['name'];
						$mail->body    = $mailbody;
						$mail->send();
					}

					if ($sender_email && $form['form_send_email_resource']) {
						$mail_resource = \Poodle\Resource::factory($form['form_send_email_resource']);
						$MAIL = \Poodle\Mail::sender();
//						$MAIL->setFrom('noreply@'.$_SERVER['HTTP_HOST']);
						$MAIL->addTo($sender_email);
						$MAIL->body = $mail_resource->toString($MAIL);
						$MAIL->send();
					}

					return \Poodle\URI::redirect($form['result_uri']);
				}
			}
		}
		\Poodle\URI::redirect($_SERVER['REQUEST_PATH'].'?error='.time());
	}

}
