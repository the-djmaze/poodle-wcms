<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	upload = \Poodle\ACL::create() || IDENTITY->ACL->create
*/

namespace Poodle\Media;

class Explorer extends \Poodle\Resource
{
	public
		$title = 'Media',
		$allowed_methods = array('GET','HEAD','POST');

	protected
		$path = '';

	function __construct(array $data=array())
	{
		parent::__construct($data);

		$path = \Poodle::$PATH->getArrayCopy();
		array_shift($path);
		if ($path && $path[0]) {
			$dir = array_pop($path);
			$file = \Poodle\Base64::urlDecode($dir, true);
			$path[] = $file ? $file : $dir;
			$this->title .= ' / '.implode(' / ',$path);
		}
		$this->path = implode('/',$path);
		if (strpos($this->path,'..')) {
			throw new \Exception('Invalid path');
		}
	}

	public function GET()
	{
		if (XMLHTTPRequest && isset($_GET['tree']))
		{
			if ('getChildren' === $_GET['tree'])
			{
				$ul = array('ul',null);
				foreach ($this->getDirectories() as $r)
				{
					$ul[] = array('li',array('class'=>'unfolds'),
						array('a',array(
							'href'=>\Poodle\Media::getFileUri($r),
							'class'=> $r->getCSSClass()
						), $r->getFilename())
					);
				}

				header('Content-Type: application/json');
				echo json_encode(array('DOM'=>$ul));
				return;
			}
			if ('getFiles' === $_GET['tree'])
			{
				$items = array();
				foreach ($this->getFiles() as $r)
				{
					$items[] = array(
						'name'=>$r->getFilename(),
						'size'=>$r->getHumanReadableSize(),
						'uri'=>\Poodle::$URI_BASE.'/'.$r->getPathname(),
						'class'=>$r->getCSSClass(),
					);
				}
				header('Content-Type: application/json');
				echo json_encode(array('dir'=>$this->path, 'files'=>$items));
			}
			if ('getFileInfo' === $_GET['tree'])
			{
			}
			return;
		}

		$OUT = \Poodle::getKernel()->OUT;
		$OUT->L10N->load('poodle_media');
		$OUT->setTPLName('default');
		$OUT->tpl_layout = 'blank';

		$OUT->media_dirs = $this->getDirectories();
		$OUT->media_files = $this->getFiles();
		$OUT->media_tree_title = ucfirst(basename(\Poodle::$DIR_MEDIA.$this->path));
		$OUT->media_tree_root_class = 'root folder-'.basename(\Poodle::$DIR_MEDIA.$this->path);

		$OUT->head
			->addCSS('poodle_media_explorer')
			->addScript('poodle_media_explorer');
		$OUT->display('poodle/media/explorer');
	}

	public function POST()
	{
		$K = \Poodle::getKernel();
		if (!$K->IDENTITY->ACL->create()) {
			\Poodle\Report::error(403);
		}
		if (isset($_FILES['media_file']))
		{
			\Poodle\Media\Item::createFromUpload($_FILES->getAsFileObject('media_file'), $_POST['media_dir']);
//			\Poodle\URI::redirect($this->uri.$item->getPath());
		}
		\Poodle\URI::redirect($this->uri);
	}

	public function getFiles()
	{
		$dir = new \Poodle\Filesystem\FilesIterator(\Poodle::$DIR_MEDIA.$this->path);
		return $dir->sorted();
	}

	public function getDirectories()
	{
		$dir = new \Poodle\Filesystem\DirectoryIterator(\Poodle::$DIR_MEDIA.$this->path);
		return $dir->sorted();
	}

}
