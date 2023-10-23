<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Comments;

class Comment extends \Poodle\Resource\Edit
{
	public
		$allowed_methods = array('GET','HEAD','POST'),
		$type_id = 7,
		$flags   = 7; // self::FLAG_FIXED_URI | self::FLAG_FIXED_TYPE | self::FLAG_FIXED_DATE

	function __construct(array $data=array())
	{
		parent::__construct($data);
		if (!$this->id) {
			$K = \Poodle::getKernel();
			// This URI structure is important for viewing comments
			// in the right order on Threaded/Nested display modes
			$this->uri = time().'-'.$K->IDENTITY->id;
			// Set parent_id to current resource by default, you may
			// override this when, for example, replying to a comment
			$this->parent_id = $K->RESOURCE->id;
		}
	}

	public function save()
	{
		if (parent::save()) {
			$md = $this->getMetadata();
			$md->append(0, 'meta-robots', 'none');
			$md->save();
		}
	}

}
