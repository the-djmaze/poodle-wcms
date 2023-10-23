<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Session\Handler;

class Database extends \Poodle\Session\Handler
{
	public function read($id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$data = $SQL->uFetchRow("SELECT sess_expiry, sess_timeout, sess_value
			FROM {$SQL->TBL->sessions} WHERE sess_id={$SQL->quote($id)}");
		if ($data && $data[0] >= time()) {
			$this->SESSION->setTimeout($data[1]);
			return $SQL->unescapeBinary($data[2]);
		}
		return '';
	}

	public function write($id, $data)
	{
		return $this->db_write($id, $data);
	}
}
