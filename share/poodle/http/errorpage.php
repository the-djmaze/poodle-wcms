<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\HTTP;

class Errorpage extends \Poodle\Resource
{
	public
		// Handle any request method
		$allowed_methods = '*';

	// Handle any request method
	public function __call($name, array $arguments)
	{
		if (301 == $this->type_id || 302 == $this->type_id) {
			\Poodle\URI::redirect($this->getMetadata('Location'), $this->type_id);
		}
		echo "{$name}() {$this->type_id}: {$this->type_name}";
	}
}
