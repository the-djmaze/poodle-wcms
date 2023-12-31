<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail\Attachment;

class File extends \Poodle\Mail\Attachment
{
	protected
		$filename;

	/**
	 * Adds an attachment from a path on the filesystem.
	 * Throws and exception if the file could not be found or accessed.
	 * @param string $filename  Path to the attachment.
	 * @param string $mime_type File extension (MIME) type.
	 */
	function __construct($owner, $filename, $mime_type=null)
	{
		if (!is_file($filename)) {
			throw new \Exception(self::l10n('file_access').$filename, E_USER_WARNING);
		}
		$this->name     = $filename;
		$this->filename = $filename;
		if (!$mime_type && function_exists('mime_content_type')) {
			$mime_type = mime_content_type($this->filename);
		}
		$this->mime_type = $mime_type ? $mime_type : 'application/octet-stream';
		parent::__construct($owner);
	}

	protected function getContent()
	{
		if (!($file_buffer = file_get_contents($this->filename))) {
			throw new \Exception(self::l10n('file_open').$this->filename, E_USER_ERROR);
		}
		return $file_buffer;
	}

}
