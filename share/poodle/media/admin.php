<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Media;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Media',
		$allowed_methods = array('GET','HEAD','POST');

	protected
		$path = '';

	private static
		$mdirl = 0;

	function __construct(array $data=array())
	{
		parent::__construct($data);
		self::$mdirl = mb_strlen(\Poodle::$DIR_MEDIA);

		$path = \Poodle::$PATH->getArrayCopy();
		array_shift($path);
		if ($path[0]) {
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
		if (!isset($_SESSION['ADMIN_MEDIA_VIEW'])) {
			$_SESSION['ADMIN_MEDIA_VIEW'] = 'list';
		}

		if (isset($_GET['view'])) {
			$_SESSION['ADMIN_MEDIA_VIEW'] = $_GET['view'];
			\Poodle\URI::redirect('/admin'.$_SERVER['PATH_INFO']);
		}

		if (isset($_GET['synchronize'])) {
			$this->synchronize();
			$this->closeRequest('Database Synchronized', '/admin'.$_SERVER['PATH_INFO']);
		}

		$OUT = \Poodle::getKernel()->OUT;

		$dirs = explode('/',$this->path);
		$ldir = array_pop($dirs);
		$path = '/admin/poodle_media/';
		foreach ($dirs as $dir) {
			$path .= $dir.'/';
			$OUT->crumbs->append($dir, $path);
		}

		if (is_file(\Poodle::$DIR_MEDIA.$this->path)) {
			$item = \Poodle\Media\Item::createFromPath($this->path);
			if (!$item) {
				throw new \Exception('File not found');
			}

			$isImage = ('image' === $item->getFileInfo()->getMimeRoot());

			if (isset($_GET['rotate']) || isset($_GET['mirror'])) {
				if (!$isImage) {
					throw new \Exception('Invalid image');
				}
				$image = \Poodle\Image::open($item->getPathname());

				if (isset($_GET['mirror'])) {
					if ('flip' === $_GET['mirror']) {
						// Creates a vertical mirror image
						$image->flipImage();
						$file = preg_replace('#(\\.[^\\.]+)$#D','.flip$1',$item->file);
					} else
					if ('flop' === $_GET['mirror']) {
						// Creates a horizontal mirror image
						$image->flopImage();
						$file = preg_replace('#(\\.[^\\.]+)$#D','.flop$1',$item->file);
					}
				}
				if (isset($_GET['rotate'])) {
					$image->rotate($_GET->int('rotate'));
					$file = preg_replace('#(\\.[^\\.]+)$#D','.'.$_GET->int('rotate').'d$1',$item->file);
				}

				$path = dirname(\Poodle::$DIR_MEDIA.$file);
				if ($image->writeImage(\Poodle::$DIR_MEDIA.$file)) {
					$item = \Poodle\Media\Item::createFromPath($file);
				} else {
					\Poodle\Report::error('Failed to save rotated image','Failed to save rotated image');
				}
				\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[0].'/'.$item->getPath().'/'.\Poodle\Base64::urlEncode($item->getFilename()));
			}

			$OUT->media_info = null;
			if ($isImage && $imginfo = getimagesize($item->getPathname())) {
				$OUT->media_info = array(
					'width'  => $imginfo[0],
					'height' => $imginfo[1],
				);
			}

			$OUT->head
				->addCSS('poodle_tabs')
				->addCSS('poodle_media_admin')
				->addScript('poodle_tabs')
				->addScript('poodle_media_resize')
				->addScript('poodle_media_admin');
			$OUT->media_dirs = $this->fetch_dirs(\Poodle::$DIR_MEDIA);
			$OUT->media_item = $item;
			$OUT->crumbs->append($ldir, $path.\Poodle\Base64::urlEncode($ldir));
			$OUT->display('poodle/media/admin/file');
			return;
		}

		$dir = new \Poodle\Filesystem\DirectoryIterator(\Poodle::$DIR_MEDIA.$this->path);

		if (XMLHTTPRequest && isset($_GET['tree']) && 'getChildren' === $_GET['tree']) {
			$ul = array('ul',null);
			foreach ($dir->sorted() as $r) {
				$details = array('span',array('class'=>'details'));
				if (!$r->isDir()) {
					$details[] = array('span',array('class'=>'filesize'),$r->getHumanReadableSize());
				}
				$details[] = array('span',null,$r->getPermsRWX());
				$ul[] = array('li',array('class'=> $r->isDir()?'unfolds':null),
					array('a',array(
						'href'=>\Poodle\Media::getFileUri($r),
						'class'=> $r->getCSSClass()
					), $r->getFilename()),
					$details,
				);
			}

			header('Content-Type: application/json');
			echo json_encode(array('DOM'=>$ul));
			return;
		}

		$files = new \Poodle\Filesystem\FilesIterator(\Poodle::$DIR_MEDIA.$this->path);

		$OUT->head
			->addCSS('poodle_media_admin')
			->addCSS('poodle_media_tree')
			->addScript('poodle_media_tree');

		$OUT->media_dir = $this->path;
		$OUT->media_items = $dir->sorted();
		$OUT->media_dir_files = $files->sorted();
		$OUT->media_tree_title = ucfirst(basename(\Poodle::$DIR_MEDIA.$this->path));
		$OUT->media_tree_root_class = 'root'.($this->path ? ' sub' : '').' folder-'.basename(\Poodle::$DIR_MEDIA.$this->path);

		if ('icons' === $_SESSION['ADMIN_MEDIA_VIEW']) {
			$OUT->display('poodle/media/admin/overview-icons');
		} else {
			$OUT->display('poodle/media/admin/overview');
		}
	}

	public function POST()
	{
		if (isset($_FILES['media_file']))
		{
			$item = \Poodle\Media\Item::createFromUpload($_FILES->getAsFileObject('media_file'), $_POST['media_dir']);
			$msg = \Poodle::getKernel()->L10N->get('Media added');
			\Poodle::closeRequest($msg, 201,
				\Poodle\URI::admin('/'.\Poodle::$PATH[0].'/'.$item->getPath().'/'.\Poodle\Base64::urlEncode($item->getFilename())),
				$msg);
		}
		else if (isset($_POST['move_to']))
		{
			$to = $_POST['move_to'];
			if (!is_dir(\Poodle::$DIR_MEDIA.$to)) {
				return;
			}
			if (is_file(\Poodle::$DIR_MEDIA.$this->path))
			{
				$item = \Poodle\Media\Item::createFromPath($this->path);
				if ($item->moveTo($to)) {
					header('Content-Type: application/json');
					echo json_encode(array('href'=>\Poodle\Media::getFileUri($item->getFileInfo())));
					return;
				}
			}
			if (is_dir(\Poodle::$DIR_MEDIA.$this->path))
			{
				$to = rtrim($to,'/').'/'.basename($this->path);
				if (!is_dir(\Poodle::$DIR_MEDIA.$to) && rename(\Poodle::$DIR_MEDIA.$this->path, \Poodle::$DIR_MEDIA.$to)) {

					// TODO: update database entries!

					header('Content-Type: application/json');
					echo json_encode(array('href'=>\Poodle\URI::admin('/poodle_media/'.$to.'/')));
					return;
				}
			}
			echo 'move to failed';
		}
		else if (is_dir(\Poodle::$DIR_MEDIA.$this->path))
		{
			if (isset($_POST['rename_folder'])) {
				$to = $_POST['rename_folder'];
				if (strlen($to) && '..'!=$to && '.'!=$to && !preg_match('#["*/:<>?|\p{C}\p{Zs}]#u',$to))
				{
					$from = $this->path;
					$to   = dirname($from).'/'.$to.'/';
					if (!is_dir(\Poodle::$DIR_MEDIA.$to) && rename(\Poodle::$DIR_MEDIA.$from, \Poodle::$DIR_MEDIA.$to))
					{
						$SQL = \Poodle::getKernel()->SQL;
						$qr = $SQL->query("SELECT media_id, media_file FROM {$SQL->TBL->media} WHERE media_file LIKE '{$from}%'");
						while ($row = $qr->fetch_row())
						{
							$SQL->exec("UPDATE {$SQL->TBL->media}
							SET media_file=".$SQL->quote(preg_replace("#^{$from}#", $to, $row[1]))."
							WHERE media_id={$row[0]}");
						}
						$SQL->exec("UPDATE {$SQL->TBL->resources_data}
						SET resource_body=REPLACE(resource_body, "
							.$SQL->quote(\Poodle::$URI_MEDIA.$from.'"').", "
							.$SQL->quote(\Poodle::$URI_MEDIA.$to.'"').")");

						header('Content-Type: application/json');
						echo json_encode(array('href'=>\Poodle\URI::admin('/poodle_media/'.$to.'/')));
						return;
					}
				}
				echo 'Failed to rename folder '.$from.' to '.$to;
			}
			else if (isset($_POST['create_folder'])) {
				$dir = $_POST['create_folder'];
				if (strlen($dir) && '..'!=$dir && '.'!=$dir && !preg_match('#["*/:<>?|\p{C}\p{Zs}]#u',$dir))
				{
					$dir = \Poodle::$DIR_MEDIA.$this->path.$dir;
					if (!is_dir($dir) && mkdir($dir, 0777)) {
						header('Content-Type: application/json');
						echo json_encode(array('DOM'=>array('li',
							array('class'=>'unfolds'),
							array('a',array(
								'href'=>$this->path.$_POST['create_folder'].'/',
								'class'=> 'folder-'.$_POST['create_folder']
							), $_POST['create_folder']),
							array('span',array('class'=>'details'),array('span',null,'rwx')),
						)));
						return;
					}
					echo 'Failed to create directory: '.$dir;
				}
				else
				{
					echo 'Invalid directory name: '.$dir;
				}
			}
			else if (!empty($_POST['delete_folder'])) {
				$dir = \Poodle::$DIR_MEDIA.$this->path;
				if (!is_dir($dir)) {
					echo $dir.' is not a directory';
				} else
				if (glob($dir.'*')) {
					echo $dir.' still contains files';
				} else
				if (!rmdir($dir)) {
					echo 'Failed to delete directory: '.$dir;
				} else {
					header('Content-Type: application/json');
					echo json_encode(array('deleted'=>true));
				}
			}
			return;
		}
		else if (is_file(\Poodle::$DIR_MEDIA.$this->path))
		{
			$item = \Poodle\Media\Item::createFromPath($this->path);

			if (isset($_POST['resizeimage']))
			{
				$image = \Poodle\Image::open($item->getPathname());
				if (!empty($_POST['scale'])) {
					$image->sampleImage($_POST['scale']['w'], $_POST['scale']['h']);
				}
				if (!empty($_POST['actions'])) {
					foreach ($_POST['actions'] as $f => $v) {
						if ('rotate' === $f && $v > 0 && $v < 360) {
							$image->rotate($v);
						}
						if ('mirror' === $f) {
							$image->flopImage();
						}
					}
				}
				if (!empty($_POST['crop'])) {
					$image->cropImage($_POST['crop']['w'], $_POST['crop']['h'], $_POST['crop']['x'], $_POST['crop']['y']);
				}
				if (!empty($_POST['format'])) {
					$format = \Poodle\Media::getImageFormat($_POST['format']);
					if (!$format) {
						trigger_error('Failed to find image format with id '.$id);
					}
					else if (25 < $format['width']) {
						$image->cropThumbnailImage($format['width'], $format['height']);
					}
				}
				$file = 'images/'.$_POST['file'].'.'.$image->getImageFormat();
				$file = dirname($file) . '/' . \Poodle\Filesystem\File::fixName(basename($file));
				if ($image->writeImage(\Poodle::$DIR_MEDIA.$file)) {
					$item = \Poodle\Media\Item::createFromPath($file);
				} else {
					echo 'Failed to save resized image';
					trigger_error('Failed to save resized image');
					return;
				}
			}
			else if (isset($_POST['delete']))
			{
				if ($item->delete()) {
					\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[0].'/'.$item->getPath().'/');
				}
			}
			else
			{
				if (!$item->moveTo($_POST['media_dir'])) {
					\Poodle\Report::error(409,'File not moved');
				}
				foreach ($_POST['media_details'] as $l10n_id => $data) {
					$item->setDetails($l10n_id, $data['title'], $data['description']);
				}
			}
			\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[0].'/'.$item->getPath().'/'.\Poodle\Base64::urlEncode($item->getFilename()));
		}
	}

	protected function fetch_dirs($path)
	{
		$dirs = array();
		if ($sdirs = glob($path.'*', GLOB_ONLYDIR))
		{
			foreach ($sdirs as $dir)
			{
				$uri = mb_substr($dir, self::$mdirl);
				$name = basename($uri);

				if ('.' === $name[0] || 'CVS' === $name) continue;

				$dirs[] = array(
					'value' => $uri,
					'name'  => $name,
					'level' => substr_count($uri,'/'),
					'disabled' => is_writable($dir)
				);
				$dirs = array_merge($dirs, $this->fetch_dirs($dir.'/'));
			}
		}
		return $dirs;
	}

	protected function synchronize($path=null)
	{
		if (!$dir) $dir = \Poodle::$DIR_MEDIA;
		$dir = realpath($dir).'/';

		/**
		 * \Poodle\Media\Item::createFromPath() inserts missing files automatically
		 */
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
		);
		foreach ($iterator as $path) {
			if (!$path->isDir()
			 && !preg_match('#[\\/]\.trash|CVS[\\/]#i',$path)
			 && preg_match('#'.\Poodle::$DIR_MEDIA.'[^/]+/#', $path))
			{
				$item = \Poodle\Media\Item::createFromPath($path);
			}
		}

		/**
		 * Delete removed files from the database
		 */
		$SQL = \Poodle::getKernel()->SQL;
		$ids = array();
		foreach ($SQL->query("SELECT media_id, media_file FROM {$SQL->TBL->media}") as $item) {
			if (!is_file(\Poodle::$DIR_MEDIA.$item['media_file'])) {
				$ids[] = $item['media_id'];
			}
		}
		if ($ids) {
			$ids = implode(',', $ids);
			$SQL->TBL->media->delete("media_id IN ({$ids})");
			$SQL->TBL->resources_attachments->delete("media_id IN ({$ids})");
		}
	}

}
