<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Provider;

class XMPP extends \Poodle\Auth\Provider
{

	protected
		$has_form_fields = true;

	public function getAction($credentials=array())
	{
		// Send code through XMPP
		$cfg = \Poodle::getKernel()->CFG->auth_xmpp->uri;
		if ($cfg && $secret = $this->getSecret($credentials['identity_id'])) {
			$code = \Poodle\TOTP::getCurrentCode($secret[0], 8);
			$XMPP = new \Poodle\XMPP\Client($cfg);
			$XMPP->connect();
			$message = new \Poodle\XMPP\Request\Message($code, $secret[1]);
			$XMPP->send($message);
			$XMPP->disconnect();
		}

		return new \Poodle\Auth\Result\Form(
			array(
				array('name'=>'auth_xmpp', 'type'=>'text', 'label'=>'Enter the security code'),
//				array('name'=>'new_xmpp', 'type'=>'checkbox', 'label'=>'I don\'t have the code, send me a new one'),
			),
			'?auth='.$this->id,
			'auth-xmpp'
		);
	}

	public function createForIdentity(\Poodle\Identity $identity)
	{
		$secret = \Poodle\TOTP::createSecret();
		$secret = "{$secret}:user@example.com";
		$this->updateAuthentication(new \Poodle\Auth\Credentials($identity, $secret));
		return $secret;
	}

	public static function getConfigOptions()
	{
		$cfg = \Poodle::getKernel()->CFG->auth_xmpp->uri;
		$cfg = array_merge(array(
			'scheme' => 'tcp',
			'host'   => '',
			'port'   => '',
			'user'   => '',
			'pass'   => '',
		), parse_url($cfg) ?: array());
		$cfg['user'] = rawurldecode($cfg['user']);
		$cfg['pass'] = rawurldecode($cfg['pass']);
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
				'name'  => 'user',
				'type'  => 'text',
				'label' => 'User',
				'value' => $cfg['user']
			),
			array(
				'name'  => 'pass',
				'type'  => 'text',
				'label' => 'Passphrase',
				'value' => $cfg['pass']
			),
		);
	}

	public static function setConfigOptions($data)
	{
		$cfg = \Poodle\URI::unparse(array_merge(array(
			'scheme' => 'tcp',
			'host'   => '',
			'port'   => '',
			'user'   => '',
			'pass'   => '',
		), $data));
		\Poodle::getKernel()->CFG->set('auth_xmpp', 'uri', $cfg);
	}

	public function authenticate($credentials)
	{
		$identity_id = (int) $credentials['identity_id'];

		if (!isset($credentials['auth_xmpp'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_FAILURE, 'auth_xmpp is missing');
		}

		if (empty($credentials['auth_xmpp'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'auth_xmpp is empty');
		}

		$secret = $this->getSecret($credentials['identity_id']);
		if (!$secret) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'A database record was not found');
		}

		if (!\Poodle\TOTP::verifyCode($credentials['auth_xmpp'], $secret[0], 1, 8)) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'The code verification failed.');
		}

		# Code is correct so lookup user in the database
		$user = \Poodle\Identity\Search::byID($identity_id);
		if (!$user) {
			return new \Poodle\Auth\Result\Error(self::ERR_IDENTITY_NOT_FOUND, 'A database record with the supplied identity_id ('.$identity_id.') could not be found.');
		}

		return new \Poodle\Auth\Result\Success($user);
	}

	protected function getSecret($identity_id)
	{
		$secret = $this->getClaimedIdByIdentity($identity_id);
		return $secret ? explode(':', $secret, 2) : null;
	}

}
