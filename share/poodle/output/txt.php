<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Output;

class TXT extends \Poodle\Output
{
	function __construct()
	{
		parent::__construct();
		$this->tpl_layout = 'txt';
		$this->tpl_type = 'txt';
		\Poodle\HTTP\Headers::setContentType('text/plain');
	}
	public function finish() : void {}

	# TPL
	public function display(string $filename, $data = null, int $mtime = 0, $final = 0) : bool
	{
		if ($this->findFile($filename)) {
			return parent::display($filename, $data, $mtime, $final?\Poodle\TPL::OPT_END_PARSER:0);
		} else {
			\Poodle\Report::error(404);
		}
		return true;
	}
}
