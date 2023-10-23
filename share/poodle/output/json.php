<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Output;

class JSON extends \Poodle\Output\HTML
{

	function __construct()
	{
		parent::__construct();
		$this->http = array();
	}

	public function start() : bool
	{
		$this->tpl_header = '';
		return parent::start();
	}

	public function finish() : void
	{
		if ($this->started()) {
			$R = \Poodle::getKernel()->RESOURCE;
			header('Content-Type: application/json');
			echo json_encode(array(
				'lang'  => $R->lng,
				'title' => $R->title, // $this->head->title
				'body'  => $this->body
			));
		}
	}

	public static function ob_handler($buffer, $mode) : string
	{
		return $buffer;
	}

}
