<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.
*/

namespace Poodle\Media;

class Albums extends \Poodle\Resource
{
	public
		$allowed_methods = array('GET');

	public function GET()
	{
		$OUT = \Poodle::getKernel()->OUT;

		$i = substr_count(rtrim($this->uri,'/'),'/');
		if (!empty(\Poodle::$PATH[$i])) {
			$resource = \Poodle\Resource::factory((int)\Poodle::$PATH[$i]);
			if ($resource && 10 == $resource->type_id) {
				if (\Poodle\ACL::view($resource->uri)) {
					$OUT->crumbs->append($resource->title);
					return $resource->displayAlbum();
				}
				\Poodle\Report::error(403);
			}
			\Poodle\Report::error(404);
		}

		$OUT->media_albums = self::getActive($this->uri);
		$OUT->head->addCSS('poodle_media_albums');
		$OUT->display('poodle/media/albums');
	}

	public static function getActive($uri = null)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$a_type_id = \Poodle\Media\Album::getAttachmentTypeId();
		$uri = $SQL->escape_string($uri);
		return $SQL->query("SELECT
			id,
			uri,
			ptime,
			title,
			body,
			(SELECT COUNT(resource_attachment_id)
				FROM {$SQL->TBL->resources_attachments} a
				WHERE a.resource_id=r.id
				  AND resource_attachment_type_id={$a_type_id}
			) items_count,
			(SELECT media_file_hash
				FROM {$SQL->TBL->resources_attachments} a
				INNER JOIN {$SQL->TBL->media} USING (media_id)
				WHERE a.resource_id=r.id
				  AND a.resource_attachment_type_id={$a_type_id}
				ORDER BY a.resource_attachment_sortorder ASC, a.media_id DESC
				LIMIT 1
			) item_file_hash
		FROM {$SQL->TBL->view_latest_resources_data} r
		WHERE type_id=10
		  AND ptime<=UNIX_TIMESTAMP()
		  AND (etime=0 OR etime>UNIX_TIMESTAMP())
		  ".($uri ? "AND uri LIKE '{$uri}/%' AND NOT uri LIKE '{$uri}/%/%'" : '')."
		ORDER BY ptime DESC, title");
	}
}
