<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Filesystem\File\Stream;

class Raw
{

	private $fp;
	const type = 'default';

	function __construct($filename)
	{
		$this->fp = fopen($filename, 'rb');
		if (!$this->fp) { return false; }
	}
	public function type() { return self::type; }

	public function eof()
	{
		if (!$this->fp) { return true; }
		return feof($this->fp);
	}

	public function read($size=1024)
	{
		if (!$this->fp) { return false; }
		return fread($this->fp, $size);
	}

	public function gets()
	{
		if (!$this->fp) { return false; }
		$data = '';
		while (!feof($this->fp) && substr($data, -1) !== "\n") {
			$data .= fgets($this->fp, 8192);
		}
		return $data;
	}

	public function close()
	{
		if (!$this->fp) { return false; }
		$ret = fclose($this->fp);
		$this->fp = false;
		return $ret;
	}

	# only allow forward seeking to stay compatible with bzip2 functionality
	public function seek($offset, $whence=SEEK_CUR)
	{
		if (!$this->fp) { return false; }
		if ($whence === SEEK_SET) { $offset -= ftell($this->fp); }
		if ($offset <= 0) { return false; }
		return (fseek($this->fp, $offset, SEEK_CUR) === 0);
	}

}
