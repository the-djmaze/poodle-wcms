<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\FTP\Interfaces;

interface Adapter
{

	public function connect($host, $username, $passphrase);

	public function disconnect();

	public function chdir($directory);

	public function chmod($path, $mode);

	public function delete($path);

	// NOTE: Not all servers support this feature.
	public function fileSize($remote_file);

	public function fget($handle, $remote_file, $resumepos = 0);

	public function fput($remote_file, $handle, $startpos = 0);

	public function get($local_file, $remote_file, $resumepos = 0);

	public function put($remote_file, $local_file, $startpos = 0);

	public function mkdir($directory);

	public function rename($oldname, $newname);

	public function rmdir($directory);

	public function getSystemType();

	public function getCWD();

	public function raw($command);

	public function rawlist($directory = null, $recursive = false);

	public function scanDir($directory = null);

	public function setPassiveMode($pasv);

	public function exists($name);

	public function isDir($directory);

}
