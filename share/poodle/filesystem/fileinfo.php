<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Filesystem;

class FileInfo extends \SplFileInfo
{
	// This method is built-in as of PHP 5.3.6.
	public function getExtension()
	{
		return pathinfo($this->getFilename(), PATHINFO_EXTENSION);
	}

	public function getNameWithoutExtension()
	{
		return $this->getBasename('.'.$this->getExtension());
	}

	protected $mime;
	public function getMimeType()
	{
		if (!$this->mime) $this->mime = File::getMime($this->getPathname());
		return $this->mime;
	}

	public function getMimeRoot()
	{
		$m = $this->getMimeType();
		return substr($m, 0, strpos($m,'/'));
	}

	public function getHumanReadableSize($precision=2)
	{
		return \Poodle::getKernel()->L10N->filesizeToHuman($this->getSize(), $precision);
	}

	public function getCSSClass()
	{
		return $this->isDir() ? 'folder-'.$this->getBasename() : 'mime-'.strtr($this->getMimeType(), '/', ' ');
	}

	public function getUri()
	{
		return \Poodle::$URI_BASE.'/'.$this->getPathname();
	}

	public function getPermsRWX()
	{
		return ($this->isReadable() ? 'r' : '-')
		      .($this->isWritable() ? 'w' : '-')
		      .($this->isExecutable() ? 'x' : '-');
	}
}
