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
 * Root: /
 * Allow: 1 (view)
 *
 * Root: /sub-folder
 * Allow: 0 (nothing) supersedes /
 *
 * Root: admin
 * Allow: 1 (view admin area)
 *
 * Root: *
 * Allow: 1 (view admin and / area)
 */

namespace Poodle\ACL;

class Groups
{

	protected
		$groups = '0',
		$rules  = array();

	protected static
		$actions;

	function __construct(array $groups=array())
	{
		$this->groups = implode(',', $groups);
	}

	# Check visitor permission
	public function view    ($path=null) { return $this->check($path, \Poodle\ACL::VIEW); }
	public function reply   ($path=null) { return $this->check($path, \Poodle\ACL::REPLY); }
	public function create  ($path=null) { return $this->check($path, \Poodle\ACL::CREATE); }
	public function edit    ($path=null) { return $this->check($path, \Poodle\ACL::EDIT); }
	public function delete  ($path=null) { return $this->check($path, \Poodle\ACL::DELETE); }

	public function admin($path='', $action=\Poodle\ACL::VIEW)
	{
		return $this->check("admin{$path}", $action);
	}

	public function __call($name, $arguments)
	{
		if (!self::$actions) {
			self::$actions = \Poodle\ACL::getActions();
		}
		$action_id = array_search($name, self::$actions, true);
		if (false === $action_id) {
			\Poodle\Debugger::trigger('Unknown method: '.$action, __FILE__);
			return false;
		}
		return $this->check($arguments[0], $action_id);
	}

	public function check($path, $action=\Poodle\ACL::VIEW)
	{
		if (!$this->groups) { $this->groups = 0; }
		if (!is_array($this->rules)) { $this->rules = array(); }
		return self::isAllowed($path, $action, $this->groups, $this->rules);
	}

	protected static $already_queried = array();
	public static function isAllowed($path, $action, $groups, array &$rules=array())
	{
		if (POODLE_CLI) {
			return true;
		}
		$K = \Poodle::getKernel();
		if (!$K->SQL || !$K->SQL->TBL || !isset($K->SQL->TBL->acl_groups)) {
			return false;
		}

		if (!strlen($path)) {
			$path = $_SERVER['PATH_INFO'];
		}
		if (!\Poodle\ACL::isValidPath($path)) {
//			throw new \Exception('Bad ACL format: '.$path);
			trigger_error('Bad ACL format: '.$path, E_USER_WARNING);
			return false;
		}

		if (is_array($groups)) {
			$groups = implode(',',$groups);
		}
		if (!preg_match('#^([0-9],?)*[0-9]$#D', $groups)) {
			throw new \Exception('Bad groups format: '.$groups);
		}

		if (!self::$actions) {
			self::$actions = \Poodle\ACL::getActions();
		}
		if (!is_int($action)) {
			$int = array_search($action, self::$actions, true);
			if (false === $int) {
				\Poodle\Debugger::trigger('Unknown action: '.$action, __FILE__);
				return false;
			}
			$action = $int;
		} else if (!isset(self::$actions[$action])) {
			\Poodle\Debugger::trigger('Unknown action number: '.$action, __FILE__);
			return false;
		}

		$acl_paths = \Poodle\ACL::resolvePaths($path);
		$q_key = $groups.implode(',',$acl_paths);
		array_unshift($acl_paths, '*');
		if (!in_array($q_key, self::$already_queried)) {
			$q_acl_paths = array_diff($acl_paths, array_keys($rules));
			if ($q_acl_paths) {
				$q_acl_paths = implode(',',$K->SQL->prepareValues($q_acl_paths));
				$result = $K->SQL->uQuery("SELECT acl_path, acl_a_ids
				FROM {$K->SQL->TBL->acl_groups}
				WHERE group_id IN ({$groups})
				  AND acl_path IN ({$q_acl_paths})
				ORDER BY LENGTH(acl_path) DESC");
				while ($row = $result->fetch_row()) {
					$vd = $row[0];
					$rules[$vd] = (isset($rules[$vd]) ? $rules[$vd].',' : '') . $row[1];
					$rules[$vd] = (false === strpos($rules[$vd], '*') ? implode(',', array_unique(explode(',', $rules[$vd]))) : '*');
				}
				self::$already_queried[] = $q_key;
			}
		}

		$rights = 0;
		foreach ($acl_paths as $vd) {
			if (isset($rules[$vd])) {
				$rights = $rules[$vd];
			}
		}

		return \Poodle\ACL::isValidAction($action, $rights ?: 0);
	}

}
