<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail\Send;

class SMTP extends \Poodle\Mail\Send
{

	protected
		$server;

	public static function getConfigOptions($cfg)
	{
		$cfg = \Poodle\Mail\SMTP::parseConfigUri($cfg);

		$transports = stream_get_transports();
		$encryption = array(
			array('label' => 'auto-detect', 'value' => '', 'selected' => !$cfg['scheme'])
		);
		if (in_array('tls', $transports)) {
			$encryption[] = array('label' => 'TLS', 'value' => 'tls', 'selected' => ('tls' === $cfg['scheme']));
			$encryption[] = array('label' => 'STARTTLS', 'value' => 'starttls', 'selected' => ('starttls' === $cfg['scheme']));
		}
		if (in_array('ssl', $transports)) {
//			$encryption[] = array('label' => 'SSL', 'value' => 'ssl', 'selected' => ('ssl' === $cfg['scheme']));
		}

		$auth_options = array();
		$auth_options[] = array('label' => 'auto-detect', 'value' => '', 'selected' => ('' === $cfg['auth']));
		foreach (\Poodle\Mail\SMTP::getAuthMethods() as $method) {
			if (\Poodle\Auth\SASL::isSupported($method)) {
				$auth_options[] = array('label' => $method, 'value' => $method, 'selected' => ($method === $cfg['auth']));
			}
		}

		return array(
			array(
				'name'  => 'host',
				'type'  => 'text',
				'label' => 'Host',
				'value' => $cfg['host']
			),
			array(
				'name'  => 'port',
				'type'  => 'number',
				'label' => 'Port',
				'value' => $cfg['port']
			),
			array(
				'name'  => 'scheme',
				'type'  => 'select',
				'label' => 'Encryption',
				'options' => $encryption
			),
			array(
				'name'  => 'legacy',
				'type'  => 'checkbox',
				'label' => 'Allow legacy encryption',
				'checked' => $cfg['legacy']
			),
			array(
				'name'  => 'auth',
				'type'  => 'select',
				'label' => 'Authentication',
				'options' => $auth_options
			),
			array(
				'name'  => 'user',
				'type'  => 'text',
				'label' => 'Login',
				'value' => $cfg['user']
			),
			array(
				'name'  => 'pass',
				'type'  => 'text',
				'label' => 'Passphrase',
				'value' => $cfg['pass']
			),
/*
			array(
				'name'  => 'timeout',
				'type'  => 'numer',
				'label' => 'timeout',
				'value' => $cfg['timeout']
			),
			array(
				'name'  => 'ehlo',
				'type'  => 'text',
				'label' => 'HELO',
				'value' => $cfg['ehlo']
			),
*/
		);
	}

	public static function getConfigAsString($data)
	{
		if (empty($data['scheme'])) {
			if (empty($data['port'])) {
				if (\Poodle\Mail\SMTP::detectCrypto($data['host'], 587, true)) {
					$data['scheme'] = 'starttls';
					$data['port'] = 587;
				} else if (\Poodle\Mail\SMTP::detectCrypto($data['host'], 465)) {
					$data['scheme'] = 'tls';
					$data['port'] = 465;
				} else {
					$data['port'] = 25;
				}
			} else if (25 == $data['port']
//			 && ('127.0.0.1' === $data['host'] || '::1' === $data['host'] || preg_match('/^(10|172\\.(1[6-9]|2[0-9]|3[01])|192\\.168)\\./', $data['host']))
			) {
				$data['scheme'] = '';
			} else if (465 == $data['port'] || \Poodle\Mail\SMTP::detectCrypto($data['host'], $data['port'])) {
				$data['scheme'] = 'tls';
			} else if (587 == $data['port'] || \Poodle\Mail\SMTP::detectCrypto($data['host'], $data['port'], true)) {
				$data['scheme'] = 'starttls';
			}
		} else if (empty($data['port'])) {
			if ('starttls' == $data['scheme']) {
				$data['port'] = 587;
			} else if ('tls' == $data['scheme']) {
				$data['port'] = 465;
			} else {
				$data['port'] = 25;
			}
		}
		$data['query'] = http_build_query(array(
			'auth' => $data['auth'],
			'legacy' => empty($data['legacy'])?0:1,
		), '', '&');
		return \Poodle\URI::unparse($data);
	}

	public function send()
	{
		if (!$this->server) {
			$this->server = new \Poodle\Mail\SMTP($this->cfg);
		}
		$this->server->connect();

		if ('8bit' === $this->encoding && !$this->server->hasExtension('8BITMIME')) {
			$this->encoding = '7bit';
		}
		if (!$this->server->hasExtension('SMTPUTF8')) {
//			trigger_error("SMTP Server {$cfg['host']}  does not support SMTPUTF8");
//			throw new \Exception("SMTP Server {$cfg['host']} does not support SMTPUTF8");
		}

		if (false !== strpos($this->cfg, 'smtp.office365.com')) {
			if ($this->sender) {
				$this->sender->address = $cfg['user'];
			} else {
				$this->setSender($cfg['user'], $this->from[0]->name);
			}
		}

		$this->prepare($header, $body, self::HEADER_ADD_TO);
		if (empty($body)) {
			$this->error = 'empty body';
			return false;
		}

		try {
			# Set sender address (translates to Return-Path)
			$this->server->from($this->__get('sender')->address);

			# Send to all recipients
			$this->bad_rcpt = array();
			foreach ($this->recipients as $recipients) {
				foreach ($recipients as $recipient) {
					try {
						if (!$this->server->to($recipient->address)) {
							$this->bad_rcpt[] = $recipient->address;
						}
					} catch (\Throwable $e) {
						$this->bad_rcpt[] = $recipient->address;
					}
				}
			}
			if (count($this->bad_rcpt)) {
				throw new \Exception($this->l10n('recipients_failed').implode(', ', $this->bad_rcpt), E_USER_ERROR);
			}

			# Send the data and finalize
			if (!$this->server->data($header . "\r\n\r\n" . $body)) {
				throw new \Exception($this->l10n('data_not_accepted'), E_USER_ERROR);
			}
		} catch (\Throwable $e) {
			try {
				$this->server->reset();
			} catch (\Throwable $dummy) {}
			throw $e;
		}

		return true;
	}

	# Close the active SMTP session if one exists.
	public function close()
	{
		if ($this->server) {
			$this->server->quit();
		}
	}

}
