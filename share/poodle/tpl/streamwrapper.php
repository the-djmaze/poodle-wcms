<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\TPL;

class StreamWrapper
{

	private
		$data = '',
		$datapos = 0,
		$datalen = 0,
		$options;

	# bool stream_open ( string path, string mode, int options, string opened_path )
	public function stream_open($path, $mode, $options, $opened_path)
	{
		if (!preg_match('#://(cache|data)/(.*)$#D', $path, $match)) {
			return false;
		}
		if ('cache' === $match[1]) {
			$this->data = \Poodle::getKernel()->CACHE->get($match[2]);
		} else {
			$this->data = base64_decode($match[2]);
		}
		$this->datalen = strlen($this->data);
		$this->options = $options;
		return true;
	}
	public function stream_close() { }
	public function stream_eof() { return $this->datapos >= $this->datalen; }
	public function stream_read($bytes)
	{
		if ($this->stream_eof()) { return ''; }
		$r = substr($this->data, $this->datapos, $bytes);
		$this->datapos += strlen($r);
		return $r;
	}
	public function stream_write($data) { return 0; }
	public function stream_stat() { return false; }
	public function url_stat($path, $flags) { return false; }
	private function error($msg)
	{
		if ($this->options & STREAM_REPORT_ERRORS) { trigger_error($msg, E_USER_WARNING); }
		return false;
	}

}
