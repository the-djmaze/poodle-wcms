<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2016. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Filesystem;

class Backup
{
	const
		TYPE_FILE = '0',
		TYPE_LINK = '2',
		TYPE_DIR  = '5';

	protected
		$gzip = array('w' => null, 'h' => null, 'l' => 0),
		$stream = null;

	function __construct($name = 'backup', $compress = true, $target = 'php://output')
	{
		$this->stream = fopen($target, 'wb');
		if (!$this->stream) {
			throw new \Exception('Failed to open stream');
		}
		if ('php://output' === $target) {
			\Poodle::startStream();
			header('Content-Transfer-Encoding: binary');
			if ($compress) {
				\Poodle\HTTP\Headers::setContentDisposition('attachment', array('filename' => "{$name}.tgz"));
				\Poodle\HTTP\Headers::setContentType('application/x-gzip', array('name' => "{$name}.tgz"));
			} else {
				\Poodle\HTTP\Headers::setContentDisposition('attachment', array('filename' => "{$name}.tar"));
				\Poodle\HTTP\Headers::setContentType('application/x-ustar', array('name' => "{$name}.tar"));
			}
		}
		if ($compress) {
			// Write gzip header, see http://www.zlib.org/rfc-gzip.html#member-format
			if (!fwrite($this->stream, "\x1F\x8B\x08\x00".pack('V', time())."\0\x03", 10)) {
				throw new \Exception('Failed to write to stream');
			}
			// Start compression
			$this->gzip['w'] = stream_filter_append($this->stream, 'zlib.deflate', STREAM_FILTER_WRITE, array(
				'level' => 9,
//				'window' => 32768,
				'memory' => 9,
			));
			// Start CRC32 hashing
			$this->gzip['h'] = hash_init('crc32b');
		}
	}

	function __destruct()
	{
		$this->close();
	}

	public function close()
	{
		if ($this->stream) {
			// Write tar footer
			$this->write(pack('a1024', ''));
			// Stop compression
			if ($this->gzip['w']) {
				stream_filter_remove($this->gzip['w']);
				// hash_final is a string, not an integer
				$crc = hash_final($this->gzip['h'], 1);
				// write the little endian CRC32 and uncompressed file size
				fwrite($this->stream, $crc[3].$crc[2].$crc[1].$crc[0].pack('V', $this->gzip['l']), 8);
			}
			fclose($this->stream);
			$this->stream = null;
			return true;
		}
	}

	public function addFile($name, \SplFileInfo $fileinfo = null)
	{
		if (!$fileinfo) {
			$fileinfo = new \SplFileInfo($name);
		}
		if ($fileinfo->isLink()) {
			$stat = lstat($fileinfo);
			$target = $fileinfo->getLinkTarget();
			if (dirname($fileinfo->getPathname()) === dirname($fileinfo->getRealPath())) {
				$target = basename($target);
			}
			$this->writeEntryHeader(
				$name,
				static::TYPE_LINK,
				0,
				$stat['uid'],
				$stat['gid'],
				$stat['mode'],
				$stat['mtime'],
				$target
			);
		} else {
			if ($fileinfo->isDir()) {
				$name .= '/';
				$type = static::TYPE_DIR;
				$file = null;
			} else {
				$type = static::TYPE_FILE;
				$file = $fileinfo->openFile('rb');
			}
			$this->writeEntryHeader(
				$name,
				$type,
				$file ? $file->getSize() : 0,
				$fileinfo->getOwner(),
				$fileinfo->getGroup(),
				$fileinfo->getPerms(),
				$fileinfo->getMTime()
			);
			if ($file) {
				while (!$file->eof()) {
					$data = $file->fread(4096);
					if (false === $data || '' === $data) {
						break;
					}
					$this->write($data);
				}
				if ($l = $file->ftell() % 512) {
					$l = 512 - $l;
					$this->write(pack("a{$l}", ''));
				}
				unset($file);
			}
		}
	}

	public function addRecursive($dir, $ignore = '#/(\\.hg(/|$)|\\.hgignore)#')
	{
		if (!$this->stream) {
			throw new \Exception('Invalid stream');
		}
		clearstatcache();
		$dir = rtrim($dir,'\\/') . '/';
		$dirl = strlen($dir);
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS /*| \FilesystemIterator::FOLLOW_SYMLINKS*/),
			\RecursiveIteratorIterator::SELF_FIRST,
			\RecursiveIteratorIterator::CATCH_GET_CHILD);
		$ignore_paths = array();
		foreach ($iterator as $name => $fileinfo) {
			if ($ignore_paths && preg_match('#'.implode('|',$ignore_paths).'#', $name)) {
				continue;
			}
			if (!$ignore || !preg_match($ignore, $name)) {
				$name = substr($name, $dirl);
				$this->addFile($name, $fileinfo);
			}
			// like: tar --exclude-caches -czf file.tgz *
			if (strpos($name, 'CACHEDIR.TAG')) {
				$ignore_paths[] = preg_quote(dirname($name) . '/','#');
			}
		}
	}

	protected function write($string)
	{
		$length = strlen($string);
		$written = 0;
		while ($written < $length) {
			$bytes = fwrite($this->stream, $written ? substr($string, $written) : $string);
			if (!$bytes) {
				return $written ?: false;
			}
			$written += $bytes;
		}

		if ($this->gzip['h']) {
			$this->gzip['l'] += $length;
			hash_update($this->gzip['h'], $string);
		}

		return $written;
	}

	protected function writeEntryHeader($name, $type, $size, $uid = 0, $gid = 0, $perm = 0, $mtime = 0, $link = '', $prefix  = '')
	{
		// handle long filename length
		$paxdata = $paxname = '';
		if (100 < strlen($link)) {
			$length = strlen($link) + 11;
			$length += strlen($length);
			$paxdata = "{$length} linkpath={$link}\n";
			$link = '././@LongSymLink';
		}
		$l = strlen($name);
		if (($paxdata?90:100) < $l) {
			// split into name and prefix
			$p = strpos($name, '/', max(0, $l - 90));
			if ($p && $p < $l-1) {
				$file = substr($name, $p+1);
				$prefix = substr($name, 0, $p);
				$paxname = preg_replace('#(^|/)([^/]+)$#', '$1PaxHeader/$2', $file);
			} else {
				$file = basename($name);
				if (static::TYPE_DIR === $type) {
					$file .= '/';
				}
				$prefix = dirname($name);
				$paxname = 'PaxHeader/' . $file;
			}
			if (100 < strlen($file) || 155 < strlen($prefix)) {
				// POSIX.1-2001/pax
				$length = $l + 7;
				$length += strlen($length);
				$paxdata = "{$length} path={$name}\n{$paxdata}";
				if (static::TYPE_DIR === $type) {
					$name = substr($file, 0, 98) . '/';
				} else {
					$name = substr($file, 0, 99);
				}
			} else {
				// POSIX ustar
				$name = $file;
			}
			$paxname = substr($paxname, 0, 98);
		}
		if ($paxdata) {
			/* Add?
			$data .= "30 mtime=1461056595.149922432\n"
			$data .= "30 ctime=1461056595.149922432\n"
			*/
			$this->writeUStarEntryHeader(
				$paxname ?: preg_replace('#(^|/)([^/]+)$#', '$1PaxHeader/$2', $name),
				'x',
				strlen($paxdata),
				$uid,
				$gid,
				$perm,
				$mtime,
				'',
				$prefix
			);
			$l = 512 * ceil(strlen($paxdata) / 512);
			$this->write(pack("a{$l}", $paxdata));
		}

		$this->writeUStarEntryHeader($name, $type, $size, $uid, $gid, $perm, $mtime, $link, $prefix);
	}

	// Writes Pre-POSIX.1-1988 (i.e. v7) and POSIX UStar headers
	protected function writeUStarEntryHeader($name, $type, $size, $uid = 0, $gid = 0, $perm = 0, $mtime = 0, $link = '', $prefix  = '')
	{
		$data = pack('a100a8a8a8a12A12',
			$name,
			// values in octal
			sprintf("%06u ", substr(decoct($perm),-3)),
			sprintf("%06u ", decoct($uid)),
			sprintf("%06u ", decoct($gid)),
			sprintf("%011u ", decoct($size)),
			sprintf("%011u", decoct($mtime)));
		$this->write($data);
		$checksum = 0;
		$i = 148;
		while ($i--) {
			$checksum += ord($data[$i]);
		}

		$data = pack('a1a100a6a2a32a32a8a8a155a12', $type, $link, 'ustar', '00', '', '', '000000 ', '000000 ', $prefix, '');
		$checksum += 256;
		$i = 356;
		while ($i--) {
			$checksum += ord($data[$i]);
		}

		$this->write(pack('a8', sprintf('%06u ', decoct($checksum))) . $data);
	}

}
