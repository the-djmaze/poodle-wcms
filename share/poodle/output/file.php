<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://tools.ietf.org/html/rfc7233
*/

namespace Poodle\Output;

class File
{

	public static function send($filename, $name='', $resumable = true)
	{
		$name = basename($name ?: $filename);
		if (!($fp = fopen($filename, 'rb'))) {
			throw new \Exception("{$filename} could not be opened", E_USER_WARNING);
		}

		$file_size = filesize($filename);
		$offset = 0;
		$length = $file_size - 1;

		if ($resumable) {
			// check if Range header is sent by client
			if (isset($_SERVER['HTTP_RANGE']) && 'GET' === $_SERVER['REQUEST_METHOD']) {
				if (preg_match('#bytes=([0-9]*)-([0-9]*)#', $_SERVER['HTTP_RANGE'], $range)) {
					if (strlen($range[1])) {
						$offset = (int)$range[1];
						if (strlen($range[2])) {
							$length = min((int)$range[2], $length);
						}
					} else if (strlen($range[2])) {
						// The final N bytes
						$offset = max($file_size - $range[2], 0);
					}
					if ($length < $offset) {
						\Poodle\HTTP\Status::set(416);
						fclose($fp);
						return false;
					}
				} else {
					\Poodle\HTTP\Status::set(416);
					fclose($fp);
					return false;
				}
			}
			header('Accept-Ranges: bytes');
		}

		\Poodle::startStream();
		header('Content-Length: '.($length - $offset + 1));
		\Poodle\HTTP\Headers::setContentDisposition('attachment', array('filename'=>$name));
		\Poodle\HTTP\Headers::setContentType(\Poodle\Filesystem\File::getMime($filename), array('name'=>$name));

		if ($offset > 0 || $length < $file_size - 1) {
			\Poodle\HTTP\Status::set(206);
			header("Content-Range: bytes {$offset}-{$length}/{$file_size}");
			fseek($fp, $offset);
		}

		// send partial data?
		if ($length < $file_size - 1) {
			$chunk = 8192;
			while (!feof($fp) && ($p = ftell($fp)) <= $length) {
				if ($p + $chunk > $length) {
					$chunk = $length - $p + 1;
				}
				set_time_limit(10);
				echo fread($fp, $chunk);
				flush();
			}
		} else {
			set_time_limit(0);
			if (false === fpassthru($fp)) {
				trigger_error('fpassthru failed', E_USER_WARNING);
				fclose($fp);
				set_time_limit(\Poodle\PHP\ini::get('max_execution_time'));
				return false;
			}
			set_time_limit(\Poodle\PHP\ini::get('max_execution_time'));
		}
		return fclose($fp);
	}

}
