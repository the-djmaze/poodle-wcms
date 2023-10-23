<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Media\Admin;

class Albums extends \Poodle\Resource\Admin\Basic
{
	public
		$title = 'Media albums',
		$allowed_methods = array('GET', 'HEAD', 'POST');

	public function GET()
	{
		if ('resources' === \Poodle::$PATH[0]) {
			return parent::GET();
			\Poodle\URI::redirect('/admin/poodle_media_albums/');
		}

		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;

		if (!ctype_digit(\Poodle::$PATH[1])) {
			$time = time();

			$OUT->active_albums = $SQL->query("SELECT
				id,
				ctime,
				title,
				COUNT(resource_attachment_id) items_count
			FROM {$SQL->TBL->view_latest_resources_data} r
			LEFT JOIN {$SQL->TBL->resources_attachments} a ON (
				a.resource_id = r.id AND
				resource_attachment_type_id = ".\Poodle\Media\Album::getAttachmentTypeId().")
			WHERE type_id = 10
			  AND (ptime = 0 OR ptime < {$time})
			  AND (etime = 0 OR etime > {$time})
			GROUP BY 1,2,3
			ORDER BY title");

			$OUT->inactive_albums = $SQL->query("SELECT
				id,
				ctime,
				title,
				COUNT(resource_attachment_id) items_count
			FROM {$SQL->TBL->view_latest_resources_data} r
			LEFT JOIN {$SQL->TBL->resources_attachments} a ON (
				a.resource_id = r.id AND
				resource_attachment_type_id = ".\Poodle\Media\Album::getAttachmentTypeId().")
			WHERE type_id = 10
			  AND (ptime > {$time} OR (etime < {$time} AND etime > 0))
			GROUP BY 1,2,3
			ORDER BY title");

			$OUT->head->addCSS('poodle_media_admin');
			$OUT->display('poodle/media/admin/albums');
		}
	}

}
