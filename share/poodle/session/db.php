<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Session;

/**
 * This trait is used inside Session handlers to write some data to the database.
 * That way you can use it to see who is online and where.
 */
trait DB
{
	protected
		$SESSION;

	function __construct(\Poodle\Session $obj)
	{
		$this->SESSION = $obj;
	}

	final protected function db_destroy($id)
	{
		$K = \Poodle::getKernel();
		if (!empty($K->SQL->TBL->sessions)) {
			$K->SQL->TBL->sessions->delete(array('sess_id'=>$id));
		}
		return true;
	}

	# garbage collector
	final protected function db_gc()
	{
		$K = \Poodle::getKernel();
		if (!empty($K->SQL->TBL->sessions)) {
			return $K->SQL->TBL->sessions->delete('sess_expiry < '.time());
		}
		return 0;
	}

	final protected function db_updateTimestamp($id)
	{
		$K = \Poodle::getKernel();
		if (empty($K->SQL->TBL->sessions)) {
			return false;
		}
		$K->SQL->query("UPDATE {$K->SQL->TBL->sessions}
			SET sess_expiry = sess_timeout + ".time()."
			WHERE sess_id = {$K->SQL->quote($id)}");
		return true;
	}

	final protected function db_validateId($id)
	{
		$K = \Poodle::getKernel();
		if (!empty($K->SQL->TBL->sessions)) {
			return (bool) $K->SQL->TBL->sessions->count("sess_id = {$SQL->quote($id)} AND sess_expiry >= " . time());
		}
	}

	final protected function db_write($id, $value = '')
	{
		$K = \Poodle::getKernel();
		if (empty($K->SQL->TBL->sessions)) {
			return false;
		}
		$SQL = $K->SQL;
		return $SQL->TBL->sessions->upsertPrepared(
			array(
				'sess_id'      => $SQL->quote($id),
				'sess_timeout' => (int) $this->SESSION->getTimeout(),
				'sess_expiry'  => time() + $this->SESSION->getTimeout(),
				'sess_value'   => $SQL->quoteBinary($value),
				'identity_id'  => $SQL->quote($K->IDENTITY->id),
				'sess_ip'         => $SQL->quote($_SERVER['REMOTE_ADDR']),
				'sess_uri'        => $SQL->quote($_SERVER['REQUEST_URI']),
				'sess_user_agent' => $SQL->quote($_SERVER['HTTP_USER_AGENT']),
			),
			array(
				'sess_expiry'  => time() + $this->SESSION->getTimeout(),
				'sess_value'   => $SQL->quoteBinary($value),
				'identity_id'  => $SQL->quote($K->IDENTITY->id),
			),
			array(
				'sess_id'      => $SQL->quote($id),
			)
		);
		return true;
	}

}
