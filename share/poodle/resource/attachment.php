<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Attachment
{
	protected
		$id          = 0,
		$type_id     = 0,
		$sortorder   = 0,
		$resource_id = 0,
		$identity_id = 0,
		$l10n_id     = 0,
		$media_id    = 0,
		$media       = null; // \Poodle\Media\Item

	function __construct($id=0)
	{
		$K = \Poodle::getKernel();
		if ($id)
		{
			if (is_int($id) || ctype_digit($id)) {
				$SQL = $K->SQL;
				$id = $SQL->uFetchAssoc("SELECT
					resource_attachment_id id,
					resource_attachment_type_id type_id,
					resource_attachment_sortorder sortorder,
					resource_id,
					identity_id,
					media_id,
					l10n_id
				FROM {$SQL->TBL->resources_attachments}
				WHERE resource_attachment_id={$id}");
			}
			if ($id instanceof \Poodle\Input\File) {
//				$id->validateType(array('text/csv'));
				if ($id->errno) {
					if (UPLOAD_ERR_NO_FILE != $id->errno) {
						throw new \Exception($id->error, $id->errno);
					}
				} else {
					$id = \Poodle\Media\Item::createFromUpload($id);
				}
			}
			if ($id instanceof \Poodle\Media\Item) {
				$this->media    = $id;
				$this->media_id = $id->id;
			}
			if (is_array($id)) {
				$this->id          = (int)$id['id'];
				$this->type_id     = (int)$id['type_id'];
				$this->resource_id = (int)$id['resource_id'];
				$this->identity_id = (int)$id['identity_id'];
				$this->media_id    = (int)$id['media_id'];
				$this->l10n_id     = (int)$id['l10n_id'];
			}
		}
		else
		{
			$this->identity_id = $K->IDENTITY->id;
		}
	}

	function __get($k)
	{
		if (property_exists($this,$k)) {
			if ('media' === $k) { return $this->getMediaItem(); }
			return $this->$k;
		}
		switch ($k)
		{
		case 'file':
		case 'extension': return $this->getMediaItem()->$k;
		case 'uri':       return $this->getMediaItem()->getURI();
		case 'language':  return \Poodle::getKernel()->L10N->getNameByID($this->l10n_id);
		case 'type':      return Attachments::getTypeLabel($this->type_id);
		case 'IDENTITY':  return \Poodle\Identity\Search::byID($this->identity_id);
		case 'type_name':
			$t = Attachments::getType($this->type_id);
			return $this->type_name = $t['name'];
		}
		trigger_error("Property {$k} does not exist");
	}

	function __set($k, $v)
	{
		if (property_exists($this,$k) && 'id' !== $k) {
			if ('media' === $k) {
				if ($v instanceof \Poodle\Media\Item) {
					$this->media = $v;
					if (!$v->id) { $v->save(); }
					$this->media_id = $v->id;
				}
			} else {
				$this->$k = (int)$v;
			}

			// Validate if file type is allowed for the configured attachment_type
			// This done by file extension (generated through mime type detection)
			if ('type_id' === $k || 'media_id' === $k || 'media' === $k) {
				$t = Attachments::getType($this->type_id);
				$m = $this->getMediaItem();
				if ($m && $t && $t['exts'] && !in_array($m->extension, $t['exts'])) {
					throw new \Exception('Invalid file type: '.$m->extension);
				}
			}
		}
	}

	public function getMediaItem()
	{
		if (!$this->media && $this->media_id) {
			$this->media = new \Poodle\Media\Item($this->media_id);
		}
		return $this->media;
	}

	public function save()
	{
		if (!$this->resource_id) {
			trigger_error('Resource_Attachment resource_id not set');
			return false;
		}

		if ($this->type_id && !$this->id) {
			$t = Attachments::getType($this->type_id);
			if ($t && $t['callback']) {
				if (!is_callable($t['callback'])) {
					throw new \Exception('Attachment callback not callable');
				}
				call_user_func($t['callback'], $this);
			}
		}

		if (!$this->media_id) {
			trigger_error('Resource_Attachment media_id not set');
			return false;
		}

		$SQL = \Poodle::getKernel()->SQL;
		$data = array(
			'resource_id' => $this->resource_id,
			'identity_id' => $this->identity_id,
			'media_id'    => $this->media_id,
			'l10n_id'     => $this->l10n_id,
			'resource_attachment_type_id' => $this->type_id,
			'resource_attachment_sortorder' => $this->sortorder,
		);
		$tbl = $SQL->TBL->resources_attachments;
		if ($this->id) {
			$tbl->update($data, "resource_attachment_id={$this->id}");
		} else {
			// prevent duplicates
			$r = $SQL->uFetchRow("SELECT
				MAX(resource_attachment_id)
			FROM {$tbl}
			WHERE resource_id={$this->resource_id}
			  AND media_id={$this->media_id}
			  AND resource_attachment_type_id={$this->type_id}
			  AND l10n_id={$this->l10n_id}");
			if ($r[0]) {
				$this->id = (int)$r[0];
			} else {
				$s = $SQL->uFetchRow("SELECT
					MAX(resource_attachment_sortorder)
				FROM {$tbl}
				WHERE resource_id={$this->resource_id}
				  AND l10n_id={$this->l10n_id}");
				$data['resource_attachment_sortorder'] = $this->sortorder = (int)$s[0];
				$this->id = $tbl->insert($data, 'resource_attachment_id');
			}
		}
		return $this;
	}
}
