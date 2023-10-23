<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Session;

abstract class Handler implements \SessionHandlerInterface /*, \SessionUpdateTimestampHandlerInterface, \SessionIdInterface */
{
	use \Poodle\Session\DB;

	public function close()
	{
		return true;
	}
/*
	public function create_sid()
	{
		return false;
	}
*/
	public function destroy($id)
	{
		return $this->db_destroy($id);
	}

	public function gc($maxlifetime)
	{
		return $this->db_gc();
	}

	public function open($save_path, $name)
	{
		return true;
	}
/*
	public function read($id)
	{
		return false;
	}
*/
	/**
	 * Not required to implement SessionUpdateTimestampHandlerInterface
	 */
	public function updateTimestamp($id, $data)
	{
		return $this->db_updateTimestamp($id);
	}
/*
	public function validateId($id)
	{
		return $this->db_validateId($id);
	}
*/
	public function write($id, $data)
	{
		return $this->db_write($id);
	}
}
