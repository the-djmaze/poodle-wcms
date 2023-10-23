<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Kernels;

class JavaScript extends PushFiles
{
	protected
		$f_ext = '.js',
		$mime  = 'application/javascript';

	function __construct(array $cfg, $path='')
	{
		$keys = \Poodle::$PATH;
		if (2 != count($keys)
		 || !preg_match('#^([a-z0-9_\-]+(;|$))+$#D', $keys[1])
		) {
			\Poodle\HTTP\Status::set(412);
			exit(\Poodle\HTTP\Status::get(412));
		}

		parent::__construct($cfg);

		$files = $this->getValidFilesList($keys[1]);

		// Make poodle.js the main priority
		if (isset($files['poodle'])) {
			$f = $files['poodle'];
			unset($files['poodle']);
			array_unshift($files, $f);
		}
		// filter out the missing files
		$files = array_filter($files);

		if (empty($this->_readonly_data['expires']) || $this->_readonly_data['design_mode']) {
			$this->_readonly_data['expires'] = 0;
			foreach ($files as $file) {
				$this->mtime = max($this->mtime, @filemtime($file));
			}
		}
		$this->_readonly_data['strict'] = !empty($this->_readonly_data['strict']);

		$etag_files = $files;
		ksort($etag_files);
		$this->ETag  = md5(implode(';', $etag_files))
			. (self::$DEBUG & self::DBG_JAVASCRIPT ? '-1' : '-0')
			. ($this->_readonly_data['design_mode'] ? '-1' : '-0')
			. ($this->_readonly_data['strict'] ? '-strict' : '');
		$this->CFile = 'javascript/'.$this->ETag;

		$this->files = $files;
	}

	protected function findFile($filename)
	{
		$ext  = $this->f_ext;
		$dirs = array(self::$DIR_BASE.'inc/js');
		if ($file = \Poodle::getFile($filename.$ext, $dirs)) {
			return $file;
		}
		$path = explode('_', $filename);
		$lib  = array_shift($path); // library
		$comp = $path ? array_shift($path) : $lib; // component
		if (empty($path[0])) { $path[0] = $comp; }
		$filename = implode('/',$path) . $ext;
		if (($file = \Poodle::getFile("{$lib}/{$comp}/tpl/javascript/{$filename}"))
		 || ($file = \Poodle::getFile("{$lib}/{$comp}/tpl/{$filename}"))
		 || ($file = \Poodle::getFile("{$lib}/javascript/{$comp}".($path[0]!=$comp?'_'.implode('_',$path):'').$ext))
		 || ($file = \Poodle::getFile("tpl/{$lib}/javascript/{$filename}")))
		{
			return $file;
		}
		return false;
	}

	protected function findL10NFile($filename)
	{
		$ext  = '.'.$this->lng.$this->f_ext;
		$dirs = array(self::$DIR_BASE.'inc/js');
		if ($file = \Poodle::getFile($filename.$ext, $dirs)) {
			return $file;
		}
		$path = explode('_', $filename);
		$lib  = array_shift($path); // library
		$comp = $path ? array_shift($path) : $lib; // component
		if (empty($path[0])) { $path[0] = $comp; }
		$filename = implode('/',$path) . $ext;
		$compfile = $comp . ($path[0] != $comp ? '_'.implode('_',$path) : '');
		if (($file = \Poodle::getFile("{$lib}/{$comp}/l10n/{$filename}"))
		 || ($file = \Poodle::getFile("{$lib}/javascript/l10n/{$compfile}{$ext}"))
		 || ($file = \Poodle::getFile("{$lib}/l10n/locales/{$this->lng}/javascript/{$compfile}{$this->f_ext}"))
		 || ($file = \Poodle::getFile("tpl/{$lib}/javascript/l10n/{$filename}")))
		{
			return $file;
		}
		return false;
	}

	public function run()
	{
		$this->send_file();

		# http://blog.stevenlevithan.com/archives/match-quoted-string speed?
//		$str_re = '#(["\'])(?:.*[^\\\\]+)*(?:(?:\\\\{2})*)+\1#xU';
//		$str_re = '#(["\'])(?:\\\\?[^\n])*?\1#s';
		$str_re = '#"[^\n"\\\\]*(?:\\\\.[^\n"\\\\]*)*"|\'[^\n\'\\\\]*(?:\\\\.[^\n\'\\\\]*)*\'|/[^\s/\\\\]+(?:\\\\.[^\n/\\\\]*)*/[gmi]*#';

		# file didn't exist yet, failed or needs updating
		$data = "/* Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved. */\n";
		if ($this->missing) {
			$data .= "/* Not found:\n\t".implode("\n\t",$this->missing)."\n*/\n";
		}
		if (self::$DEBUG & self::DBG_JAVASCRIPT) {
			$data .= "/* Found:\n\t".implode("\n\t",$this->files)."\n*/\n";
		}
		if ($this->_readonly_data['strict']) {
			$data .= "\"use strict\";\n";
		}
		foreach ($this->files as $i => $file) {
			$buffer = file_get_contents($file);
			if ($buffer) {
				if (0 === $i) {
					$buffer = preg_replace('/P.PostMax[\\s=][^;]+;/s', 'P.PostMax='.\Poodle\Input\POST::max_size().';', $buffer);
					$buffer = preg_replace('/P.PostMaxFiles[\\s=][^;]+;/s', 'P.PostMaxFiles='.\Poodle\Input\FILES::max_uploads().';', $buffer);
					$buffer = preg_replace('/P.PostMaxFilesize[\\s=][^;]+;/s', 'P.PostMaxFilesize='.\Poodle\Input\FILES::max_filesize().';', $buffer);
				}

				$buffer = str_replace("\r", '', $buffer);
				// Strip comments but keep IE specific stuff
				$buffer = preg_replace('#(^|\s+)//.*#m', '', $buffer);
				$buffer = preg_replace('#(^|\s+)/\*[^@].*?\*/#s', '', $buffer);
				// Strip console.* when debug is off
				if (!(self::$DEBUG & self::DBG_JAVASCRIPT)) {
					$buffer = preg_replace('#console\.[A-Za-z]+\(((?(?!\);).)*)\);#', '', $buffer);
				}
				if (!$this->_readonly_data['design_mode']) {
					preg_match_all($str_re, $buffer, $strings);
					$strings = $strings[0];
					$buffer = preg_split($str_re, $buffer);
					$buffer = preg_replace('#\n+#', ' ', $buffer);
					$buffer = preg_replace('#\s+#', ' ', $buffer);
					# case|else if|function|in|new|return|typeof|var|const|let
					$buffer = preg_replace('#\s*([&%/\[\]{}\(\)\|\+!\?\-=:;,><\.\*]+)\s*#', '$1', $buffer);
					$c = 1;
					while ($c) {
						$buffer = preg_replace('#var ([^;{}]+);var #', 'var $1,', $buffer, -1, $c);
					}
					$c = 1;
					while ($c) {
						$buffer = preg_replace('#const ([^;{}]+);const #', 'const $1,', $buffer, -1, $c);
					}
					$c = 1;
					while ($c) {
						$buffer = preg_replace('#let ([^;{}]+);let #', 'let $1,', $buffer, -1, $c);
					}

					foreach ($strings as $i => $string) {
						$buffer[$i] .= $string;
					}
					unset($strings);

					$data .= implode('', $buffer);
				} else {
					$buffer = preg_replace('#[ \t]+#s', ' ', $buffer);
					$data .= preg_replace('#(\n)\s+#s', '$1', $buffer);
				}
				$buffer = '';
			}
		}

		// https://github.com/sqmk/pecl-jsmin
		if (!$this->_readonly_data['design_mode'] && function_exists('jsmin')) {
			$data .= jsmin($buffer);
		}

		$this->send_file($data);
	}

}
