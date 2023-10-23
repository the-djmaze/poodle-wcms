<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Input;

class POST extends GET
{
	public function html(...$args) { return HTML::fix(self::_get($args)); }

	public static function raw_data()
	{
		return file_get_contents('php://input');
	}

	public static function max_size()
	{
		return \Poodle\PHP\INI::getInt('post_max_size', '8M');
	}

	public function __toString() { return http_build_query($this, '', '&'); }
}
