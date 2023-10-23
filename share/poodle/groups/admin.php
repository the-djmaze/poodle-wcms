<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Groups;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Groups',
		$allowed_methods = array('GET','HEAD','POST');

	public function GET()
	{
		if (ctype_digit(\Poodle::$PATH[1])) {
			$this->editGroup(\Poodle::$PATH[1]);
		} else {
			$this->viewList();
		}
	}

	public function viewList()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;

		$OUT->groups = array();
		$qr = $SQL->query("SELECT
			group_id   id,
			group_type type,
			group_name name
		FROM {$SQL->TBL->groups}
		WHERE group_parent = 0");
		while ($r = $qr->fetch_assoc()) {
			$r['sub_groups'] = array();
			$OUT->groups[$r['id']] = $r;
		}
		$qr = $SQL->query("SELECT
			group_id     id,
			group_parent parent,
			group_type   type,
			group_name   name
		FROM {$SQL->TBL->groups}
		WHERE group_parent > 0
		  AND NOT group_type & " . \Poodle\Groups::USER);
		while ($r = $qr->fetch_assoc()) {
			$p = $r['parent'];
			unset($r['parent']);
			$OUT->groups[$p]['sub_groups'][] = $r;
		}
		$OUT->display('poodle/groups/admin/index');
	}

	public function editGroup($group_id)
	{
		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		$SQL = $K->SQL;

		if ($group_id) {
			$OUT->group = $SQL->uFetchAssoc("SELECT
				group_parent      parent,
				group_type        type,
				group_name        name,
				group_description description
			FROM {$SQL->TBL->groups}
			WHERE group_id = {$group_id}");
		} else {
			$OUT->group = array(
				'parent' => 0,
				'type' => 0,
				'name' => '',
				'description' => ''
			);
		}

		if ($group_id < 1 || $group_id > 3) {
			$OUT->parent_groups = $SQL->query("SELECT
				group_id   id,
				group_name name
			FROM {$SQL->TBL->groups}
			WHERE group_parent = 0
			  AND NOT group_id = {$group_id}");
		} else {
			$OUT->parent_groups = false;
		}

		$OUT->crumbs->append($OUT->group['name']);

		$OUT->acl_actions = $acl_actions = \Poodle\ACL::getActions();
		$OUT->group_acl = array();
		$qr = $SQL->query("SELECT
			acl_path,
			acl_a_ids,
			group_id
		FROM {$SQL->TBL->acl_groups}
		WHERE group_id IN (0, {$group_id}, {$OUT->group['parent']})
		ORDER BY acl_path");
		while ($r = $qr->fetch_row()) {
			$allowed = explode(',',$r[1]);
			$acl = array(
				'path' => $r[0],
				'actions' => array()
			);
			foreach ($acl_actions as $a_id => $a_name) {
				$acl['actions'][] = array(
					'id'  => $a_id,
					'active' => ('*'===$r[1]) || in_array($a_id, $allowed)
				);
			}
			$OUT->group_acl[] = $acl;
		}

		$OUT->group_members = $SQL->query("SELECT
			identity_id     id,
			user_nickname   nickname,
			identity_status status
		FROM {$SQL->TBL->groups_users}
		INNER JOIN {$SQL->TBL->users} USING (identity_id)
		WHERE group_id = {$group_id}
		ORDER BY user_nickname");

		$OUT->display('poodle/groups/admin/group');
	}

	public function POST()
	{
		if (ctype_digit(\Poodle::$PATH[1])) {
			$K = \Poodle::getKernel();
			$group_id = (int) \Poodle::$PATH[1];
			if ($group_id) {
				$K->SQL->TBL->groups->update(array(
					'group_parent'      => (int) $_POST->uint('group', 'parent'),
					'group_type'        => array_sum($_POST['group']['type']),
					'group_name'        => $_POST->raw('group', 'name'),
					'group_description' => $_POST->raw('group', 'description'),
				), "group_id = {$group_id}");
				$msg = $K->L10N->get('The changes have been saved');
			} else {
				$group_id = $K->SQL->TBL->groups->insert(array(
					'group_parent'      => $_POST->uint('group', 'parent'),
					'group_type'        => array_sum($_POST['group']['type']),
					'group_name'        => $_POST->raw('group', 'name'),
					'group_description' => $_POST->raw('group', 'description'),
				), 'group_id');
				$msg = $K->L10N->get('Added');
			}

			$path = $_POST->raw('group_acl', 'path');
			if (\Poodle\ACL::isValidPath(rtrim($path,'*'))) {
				$K->SQL->TBL->acl_groups->insert(array(
					'acl_path'  => $path,
					'acl_a_ids' => implode(',',$_POST->raw('group_acl', 'actions')) ?: 0,
					'group_id'  => $group_id,
				));
			} else if (strlen($path)) {
				throw new \Exception('Bad ACL format: '.$path);
			}

			\Poodle::closeRequest($msg, 201, \Poodle\URI::admin("/poodle_groups/{$group_id}"), $msg);
		}
	}

}
