<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Groups
{

	const
		OPEN    = 0,
		CLOSED  = 1,
		HIDDEN  = 2,
		USER    = 4,
		PENDING = 8;

	# Load user's active group memberships
	public static function getForIdentity($id)
	{
		$groups = array();
		if ($id) {
			$K = \Poodle::getKernel();
			$SQL = $K->SQL;
			$result = $SQL->uQuery("SELECT
				g.group_id,
				g.group_name
			FROM {$SQL->TBL->groups} g, {$SQL->TBL->groups_users} ug
			WHERE ug.group_id=g.group_id AND ug.identity_id={$id} AND ug.identity_status>0");
			while (list($g_id, $g_name) = $result->fetch_row()) {
				$groups[$g_id] = $g_name;
			}
		} else {
			return array(0 => 'Anonymous');
		}
		return $groups;
	}

	# Get group data
	public static function get($id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$group = $SQL->uFetchAssoc('SELECT * FROM '.$SQL->TBL->groups.' WHERE group_id='.(int)$id);
		if (!$group || !is_array($group)) { return false; }
		$SQL->removePrefix($group, 'group');
		return $group;
	}
/*
	# Return groupname if visitor is in the group, otherwise false
	public static function member($id)
	{
		if (!is_array($id)) { $id = array($id); }
		$K = \Poodle::getKernel();
		foreach ($id as $g_id) {
			if (isset($K->IDENTITY->groups[$g_id])) { return $K->IDENTITY->groups[$g_id]; }
		}
		return false;
	}

	# Return true if visitor is an pending group member, otherwise false
	public static function pending($id)
	{
		$K = \Poodle::getKernel();
		return ($K->SQL->count('groups_users', 'group_id='.(int)$id.' AND identity_id='.$K->IDENTITY->id.' AND identity_status=0') > 0);
	}
*/
	public static function listMembers($id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->uFetchAll('SELECT g.group_id, u.identity_id as id, u.user_nickname as nickname, u.user_email as email FROM '.$SQL->TBL->groups_users.' AS g
			INNER JOIN '.$SQL->TBL->users.' AS u USING (identity_id) WHERE g.identity_status>0 AND g.group_id IN ('.$id.') ORDER BY u.user_nickname');
	}

	public static function listAll()
	{
		static $groups = null;
		if (!$groups) {
			$K = \Poodle::getKernel();
			$groups = array($K->L10N['Anonymous'], $K->L10N['Members'], $K->L10N['Moderators'], $K->L10N['Administrators']);
			$result = $K->SQL->uQuery('SELECT group_id, group_name FROM '.$K->SQL->TBL->groups.'
			WHERE group_id>3 ORDER BY group_name');
			while (list($group_id, $group_name) = $result->fetch_row()) {
				$groups[$group_id] = $group_name;
			}
		}
		return $groups;
	}

	public static function listPublic()
	{
		static $groups = null;
		if (!$groups) {
			$SQL = \Poodle::getKernel()->SQL;
			$result = $SQL->uQuery('SELECT group_id, group_name FROM '.$SQL->TBL->groups.' WHERE group_type < '.self::HIDDEN);
			while (list($group_id, $group_name) = $result->fetch_row()) {
				$groups[$group_id] = $group_name;
			}
		}
		return $groups;
	}

	public static function listSubGroupsOf($parent)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$result = $SQL->uQuery('SELECT group_id, group_name FROM '.$SQL->TBL->groups.' WHERE group_parent='.$parent.' AND group_type < '.self::USER);
		$groups = array();
		while ($row = $result->fetch_row()) { $groups[$row[0]] = $row[1]; }
		natcasesort($groups);
		return $groups;
	}

	public static function listAdmins()
	{
		return self::getGroups(3);
	}

	public static function listModerators()
	{
		return self::getGroups(2);
	}

	public static function addIdentityTo($group_id, $identity_id, $pending=false)
	{
		\Poodle::getKernel()->SQL->TBL->groups_users->insert(array(
			'group_id'   => $group_id,
			'identity_id'=> $identity_id,
			'identity_status' => $pending?0:1
		));
		return true;
	}

	public static function removeIdentityFrom($group_id, $identity_id)
	{
		\Poodle::getKernel()->SQL->TBL->groups_users->delete(array(
			'group_id'   => $group_id,
			'identity_id'=> $identity_id,
		));
		return true;
	}

	public static function setUserGroups($identity_id, array $group_ids)
	{
		$tbl = \Poodle::getKernel()->SQL->TBL->groups_users;
		$tbl->delete(array('identity_id'=> $identity_id));
		foreach ($group_ids as $gid) {
			self::addIdentityTo($gid, $identity_id);
		}
		return true;
	}

	protected static function getGroups($id)
	{
		static $groups = array();
		if (!isset($groups[$id])) {
			$groups[$id] = array();
			$SQL = \Poodle::getKernel()->SQL;
			$result = $SQL->uQuery("SELECT
				group_id,
				group_name
			FROM {$SQL->TBL->groups}
			WHERE group_id={$id} OR group_parent={$id}
			ORDER BY 1");
			while ($r = $result->fetch_row()) {
				$groups[$id][$r[0]] = $r[1];
			}
		}
		return $groups[$id];
	}

	// Called by \Poodle\Identity->delete()
	public static function onIdentityDelete(\Poodle\Events\Event $event)
	{
		if ($event->target instanceof \Dragonfly\Identity) {
			\Dragonfly::getKernel()->SQL->TBL->groups_users->delete(array(
				'identity_id' => $event->target->id
			));
		}
	}

}
