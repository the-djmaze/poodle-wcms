<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Auth;

abstract class Provider extends \Poodle\Auth
{
	protected
		// from auth_providers table
		$id     = 0,
		$class  = '',
		$mode   = 0,
		$is_2fa = false,
		$name   = '',
		// from auth_identities table
		$passphrase = null,
		// Generated with auth_providers_detect table
		$discover_uri = null,
		$identifier = null,
		$has_form_fields = false;

	function __construct(array $config=array())
	{
		foreach ($config as $k => $v) { $this->$k = $v; }
		if (!$this->id) {
			$this->id = self::getIdByClass(get_class($this));
		} else {
			$this->id     = (int)$this->id;
			$this->is_2fa = !!$this->is_2fa;
			$this->mode   = (int)$this->mode;
		}
	}

	public function __get($key)
	{
		return (property_exists($this, $key) ? $this->$key : null);
	}

	abstract public function getAction($credentials=array());

	abstract public function authenticate($credentials);

	public static function getConfigOptions() { return array(); }
	public static function setConfigOptions($data) { }
/*
	public function createForIdentity(\Poodle\Identity $identity)
	{
		return false;
	}
	public static function getQRCode($name, $secret, $issuer = '')
	{
		return false;
	}
*/
	public function updateAuthentication(Credentials $credentials)
	{
		$identity_id = (int)$credentials->identity_id;
		if (1 > $identity_id) {
			throw new \Exception('Invalid $identity_id');
		}

		$credentials->hashPassphrase();
		$credentials->hashClaimedID();

		$SQL = \Poodle::getKernel()->SQL;
		$tbl = $SQL->TBL->auth_identities;
		$where = "auth_provider_id={$this->id} AND auth_claimed_id=".$SQL->quote($credentials->claimed_id);
		if (!$tbl->count($where)) {
			return $tbl->insert(array(
				'identity_id'      => $identity_id,
				'auth_provider_id' => $this->id,
				'auth_claimed_id'  => $credentials->claimed_id,
				'auth_password'    => $credentials->passphrase,
				'auth_claimed_id_info' => $credentials->info,
			));
		}
		return $tbl->update(array(
//			'auth_claimed_id' => $credentials->claimed_id,
			'auth_password' => $credentials->passphrase,
		), "identity_id={$identity_id} AND {$where}");
	}

	protected function getIdentityByClaimedId($id)
	{
		if (empty($id)) {
			return false;
		}
		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->uFetchAssoc("SELECT
			identity_id          id,
			auth_password        passphrase,
			auth_claimed_id_info info
		FROM {$SQL->TBL->auth_identities}
		WHERE auth_provider_id = {$this->id}
		  AND auth_claimed_id = {$SQL->quote($id)}");
	}

	protected function getClaimedIdByIdentity($id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$row = $SQL->uFetchRow("SELECT
			auth_claimed_id
		FROM {$SQL->TBL->auth_identities}
		WHERE auth_provider_id = {$this->id}
		  AND identity_id = " . intval($id));
		return $row ? $row[0] : false;
	}

	public static function validClaimedId($id) { return false; }

	public static function getById($id)
	{
		$id = (int)$id;
		$SQL = \Poodle::getKernel()->SQL;
		$result = $SQL->uFetchAssoc("SELECT
			auth_provider_id id,
			auth_provider_class class,
			auth_provider_is_2fa is_2fa,
			auth_provider_name name,
			auth_provider_mode mode
		FROM {$SQL->TBL->auth_providers}
		WHERE auth_provider_id = {$id}");
		return $result ? new $result['class']($result) : null;
	}

	public static function getActiveById($id)
	{
		$provider = static::getById($id);
		return $provider->mode > 0 ? $provider : null;
	}

	public static function getIdByClass($class)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$id = $SQL->uFetchRow("SELECT auth_provider_id FROM {$SQL->TBL->auth_providers} WHERE auth_provider_class=".$SQL->quote($class));
		return $id ? (int)$id[0] : false;
	}

	public static function getPublicProviders($is_2fa = false)
	{
		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->uFetchAll("SELECT
			auth_provider_id id,
			auth_provider_class class,
			auth_provider_mode mode,
			auth_provider_name name
		FROM {$SQL->TBL->auth_providers}
		WHERE auth_provider_is_2fa = ".($is_2fa?1:0)."
		  AND auth_provider_mode & 1");
	}

	public static function getAdminProviders($is_2fa = false)
	{
		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->uFetchAll("SELECT
			auth_provider_id id,
			auth_provider_class class,
			auth_provider_mode mode,
			auth_provider_name name
		FROM {$SQL->TBL->auth_providers}
		WHERE auth_provider_is_2fa = ".($is_2fa?1:0)."
		  AND auth_provider_mode & 2");
	}

	public static function getPublic2FAProviders()
	{
		return static::getPublicProviders(true);
	}

	public static function getAdmin2FAProviders()
	{
		return static::getAdminProviders(true);
	}

	protected function getAuthURI()
	{
		return \Poodle\URI::abs($_SERVER['REQUEST_PATH'].'?auth='.$this->id);
		return \Poodle\URI::abs(\Poodle\URI::appendArgs($_SERVER['REQUEST_URI'], array('auth' => $this->id)));
	}

}
