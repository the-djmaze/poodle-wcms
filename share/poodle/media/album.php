<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.
*/

namespace Poodle\Media;

class Album extends \Poodle\Resource\Basic
{
	const
		FORMAT_CROP       = 0, // Creates a fixed size thumbnail by first scaling the image up or down and cropping a specified area from the center.
		FORMAT_SCALE      = 1,
		FORMAT_SCALE_CROP = 2;

	public function GET()
	{
		$this->displayAlbum();
	}

	public function displayAlbum()
	{
		$OUT = \Poodle::getKernel()->OUT;
		$OUT->album = $this;
		$OUT->album_items = $this->getItems();

		$OUT->media_albums = Albums::getActive($this->uri);
		if ($OUT->media_albums->num_rows) {
			$OUT->head->addCSS('poodle_media_albums');
		} else {
			$OUT->media_albums = false;
		}

		$OUT->head
			->addCSS('poodle_media_album')
			->addScript('poodle_media_album');
		parent::display('poodle/media/album');
	}

	public static function getAttachmentTypeId() : int
	{
		static $a_type_id;
		if (!is_int($a_type_id)) {
			$SQL = \Poodle::getKernel()->SQL;
			$r = $SQL->uFetchRow("SELECT MAX(resource_attachment_type_id) FROM {$SQL->TBL->resource_attachment_types} WHERE resource_type_id=10");
			$a_type_id = (int)$r[0];
		}
		return $a_type_id;
	}

	public function getItems()
	{
		return $this->getAttachments()->getOnlyOfType(self::getAttachmentTypeId());
	}

	public static function getItemFormats() : array
	{
//		return \Poodle\Media::getImageFormats();
		static $types = array();
		if (!$types) {
			$SQL = \Poodle::getKernel()->SQL;
			$result = $SQL->query("SELECT
				format_id id,
				format_label label,
				format_width width,
				format_height height,
				format_filename filename,
				format_options options
			FROM {$SQL->TBL->media_album_formats}
			ORDER BY format_label ASC");
			while ($type = $result->fetch_assoc()) {
				$type['id'] = (int)$type['id'];
				$type['width'] = (int)$type['width'];
				$type['height'] = (int)$type['height'];
				$type['options'] = (int)$type['options'];
				$types[$type['id']] = $type;
			}
		}
		return $types;
	}

	public static function getItemFormat($id) : ?array
	{
		$items = static::getItemFormats();
		return isset($items[$id]) ? $items[$id] : null;
	}
}
