<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

// http://php.net/filesystemiterator
namespace Poodle\Filesystem;

class FilesIterator extends Iterator
{
	public static function compare($i1, $i2)
	{
		return strnatcasecmp(
			pathinfo($i1, PATHINFO_FILENAME), // preg_replace('#\\.[^\\.]+$#D','',$i1)
			pathinfo($i2, PATHINFO_FILENAME)  // preg_replace('#\\.[^\\.]+$#D','',$i2)
		);
	}

	public static function validEntry($file)
	{
		if ($file instanceof \FilesystemIterator // CURRENT_AS_SELF
		 || $file instanceof \SplFileInfo)       // CURRENT_AS_FILEINFO
		{
			if (!$file->isFile()) return false;
			$file = $file->getBasename();
		}
		else { $file = basename($file); }       // CURRENT_AS_PATHNAME
		return ('CVS' !== $file && '.' !== $file[0]);
	}
}
