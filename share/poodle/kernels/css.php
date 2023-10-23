<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://www.w3.org/TR/css3-images/#linear-gradients
*/

namespace Poodle\Kernels;

class CSS extends PushFiles
{
	protected
		$f_ext  = '.css',
		$mime   = 'text/css',
		$engine = 'unknown', # moz, khtml, opera, ms, webkit
		$embed,
		$ua_re,

		/**
		 * CSS3
		 * https://developer.mozilla.org/en-US/docs/Web/CSS/* where * is the css property
		 * http://www.w3.org/TR/css3-userint is superseded so there is no 'user-*'
		 */
		$CSS3 = '(animation[a-z\-]*|background-size|box-shadow|box-sizing|column-(count|rule|gap)|columns|text-(fill|overflow|resize|shadow|stroke)|transform|transition(-[a-z\-]+)?|border-radius(-[a-z\-]+)?)';

	function __construct(array $cfg)
	{
		$keys = \Poodle::$PATH;
		if (3 != count($keys)
		 || !preg_match('#^[a-z0-9_\-]+$#',$keys[1])
		 || !is_dir('tpl/'.$keys[1].'/css')
		 || !preg_match('#^([a-z0-9_\-]+(;|$))+$#D', $keys[2])
		) {
			\Poodle\HTTP\Status::set(412);
			exit(\Poodle\HTTP\Status::get(412));
		}

		if (!isset($cfg['max_embed_size'])) {
			$cfg['max_embed_size'] = 5800;
		}
		parent::__construct($cfg);

		$this->embed = !stripos($_SERVER['HTTP_USER_AGENT'], 'iPhone');
		$ETag  = null;
		$ua_re = array('moz'=>'moz','khtml'=>'khtml','opera'=>'o','ms'=>'ms','webkit'=>'webkit');
		if (stripos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false) {
			$this->engine = 'opera';
		} else
		if (stripos($_SERVER['HTTP_USER_AGENT'], 'WebKit/') !== false) {
			$this->engine = 'webkit';
		} else
		if (stripos($_SERVER['HTTP_USER_AGENT'], 'KHTML/') !== false) {
			$this->engine = 'khtml';
		} else
		if (stripos($_SERVER['HTTP_USER_AGENT'], 'Gecko/') !== false) {
			$this->engine = 'moz';
		} else
		if (stripos($_SERVER['HTTP_USER_AGENT'], 'Trident/') !== false) {
			$this->engine = 'ms';
		} else {
			$this->embed = false;
		}
		unset($ua_re[$this->engine]);
		$this->ua_re = $ua_re;
		$this->tpl   = $keys[1];

		$this->dirs = array(
			self::$DIR_BASE."tpl/{$this->tpl}/css",
			self::$DIR_BASE.'tpl/default/css'
		);

		$files = $this->getValidFilesList($keys[2]);
		ksort($files);

		if (isset($files['style'])) {
			$f = $files['style'];
			unset($files['style']);
			array_unshift($files, $f);

			if (isset($files['normalize'])) {
				array_unshift($files, $files['normalize']);
			} else if (isset($files['reset'])) {
				array_unshift($files, $files['reset']);
			}
		}
		unset($files['reset']);
		unset($files['normalize']);

		if (!$this->_readonly_data['expires'] || $this->_readonly_data['design_mode']) {
			$this->_readonly_data['expires'] = 0;
			foreach ($files as $file) {
				$this->mtime = max($this->mtime, @filemtime($file));
			}
		}

		if (!$ETag) { $ETag = $this->engine; }
		$this->CFile = "tpl/{$this->tpl}/css/{$ETag}-" . md5(implode(';', $files)) . ($this->_readonly_data['design_mode']?'-1':'-0');
		$this->ETag  = strtr($this->CFile,'/','-');
		$this->files = $files;
	}

	protected function findFile($filename)
	{
		$ext = $this->f_ext;
		if ($file = \Poodle::getFile($filename.$ext, $this->dirs)) { return $file; }
		else if ($file = \Poodle::getFile(strtr($filename,'_','/').$ext, $this->dirs)) { return $file; }
		else {
			$path = explode('_', $filename);
			$lib  = array_shift($path); // library
			$comp = $path ? array_shift($path) : $lib; // component
			if (empty($path[0])) { $path[0] = $comp; }
			if ($file = \Poodle::getFile("{$lib}/{$comp}/tpl/css/".implode('/',$path).$ext)) { return $file; }
			if ($file = \Poodle::getFile("{$lib}/{$comp}/tpl/".implode('/',$path).$ext)) { return $file; }
			if ($file = \Poodle::getFile("{$lib}/css/{$comp}".($path[0]!=$comp?'_'.implode('_',$path):'').$ext)) { return $file; }
		}
		if ('normalize' === $filename || 'reset' === $filename) {
			return $this->findFile('poodle_'.$filename);
		}
		return false;
	}

	public function run()
	{
		$this->send_file();

		// file didn't exist yet, failed or needs updating
		$TPL_URI = self::$URI_BASE."/tpl/{$this->tpl}/";

		$str_re    = '#"[^\n"\\\\]*(?:\\\\.[^\n"\\\\]*)*"|\'[^\n\'\\\\]*(?:\\\\.[^\n\'\\\\]*)*\'*#';
		$ua_re     = '#([a-z-]+:\s*)?-('.implode('|', $this->ua_re).')-[^;{}]+;#';
		$ua_engine = 'ua_'.$this->engine;
		$image_cb  = array($this,'embed_image');

		$data = "/* Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved. */\n";
		if ($this->missing) { $data .= "/* Not found:\n\t".implode("\n\t",$this->missing)."\n*/\n"; }
//		$data .= "/* Found:\n\t".implode("\n\t",$this->files)."\n*/\n";
		foreach ($this->files as $file) {
			$buffer = file_get_contents($file);

			/**
			 * TODO: add variables support?
			 * https://drafts.csswg.org/css-variables/
			 */

			// Minify data
			$buffer = preg_replace('#/\*.*?\*/#s','',$buffer);
			$buffer = preg_replace('#@import[^;]*;#','',$buffer);
			preg_match_all($str_re, $buffer, $strings);
			$strings = $strings[0];
			$buffer = preg_split($str_re, $buffer);
/*			$buffer = preg_replace('#(?:([^a-z0-9*])\s+|\s+([^a-z0-9*]))#i', '$1$2', $buffer);
			$buffer = preg_replace('#([^a-z0-9*])\s+#i', '$1', $buffer);
			$buffer = preg_replace('#\s+([^a-z0-9*])#i', '$1', $buffer);
*/			$buffer = preg_replace('#\s+#', ' ', $buffer);
			$buffer = preg_replace('#([:\s]+0)(em|ex|ch|rem|vw|vh|vmin|vmax|cm|mm|in|px|pt|pc)#i', '$1', $buffer);
			$buffer = preg_replace('#\s*([{},;:])\s*#', '$1', $buffer);
			$buffer = preg_replace('#([^;}])}#','$1;}',$buffer);

			// Merge strings with minified data
			foreach ($strings as $i => $string) {
				$buffer[$i] .= $string;
			}
			unset($strings);

			$buffer = preg_replace($ua_re, '', implode('', $buffer));

			$this->$ua_engine($buffer);

			$buffer = preg_replace('#;+;#',';',$buffer);
			// Semicolon after last declaration not needed
			$buffer = preg_replace('#;+}#','}',$buffer);

			// Remove selectors with empty declarations
			$buffer = preg_replace('#[^{}]+{}#','',$buffer);

			$buffer = preg_replace('#url\((["\']?)\\.\\./([a-z])#', 'url($1'.$TPL_URI.'$2', $buffer);
			$buffer = preg_replace('#url\((["\']?)/(inc|media)/#', 'url($1'.self::$URI_BASE.'/$2/', $buffer);
			$buffer = preg_replace_callback('#url\\((["\']?)'.self::$URI_BASE.'(/[a-z0-9/_-]+\\.(png|jpe?g|gif|cur|svg))\\1\\)#', $image_cb, $buffer);
			$data .= trim($buffer);
			$buffer = '';
		}

		$debug = '';
		$images = $this->embed_image();
		foreach ($images as $img => $c) {
			if ($c>1) $debug .= "{$img}:{$c}\n";
		}
		if ($debug) {
			$data .= "\n/*\n{$debug}*/";
		}
		if ($this->_readonly_data['design_mode']) {
			$data = preg_replace('#([}]+)#', "\$1\n", $data);
		}

		$this->send_file($data);
	}

	public function embed_image($img=null)
	{
		static $count = array();
		if (null === $img) { return $count; }
		$ext = $img[3];
		$img = $img[2];
		if (!isset($count[$img])) { $count[$img] = 0; }
		++$count[$img];

		$file = self::$DIR_BASE.$img;
		if (!is_file($file)) {
			$img = preg_replace('#/tpl/[^/]+/#', '/tpl/default/', $img);
			$file = self::$DIR_BASE.$img;
			if (!is_file($file)) { return "none/*missing {$img}*/"; }
		}
		$img = self::$URI_BASE.$img;
		if ($this->embed && filesize($file) < $this->_readonly_data['max_embed_size']) {
			if ('jpg' === $ext) { $ext = 'jpeg'; }
			if ('cur' === $ext) { $ext = 'x-cursor'; }
			if ('svg' === $ext) { $ext = 'svg+xml'; }
			$img = 'data:image/'.$ext.';base64,'.base64_encode(file_get_contents($file));
		}
		return "url({$img})";
	}

	/**
	 * Create two versions of attributes,
	 * like: border-radius & -moz-border-radius
	 */
	protected function fix_css3($attrs, $prefix, &$buffer)
	{
		# Must be seperate in this order or it fails
		$buffer = preg_replace('#;('.$attrs.'):([^;}]+)#', ';-'.$prefix.'-$1:$2;$1:$2', $buffer);
		$buffer = preg_replace('#{('.$attrs.'):([^;}]+)#', '{-'.$prefix.'-$1:$2;$1:$2', $buffer);
//		$buffer = preg_replace('#@keyframes([^{}]+{.+})#', '@-'.$prefix.'-keyframes$1', $buffer);
	}

	protected function ua_edge(&$buffer)
	{
		# CSS3
		# Pseudo class :placeholder for input and textarea doesn't exist yet
		$buffer = str_replace(':placeholder', ':-ms-input-placeholder', $buffer);
		// :fullscreen => :-moz-full-screen
	}

	protected function ua_khtml(&$buffer)
	{
		self::fix_css3('background-size|border-radius|border-[a-z]+-colors|box-shadow|tab-size|transform', 'khtml', $buffer);
	}

	# Mozilla Gecko
	protected function ua_moz(&$buffer)
	{
		# CSS3
//		self::fix_css3('background-size|border-[a-z]+-colors|box-sizing|column-(?:[a-z-]+)|columns|tab-size|transform|transition|user-select|user-modify|pointer-events', 'moz', $buffer);
//		$buffer = preg_replace('#([{;])border-(top|bottom)-(left|right)-radius:#', '$1-moz-border-radius-$2$3:', $buffer);
		# Pseudo class :placeholder for input and textarea doesn't exist yet
//		$buffer = str_replace(':placeholder', ':-moz-placeholder', $buffer);
		// :fullscreen => :-moz-full-screen
		# Firefox < 16 -moz-linear-gradient
//		$buffer = preg_replace('/(background-image:linear-gradient\\(([^;}]+))/', 'background-image:-moz-linear-gradient($2;$1', $buffer);
	}

	# presto
	protected function ua_opera(&$buffer)
	{
		# CSS3, 10.5=border-radius
		self::fix_css3('background-size|border-radius|box-shadow|tab-size|transform', 'o', $buffer);
/*		Bug in Opera 10.60 makes them hidden
		if (preg_match_all('#border-radius:([^\s;]+)\s+([^\s;]+)\s+([^\s;]+)\s+([^\s;]+)\s*;#', $buffer, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $m) $buffer = str_replace($m[0], "border-top-left-radius:{$m[1]};border-top-right-radius:{$m[2]};border-bottom-right-radius:{$m[3]};border-bottom-left-radius:{$m[4]}", $buffer);
		}*/
//		$buffer = preg_replace('#border-radius[^;}]+#', '', $buffer);
	}

	protected function ua_safari(&$buffer)
	{
		// :fullscreen => :full-screen
	}

	protected function ua_webkit(&$buffer)
	{
		# CSS3, WebKit 534 (Safari 5.1) supports border-radius & box-shadow
//		self::fix_css3('background-size|border-radius|border-[a-z]+-colors|box-shadow|column(?:[a-z-]+)|mask(?:-[a-z]+)?|tab-size|transform(?:-[a-z-]+)?|transition|user-select|user-modify|appearance|pointer-events', 'webkit', $buffer);
		# Pseudo class :placeholder for input and textarea doesn't exist yet
//		$buffer = str_replace(':placeholder', ':-webkit-input-placeholder', $buffer);
		// :fullscreen => :-webkit-full-screen
		# WebKit < 537.27 -webkit-linear-gradient
//		$buffer = preg_replace('/(background-image:linear-gradient\\(([^;}]+))/', 'background-image:-webkit-linear-gradient($2;$1', $buffer);
	}

	protected function ua_unknown(&$buffer)
	{
	}

	# Internet Explorer Trident
	protected function ua_ms(&$buffer)
	{
		# CSS3
		self::fix_css3('box-sizing|touch-action|user-select|transition|transform(?:-[a-z-]+)?', 'ms', $buffer);
	}

}
