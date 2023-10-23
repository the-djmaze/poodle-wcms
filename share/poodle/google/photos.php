<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Google;

class Photos
{

	public static function getAlbums($userid, $offset = 0, $limit = 0, $thumbsize = 160)
	{
		$CACHE = \Poodle::getKernel()->CACHE;
		$cache_key = "google/photos/{$userid}/albums";
		$albums = $CACHE->get($cache_key) ?: array();
		if (!$albums && $data = static::fetch($userid, '', $thumbsize)) {
			$max = $offset + $limit;
			foreach ($data['entry'] as $entry) {
				$albums[] = array (
					'id'        => $entry['gphoto$id']['$t'],
					'title'     => $entry['title']['$t'],
					'summary'   => $entry['summary']['$t'],
					'published' => new \DateTime($entry['published']['$t']),
					'updated'   => new \DateTime($entry['published']['$t']),
					'thumbURL'  => $entry['media$group']['media$thumbnail'][0]['url'],
				);
			}
			$CACHE->set($cache_key, $albums, 3600);
		}
		return ($offset || $limit) ? array_slice($albums, $offset, $limit ?: null) : $albums;
	}

	public static function getAlbum($userid, $albumid, $offset = 0, $limit = 0, $thumbsize = 160, $imgmax = 1600)
	{
		$CACHE = \Poodle::getKernel()->CACHE;
		$cache_key = "google/photos/{$userid}/album_{$albumid}";
		$album = $CACHE->get($cache_key) ?: array();
		if (!$album && $data = static::fetch($userid, "/albumid/{$albumid}", $thumbsize, $imgmax)) {
			$max = $offset + $limit;
			$album = array (
				'id'      => $albumid,
				'title'   => $data['title']['$t'],
				'summary' => empty($data['summary']['$t']) ? '' : $data['summary']['$t'],
				'updated' => empty($data['published']['$t']) ? '' : new \DateTime($data['published']['$t']),
				'photos'  => array(),
			);
			foreach ($data['entry'] as $entry) {
				$album['photos'][] = array (
					'id'        => $entry['gphoto$id']['$t'],
					'title'     => $entry['title']['$t'],
					'summary'   => $entry['summary']['$t'],
					'published' => new \DateTime($entry['published']['$t']),
					'updated'   => new \DateTime($entry['published']['$t']),
					'url'       => $entry['media$group']['media$content'][0]['url'],
					'width'     => $entry['media$group']['media$content'][0]['width'],
					'height'    => $entry['media$group']['media$content'][0]['height'],
					'thumbURL'  => $entry['media$group']['media$thumbnail'][0]['url'],
				);
			}
			$CACHE->set($cache_key, $album, 3600);
		}
		$album['count'] = count($album['photos']);
		if ($offset || $limit) {
			$album['photos'] = array_slice($album['photos'], $offset, $limit ?: null);
		}
		return $album;
	}

	protected static function fetch($userid, $path, $thumbsize = 160, $imgmax = 1600)
	{
		$ch = curl_init("https://picasaweb.google.com/data/feed/api/user/{$userid}{$path}?alt=json&thumbsize={$thumbsize}c&imgmax={$imgmax}");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$data = json_decode(curl_exec($ch), true);
		curl_close($ch);
		return $data ? $data['feed'] : false;
	}

}
