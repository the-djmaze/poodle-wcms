<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\ACL;

abstract class Admin
{
	# Check visitor permission
	public static function view    ($path) { return self::check($path, \Poodle\ACL::VIEW); }
	public static function reply   ($path) { return self::check($path, \Poodle\ACL::REPLY); }
	public static function create  ($path) { return self::check($path, \Poodle\ACL::CREATE); }
	public static function edit    ($path) { return self::check($path, \Poodle\ACL::EDIT); }
	public static function delete  ($path) { return self::check($path, \Poodle\ACL::DELETE); }
	public static function check   ($path, $action=\Poodle\ACL::VIEW)
	{
		$ID = \Poodle::getKernel()->IDENTITY;
		if (!$ID) { throw new \Exception('IDENTITY not found'); }
		if (!$ID->ACL) { throw new \Exception('IDENTITY->ACL not found in '.get_class($ID)); }
		return \Poodle::getKernel()->IDENTITY->ACL->check("admin/{$path}", $action);
	}

	protected
		$actions = array(),
		$group_id = 0;

	function __construct()
	{
		$SQL = \Poodle::getKernel()->SQL;
		$result = $SQL->uQuery('SELECT acl_a_id, acl_a_name FROM '.$SQL->TBL->acl_actions);
		while ($row = $result->fetch_row()) { $this->actions[$row[1]] = $row[0]; }

		if (isset(\Poodle::$PATH[0]))
		{
			$this->group_id = (int)\Poodle::$PATH[0];
		}
	}

	public function POST()
	{
		if ($_POST->exist('add') && $_POST->exist('save'))
		{
			\Poodle\Report::error('Issue with data');
		}
		$SQL = \Poodle::getKernel()->SQL;
		$ids_count = count($this->actions);
		if ($_POST->exist('add'))
		{
			$item = $_POST['new_acl'];
			$c = count($item['ids']);
			$acl_a_ids = '0';
			if ($c === $ids_count) { $acl_a_ids = '*'; }
			else if (0 < $c) { $acl_a_ids = implode(',', array_keys($item['ids'])); }
			$SQL->exec("INSERT INTO {$SQL->TBL->acl_groups} (group_id, acl_path, acl_a_ids) VALUES ({$this->group_id}, ".$SQL->quote($item['path']).", ".$SQL->quote($acl_a_ids).")");
		}
		if ($_POST->exist('save'))
		{
			foreach ($_POST['acl'] as $item)
			{
				$c = count($item['ids']);
				$acl_a_ids = '0';
				if ($c === $ids_count) { $acl_a_ids = '*'; }
				else if (0 < $c) { $acl_a_ids = implode(',', array_keys($item['ids'])); }
				$SQL->exec("UPDATE {$SQL->TBL->acl_groups} SET acl_a_ids=".$SQL->quote($acl_a_ids)." WHERE group_id={$this->group_id} AND acl_path=".$SQL->quote($item['path']));
			}
		}
	}

	public function run()
	{
		$K = \Poodle::getKernel();
//		$K->OUT->crumb_img = 'permissions';

		$result = $K->SQL->uQuery('SELECT
			group_id,
			group_name,
			group_parent,
			CASE WHEN group_parent THEN CONCAT(group_parent, \'-\', group_id) ELSE CONCAT(group_id, \'-0\') END
		FROM '.$K->SQL->TBL->groups.'
		ORDER BY 4 ASC');
		$groups = array(0=>$K->OUT->L10N['Anonymous']);
		$K->OUT->set_loop_vars('groups', array('ID' => 0, 'NAME' => $groups[0]));
		while ($row = $result->fetch_row())
		{
			$groups[$row[0]] = $row[1];
			$K->OUT->set_loop_vars('groups', array('ID' => $row[0], 'NAME' => $row[1]));
		}

		foreach ($this->actions as $name => $id) {
			$K->OUT->set_loop_vars('acl_actions', array('ID' => $id, 'NAME' => $K->OUT->L10N->get($name)));
		}

		$K->OUT->Title($groups[$this->group_id]);
		$K->OUT->TitleAppend($K->OUT->L10N['Permissions']);
		$K->OUT->crumbs->append($K->OUT->L10N['Permissions']);
		$K->OUT->crumbs->append($groups[$this->group_id]);
		$K->OUT->start();
		if (isset($this->group_id))
		{
			$i = 0;
			$result = $K->SQL->uQuery("SELECT acl_path, acl_a_ids FROM {$K->SQL->TBL->acl_groups} WHERE group_id={$this->group_id}");
			$paths = array();
			while ($row = $result->fetch_row())
			{
				$paths[] = $row[0];
				$K->OUT->set_loop_vars('group_perms', array(
					'PATH'  => $K->OUT->specialchars($row[0]),
					'INDEX' => $i
				));
				foreach ($this->actions as $a_id) {
					$K->OUT->set_loop_vars('group_perms.actions', array(
						'ID'  => $K->OUT->specialchars($a_id),
						'ACTIVE' => \Poodle\ACL::isValidAction($a_id, $row[1])
					));
				}
				++$i;
			}

			$K->OUT->set_loop_vars('pages', array('TITLE' => '', 'TEXT' => '[home]', 'VALUE' => 0, 'SELECTED' => false));
//			$result = $K->SQL->query('SELECT page_id, page_uri, REPLACE(page_uri, \'/\', \' \') FROM '.$K->SQL->TBL->pages.' ORDER BY 3');
			$result = $K->SQL->query('SELECT page_id, page_uri FROM '.$K->SQL->TBL->pages.' ORDER BY 2');
			while ($uri = $result->fetch_row())
			{
				$base = \Poodle\Tree::convertURI($uri[1]);
				$K->OUT->set_loop_vars('pages', array(
					'TITLE' => $uri[1],
					'TEXT'  => $base,
					'VALUE' => $uri[1],
					'CLASS' => 'lvl'.substr_count($uri[1],'/'),
					'SELECTED' => false,
					'DISABLED' => in_array($uri[1], $paths),
				));
			}

			$K->OUT->display('admin/acl/details');
		}
	}

	public static function getGroupsPermissions($uri)
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
	}
}
