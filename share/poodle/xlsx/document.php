<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://www.ecma-international.org/publications/standards/Ecma-376.htm
*/

namespace Poodle\XLSX;

class Document
{

	const
		// https://support.office.com/en-us/article/excel-specifications-and-limits-1672b34d-7043-467e-8e27-269d656771c3
		// https://wiki.documentfoundation.org/Faq/Calc/022
		MAX_ROWS = 1048576,
		MAX_COLS = 1024,
		MAX_CHAR = 32767;

	protected
		$author = 'PoodleCMS',

		$stylesheet,

		$sheets = array(),

		$tempfiles = array(),
		$tempdir;

	function __construct()
	{
		$this->stylesheet = new StyleSheet($this);
	}

	public function __destruct()
	{
		foreach ($this->tempfiles as $tmp) {
			unlink($tmp);
		}
	}

	function __get($k)
	{
		if (property_exists($this, $k)) {
			return $this->$k;
		}
	}

	public function setAuthor($author)
	{
		$this->author = $author;
	}

	public function setTempDir($tempdir)
	{
		$this->tempdir = $tempdir;
	}

	public function newTempFile()
	{
		$tmp = tempnam($this->tempdir ?: sys_get_temp_dir(), 'poodle_xlsx_writer_');
		$this->tempfiles[] = $tmp;
		return $tmp;
	}
}
