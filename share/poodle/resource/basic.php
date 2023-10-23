<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Basic extends \Poodle\Resource
{
	public $allowed_methods = array('GET','HEAD');

	function __construct($data=array())
	{
		$data = static::detectData($data);
		parent::__construct($data);
	}

	public function GET()
	{
		$this->HEAD();
		$this->display();
	}

	public function HEAD()
	{
		$K = \Poodle::getKernel();
		$ETag = $K->IDENTITY->id.'-'.$K->OUT->L10N->id.'-'.md5($_SERVER['REQUEST_URI']);
		\Poodle\HTTP\Headers::setETagLastModified($ETag, $this->mtime);
		$K->OUT->send_headers();
	}
}
