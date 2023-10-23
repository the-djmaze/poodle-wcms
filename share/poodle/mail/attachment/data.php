<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail\Attachment;

class Data extends \Poodle\Mail\Attachment
{
	protected
		$data;

	/**
	 * Adds a string or binary attachment (non-filesystem) to the list.
	 * This method can be used to attach ascii or binary data,
	 * such as a BLOB record from a database.
	 * @param string $string    String attachment data.
	 * @param string $name      Name of the attachment.
	 * @param string $mime_type File extension (MIME) type.
	 */
	function __construct($owner, $string, $name, $mime_type=null)
	{
		$this->name      = $name;
		$this->data      = $string;
		$this->mime_type = $mime_type ? $mime_type : 'application/octet-stream';
		parent::__construct($owner);
	}

	protected function getContent()
	{
		return $this->data;
	}

}
