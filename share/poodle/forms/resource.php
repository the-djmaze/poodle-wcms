<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Forms;

class Resource extends \Poodle\Resource\Basic
{

	public
		$form_data = array(),
		$form_errors = array(),
		$allowed_methods = array('GET','HEAD','POST');

	private
		$form_action,
		$form_files,
		$select_name;

	protected static
		$NO_END_TAGS = array('area', 'base', 'br', 'col', 'hr', 'img', 'input', 'link', 'meta', 'param');

	protected function xml_character_data($parser, $data) { $this->body .= $data; }

	protected function xml_node_end($parser, $name)
	{
		if ('poodle_form' === $name) return;
		if (!in_array(strtolower($name), self::$NO_END_TAGS)) {
			$this->body .= "</{$name}>";
		}
	}

	protected function push_field($attribs)
	{
		$this->form_data[$attribs['name']] = array(
			'id'       => isset($attribs['id']) ? $attribs['id'] : null,
			'label'    => null,
			'maxlength'=> isset($attribs['maxlength']) ? (int)$attribs['maxlength'] : 0,
			'pattern'  => isset($attribs['pattern']) ? $attribs['pattern'] : null,
			'required' => isset($attribs['required']),
			'type'     => isset($attribs['type']) ? $attribs['type'] : null,
			'value'    => null/*$attribs['value']*/,
			'accept'   => isset($attribs['accept']) ? $attribs['accept'] : null,
			'error'    => false,
		);
	}

	protected function xml_node_start($parser, $name, $attribs)
	{
		if ('poodle_form' === $name) return;

		$this->body .= "<{$name}";

		switch (strtolower($name))
		{
		case 'form':
			if (!empty($attribs['action'])) { $this->form_action = $attribs['action']; }
			$attribs['action'] = '';
			$attribs['data-p-challenge'] = \Poodle\AntiSpam\Captcha::generateHidden();
			if ($this->form_files) {
				$attribs['method'] = 'POST';
				$attribs['enctype'] = 'multipart/form-data';
			}
			break;

		case 'label':
			if (!empty($attribs['for'])) {
				$c = empty($attribs['class']) ? '' : $attribs['class'];
				$attribs['tal:attributes'] = "class php:\${RESOURCE/form_errors/{$attribs['for']}}?'{$c} error':'{$c}'";
				$this->form_errors[$attribs['for']] = false;
			}
			break;

		case 'textarea':
			if (!empty($attribs['name'])) {
				$this->push_field($attribs);
				$attribs['tal:content'] = "RESOURCE/form_data/{$attribs['name']}/value";
			}
			if (!empty($attribs['id'])) {
				$c = empty($attribs['class']) ? '' : $attribs['class'];
				$attribs['tal:attributes'] = "class php:\${RESOURCE/form_errors/{$attribs['id']}}?'{$c} error':'{$c}'";
				$this->form_errors[$attribs['id']] = false;
			}
			break;

		case 'input':
			$attribs['type'] = isset($attribs['type']) ? strtolower($attribs['type']) : 'text';
			if (!empty($attribs['name']) && !in_array($attribs['type'], array('button', 'image', 'reset', 'submit'))) {
				$this->push_field($attribs);
				if ('radio' === $attribs['type'] || 'checkbox' === $attribs['type']) {
					if (isset($attribs['value'])) {
						$attribs['tal:attributes'] = "checked php:'{$attribs['value']}'==\${RESOURCE/form_data/{$attribs['name']}/value}";
					} else {
						$attribs['tal:attributes'] = "checked RESOURCE/form_data/{$attribs['name']}/value";
					}
				} else if ('file' !== $attribs['type']) {
					$attribs['tal:attributes'] = "value RESOURCE/form_data/{$attribs['name']}/value";
				}
			}
			if (!empty($attribs['id'])) {
				$c = empty($attribs['class']) ? '' : $attribs['class'];
				$tc = "class php:\${RESOURCE/form_errors/{$attribs['id']}}?'{$c} error':'{$c}';";
				$attribs['tal:attributes'] = (empty($attribs['tal:attributes']) ? $tc : $tc.$attribs['tal:attributes']);
				$this->form_errors[$attribs['id']] = false;
			}
			break;

		case 'select':
			if (empty($attribs['name'])) {
				$this->select_name = null;
			} else {
				$this->push_field($attribs);
				$this->select_name = $attribs['name'];
			}
			if (!empty($attribs['id'])) { $this->form_errors[$attribs['id']] = false; }
			break;
		case 'option':
			if ($this->select_name) {
				$attribs['tal:attributes'] = "selected php:'{$attribs['value']}'==\${RESOURCE/form_data/{$this->select_name}/value}";
			}
			break;
		}

		foreach ($attribs as $n => $v) {
			$this->body .= " {$n}=\"".htmlspecialchars($v)."\"";
		}

		if (in_array(strtolower($name), self::$NO_END_TAGS)) {
			$this->body .= '/';
		}
		$this->body .= '>';
	}

	function __construct($data=array())
	{
		if (!empty($data['body'])) {
			$this->body = '';
//			$this->form_files = !!preg_match('/<input[^>]+type="file"/si', $data['body']);
			$parser = xml_parser_create('UTF-8');
			xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
			xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, false);
			xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
			xml_set_object($parser, $this);
			xml_set_character_data_handler($parser, 'xml_character_data');
			xml_set_element_handler($parser, 'xml_node_start', 'xml_node_end');
			if (xml_parse($parser, '<poodle_form>'.$data['body'].'</poodle_form>')) {
				$data['body'] = $this->body;
			}
			xml_parser_free($parser);
			// Set labels
			if (preg_match_all('#<label[^>]*>(.*?)</label>#s', $data['body'], $m, PREG_SET_ORDER)) {
				foreach ($m as $l) {
					$set = false;
					if (preg_match('#for="([^"]+)"#', $l[0], $m2)) {
						foreach ($this->form_data as $k => &$v) {
							if ($m2[1] == $v['id']) {
								$v['label'] = strip_tags($l[1]);
								$set = true;
								break;
							}
						}
					}
					if (!$set && preg_match('#name="([^"]+)"#', $l[1], $m2)) {
						if (isset($this->form_data[$m2[1]])) {
							$this->form_data[$m2[1]]['label'] = strip_tags(preg_replace('#^.*<span>(.+)</span>.*$#Ds','$1',$l[1]));
						}
					}
				}
			}
		}
		parent::__construct($data);
	}

	public function GET()
	{
		if ($this->body) {
			if (!empty($_SESSION['FORM'])) {
				$f = &$_SESSION['FORM'];
				foreach ($this->form_data as $name => &$a) {
					if (isset($f[$name])) {
						$a['error'] = $f[$name]['error'];
						$a['value'] = $f[$name]['value'];
						if ($a['error'] && isset($a['id'])) {
							$this->form_errors[$a['id']] = true;
						}
					}
				}
				unset($_SESSION['FORM']);
			}

			$this->mtime = time();
			$this->display();
		} else {
			parent::GET();
		}
	}

/*
		if (!empty($_FILES)) {
			foreach ($_FILES as $file) {
				if (!$file['error']) {
					$mail->addAttachment($file['tmp_name'], $file['name']);
				}
			}
		}
*/

	protected function processPostData()
	{
		$errors = array();

		$L10N = \Poodle::getKernel()->L10N;
		foreach ($this->form_data as $name => &$a) {
			$keys = explode('[', str_replace(']', '', $name));
			if ('file' === $a['type']) {
				if (isset($keys[1])) {
//					call_user_func_array(array($_FILES, 'getAsFileObject'), $keys);
					$file = $_FILES->getAsFileObject($keys[0], $keys[1]);
				} else {
					$file = $_FILES->getAsFileObject($name);
				}
				if ($file) {
					if ($a['accept']) {
						$file->validateType($a['accept']);
					}
					if ($file->errno && (UPLOAD_ERR_NO_FILE != $file->errno || $a['required'])) {
						$errors[] = "{$name}: {$file->error}";
						$a['error'] = true;
					} else {
						$value = $file;
					}
				} else if ($a['required']) {
					$errors[] = $name;
					$a['error'] = true;
				}
			} else {
				if (isset($keys[1])) {
					$value = $_POST[$keys[0]];
					for ($i = 1; $i < count($keys); ++$i) {
						if (empty($keys[$i])) {
							$value = implode(', ', $value);
						} else {
							$value = isset($value[$keys[$i]]) ? $value[$keys[$i]] : null;
						}
					}
				} else {
					$value = $_POST[$name];
				}

				if (0 < $a['maxlength']) {
					$value = mb_substr($value, 0, $a['maxlength']);
				}

				if (strlen($value)) {
					if ($a['pattern'] && !preg_match("#^{$a['pattern']}$#Ds", $value)) {
						$a['error'] = true;
					}
					else if ('email' === $a['type']) {
						try {
							\Poodle\Security::checkEmail($value);
						} catch (\Throwable $e) {
							$a['error'] = true;
						}
					}
				} else if ($a['required']) {
					$a['error'] = true;
				}
				if ($a['error']) {
					$errors[] = $name;
				}
			}

			if ($a['error']) {
				\Poodle\Notify::error(sprintf($L10N->get('Invalid value for: %s'), $a['label']?:$name));
			}

			$a['value'] = $value;
		}

		if (\Poodle\AntiSpam\Captcha::validate($_POST) < intval($this->getMetadata('captcha-wait'))) {
			\Poodle\Notify::error($L10N->get('Form validation failed'));
			$errors[] = 'captcha';
		}

		if ($errors) {
			\Poodle\LOG::warning('Form errors', 'Invalid: '.print_r($errors,1), true);
		}

		return !$errors;
	}

	public function POST()
	{
		if (!$this->processPostData()) {
			$_SESSION['FORM'] = $this->form_data;
			\Poodle\URI::redirect($_SERVER['REQUEST_PATH'].'?error='.time());
			return;
		}

		// Mail the submitted data depending on mail-data metadata
		$this->mailData();

		$page = $this->getMetadata('thanks-page');
		if ($page) {
			\Poodle::closeRequest(\Poodle::getKernel()->L10N['Verzonden'], 201, $page);
		} else {
			\Poodle::getKernel()->OUT->display(
				'resources/'.$this->id.'-thanks-'.$this->l10n_id,
				'thank you for submitting', time()
			);
		}
	}

	/**
	 * Send the data that was submitted in the form by email to the given address.
	 * @return boolean|Exception
	 */
	protected function mailData()
	{
		$msg = $from = $from_name = '';

		$mail = \Poodle\Mail::sender();

		// Retrieve mailto address
		$CFG = \Poodle::getKernel()->CFG;
		$email = $CFG->mail->from;
		if (0 === strpos($this->form_action, 'mailto:')) {
			$email = substr($this->form_action, 7);
		} else if ($mail_data_to = $this->getMetadata('mail-data-to')) {
			$email = $mail_data_to;
		} else {
			$email = $mail->from[0]->address;
		}

		foreach ($this->form_data as $k => $a) {
			if (!$from && 'email' === $a['type']) {
				$from = $a['value'];
			}
			if (is_scalar($a['value'])) {
				$msg .= str_pad($k, 20).": {$a['value']}\n";
			} else if (is_array($a['value'])) {
				$msg .= str_pad($k, 20).": ".implode(', ', $a['value']) . "\n";
			} else if ($a['value'] instanceof \Poodle\Input\File) {
				$mail->addAttachment($a['value']->tmp_name, $a['value']->name, null, $a['value']->type);
			}
		}

		if ($email) {
			$mail->setFrom($email, $from_name);
			$mail->addTo($email, $CFG->site->name);
		}
		if ($from) {
//			$mail->setSender(clone $mail->from[0]);
//			$mail->setFrom($from, $from_name);
			$mail->addReplyTo($from, $from_name);
		}
		$mail->subject = $this->title.' '.$_SERVER['HTTP_HOST'];
		$mail->body    = '<html><body>'.nl2br(htmlspecialchars($msg)).'</body></html>';
/*
		if (!empty($_FILES)) {
			foreach ($_FILES as $file) {
				if (!$file['error']) {
					$mail->addAttachment($file['tmp_name'], $file['name']);
				}
			}
		}
*/
//		$mail->Message = '<html><body><img src="cid:poodle-logo" align="right"><pre>'.$msg.'</pre></body></html>';
//		$mail->AddEmbeddedImage(\Poodle::$DIR_BASE.'tpl/default/images/setup/poodle-logo.png', 'poodle-logo', 'logo.png', 'base64', 'image/png');

		return $mail->send();
	}
}
