<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Kernels;

abstract class PushFiles extends \Poodle
{
	protected
		$mtime = 0,
		$f_ext = null, // with dot: '.css', '.js', etc.
		$mime  = 'application/octet-stream',
		$lng   = 'en';

	function __construct(array $cfg)
	{
		$this->lng = substr(\Poodle::$UA_LANGUAGES,0,2);

		if (!isset($cfg['expires'])) {
			$cfg['expires'] = 8035200;
		}
		parent::__construct($cfg);

		$this->CACHE = \Poodle\Cache::factory($this->_readonly_data['cache_uri']);

		\Poodle\HTTP\Status::set(200);
	}

	protected static function assoc_array_push_before(array &$array, $ref_key, $key)
	{
		$new = array();
		foreach ($array as $k => $v) {
			if ($ref_key===$k) {
				$new[$key] = isset($array[$key])?$array[$key]:null;
			}
			$new[$k] = $v;
		}
		$array = $new;
	}

	protected function getValidFilesList($keys)
	{
		$keys  = is_array($keys) ? $keys : explode(';', $keys);
		$files = $this->missing = array();
		$c = count($keys);
		for ($i=0; $i<$c; ++$i) {
			$name = $keys[$i];
			if ($file = $this->findFile($name)) {
				$files[$name] = $file;
				// Process @import rules
				if (preg_match_all('#@import[^"]+"([^"]+)"#', file_get_contents($file,false,null,0,4096), $m)) {
					foreach ($m[1] as $filename) {
						$filename = strtr(str_replace($this->f_ext,'',$filename), '/', '_');
						if (!in_array($filename, $keys)) {
							$keys[] = $filename;
							++$c;
						}
						// imported file should be included before
						// this file as it relies on it
						static::assoc_array_push_before($files,$name,$filename);
					}
				}
				// Add language file
				if ($file = $this->findL10NFile($name)) {
					$files[$name.'~'.$this->lng] = $file;
				}
			} else {
				$this->missing[] = $name;
			}
		}
		return $files;
	}

	abstract protected function findFile($filename);

	protected function findL10NFile($filename) { return false; }

	protected function send_file(&$data=null)
	{
		clearstatcache();
		$file = $this->CFile.$this->f_ext;
		$mtime = 0;
		if ($data) {
			$data  = trim($data);
			$mtime = $this->mtime;
			error_reporting(0);
			if ($this->CACHE->set($file, $data)) {
				if (function_exists('gzcompress')) {
					$this->CACHE->set($file.'.gz', gzcompress($data,9));
				}
				if (function_exists('brotli_compress')) {
					$this->CACHE->set($file.'.br', brotli_compress($data, 11, BROTLI_TEXT));
				}
				$mtime = $this->CACHE->mtime($file);
			}
		} else {
			if (!$this->CACHE->exists($file)) { return; }
			$mtime = $this->CACHE->mtime($file);
		}
		if ($mtime < $this->mtime) { return; }

		\Poodle\HTTP\Headers::setETagLastModified($this->ETag.'-'.$mtime, $mtime);
		header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time()+$this->_readonly_data['expires']));
		if ($this->_readonly_data['design_mode']) {
			header('Cache-Control: no-cache');
		} else {
			header('Cache-Control: public');
		}
		header('Content-Type: '.$this->mime.'; charset=utf-8');
		if ($data) {
			if (\Poodle::$COMPRESS_OUTPUT && function_exists('ob_gzhandler')) { ob_start('ob_gzhandler'); }
			else { header('Content-Length: '.strlen($data)); ob_start(); }
			echo $data;
			ob_end_flush();
			exit;
		}
		$ext = (\Poodle::$COMPRESS_OUTPUT && !empty($_SERVER['HTTP_ACCEPT_ENCODING'])
			&& stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false && is_file($file.'.gz')) ? '.gz' : '';
		if ($this->CACHE->exists($file)) {
			$data = $this->CACHE->get($file);
			$ob_handler = '';
			if ($ext) {
				$file .= $ext;
				header('Content-Encoding: gzip');
				header('Content-Length: '.strlen($data));
			} else if (\Poodle::$COMPRESS_OUTPUT && function_exists('ob_gzhandler')) {
				$ob_handler = 'ob_gzhandler';
			} else {
				header('Content-Length: '.strlen($data));
			}
			ob_start($ob_handler);
			echo $data;
			ob_end_flush();
			exit;
		}
	}

}
