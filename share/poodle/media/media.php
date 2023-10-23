<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Media
{
	private static
		$mdirl = 0;

	public static function getTypes($media_type_dir=null)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$type_dirs = array();
		$query = 'SELECT media_type_mime, media_type_directory FROM '.$SQL->TBL->media_types.' WHERE media_type_flags & 1';
		if ($media_type_dir) { $query .= ' AND media_type_mime LIKE ('.$SQL->quote($media_type_dir.'/%').')'; }
		$result = $SQL->query($query);
		while ($mmt = $result->fetch_row()) { $type_dirs[str_replace('/x-', '/', $mmt[0])] = $mmt[1]; }
		$result->free();
		return $type_dirs;
	}

	public static function mapExtensionsToMime()
	{
		$SQL = \Poodle::getKernel()->SQL;
		$type_dirs = array();
		$result = $SQL->query("SELECT media_type_mime, media_type_extension FROM {$SQL->TBL->media_types}");
		while ($mmt = $result->fetch_row()) { $type_dirs[$mmt[1]] = $mmt[0]; }
		$result->free();
		return $type_dirs;
	}

	public static function getImageFormats()
	{
		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->query("SELECT
			mif_id     id,
			mif_width  width,
			mif_height height,
			mif_label  label,
			mif_dir    dir
		FROM {$SQL->TBL->media_imageformats}
		ORDER BY mif_label");
	}

	public static function getImageFormat($id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->uFetchAssoc("SELECT
			mif_width  width,
			mif_height height,
			mif_label  label,
			mif_dir    dir
		FROM {$SQL->TBL->media_imageformats}
		WHERE mif_id=".(int)$id);
	}

	public static function getTypeDir($mime)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$r = $SQL->uFetchRow("SELECT
			media_type_directory
		FROM {$SQL->TBL->media_types}
		WHERE media_type_flags & 1
		  AND media_type_mime=".$SQL->quote($mime));
		return $r ? $r[0] : false;
	}

	public static function getFileUri(\SplFileInfo $file, $admin=POODLE_BACKEND)
	{
		if (!self::$mdirl) self::$mdirl = mb_strlen(\Poodle::$DIR_MEDIA);

		$uri = '/'.ltrim($file->isDir()
				? mb_substr($file->getPathname(),self::$mdirl).'/'
				: mb_substr($file->getPath(),self::$mdirl).'/'.\Poodle\Base64::urlEncode($file->getFilename())
			,'/');

		return $admin ? \Poodle\URI::admin('/poodle_media'.$uri) : \Poodle\URI::index('/media-explorer'.$uri);
	}

	// Called by \Poodle\Identity->delete()
	public static function onIdentityDelete(\Poodle\Events\Event $event)
	{
		if ($event->target instanceof \Dragonfly\Identity) {
/*
			// TODO: remove files
			\Dragonfly::getKernel()->SQL->TBL->media->delete(array(
				'identity_id' => $event->target->id
			));
*/
		}
	}

}
