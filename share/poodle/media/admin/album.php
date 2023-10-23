<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Media\Admin;

class Album extends \Poodle\Resource\Admin\Basic
{
	public
		$title = '',
		$a_type_id = 0;

	function __construct(array $data = array())
	{
		parent::__construct($data);
		if (!$this->parent_id) {
			$SQL = \Poodle::getKernel()->SQL;
			$pid = $SQL->uFetchRow("SELECT resource_id FROM {$SQL->TBL->resources} WHERE resource_type_id = 11 AND (resource_etime = 0 OR resource_etime > ".time().")");
			if ($pid) {
				$this->parent_id = (int)$pid[0];
			}
		}
		$this->a_type_id = \Poodle\Media\Album::getAttachmentTypeId();
	}

	public function GET()
	{
		if (isset($_GET['synchronize'])) {
			set_time_limit(0);
			\Poodle::startStream();
			header('Content-Type: text/plain');
			$items = $this->getAttachments()->getOnlyOfType($this->a_type_id);
			foreach ($items as $item) {
				echo $item->media->file . "\n";
				static::resizeMediaItem($item->media);
			}
			exit('synchronized');
		}

		$this->HEAD();

		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;
		$OUT->L10N->load('poodle_media');

		$this->initOutput();
/*
		$this->getAttachments();
		$offset = (int)$_GET->uint('offset');
		$limit  = 25;
		$OUT->media_pagination = new \Poodle\Pagination(
			$_SERVER['REQUEST_PATH'].'?offset=${offset}',
			count($this->attachments), $offset, $limit);
		$this->attachments = array_slice($this->attachments->getArrayCopy(), $offset, $limit);
*/

		$OUT->media_pagination = null;

		unset($OUT->crumbs[1]);
		$OUT->crumbs->append($OUT->L10N['Media albums'], '/admin/poodle_media_albums/');
		$OUT->crumbs->append($this->title ?: $OUT->L10N['New']);
		if (isset($_GET['revision'])) {
			$OUT->crumbs->append("Revision #{$_GET->uint('revision')}");
		}

		$OUT->head->addCSS('poodle_media_admin');

		$OUT->display('poodle/media/admin/album');
	}

	public function POST()
	{
		if (isset($_POST['move_item_up'])) {
			foreach ($_POST['move_item_up'] as $id => $d) {
				$a = $this->getAttachments()->findByID($id);
				if ($a) {
					$SQL = \Poodle::getKernel()->SQL;
					$r = $SQL->uFetchRow("SELECT
						resource_attachment_sortorder - 1
					FROM {$SQL->TBL->resources_attachments}
					WHERE resource_attachment_id={$a->id}");
					$SQL->exec("UPDATE {$SQL->TBL->resources_attachments}
					SET resource_attachment_sortorder = resource_attachment_sortorder + 1
					WHERE resource_id = {$a->resource_id}
					  AND resource_attachment_sortorder = {$r[0]}");
					$SQL->exec("UPDATE {$SQL->TBL->resources_attachments}
					SET resource_attachment_sortorder = resource_attachment_sortorder - 1
					WHERE resource_attachment_id = {$a->id}");
				}
			}
			$this->closeRequest('Item moved');
		}

		if (isset($_POST['move_item_down'])) {
			foreach ($_POST['move_item_down'] as $id => $d) {
				$a = $this->getAttachments()->findByID($id);
				if ($a) {
					$SQL = \Poodle::getKernel()->SQL;
					$r = $SQL->uFetchRow("SELECT
						resource_attachment_sortorder + 1
					FROM {$SQL->TBL->resources_attachments}
					WHERE resource_attachment_id = {$a->id}");
					$SQL->exec("UPDATE {$SQL->TBL->resources_attachments}
					SET resource_attachment_sortorder = resource_attachment_sortorder - 1
					WHERE resource_id = {$a->resource_id}
					  AND resource_attachment_sortorder = {$r[0]}");
					$SQL->exec("UPDATE {$SQL->TBL->resources_attachments}
					SET resource_attachment_sortorder = resource_attachment_sortorder + 1
					WHERE resource_attachment_id = {$a->id}");
				}
			}
			$this->closeRequest('Item moved');
		}

		if (isset($_POST['remove_item'])) {
			foreach ($_POST['remove_item'] as $id => $d) {
				$this->getAttachments()->removeByID($id);
			}
			$this->closeRequest('Item removed');
		}

		if (isset($_POST['upload_media'])) {
			/*
			 * Upload original file as attachment
			 * Then store formatted copies in /media/albums/resized/[media_file_hash]/
			 */
/*
			if (!empty($_POST['attachment_media_items'])) {
				// media/images/*
				foreach (glob(rtrim($_POST['attachment_media_items'], '/').'/*') as $file) {
					if (!preg_match('/\\.(png|jpeg)/', $file)) { continue; }
					$this->addMediaItem(\Poodle\Media\Item::createFromPath($file));
				}
				$this->closeRequest('Attachments added');
			}
*/
			$file = null;
			if (false !== strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data'))
			{
				$stream = $archive = null;
				if (isset($_FILES['attachment_file']))
				{
					$file = $_FILES->getAsFileObject('attachment_file');
					if (!$file->errno) {
						$archive = \Poodle\Filesystem\File::open($file->tmp_name);
					}
					else if (UPLOAD_ERR_NO_FILE != $file->errno) {
						throw new \Exception($file->error);
					}
				}
				if (!$archive && !empty($_POST['attachment_media_item']))
				{
					$file = \Poodle\Media\Item::createFromPath($_POST['attachment_media_item']);
					$archive = \Poodle\Filesystem\File::open($file->file);
				}
				if ($archive instanceof \Poodle\Filesystem\File\Archive)
				{
					$dir = \Poodle::$DIR_MEDIA.'albums/uploaded/';
					if (is_dir($dir) || mkdir($dir, 0777, true)) {
						foreach ($archive->toc['files'] as $file_id => $file) {
							$filename = mb_strtolower(\Poodle\Filesystem\File::fixName(basename($file['filename'])));
							if (preg_match('#\.(png|jpe?g)$#Di',$filename)) {
								set_time_limit(\Poodle\PHP\ini::get('max_execution_time'));
								$filename = preg_replace('#(\.[a-z]+)$#Di', '-'.time().'$1', $filename);
								$fp = fopen($dir.$filename,'wb');
								if ($fp && $archive->extract($file_id, $fp)) {
									fclose($fp);
									$item = \Poodle\Media\Item::createFromPath($dir.$filename);
									if (false === strpos($dir.$filename,$item->file)) {
										unlink($dir.$filename);
									}
									$this->addMediaItem($item);
								} else {
									fclose($fp);
									unlink($dir.$filename);
								}
							}
						}
					}
				}
				else if (!empty($_POST['picasa_user']) && !empty($_POST['picasa_album']))
				{
					$path = "images/picasa/{$_POST['picasa_album']}/";
					$dir  = \Poodle::$DIR_MEDIA . "images/picasa/{$_POST['picasa_album']}/";
					if (is_dir($dir) || mkdir($dir, 0777, true)) {
						$Picasa = new \Poodle\Media\Picasa($_POST['picasa_user']);
						$album = $Picasa->getAlbumphotos($_POST['picasa_album']);
						$HTTP = \Poodle\HTTP\Request::factory();
						foreach ($album['photos'] as $photo) {
							set_time_limit(\Poodle\PHP\ini::get('max_execution_time'));
							$name = urldecode(preg_replace('#^.+/([^/]+)$#D', '$1', $photo['src']));
							$name = str_replace('%2c', '-', mb_strtolower($name));
							$name = str_replace('%27', '-', $name);
							$name = \Poodle\Filesystem\File::fixName(str_replace('.jpg', '.jpeg', $name));
							$photo = $HTTP->get($photo['src']);
							file_put_contents($dir . $name, $photo->body);
							$this->addMediaItem(\Poodle\Media\Item::createFromPath($path . $name));
						}
						$this->closeRequest('Attachment added');
					}
				}
				else if ($this->addMediaItem($file))
				{
					$this->closeRequest('Attachment added');
				}
			}
			$this->closeRequest('Failed to add media item');
		}

		/**
		 * Process $_POST['resource']
		 */
		foreach ($_POST['resource'] as $k => $v) {
			$this->$k = $v;
		}

		if ($this->save()) {
			/**
			 * Process $_POST['resource_data'] revision
			 */
			$this->closeRequest('Album saved');

			/**
			 * Process $_POST['resource_metadata'][{l10n_id}]
			 */
			$this->setMetadata($metadata);

			/**
			 * Process $_POST['acl'][{group_id}]
			 */
			$this->setACL($_POST['acl']?:array());
		}
	}

	protected static function cropScaledImage($img, $w, $h)
	{
		$tmp_img = \Poodle\Image::create($w, $h, 'none', $img->getImageFormat());
		$x = floor(($tmp_img->getImageWidth() - $img->getImageWidth()) / 2);
		$y = floor(($tmp_img->getImageHeight() - $img->getImageHeight()) / 2);
		$tmp_img->compositeImage($img, 1, $x, $y);
		return $tmp_img;
	}

	protected function addMediaItem($file)
	{
		if ($file) {
			$a = $this->addAttachment($file, $this->a_type_id);
			if ($a->save()) {
				return static::resizeMediaItem($a->media, $_POST['media_item_types']);
			}
		}
		return false;
	}

	protected static function resizeMediaItem(\Poodle\Media\Item $item, array $formats = array())
	{
		static $types;
		$path = \Poodle::$DIR_MEDIA.'albums/resized/'.$item->file_hash.'/';
		$file = \Poodle::$DIR_MEDIA.$item->file;
		if (!$types) {
			$types = \Poodle\Media\Album::getItemFormats();
		}

		// Admin image
		if (!is_file($path.'admin.png')) {
			$img  = \Poodle\Image::open($file);
			$img->thumbnailImage(128, 128, true);
			$img = self::cropScaledImage($img, 128, 128);
			$img->setImageFormat('png');
			$img->writeImage($path.'admin.png');
		}

		// Process formats
		foreach ($formats ?: array_keys($types) as $type) {
			if (!isset($types[$type])) {
				continue;
			}
			$type = $types[$type];
//			if ($x>$type['width'] || $y>$type['height']) {
			if (!is_file($path.$type['filename'])) {
				$img = \Poodle\Image::open($file);
				if ($type['options']) {
					$img->thumbnailImage($type['width'], $type['height'], true);
					if ($type['options'] & \Poodle\Media\Album::FORMAT_SCALE_CROP) {
						$img = self::cropScaledImage($img, $type['width'], $type['height']);
					}
				} else {
					$img->cropThumbnailImage($type['width'], $type['height']);
				}
				$img->setImageFormat(pathinfo($type['filename'],PATHINFO_EXTENSION));
				if (!$img->writeImage($path.$type['filename'])) {
					throw new \Exception('Failed to store '.$type['filename'].' image');
				}
			}
		}
	}

	public function save()
	{
		if (parent::save()) {
			$data = $_POST['resource_data'];
			if (array_key_exists('body', $data)) {
				$data['body'] = \Poodle\Input\HTML::fix($data['body']);
				$this->addRevision($data);
			}
			return true;
		}

		return false;
	}

}
