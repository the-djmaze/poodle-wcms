<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Provider;

class IMAP extends \Poodle\Auth\Provider
{

	protected
		$has_form_fields = true;

	public function getAction($credentials=array())
	{
		$value = null;
		if (!empty($credentials['auth_claimed_id'])) {
			$value = $credentials['auth_claimed_id'];
		} else
		if (!empty($credentials['openid_identifier'])) {
			$value = $credentials['openid_identifier'];
		}
		return new \Poodle\Auth\Result\Form(
			array(
				array('name'=>'auth_claimed_id', 'type'=>'text',     'label'=>'Username', 'value'=>$value),
				array('name'=>'auth_passphrase', 'type'=>'password', 'label'=>'Passphrase'),
			),
			'?auth='.$this->id,
			'auth-imap'
		);
	}

	protected static function getConfig()
	{
		$name = 'auth_' . strtolower(substr(static::class, strrpos(static::class, '\\') + 1));
		$cfg = \Poodle::getKernel()->CFG->$name;
		return array_merge(array(
			'scheme' => '',
			'host'   => '',
			'port'   => '',
			'domain' => $cfg->domain,
		), parse_url($cfg->mailbox) ?: array());
	}

	public static function getConfigOptions()
	{
		$cfg = static::getConfig();
		return array(
			array(
				'name'  => 'host',
				'type'  => 'text',
				'label' => 'Server',
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
				'options' => array(
					array('label' => 'none (port 143)', 'value' => '', 'selected' => !$cfg['scheme']),
					array('label' => 'STARTTLS (port 143)', 'value' => 'tls', 'selected' => 'tls' === $cfg['scheme']),
					array('label' => 'SSL (port 993)', 'value' => 'ssl', 'selected' => 'ssl' === $cfg['scheme'])
				)
			),
			array(
				'name'  => 'domain',
				'type'  => 'text',
				'label' => 'Domain',
				'value' => $cfg['domain']
			),
		);
	}

	public static function setConfigOptions($data)
	{
		$name = 'auth_' . strtolower(substr(static::class, strrpos(static::class, '\\') + 1));
		$cfg = \Poodle\URI::unparse(array_merge(array(
			'scheme' => '',
			'host'   => '',
			'port'   => '',
		), $data));
		$CFG = \Poodle::getKernel()->CFG;
		$CFG->set($name, 'mailbox', $cfg);
		$CFG->set($name, 'domain', $data['domain']);
	}

	public function authenticate($credentials)
	{
		if (!isset($credentials['auth_claimed_id']) && !isset($credentials['auth_passphrase'])) {
			return $this->getAction($credentials);
		}

		if (!isset($credentials['auth_claimed_id'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_FAILURE, 'auth_claimed_id is missing');
		}

		if (!isset($credentials['auth_passphrase'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_FAILURE, 'auth_passphrase is missing');
		}

		if (empty($credentials['auth_claimed_id'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'auth_claimed_id is empty');
		}

		if (empty($credentials['auth_passphrase'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'auth_passphrase is empty');
		}

		$cfg = static::getConfig();
		$mailbox = $cfg['host'];
		if (empty($cfg['port'])) {
			if ('ssl' === $cfg['scheme']) {
				$mailbox .= ':993';
			} else {
				$mailbox .= ':143';
			}
		}
		$mailbox .= '/imap/readonly';
		switch ($cfg['scheme'])
		{
		case 'tls': $mailbox .= '/tls'; break;
		case 'ssl': $mailbox .= '/ssl'; break;
		}

        if (preg_match('#^(.+)@'.preg_quote($cfg['domain']).'$#D', $credentials['auth_claimed_id'], $id)) {
			$credentials['auth_claimed_id'] = $id[1];
		}
		$email = $credentials['auth_claimed_id'] . "@{$cfg['domain']}";
		$mbox = imap_open("{{$mailbox}}INBOX", $email, $credentials['auth_passphrase']);
		if (!$mbox) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, "Invalid IMAP {$cfg->domain} user {$credentials['auth_claimed_id']}: ".imap_last_error());
		}
		imap_close($mbox);

		$user = \Poodle\Identity\Search::byEmail($email);
		if (!$user) {
			$user = \Poodle\Identity::factory(array(
				'nickname'  => $credentials['auth_claimed_id'],
				'email'     => $email,
				'givenname' => $credentials['auth_claimed_id'],
				'surname'   => '',
				'language'  => \Poodle::getKernel()->L10N->lng,
				'timezone'  => date_default_timezone_get(),
			));
		}
		return $user;
	}

}
