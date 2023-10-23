<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/
/**
 * Default setup is to disallow all access. This is for Administrators,
 * Moderators, Members and Visitors.
 * You may overrule this by granting access for specific paths.
 *
 * Root: *
 * Allow: 1 (view)
 *
 * Root: sub-folder/
 * Allow: 0 (nothing) supersedes *
 */

namespace Poodle;

abstract class ACL
{

	const
		VIEW   = 1,
		REPLY  = 2,
		CREATE = 3,
		EDIT   = 4,
		DELETE = 5;

	# Check visitor permission
	public static function view    ($path=null) { return static::check($path, static::VIEW); }
	public static function reply   ($path=null) { return static::check($path, static::REPLY); }
	public static function create  ($path=null) { return static::check($path, static::CREATE); }
	public static function edit    ($path=null) { return static::check($path, static::EDIT); }
	public static function delete  ($path=null) { return static::check($path, static::DELETE); }

	public static function admin($path='*', $action=self::VIEW)
	{
		return static::check("admin/{$path}", $action);
	}

	public static function check($path, $action=self::VIEW)
	{
		$ID = \Poodle::getKernel()->IDENTITY;
		if (!$ID) {
			trigger_error('IDENTITY not found');
			return false;
		}
		if (!$ID->ACL) {
			throw new \Exception('IDENTITY->ACL not found in '.get_class($ID));
		}
		return $ID->ACL->check($path, $action);
	}

	public static function getActions()
	{
		static $actions;
		if (!$actions) {
			$actions = array();
			$K = \Poodle::getKernel();
			$result = $K->SQL->uQuery("SELECT acl_a_id, acl_a_name FROM {$K->SQL->TBL->acl_actions}");
			while ($row = $result->fetch_row()) {
				$actions[(int)$row[0]] = $row[1];
			}
		}
		return $actions;
	}

	public static function resolvePaths($path)
	{
		$path = rtrim($path,'*');
		if (!strlen($path) || !static::isValidPath($path)) {
			trigger_error("Invalid ACL path '{$path}'");
			return false;
		}

		$fvd = '';
		$paths = explode('/', $path);
		$acl_paths = array();
		foreach ($paths as $i => $vd) {
			if ($vd) {
				$acl_paths[] = $fvd . $vd;
			}
			if ($vd || !$i) {
				$fvd .= "{$vd}/";
				$acl_paths[] = $fvd;
				$acl_paths[] = $fvd . '*';
			}
		}

		return $acl_paths;
	}

	public static function isValidAction($action, $actions)
	{
		return ('*' === $actions || $action == $actions || 0 < preg_match('#(^|,)'.$action.'(,|$)#D', $actions));
		return ('*' === $actions || 0 < in_array($action, explode(',',$actions)));
		return ('*' === $actions || 0 < preg_match('#^('.strtr($actions,',','|').')$#D', $action));
	}

	public static function isValidPath($path)
	{
		return !!preg_match('#^(/|/?([^\\s&<>/\'"\\#\\?]+)((/[^\\s&<>/\'"\\#\\?]*)+)?)$#D', $path);
	}

}
