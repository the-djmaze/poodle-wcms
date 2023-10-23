<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Edit extends \Poodle\Resource
{

	public
		$searchable = true;

	protected
		$groups_perms = null,
		$revisions = null;

	function __construct(array $data=array())
	{
		if (isset($data['type_flags']) && $data['type_flags'] & Type::FLAG_NO_L10N) {
			$l10n_id = 0;
		} else {
			$l10n_id = isset($data['l10n_id']) ? (int)$data['l10n_id'] : \Poodle::getKernel()->L10N->id;
		}
		# Fetch resource data?
		if (!empty($data['id'])) {
			$this->id = (int)$data['id'];
			if ($row = $this->getL10NData($l10n_id)) {
				$data = array_merge($data, $row);
			} else if (!isset($data['searchable'])) {
				$data['searchable'] = !!\Poodle::getKernel()->SQL->TBL->resources_searchdata->count("resource_id={$this->id}");
			}
		}
		$data['l10n_id'] = $l10n_id;

		parent::__construct($data);
	}

	function __get($k)
	{
		if ('revisions' === $k) {
			return $this->getRevisions();
		}
		if ('groups_perms' === $k) {
			return $this->getGroupsPermissions();
		}
		return parent::__get($k);
	}

	public function getL10NData($l10n_id=null)
	{
		if ($this->id) {
			$SQL = \Poodle::getKernel()->SQL;
			$l10n_id = (int)(is_null($l10n_id) ? $this->l10n_id : $l10n_id);
			return $SQL->uFetchAssoc("SELECT
				l10n_id,
				resource_status status,
				resource_mtime mtime,
				resource_title title,
				resource_body body,
				rollback_of,
				CASE WHEN resource_searchdata IS NULL THEN 0 ELSE 1 END searchable,
				identity_id modifier_identity_id
			FROM {$SQL->TBL->resources_data}
			LEFT JOIN {$SQL->TBL->resources_searchdata} USING (resource_id, l10n_id)
			WHERE resource_id = {$this->id}
			  AND l10n_id IN (0,1,{$l10n_id})
			ORDER BY l10n_id DESC, resource_mtime DESC");
		}
	}

	public function hasFixedURI()  { return $this->id && $this->flags & \Poodle\Resource::FLAG_FIXED_URI; }
	public function hasFixedType() { return $this->id && $this->flags & \Poodle\Resource::FLAG_FIXED_TYPE; }
	public function hasFixedDate() { return $this->id && $this->flags & \Poodle\Resource::FLAG_FIXED_DATE; }

	protected static function toTimestamp($v)
	{
		if ($v instanceof \DateTime) {
			$v = $v->getTimestamp();
		} else
		// http://php.net/datetime.formats
		if (is_string($v) && !ctype_digit($v)) {
			$v = strtotime($v);
		}
		return (int)$v;
	}

	public function save()
	{
		$K   = \Poodle::getKernel();
		$SQL = $K->SQL;
		$tbl = $K->SQL->TBL->resources;

		if (!$this->uri) {
			$this->uri = str_replace('/', '-', $this->title);
		}

		$uri = mb_strtolower(trim(preg_replace('#^.*/([^/]+/?)$#D','$1',$this->uri)));
		$uri = preg_replace('@[\\s&<>\'"#\\?\\.%]+@', '-', $uri);
		$uri = trim(preg_replace('@\\-+@', '-', $uri), '-');
		if (!$uri) { throw new \Exception('URI cannot be empty'); }

		$org_uri = '';
		if ($this->id && $r = $tbl->uFetchRow(array('resource_uri'), array('resource_id'=>$this->id))) {
			$org_uri = $r[0];
		}

		$data = array(
			'resource_bodylayout_id' => $this->bodylayout_id,
		);

		if (!$this->hasFixedURI()) {
			$r = $tbl->uFetchRow(array('resource_uri'), array('resource_id'=>$this->parent_id));
			if (!$r) {
				throw new \Exception('Parent resource not found!');
			}
			$data['resource_parent_id'] = $this->parent_id;
			$data['resource_uri'] = $this->uri = rtrim($r[0],'/') . '/' . $uri;
		} else {
			$this->uri = $org_uri;
		}

		if (!$this->hasFixedType()) {
			$data['resource_type_id'] = $this->type_id;
		}

		if (!$this->hasFixedDate()) {
			$data['resource_ptime'] = self::toTimestamp($this->ptime) ?: time();
			$data['resource_etime'] = self::toTimestamp($this->etime);
		}

		if ($data) {
			if ($this->id) {
				$tbl->update($data,'resource_id='.$this->id);
				if (isset($data['resource_uri']) && $org_uri != $data['resource_uri']) {
					$new_uri = $SQL->quote($data['resource_uri']);
					$SQL->query("UPDATE {$SQL->TBL->resources} SET resource_uri=REPLACE(resource_uri,'{$org_uri}', {$new_uri}) WHERE resource_uri LIKE '{$org_uri}/%'");
					$SQL->query("UPDATE {$SQL->TBL->acl_groups} SET acl_path=REPLACE(acl_path,'{$org_uri}', {$new_uri}) WHERE acl_path LIKE '{$org_uri}/%' OR acl_path='{$org_uri}'");
					$SQL->query("UPDATE {$SQL->TBL->menus_items} SET mitem_uri=REPLACE(mitem_uri,'{$org_uri}', {$new_uri}) WHERE mitem_uri LIKE '{$org_uri}/%' OR mitem_uri='{$org_uri}'");
				}
			} else {
				$data['resource_ctime'] = $this->ctime;
				$data['identity_id']    = $this->creator_identity_id = $K->IDENTITY->id;
				$data['resource_flags'] = $this->flags;
				$this->id = $tbl->insert($data,'resource_id');
			}
		}

		if ($this->attachments) {
			$this->attachments->save();
		}

		if ($this->metadata) {
			$this->metadata->save();
		}

		\Poodle\LOG::info('Resource Saved',"#{$this->id} by {$_SERVER['HTTP_USER_AGENT']}");

		return true;
	}

	/**
	 * Process $acl[{group_id}]
	 */
	public function setACL(array $acl)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$tbl = $SQL->TBL->acl_groups;
		$tbl->delete(array('acl_path'=>$this->uri));
		$ids_count = $SQL->count('acl_actions');
		foreach ($acl as $group_id => $ids) {
			// Check if first checkox is the group and checked
			$i = array_search(0, $ids);
			if (false !== $i) {
				unset($ids[$i]);
				$c = count($ids);
				$acl_a_ids = '0';
				if ($c === $ids_count) {
					$acl_a_ids = '*';
				} else if (0 < $c) {
					$acl_a_ids = implode(',', $ids);
				}
				$tbl->insert(array(
					'group_id' => $group_id,
					'acl_path' => $this->uri,
					'acl_a_ids'=> $acl_a_ids
				));
			}
		}
		return true;
	}

	/**
	 * Process $data[{l10n_id}]
	 */
	public function setMetadata($data)
	{
		$md = $this->getMetadata();
		foreach ($data as $l10n_id => $items) {
			$md->set($l10n_id, $items);
		}
		$md->save();
	}

	public function addAttachment(/*\Poodle\Media\Item|\Poodle\Input\File*/ $item, $type_id=0, $l10n_id=0)
	{
		if ($type_id && $t = Attachments::getType($type_id)) {
			if (($t['width'] || $t['height']) && !$t['callback']) {
				if ($item instanceof \Poodle\Input\File) {
					$file = \Poodle::$DIR_MEDIA.'images/'.$item->name;
					$image = \Poodle\Image::open($item->tmp_name);
				} else {
					$file = $item->filename;
					$image = \Poodle\Image::open($file);
				}
				$file = preg_replace('/(\\.[a-z]+)$/Di', ".{$t['width']}x{$t['height']}\$1", $file);
				$image->cropThumbnailImage($t['width'], $t['height']);
				if ($image->writeImage($file)) {
					$item = \Poodle\Media\Item::createFromPath($file);
				}
			}
		}

		$item = $this->getAttachments()->append($item);
		if ($item) {
			$item->type_id = (int)$type_id;
			$item->l10n_id = (int)$l10n_id;
		}
		return $item ?: false;
	}

	public function addRevision($data)
	{
		if ($this->id) {
			$K = \Poodle::getKernel();
			$SQL = $K->SQL;
			$tbl = $SQL->TBL->resources_data;

			if ($this->hasL10N()) {
				$l10n_id = (int)((isset($data['l10n_id']) ? $data['l10n_id'] : $this->l10n_id) ?: $K->L10N->id);
			} else {
				$l10n_id = 0;
			}
			$data['status'] = (int)$data['status'];

			$r = $SQL->uFetchRow("SELECT
				resource_title,
				resource_body,
				resource_status
			FROM {$tbl}
			WHERE l10n_id={$l10n_id}
			  AND resource_id={$this->id}
			ORDER BY resource_mtime DESC");

			$db_data = array(
				'resource_id'    => $this->id,
				'l10n_id'        => $l10n_id,
				'resource_title' => $data['title'],
				'resource_body'  => $data['body'],
			);

			// New revision when not found or title or body is not identical
			if (!$r || $r[0]!==$data['title'] || $r[1]!==$data['body'])
			{
				// l10n + title + body doesn't match any previous, add new
				$db_data = array_merge($db_data, array(
					'identity_id'    => $K->IDENTITY->id,
					'resource_mtime' => time(),
					'resource_status'=> $data['status']
				));
				$tbl->insert($db_data);

				$max = max(0, $K->max_resource_revisions);
				if ($max) {
					$r = $SQL->query("SELECT
						resource_mtime
					FROM {$tbl}
					WHERE l10n_id={$l10n_id}
					  AND resource_id={$this->id}
					ORDER BY resource_mtime DESC
					LIMIT 1 OFFSET {$max}")->fetch_row();
					if ($r && $r[0]) {
						$r = $SQL->query("DELETE FROM {$tbl}
						WHERE l10n_id={$l10n_id}
						  AND resource_id={$this->id}
						  AND resource_mtime <= {$r[0]}");
					}
				}
			}
			// Update revision status when title and body are identical
			else if ($r[0]===$data['title'] && $r[1]===$data['body'] && $r[2] != $data['status'])
			{
				// l10n + title + body match found, update status
				$tbl->update(array(
					'resource_status'=> $data['status']
				), $db_data);
				// Push to log who changed the status
			}

			$tbl = $SQL->TBL->resources_searchdata;
			$db_data = array(
				'resource_id' => $this->id,
				'l10n_id'     => $l10n_id
			);
			if (!empty($data['searchable']) && $data['body']) {
				// Only store when status is published
				if (self::STATUS_PUBLISHED == $data['status']) {
					$r     = $tbl->uFetchRow(array('resource_searchtitle','resource_searchdata'), $db_data);
					$title = \Poodle\Unicode::as_search_txt($data['title']);
					$body  = \Poodle\Unicode::as_search_txt($data['body']);
					if (!$r || $r[0] !== $title || $r[1] !== $body) {
						if ($r) { $tbl->delete($db_data); }
						$db_data['resource_searchtitle'] = $title;
						$db_data['resource_searchdata']  = $body;
						$tbl->insert($db_data);
					}
				}
			} else {
				$tbl->delete($db_data);
			}
		}
	}

	public function rollback($mtime, $l10n_id=0)
	{
		if ($this->id) {
			$mtime   = (int)$mtime;
			$l10n_id = (int)$l10n_id;
			if (!$l10n_id) {
				$l10n_id = $this->l10n_id;
			}
			$K = \Poodle::getKernel();
			$SQL = $K->SQL;
			$SQL->query("INSERT INTO {$SQL->TBL->resources_data} (
				resource_id,
				l10n_id,
				identity_id,
				resource_mtime,
				resource_title,
				resource_body,
				resource_status,
				rollback_of
			) SELECT
				resource_id,
				l10n_id,
				{$K->IDENTITY->id},
				".time().",
				resource_title,
				resource_body,
				resource_status,
				resource_mtime
			FROM {$SQL->TBL->resources_data}
			WHERE resource_id={$this->id}
			  AND resource_mtime={$mtime}
			  AND l10n_id={$l10n_id}
			LIMIT 1");
			return true;
		}
		return false;
	}

	public function getGroupsPermissions()
	{
		if (!is_array($this->groups_perms)) {
			$K = \Poodle::getKernel();
			$SQL = $K->SQL;
			$acl_actions = \Poodle\ACL::getActions();

			$groups = array(0=>array('name'=>'Anonymous', 'parent_id'=>0, 'a_ids'=>''));
			$result = $SQL->uQuery("SELECT
				group_id,
				group_name,
				group_parent,
				CASE WHEN group_parent THEN CONCAT(group_parent, '-', group_id) ELSE CONCAT(group_id, '-0') END
			FROM {$SQL->TBL->groups}
			ORDER BY 4");
			while ($row = $result->fetch_row()) {
				$groups[$row[0]] = array('name'=>$row[1], 'parent_id'=>$row[2], 'a_ids'=>'');
			}

			$result = $SQL->uQuery("SELECT group_id, acl_a_ids FROM {$SQL->TBL->acl_groups} WHERE acl_path = ".$SQL->quote($this->uri));
			while ($row = $result->fetch_row()) {
				$groups[$row[0]]['a_ids'] = $row[1];
			}

			$this->groups_perms = array();
			foreach ($groups as $id => $group) {
				$active = 0 < strlen($group['a_ids']);
				$actions = array();
				foreach ($acl_actions as $a_id => $a_name) {
					$a_id = (int)$a_id;
					$ids = $group['parent_id'] ? "{$id},{$group['parent_id']}" : $id;
					$actions[] = array(
						'id'  => $a_id,
						'active' => ($active ? ('0' !== $group['a_ids'] && \Poodle\ACL::isValidAction($a_id, $group['a_ids'])) : \Poodle\ACL\Groups::isAllowed($this->uri, $a_id, $ids))
					);
				}
				$this->groups_perms[] = array(
					'id' => $id,
					'name'  => $K->L10N->dbget($group['name']),
					'active' => $active,
					'actions' => $actions,
					'parent_id' => $group['parent_id']
				);
			}
		}
		return $this->groups_perms;
	}

	public function getRevisions()
	{
		if (!is_array($this->revisions)) {
			$K = \Poodle::getKernel();
			$SQL = $K->SQL;
			$statuses = array(
				-1 => $K->L10N->get('removed'),
				 0 => $K->L10N->get('draft'),
				 1 => $K->L10N->get('pending'),
				 2 => $K->L10N->get('published')
			);
			$result = $SQL->query("SELECT
				l10n_id,
				resource_mtime mtime,
				resource_status status,
				rollback_of,
				identity_id,
				user_nickname author
			FROM {$SQL->TBL->resources_data}
			LEFT JOIN {$SQL->TBL->users} USING (identity_id)
			WHERE resource_id={$this->id}
			ORDER BY 1, 2 DESC");
			$this->revisions = array();
			while ($row = $result->fetch_assoc()) {
				if (!isset($this->revisions[$row['l10n_id']])) {
					$this->revisions[$row['l10n_id']] = array(
						'label' => $K->L10N->getNameByID($row['l10n_id']),
						'revisions' => array()
					);
				}
				$row['status_label'] = $statuses[$row['status']];
				$this->revisions[$row['l10n_id']]['items'][] = $row;
			}
		}
		return $this->revisions;
	}

}
