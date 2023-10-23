<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Media;

class Item extends \Poodle\SQL\Record
{
	protected
		$id           = 0,
		$identity_id  = 0,
		$file         = '',
		$file_hash    = '',
		$org_filename = '',

		$fileinfo     = '';

	protected
		$sql_table = 'media',
		$sql_id_field = 'media_id',
		$sql_field_map = array(
			'media_id' => 'id',
			'identity_id' => 'identity_id',
			'media_file' => 'file',
			'media_file_hash' => 'file_hash',
			'media_org_filename' => 'org_filename'
		);

	function __construct($id=0, $org_filename='')
	{
		if ($id) {
			if (is_int($id) || ctype_digit($id)) {
				if (!$this->sqlInitRecord($id)) {
					throw new \Exception("No media found with ID: {$id}");
				}
			}
			else if (is_string($id) && strlen($id)) {
				$id = self::fixPath($id);
				if (is_file(\Poodle::$DIR_MEDIA.$id)) {
					$this->file         = $id;
					$this->file_hash    = sha1_file(\Poodle::$DIR_MEDIA.$id);
					$this->org_filename = $org_filename;
				} else {
					throw new \Exception("Media file not found: {$id}");
				}
			}
		}
	}

	function __get($k)
	{
		if (property_exists($this, $k)) { return $this->$k; }
		if ('extension' === $k) { return pathinfo($this->file, PATHINFO_EXTENSION); }
		if ('filename' === $k) { return \Poodle::$DIR_MEDIA . $this->file; }
		trigger_error("Property {$k} does not exist");
	}

	protected $details = null;
	public function getDetails($l10n_id=0, $field=null)
	{
		$K = \Poodle::getKernel();
		if (!$this->details) {
			$this->details = array(array('title' => '', 'description' => ''));
			$qr = $K->SQL->query("SELECT l10n_id, media_title, media_description FROM {$K->SQL->TBL->media_details} WHERE media_id={$this->id}");
			while ($r = $qr->fetch_row()) {
				$this->details[(int)$r[0]] = array(
					'title' => $r[1],
					'description' => $r[2],
				);
			}
		}
		$l10n_id = (int)$l10n_id;
		if (!$l10n_id) { $l10n_id = $K->L10N->id; }
		if (!isset($this->details[$l10n_id])) { $l10n_id = 0; }
		return $field ? $this->details[$l10n_id][$field] : $this->details[$l10n_id];
	}

	public function setDetails($l10n_id, $title, $description)
	{
		$l10n_id = (int)$l10n_id;
		if (!$l10n_id) return false;

		if (!$this->details) $this->getDetails();
		$update = isset($this->details[$l10n_id]);

		$this->details[$l10n_id] = array(
			'title' => $title,
			'description' => $description,
		);
		$tbl = \Poodle::getKernel()->SQL->TBL->media_details;
		if ($update) {
			$tbl->update(array(
				'media_title' => $title,
				'media_description' => $description,
			),array(
				'l10n_id' => $l10n_id,
				'media_id' => $this->id,
			));
		} else {
			$tbl->insert(array(
				'l10n_id' => $l10n_id,
				'media_id' => $this->id,
				'media_title' => $title,
				'media_description' => $description,
			));
		}
	}

	public function getTitle($l10n_id=0) { return $this->getDetails($l10n_id, 'title'); }
	public function getDescription($l10n_id=0) { return $this->getDetails($l10n_id, 'description'); }

	public function getPath()     { return dirname($this->file); }
	public function getFilename() { return basename($this->file); }
	public function getFileInfo() {
		if (!is_object($this->fileinfo)) {
			$this->fileinfo = new \Poodle\Filesystem\FileInfo($this->getPathname());
		}
		return $this->fileinfo;
	}
	public function getPathname() { return \Poodle::$DIR_MEDIA.$this->file; }
	public function getURI() { return \Poodle::$URI_MEDIA.'/'.$this->file; }

	public function getSizeURI($width, $height = 0)
	{
		$file = $this->file;
		$width = intval($width);
		if ($width) {
			$height = intval($height);
			if ($height) {
				$width = "{$width}x{$height}";
			}
			$file = preg_replace('/(\\.[^\\.]+)$/D', ".{$width}\$1", $file);
		}
		return \Poodle::$URI_MEDIA.'/'.$file;
	}

	public function getAdminURI()
	{
		return '/admin/poodle_media/'.$this->getPath().'/'.\Poodle\Base64::urlEncode($this->getFilename());
	}

	public function getDimension()
	{
		if ($imginfo = getimagesize($this->getPathname())) {
			return array(
				'width'  => $imginfo[0],
				'height' => $imginfo[1],
			);
		}
		return false;
	}

	protected static function getBaseDir($dir) { return mb_substr($dir,0,mb_strpos($dir.'/','/')); }
	public function moveTo($dir)
	{
		$dir = trim($dir,'/');
		if ($dir === $this->getPath()) { return true; }
		if ($dir && self::getBaseDir($dir) === self::getBaseDir($this->file)) {
			$new_file = $dir.'/'.$this->getFilename();
			if (!is_file(\Poodle::$DIR_MEDIA.$new_file)
			 && rename(\Poodle::$DIR_MEDIA.$this->file, \Poodle::$DIR_MEDIA.$new_file))
			{
				$SQL = \Poodle::getKernel()->SQL;
				$from = $SQL->quote($this->file.'"');
				$to   = $SQL->quote($new_file.'"');
				$SQL->exec("UPDATE {$SQL->TBL->resources_data} SET resource_body=REPLACE(resource_body, {$from}, {$to})");
				$this->file = $new_file;
				return $this->save();
			}
		}
		return false;
	}

	public function save()
	{
		return $this->sqlSaveRecord();
	}

	public function delete()
	{
		if ($this->id) {
			// move to .trash
			$dir = \Poodle::$DIR_MEDIA.dirname($this->file).'/.trash';
			if (is_dir($dir) || mkdir($dir,0777)) {
				$new_file = $dir.'/'.$this->getFilename().'.'.time();
				if (rename(\Poodle::$DIR_MEDIA.$this->file, $new_file)) {
					$SQL = \Poodle::getKernel()->SQL;
					$SQL->TBL->media->delete("media_id = {$this->id}");
					$SQL->TBL->resources_attachments->delete("media_id = {$this->id}");
					return true;
				}
			}
		}
		return false;
	}

	# $mime_type_dir see http://www.iana.org/assignments/media-types/
//	public static function save_upload($form_field, $mime_type_dir=null, $media_dir=null)
	public static function createFromUpload(\Poodle\Input\File $file, $target_dir=null)
	{
		$dir = \Poodle\Media::getTypeDir($file->mime);
		if (!$dir) {
			$L10N = \Poodle::getKernel()->L10N;
			$L10N->load('poodle_media');
			throw new \Exception(sprintf($L10N['Disallowed file type: %s'], $file->mime));
		}
		if ($target_dir && 0 === strpos($target_dir, $dir)) {
			$dir = $target_dir;
		}
		$dir = trim($dir, '/').'/';
/*
		$SQL = \Poodle::getKernel()->SQL;
		$r = $SQL->uFetchRow("SELECT media_id FROM {$SQL->TBL->media} WHERE media_file_hash=".$SQL->quote($file->sha1));
		if ($r) {
			return new Item($r[0]);
		}
*/
		if (!$file->moveTo(\Poodle::$DIR_MEDIA.$dir.$file->filename)) {
			throw new \Exception($file->error, $file->errno);
		}
		$item = new Item();
		$item->file         = $dir.$file->name;
		$item->file_hash    = $file->sha1;
		$item->org_filename = $file->org_name;
		$item->save();
		return $item;
	}

	public static function createFromPath($path, $org_filename='')
	{
		$path = self::fixPath($path);
		if (is_file(\Poodle::$DIR_MEDIA.$path)) {
			$SQL = \Poodle::getKernel()->SQL;
			$r = $SQL->uFetchRow("SELECT media_id FROM {$SQL->TBL->media}
			WHERE media_file=".$SQL->quote($path));
			if ($r) {
				$item = new Item($r[0]);
			} else {
				$item = new Item($path, $org_filename?$org_filename:basename($path));
				$item->save();
			}
			return $item;
		}
		return null;
	}

	protected static function fixPath($file)
	{
		$path = realpath($file);
		if (!$path) { $path = realpath(\Poodle::$DIR_MEDIA.$file); }
		return ltrim(strtr(str_replace(realpath(\Poodle::$DIR_MEDIA), '', $path), DIRECTORY_SEPARATOR, '/'), '/');
	}

}
