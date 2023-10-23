<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Notify
{

	public static function message($msg, $type='info')
	{
		$_SESSION['POODLE_NOTIFICATIONS'][] = array('type' => $type, 'msg' => $msg);
	}

	public static function getAll()
	{
		return empty($_SESSION['POODLE_NOTIFICATIONS']) ? array() : $_SESSION['POODLE_NOTIFICATIONS'];
	}

	public static function getClear()
	{
		$msg = self::getAll();
		self::clear();
		return $msg;
	}

	public static function clear()
	{
		$_SESSION['POODLE_NOTIFICATIONS'] = null;
		unset($_SESSION['POODLE_NOTIFICATIONS']);
	}

	public static function info($msg)    { self::message($msg, 'info'); }
	public static function success($msg) { self::message($msg, 'success'); }
	public static function warning($msg) { self::message($msg, 'warning'); }
	public static function error($msg)   { self::message($msg, 'error'); }
}
