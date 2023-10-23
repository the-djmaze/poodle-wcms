<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Session\Handler;

// Internal/Native
class Builtin extends \SessionHandler
{
	use \Poodle\Session\DB;
/*
	public function close()
	{
		return parent::close();
	}

	public function create_sid()
	{
		return parent::create_sid();
	}

	public function destroy($session_id)
	{
		return parent::destroy($session_id);
	}

	public function open($save_path, $name)
	{
		return parent::open($save_path, $name);
	}

	public function read($id)
	{
		return parent::read($id);
	}
*/
	public function gc($maxlifetime)
	{
		$this->db_gc();
		return parent::gc($maxlifetime);
	}

	public function write($id, $data)
	{
		$this->db_write($id);
		return parent::write($id, $data);
	}

	/**
	 * Not required to implement SessionUpdateTimestampHandlerInterface
	 */
	public function updateTimestamp($id, $data)
	{
		$this->db_updateTimestamp($id);
		// SessionHandler::updateTimestamp does not exists
		return parent::write($id, $data);
	}
}
