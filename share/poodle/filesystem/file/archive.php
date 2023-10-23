<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Filesystem\File;

abstract class Archive
{
	public
		$toc,
		$filename;

	function __construct($filename)
	{
		$this->filename = $filename;
		$this->load_toc();
	}

	public function type()
	{
		return strtolower(get_class_basename($this));
	}

	public function close() { return true; }

	abstract public function extract($id, $to=false);

	abstract protected function load_toc();
}
