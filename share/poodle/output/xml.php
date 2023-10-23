<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Output;

class XML extends \Poodle\Output
{
	public
		$body,
		$head;

	# en.wikipedia.org/wiki/List_of_HTTP_header_fields#Responses
	protected $http = array(
		'Content-Type' => 'text/xml',
	);

	function __construct()
	{
		parent::__construct();
		$this->tpl_type = 'xml';
	}

	# TPL
	public function display(string $filename, $data = null, int $mtime = 0, $options = 0) : bool
	{
		$this->start();
		return parent::display($filename, $data, $mtime, \Poodle\TPL::OPT_PUSH_DOCTYPE | \Poodle\TPL::OPT_END_PARSER);
/*
		if ($this->tpl_header) {
			parent::display($filename, $data, $mtime, $final?\Poodle\TPL::OPT_END_PARSER:0);
		} else {
			$this->body .= $this->toString($filename, $data, $mtime);
		}
		return true;
*/
	}

	public function finish() : void
	{
//		echo $this->body;
	}

}
