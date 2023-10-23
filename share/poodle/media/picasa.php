<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Media;

class Picasa
{
	protected $options = array(
		'userid' => '',
		'authid' => '',
	);

	function __construct($userid, $authid = '', $thumbsize = 160, $imgmax = 1280)
	{
		$this->options['userid']    = $userid;
		$this->options['authid']    = $authid;
		$this->options['thumbsize'] = ($thumbsize ?: 160) . 'c';
		$this->options['imgmax']    = $imgmax ?: 400;
	}

	public function getAlbums($start=0,$length=0)
	{
		$xmldata = $this->fetchXML('?thumbsize=' . $this->options['thumbsize']);
		$albumList = array();
		$numPhotos = 0;
		if (preg_match("@<\?xml version='1\.0' encoding='UTF-8'\?>@",$xmldata)) {
			$data = simplexml_load_string($xmldata);
			$namespace = $data->getDocNamespaces();
			foreach ($data->entry as $entry) {
				if (($numPhotos >= $start) && ($length==0 || ($numPhotos - $start)<$length)) {
					$media_group = $entry->children($namespace['media'])->group;
					$thb_attr = $media_group->thumbnail->attributes();
					$con_attr = $media_group->content->attributes();
					$albumObject = 	array (
						'albumTitle' => (string)$entry->title,
						'photoURL' => (string)$con_attr['url'],
						'thumbURL' => (string)$thb_attr['url'],
						'albumID' => (string) $entry->children($namespace['gphoto'])->id
					);
					$albumList[] = $albumObject;
					$numPhotos += 1;
				}
			}
		}
		return $albumList;
	}

	public function getAlbumphotos($albumid, $offset=0, $limit=null)
	{
		$xmldata = $this->fetchXML('/albumid/' . $albumid . '?imgmax=' . $this->options['imgmax'] . '&thumbsize=' .$this->options['thumbsize']);
		$numPhotos = 0;
		$albumTitle = '';
		$photos = array();
		if (preg_match("@<\?xml version='1\.0' encoding='UTF-8'\?>@",$xmldata)) {
			$data = simplexml_load_string($xmldata);
			$namespace = $data->getDocNamespaces();
			$albumTitle = (string)$data->title[0];
			foreach ($data->entry as $entry) {
				$media_group = $entry->children($namespace['media'])->group;
				$thb_attr = $media_group->thumbnail->attributes();
				$con_attr = $media_group->content->attributes();
				$photo = array (
					'id'    => (string) $entry->children($namespace['gphoto'])->id,
					'title' => (string) $entry->title[0],
					'src'   => (string) $con_attr['url'],
					'thumb' => (string) $thb_attr['url']
				);
				$photo['thumb'] = str_replace('/d/','/s'. $this->options['thumbsize'].'-c/', $photo['thumb']);
				$photos[] = $photo;
				++$numPhotos;
			}
		}
		return array(
			'title' => $albumTitle,
			'count' => $numPhotos,
			'photos' => array_slice($photos, $offset, $limit)
		);
	}

	private function fetchXML($uri)
	{
		$ch = curl_init("http://picasaweb.google.com/data/feed/api/user/{$this->options['userid']}" . $uri);
		if ($this->options['authid']) {
			$header[] = 'Authorization: GoogleLogin auth='.$this->options['authid'];
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			curl_setopt($ch, CURLOPT_HEADER, false);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$xmldata = curl_exec($ch);
		curl_close($ch);
		return $xmldata;
	}

}
