<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Filesystem\File\Stream;

class Gzip
{

	private $fp;
	private $offset;
	const type = 'gzip';

	function __construct($filename)
	{
		$this->fp = gzopen($filename, 'rb');
		if (!$this->fp) { return false; }
		$this->offset = 0;
	}
	public function type() { return self::type; }

	public function eof()
	{
		if (!$this->fp) { return true; }
		return gzeof($this->fp);
	}

	public function read($size=1024)
	{
		if (!$this->fp) { return false; }
		$this->offset += $size;
		return gzread($this->fp, $size);
	}

	public function gets()
	{
		if (!$this->fp) { return false; }
		$data = '';
		while (!gzeof($this->fp) && substr($data, -1) !== "\n") {
			$data .= gzgets($this->fp, 8192);
		}
		return $data;
	}

	public function close()
	{
		if (!$this->fp) { return false; }
		$ret = gzclose($this->fp);
		$this->fp = false;
		return $ret;
	}

	# gzseek can be extremely slow so we only allow forward seeking
	public function seek($offset, $whence=SEEK_CUR)
	{
		if (!$this->fp) { return false; }
		if ($whence === SEEK_SET) { $offset -= $this->offset; }
		if ($offset <= 0) { return false; }
		$this->offset += $offset;
		gzread($this->fp, $offset);
		return true;
	}

}
