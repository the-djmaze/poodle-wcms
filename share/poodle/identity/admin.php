<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Identity;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Identities',
		$allowed_methods = array('GET','HEAD','POST');

	protected static
		$map_sort_fields = array(
			'id' => 'identity_id',
			'nickname' => 'user_nickname_lc',
			'ctime' => 'user_ctime',
			'lastvisit' => 'user_last_visit',
			'givenname' => 'LOWER(user_givenname)',
		);

	public function GET()
	{
		if (isset($_GET['takeover'])) {
			if (\Poodle\Identity::switchCurrentTo($_GET['takeover'])) {
				\Poodle\URI::redirect('/', 302);
			} else {
				\Poodle\Report::error('Failed to switch Identity');
			}
		}

		if (ctype_digit(\Poodle::$PATH[1])) {
			if (!empty($_GET['auth_create'])) {
				$identity = Search::byID(\Poodle::$PATH[1]);
				$provider = \Poodle\Auth\Provider::getById($_GET['auth_create']);
				if (!$identity || !$provider || !method_exists($provider, 'createForIdentity') || !$provider->createForIdentity($identity)) {
					\Poodle\Report::error(404);
				}
				\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[0].'/'.\Poodle::$PATH[1].'/');
			}
			$this->editIdentity(\Poodle::$PATH[1]);
		} else {
			$this->viewList();
		}
	}

	public function POST()
	{
		$K = \Poodle::getKernel();
		\Poodle\Notify::success($K->L10N->get('The changes have been saved'));
		if (ctype_digit(\Poodle::$PATH[1])) {
			$identity = $this->saveIdentity(\Poodle::$PATH[1]);
			\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[0].'/'.$identity->id);
		} else if (!empty($_POST['config'])) {
			$CFG = \Poodle::getKernel()->CFG;
			$CFG->set('identity', 'nick_minlength', max(3, $_POST->uint('config', 'identity', 'nick_minlength')));
			$CFG->set('identity', 'passwd_minlength', max(6, $_POST->uint('config', 'identity', 'passwd_minlength')));
			$CFG->set('identity', 'nick_deny', $_POST->raw('config', 'identity', 'nick_deny'));
			$CFG->set('identity', 'nick_invalidchars', $_POST->raw('config', 'identity', 'nick_invalidchars'));

			$class = $_POST->raw('config', 'poodle', 'identity_class');
			if (is_subclass_of($class, 'Poodle\\Identity')) {
				$CFG->set('poodle', 'identity_class', $class);
			}

			\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[0].'/');
		}
	}

	protected function editIdentity($id)
	{
		$this->prepareIdentityEditing($id);
		\Poodle::getKernel()->OUT->display('poodle/identity/edit');
	}

	protected function prepareIdentityEditing($id)
	{
		$identity = \Poodle\Identity\Search::byID($id);
		if (!$identity) {
			\Poodle\Report::error(404);
		}

		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		$SQL = $K->SQL;

		$qr = $SQL->query("SELECT
			g.group_id,
			group_name,
			group_parent,
			identity_id
		FROM {$SQL->TBL->groups} g
		LEFT JOIN {$SQL->TBL->groups_users} gu ON (gu.group_id=g.group_id AND identity_id={$identity->id})
		ORDER BY group_parent ASC, group_name");
		$groups = array();
		while ($r = $qr->fetch_row()) {
			if ($r[2]) {
				$groups[$r[2]]['children'][] = array(
					'id'=>$r[0],
					'name'=>$r[1],
					'member'=>!empty($r[3]),
				);
			} else {
				$groups[$r[0]] = array(
					'id'=>$r[0],
					'name'=>$r[1],
					'member'=>!empty($r[3]),
					'children'=>array()
				);
			}
		}
		if (!$id) { $groups[1]['member'] = true; }

		$auths = array();
		$qr = $SQL->query("SELECT
			p.auth_provider_id,
			auth_provider_name,
			auth_claimed_id,
			auth_claimed_id_info,
			auth_provider_is_2fa,
			auth_provider_class
		FROM {$SQL->TBL->auth_providers} p
		LEFT JOIN {$SQL->TBL->auth_identities} i ON (i.auth_provider_id = p.auth_provider_id AND identity_id = {$identity->id})
		ORDER BY auth_provider_name");
		while ($r = $qr->fetch_row()) {
			if (!isset($auths[$r[0]])) {
				$auths[$r[0]] = array(
					'id' => $r[0],
					'name' => $r[1],
					'is_2fa' => $r[4],
					'claimed_ids' => array(),
					'can_create' => method_exists($r[5], 'createForIdentity'),
				);
			}
			if ($r[2]) {
				$qrcode = null;
				if (method_exists($r[5], 'getQRCode')) {
					$qrcode = $r[5]::getQRCode("{$identity->nickname}@{$_SERVER['HTTP_HOST']}", $r[2]);
					$qrcode = array(
						'obj' => $qrcode,
						'src' => 'data:image/png;base64,' . base64_encode($qrcode->createImageBlob(4)),
					);
				}
				$auths[$r[0]]['claimed_ids'][] = array(
					'id' => $r[2],
					'info' => $r[3],
					'qr'   => $qrcode,
				);
			}
		}

		$fields = array();
		$qr = $SQL->query("SELECT
			user_df_section,
			user_df_name,
			user_df_label,
			user_df_type,
			user_df_attributes,
			user_df_flags
		FROM {$SQL->TBL->users_d_fields}
		ORDER BY user_df_sortorder");
		$field_attr = array(
			'min' => false,
			'max' => false,
			'step' => false,
			'required' => false,
			'placeholder' => false,
			'options' => array(),
		);
		while ($r = $qr->fetch_row()) {
			if (!isset($fields[$r[0]])) {
				$fields[$r[0]] = array(
					'label' => $r[0],
					'fields' => array()
				);
			}
			$attr = json_decode($r[4], true);
			$fields[$r[0]]['fields'][] = array(
				'name'  => $r[1],
				'label' => $r[2],
				'type'  => $r[3],
				'attr'  => array_merge($field_attr, is_array($attr) ? $attr : array()),
				'flags' => (int) $r[5],
				'value' => $identity->{$r[1]},
			);
		}

		$OUT->identity_auths = $auths;
		$OUT->identity_fields = $fields;
		$OUT->identity_groups = $groups;
		$OUT->identity = $identity;
		$OUT->head
			->addCSS('poodle_tabs')
			->addCSS('poodle_identity_admin')
			->addScript('poodle_tabs')
			->addScript('poodle_resize')
			->addScript('poodle_identity_admin');
		$OUT->crumbs->append($identity->nickname);
	}

	protected function saveIdentity($id)
	{
		$identity = \Poodle\Identity\Search::byID($id);
		if (!$identity) { \Poodle\Report::error(404); }

		foreach ($_POST['identity'] as $k => $v) {
			$identity->$k = $v;
		}
		$identity->save();

		// Set group memberships
		$identity->setGroups($_POST['identity_groups']);

		// Delete authentications
		if (!empty($_POST['auth_delete'])) {
			$SQL = \Poodle::getKernel()->SQL;
			foreach ($_POST['auth_delete'] as $provider_id => $claimed_ids) {
				$provider_id = (int)$provider_id;
				array_walk($claimed_ids, function(&$v) use ($SQL) { $v = $SQL->quote($v); });
				$claimed_ids = implode(',', $claimed_ids);
				$SQL->exec("DELETE FROM {$SQL->TBL->auth_identities}
				WHERE auth_provider_id = {$provider_id}
				  AND auth_claimed_id IN ({$claimed_ids})");
			}
		}

		// Add DB authentication
		if (!empty($_POST['auth_db_claimed_id']) && !empty($_POST['auth_db_pass'])) {
			$identity->updateAuth(1, $_POST['auth_db_claimed_id'], $_POST['auth_db_pass']);
		}

		return $identity;
	}

	protected function viewList()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;

		$page  = max(1,$_GET->uint('page'));
		$limit = 50;
		$where = '';
		$field = $_GET->text('field');
		$order = isset($_GET['desc'])?'DESC':'ASC';
		$find  = $_GET->raw('q');
		$group = $_GET->uint('group');
		if (!isset(self::$map_sort_fields[$field])) {
			$field = 'user_nickname_lc';
		} else {
			$field = self::$map_sort_fields[$field];
		}

		if ($find) {
			$find = $SQL->escape_string(mb_strtolower($find));
			$where = "(user_nickname_lc LIKE '%{$find}%'
			OR LOWER(user_email) LIKE LOWER('%{$find}%'))";
			if ($group) {
				$where .= " AND ";
			}
		}
		if ($group) {
			$where = "identity_id IN (SELECT identity_id FROM {$SQL->TBL->groups_users} WHERE group_id = {$group})";
		}
		$offset = (max(0,$page-1)*$limit);

		$OUT->identities = $SQL->query("SELECT
			identity_id AS ID,
			user_nickname Nickname,
			user_givenname 'Given name',
			user_surname Surname,
			user_ctime ctime,
			user_last_visit last_visit,
			CASE WHEN 0 < user_type THEN '✔' ELSE '✗' END Active
		FROM {$SQL->TBL->users}
		".($where?"WHERE {$where}":'')."
		ORDER BY user_type DESC, {$field} {$order}
		LIMIT {$limit} OFFSET {$offset}");

		$OUT->identities_pagination = new \Poodle\Pagination(
			$_SERVER['REQUEST_PATH'].'?page=${page}',
			$SQL->count('users',$where), $offset, $limit);

		$OUT->groups = \Poodle\Groups::listAll();
		$OUT->identity_sort_fields = array(
			array(
				'value' => 'nickname',
				'label' => $OUT->L10N['Nickname'],
			),
			array(
				'value' => 'id',
				'label' => 'ID',
			),
			array(
				'value' => 'givenname',
				'label' => $OUT->L10N['Given name'],
			),
			array(
				'value' => 'ctime',
				'label' => $OUT->L10N['ctime'],
			),
			array(
				'value' => 'lastvisit',
				'label' => $OUT->L10N['Last visit'],
			),
		);

		$OUT->head
			->addCSS('poodle_identity_admin');
		$OUT->display('poodle/identity/index');
	}

}
