<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail;

abstract class Attachment
{
	public
		$name,
		$disposition = 'attachment', // Content-Disposition
		$encoding    = 'base64',     // Content-Transfer-Encoding
		$mime_type,                  // Content-Type
		$id;                         // Content-ID (used for inline)
	protected
		$owner;

	function __construct(Send $owner)
	{
		$this->owner = $owner;
	}

	# Return attachment as string. Returns an empty string on failure.
	function __toString()
	{
		$filename = basename($this->name);
		$body = "Content-Transfer-Encoding: {$this->encoding}\r\n"
		      . "Content-Type: {$this->mime_type}; name=\"{$filename}\"\r\n"
		      . "Content-Disposition: {$this->disposition}; filename=\"{$filename}\"\r\n";
		if ('inline' === $this->disposition) { $body .= "Content-ID: <{$this->id}>\r\n"; }
		$body .= "\r\n";
		$body .= $this->owner->encodeBody($this->getContent(), $this->encoding);
		return $body . "\r\n\r\n";
	}

	abstract protected function getContent();

}
