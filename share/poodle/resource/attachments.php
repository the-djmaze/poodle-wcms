<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Attachments extends \ArrayIterator
{
	protected
		$resource;

	protected static
		$types = null,
		$types_grouped = null;

	function __construct(\Poodle\Resource $resource)
	{
		$this->resource = $resource;
		if ($resource->id) {
			$SQL = \Poodle::getKernel()->SQL;
			$qr = $SQL->query("SELECT
				resource_attachment_id id,
				resource_attachment_type_id type_id,
				resource_attachment_sortorder sortorder,
				resource_id,
				identity_id,
				media_id,
				l10n_id
			FROM {$SQL->TBL->resources_attachments}
			WHERE resource_id={$resource->id}
			ORDER BY l10n_id, resource_attachment_sortorder");
			$items = array();
			foreach ($qr as $r) {
				$items[] = new Attachment($r);
			}
			parent::__construct($items);
		}
	}

	public static function getType($type_id) : ?array
	{
		$t = self::getTypes();
		if (isset($t[$type_id])) {
			return $t[$type_id];
		}
		return null;
	}

	public static function getTypeName($type_id) : string
	{
		$t = self::getType($type_id);
		return $t ? "{$t['type_label']}: {$t['name']}" : '';
	}

	public static function getTypeLabel($type_id) : string
	{
		$t = self::getType($type_id);
		return $t ? "{$t['type_label']}: {$t['label']}" : '';
	}

	public static function getTypes() : array
	{
		if (!is_array(self::$types)) {
			self::$types = array();
			$SQL = \Poodle::getKernel()->SQL;
			$qr = $SQL->query("SELECT
				resource_attachment_type_id id,
				resource_attachment_type_name name,
				resource_attachment_type_label label,
				resource_attachment_type_callback callback,
				resource_attachment_type_width width,
				resource_attachment_type_height height,
				resource_type_id type_id,
				resource_type_label type_label,
				media_type_extensions exts
			FROM {$SQL->TBL->resource_attachment_types}
			LEFT JOIN {$SQL->TBL->resource_types} USING (resource_type_id)
			ORDER BY resource_type_id, resource_attachment_type_label");
			while ($r = $qr->fetch_assoc()) {
				$r['id']      = (int)$r['id'];
				$r['type_id'] = (int)$r['type_id'];
				$r['exts']    = $r['exts'] ? explode(',',$r['exts']) : null;
				self::$types[$r['id']] = $r;
			}
		}
		return self::$types;
	}

	public static function getTypesGrouped() : array
	{
		if (is_null(self::$types_grouped)) {
			$groups = array();
			foreach (self::getTypes() as $r) {
				$tid = $r['type_id'];
				if (!isset($groups[$tid])) {
					$groups[$tid] = array(
						'id' => $tid,
						'label' => $r['type_label'],
						'types' => array()
					);
				}
				$groups[$tid]['types'][] = array(
					'id'     => $r['id'],
					'label'  => $r['label'],
					'exts'   => $r['exts'],
					'width'  => $r['width'],
					'height' => $r['height'],
				);
			}
			self::$types_grouped = $groups;
		}
		return self::$types_grouped;
	}

	public function findByID($id) : Attachment
	{
		foreach ($this as $attachment) {
			if ($id == $attachment->id) {
				return $attachment;
			}
		}
	}

	public function getIndexByID($id) : int
	{
		foreach ($this as $index => $attachment) {
			if ($id == $attachment->id) {
				return $index;
			}
		}
		return false;
	}

	public function getOnlyOfType($type) : array
	{
		$list = array();
		foreach ($this as $index => $attachment) {
			if ($type == $attachment->type_id || $type == $attachment->type_name) {
				$list[] = $attachment;
			}
		}
		return $list;
	}

	public function getForLanguage($l10n_id) : array
	{
		$list = array();
		foreach ($this as $index => $attachment) {
			if ($l10n_id == $attachment->l10n_id) {
				$list[] = $attachment;
			}
		}
		return $list;
	}

	public function append($attachment=null) : ?Attachment
	{
		if ($attachment instanceof \Poodle\Input\File
		 || $attachment instanceof \Poodle\Media\Item)
		{
			$attachment = new Attachment($attachment);
			if (!$attachment->media) {
				return null;
			}
		}
		if ($attachment instanceof Attachment) {
			$attachment->resource_id = $this->resource->id;
			parent::append($attachment);
			return $attachment;
		}
		throw new \Exception('Invalid attachment');
	}

	// detach media item from resource
	public function remove($attachment) : bool
	{
		if (is_int($attachment) || ctype_digit($attachment)) {
			$attachment = parent::offsetGet($attachment);
		}
		if ($attachment instanceof Attachment) {
			$index = $this->getIndexByID($attachment->id);
			if (false !== $index) {
				$this->offsetUnset($index);
				$SQL = \Poodle::getKernel()->SQL;
				$SQL->delete('resources_attachments',array('resource_attachment_id' => $attachment->id));
				$SQL->exec("UPDATE {$SQL->TBL->resources_attachments}
				SET resource_attachment_sortorder = resource_attachment_sortorder - 1
				WHERE resource_id = {$attachment->resource_id}
				  AND resource_attachment_sortorder > {$attachment->sortorder}
				  AND l10n_id = {$attachment->l10n_id}");
				return true;
			}
		}
		return false;
	}

	public function removeByID($id) : bool
	{
		return $this->remove($this->findByID($id));
	}

	public function save() : void
	{
		foreach ($this as $attachment) {
			$attachment->resource_id = $this->resource->id;
			$attachment->save();
		}
	}

	public function offsetExists($index) : bool
	{
		if (ctype_digit($index)) {
			return parent::offsetExists($index);
		}
		foreach ($this as $attachment) {
			if ($index == $attachment->type_name) {
				return true;
			}
		}
		return false;
	}

	public function offsetGet($index)
	{
		return (is_int($attachment) || ctype_digit($index)) ? parent::offsetGet($index) : self::getOnlyOfType($index);
	}

	public function offsetSet($index, $attachment)
	{
		if ($attachment instanceof Attachment) {
			$attachment->resource_id = $this->resource->id;
			parent::offsetSet($index, $attachment);
		} else {
			throw new \Exception('Invalid attachment');
		}
	}

	public function offsetUnset($index)
	{
		if (isset($this[$index])) {
			\Poodle::getKernel()->SQL->TBL->resources_attachments->delete(array(
				'resource_attachment_id' => $this[$index]->id
			));
		}
		parent::offsetUnset($index);
	}

}
