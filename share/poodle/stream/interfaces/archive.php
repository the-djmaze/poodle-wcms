<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Example:
		$zip = new \Poodle\Stream\ZIP();
		$tar = new \Poodle\Stream\TAR();
		$tar->pushHttpHeaders('filename');
		$tar->addFile('file1.txt');
		$tar->addRecursive(__DIR__);
		$tar->addFromStream(fopen('file2.txt', 'rb'), 'file2.txt');
		$tar->addFromString('file3.txt', 'file 3 content');
		$tar->close();
*/

namespace Poodle\Stream\Interfaces;

interface Archive
{
	const
		NONE    = "\x00\x00",
		DEFLATE = "\x08\x00",
		BZIP2   = "\x0C\x00",
		LZMA    = "\x0E\x00";

	function __construct($target = 'php://output', string $compression);

	public function addFile($file, string $name = null) : bool;
	public function addFromStream($resource, string $name, int $time = 0) : bool;
	public function addFromString(string $name, string $data, int $time = 0) : bool;
	public function addRecursive($dir, $ignore = '#/(\\.hg(/|$)|\\.hgignore)#');

}
