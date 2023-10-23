<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

// http://php.net/filesystemiterator
namespace Poodle\Filesystem;

class Iterator extends \FilesystemIterator
{
	public function __construct($path, $flags=self::SKIP_DOTS)
	{
		$flags |= self::UNIX_PATHS;
		parent::__construct($path, $flags);
		$this->setInfoClass('Poodle\\Filesystem\\FileInfo');
		$this->setFlags($flags);
	}

	public function sorted()
	{
		$array = array_filter(iterator_to_array($this), get_class($this).'::validEntry');
		$array = new \ArrayIterator($array);
		$array->uasort(get_class($this).'::compare');
		return $array;
	}

	public static function compare($i1, $i2)
	{
		if (is_object($i1)) {
			$d1 = $i1->isDir();
			$d2 = $i2->isDir();
			if ($d1 && !$d2) { return -1; }
			if (!$d1 && $d2) { return  1; }
		}
		return strnatcasecmp($i1, $i2);
	}

	public static function getMimeType($file)
	{
		if ($file instanceof \FilesystemIterator
		 || $file instanceof \SplFileInfo)
		{
			$file = $file->getPathname();
		}
		return File::getMime($file);
	}

	public static function validEntry($file)
	{
		if ($file instanceof \FilesystemIterator // CURRENT_AS_SELF
		 || $file instanceof \SplFileInfo)       // CURRENT_AS_FILEINFO
		{
			$file = $file->getBasename();
		}
		else { $file = basename($file); }       // CURRENT_AS_PATHNAME
		return ('CVS' !== $file && '.' !== $file[0]);
	}

	public function next()
	{
		parent::next();
		while (parent::valid()) {
			if (self::validEntry(parent::current())) break;
			else parent::next();
		}
	}
}
