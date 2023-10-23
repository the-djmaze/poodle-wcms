<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth\Provider;

class Database extends \Poodle\Auth\Provider
{

	protected
		$has_form_fields = true;

	public function getAction($credentials = array())
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
			'auth-database'
		);
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

		try {
			$user_email = \Poodle\Input::validateEmail($credentials['auth_claimed_id'])
				? \Poodle\Input::lcEmail($credentials['auth_claimed_id'])
				: false;
		} catch (\Throwable $e) {
			$user_email = false;
		}
		$SQL = \Poodle::getKernel()->SQL;
		$id_secure = self::secureClaimedID($credentials['auth_claimed_id']);
		if ($user_email) {
			$identity = $SQL->uFetchAssoc("SELECT
				identity_id   id,
				auth_password passphrase
			FROM {$SQL->TBL->auth_identities} ua
			INNER JOIN {$SQL->TBL->users} u USING (identity_id)
			WHERE auth_provider_id = {$this->id}
			  AND (auth_claimed_id = {$SQL->quote($id_secure)} OR user_email = {$SQL->quote($user_email)})");
		} else {
			$identity = $this->getIdentityByClaimedId($id_secure);
		}
		if (!$identity) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'A database record was not found for '.$credentials['auth_claimed_id']);
		}

		// Verify passphrase
		if (!$this->isValidPassphrase($identity['id'], $credentials['auth_passphrase'], $identity['passphrase'])) {
			return new \Poodle\Auth\Result\Error(self::ERR_CREDENTIAL_INVALID, 'A database record for the supplied claimed_id ('.$credentials['auth_claimed_id'].') was found but, the passphrase verification failed.');
		}

		# Lookup user in the database
		$user = \Poodle\Identity\Search::byID($identity['id']);
		if (!$user) {
			return new \Poodle\Auth\Result\Error(self::ERR_IDENTITY_NOT_FOUND, 'A database record for the supplied identity_id ('.$identity['id'].') could not be found.');
		}

		return new \Poodle\Auth\Result\Success($user);
	}

	/**
	 * Checks to see if the given passphrase is valid for the given identity.
	 *
	 * @param integer $identity_id The identity to check the passphrase for
	 * @param string $plain_passphrase The passphrase to check
	 * @param string $auth_passphrase The passphrase from the database (optional)
	 *
	 * @returns boolean true|false depending on validity
	 */
	public function isValidPassphrase($identity_id, $plain_passphrase, $auth_passphrase = null)
	{
		$identity_id = (int)$identity_id;

		// No identity_id or plain_passphrase given
		if (1 > $identity_id || empty($plain_passphrase)) {
			return false;
		}

		if (!$auth_passphrase) {
			// Retrieve the current identity passphrase
			$SQL = \Poodle::getKernel()->SQL;
			$auth = $SQL->uFetchRow("SELECT
				auth_password
			FROM {$SQL->TBL->auth_identities} ua
			WHERE identity_id = {$identity_id}
			  AND auth_provider_id = {$this->id}");
			$auth_passphrase = $auth[0];
		}

		// Verify given passphrase
		return self::verifyPassphrase($plain_passphrase, $auth_passphrase);
	}

	public function updateAuthentication(\Poodle\Auth\Credentials $credentials)
	{
		$identity_id = (int)$credentials->identity_id;
		if (1 > $identity_id) {
			throw new \InvalidArgumentException('Invalid $identity_id');
		}
		if (!$credentials->claimed_id) {
			$credentials->hashPassphrase();
			return \Poodle::getKernel()->SQL->TBL->auth_identities->update(array(
				'auth_password' => $credentials->passphrase,
			), "identity_id={$identity_id} AND auth_provider_id={$this->id}");
		} else {
			\Poodle::getKernel()->SQL->TBL->auth_identities->delete(array(
				'identity_id' => $identity_id,
				'auth_provider_id' => $this->id
			));
			return parent::updateAuthentication($credentials);
		}
	}

}
