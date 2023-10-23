<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Provider;

class TOTP extends \Poodle\Auth\Provider
{

	protected
		$has_form_fields = true;

	public function getAction($credentials=array())
	{
		return new \Poodle\Auth\Result\Form(
			array(
				array(
					'name'=>'auth_totp',
					'type'=>'text',
					'label'=>'Enter the security code',
					'inputmode'=>'numeric',
					'pattern'=>'[0-9]{6,}'
				),
//				array('name'=>'new_totp', 'type'=>'checkbox', 'label'=>'I don\'t have the code, send me a new one'),
			),
			'?auth='.$this->id,
			'auth-totp'
		);
	}

	public function createForIdentity(\Poodle\Identity $identity)
	{
		$secret = \Poodle\TOTP::createSecret();
		$this->updateAuthentication(new \Poodle\Auth\Credentials($identity, $secret));
		return $secret;
	}

	public function authenticate($credentials)
	{
		$identity_id = (int) $credentials['identity_id'];

/*
		if (!empty($credentials['new_totp'])) {
			$user = \Poodle\Identity\Search::byID($identity_id);
			if ($user) {
				$secret = $this->createForIdentity($user);
				// TODO: mail new $secret to identity
				return new \Poodle\Auth\Result\Success(null);
			}
		}
*/

		if (!isset($credentials['auth_totp'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_FAILURE, 'auth_totp is missing');
		}

		if (empty($credentials['auth_totp'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'auth_totp is empty');
		}

		$secret = $this->getClaimedIdByIdentity($identity_id);
		if (!$secret) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'A database record was not found');
		}

		if (!\Poodle\TOTP::verifyCode($credentials['auth_totp'], $secret)) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'The code verification failed.');
		}

		# Code is correct so lookup user in the database
		$user = \Poodle\Identity\Search::byID($identity_id);
		if (!$user) {
			return new \Poodle\Auth\Result\Error(self::ERR_IDENTITY_NOT_FOUND, 'A database record with the supplied identity_id ('.$identity_id.') could not be found.');
		}

		return new \Poodle\Auth\Result\Success($user);
	}

	public function updateAuthentication(\Poodle\Auth\Credentials $credentials)
	{
		$credentials->hash_claimed_id = false;
		return parent::updateAuthentication($credentials);
	}

	public static function getQRCode($name, $secret, $issuer = '')
	{
		return \Poodle\TOTP::getQRCode($name, $secret, $issuer);
	}

	protected static function getUri($name, $secret, $issuer = '')
	{
		return \Poodle\TOTP::getUri($name, $secret, $issuer);
	}

}
