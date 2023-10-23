<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://github.com/wkhtmltopdf/wkhtmltopdf/releases/tag/0.12.5

	yum install -y xorg-x11-fonts-75dpi xorg-x11-fonts-Type1 ttmkfdir
	wget https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.4/wkhtmltox-0.12.4_linux-generic-amd64.tar.xz
	tar -xvf wkhtmltox-0.12.4_linux-generic-amd64.tar.xz
	cp wkhtmltox/bin/wkhtmltopdf /usr/bin/
*/

namespace Poodle;

class wkhtmltopdf implements \ArrayAccess
{
	public
		$tmp = '/tmp',
		$xvfb = false; // Set to true when error: cannot connect to X server

	public static
		$xvfb_binary = 'xvfb-run',
		// Path to the wkhtmltopdf executable
		$binary = 'wkhtmltopdf';

	protected
		$options = array(
			// Global Options
			'no-collate'           => false,      # Do not collate when printing multiple copies
			'copies'               => 1,          # Number of copies to print into the pdf file
			'grayscale'            => false,      # PDF will be generated in grayscale
			'lowquality'           => false,      # Generates lower quality pdf/ps. Useful to shrink the result document space
			'orientation'          => 'portrait', # Set orientation to Landscape or Portrait
			'page-size'            => 'A4',       # or letter, legal, etc.
			'title'                => null,       # The title of the generated pdf file (The title of the first document is used if not specified)
			// Page Options
			'encoding'             => 'UTF-8',    # Set the default text encoding, for input
			'no-background'        => false,      # Do not print background
			'no-images'            => false,      # Do not load or print images
			'disable-javascript'   => true,       # Do not allow web pages to run javascript
			'debug-javascript'     => false,      # Show javascript debugging output
			'enable-plugins'       => false,      # Enable installed plugins (plugins will likely not work)
			'no-stop-slow-scripts' => false,      # Do not Stop slow running javascripts
			'page-offset'          => 0,          # Set the starting page number
			'javascript-delay'     => 0,          # Wait some milliseconds for javascript finish
			'disable-local-file-access' => false, # Do not allowed conversion of a local file to read in other local files, unless explicitly allowed with --allow
			'disable-smart-shrinking' => false,
			'print-media-type'     => true,
			'enable-forms'         => false,      # Turn HTML form fields into pdf form fields, mostly not working
			'margin-bottom'        => 10,
			'margin-left'          => 10,
			'margin-right'         => 10,
			'margin-top'           => 10,
			// HTTP Authentication
			'username' => '',
			'password' => '',
			// Footer Options
/*
			* [page]       Replaced by the number of the pages currently being printed
			* [topage]     Replaced by the number of the last page to be printed
			* [section]    Replaced by the name of the current section
			* [subsection] Replaced by the name of the current subsection
*/
			'footer-center'        => null,  # <text> 	Centered footer text
			'footer-font-name'     => null,  # <name> 	Set footer font name (default Arial)
			'footer-font-size'     => 0,     # <size> 	Set footer font size (default 11)
			'footer-html'          => null,  # <url> Adds a html footer
			'footer-left'          => null,  # <text> 	Left aligned footer text
			'footer-line'          => false, # Display line above the footer
			'footer-right'         => null,  # <text> 	Right aligned footer text
			'footer-spacing'       => 0,     # Spacing between footer and content in mm (default 0)
			// Header Options
			'header-html'          => null,  # <url> Adds a html header
			'header-spacing'       => 0,     # Spacing between header and content in mm (default 0)
/*
			--header-center*	<text> 	Centered header text
			--header-font-name*	<name> 	Set header font name (default Arial)
			--header-font-size*	<size> 	Set header font size (default 11)
			--header-left*	<text> 	Left aligned header text
			--header-line*		Display line below the header
			--header-right*	<text> 	Right aligned header text
			--enable-internal-links
*/
		);

	function __construct()
	{
		$this->tmp = sys_get_temp_dir() . '/pdf-' . $_SERVER['HTTP_HOST'] . '-' . microtime(1);
//		self::$binary = `which wkhtmltopdf`;
//		self::$xvfb_binary = `which xvfb-run`;
	}

	function __get($k)
	{
		return $this->offsetGet(strtr($k, '_', '-'));
	}

	function __set($k, $v)
	{
		$this->offsetSet(strtr($k, '_', '-'), $v);
	}

	public static function getVersion()
	{
		return exec(self::$binary . ' --version');
	}

	public function renderHTML($html)
	{
		return $this->render($html);
	}

	public function renderURI($uri)
	{
		return $this->render($uri, true);
	}

	public function setMargins($top, $right, $bottom, $left)
	{
		$this->offsetSet('margin-top',    $top);
		$this->offsetSet('margin-right',  $right);
		$this->offsetSet('margin-bottom', $bottom);
		$this->offsetSet('margin-left',   $left);
	}

	protected function render($html, $uri = false)
	{
		$cmd = self::$binary;
		# Add parameters
		foreach ($this->options as $k => $v) {
			if (empty($v) && false === strpos($k, 'margin-')) {
				continue;
			}
			if (true === $v) {
				$cmd .= ' --' . $k;
			} else if (is_numeric($v)) {
				$cmd .= ' --' . $k . ' ' . $v;
			} else {
				$cmd .= ' --' . $k . ' ' . escapeshellarg($v);
			}
		}
		# Use - for stdin or stdout
		$stdin = $uri ? escapeshellarg($html) : '-';
		$cmd .= " {$stdin} -";

		$descriptorspec = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'), // stdout
			2 => array('pipe', 'a')  // stderr
		);
		$pipes = array();
		$env   = array();

		$log_file = $this->tmp . '.log';
		if ($log_file) {
			$descriptorspec[2] = array('file', $log_file, 'a');
		}

		# If configured to do so, launch a temporary X server with a random display number.
		$xvfb = null;
		if ($this->xvfb) {
			$xdisplay = rand(99, 500);
/*
//			$xcmd = self::$xvfb_binary . ' -screen 0 1024x768x24 -dpi ' . $this->options['dpi'] .
			$xcmd = self::$xvfb_binary . ' -screen 0 1024x768x24'
				      . ' -terminate -nolisten tcp ' . $xdisplay
//				      . ($this->dir_Xvfb_fonts ? ' -fp ' . $this->dir_Xvfb_fonts : '')
				      . ' -tst'
				      . ' 2> ' . $this->tmp . '.Xvfb-err';
*/
			$xcmd = self::$xvfb_binary . ' --server-num='.$xdisplay.' --error-file='.$this->tmp . '.xvfb-err --server-args="-screen 0, 1024x768x24"';
			$xvfb = popen($xcmd, 'r');
			if ($xvfb) {
				$env['DISPLAY'] = ':'.$xdisplay;
			} else {
				throw new \Exception('Failed to open '.self::$xvfb_binary);
			}
		}

		$pdf = null;
		$process = proc_open($cmd, $descriptorspec, $pipes, null, $env);
		if (is_resource($process)) {
			if (!$uri) {
				if (!$this->options['enable-forms']) {
					$html = preg_replace_callback('#<select(.*?)</select#si', function($m){
						$m = preg_replace('#<option[^>]*>#', '', $m[1], 1);
						$m = preg_replace('#<option[^>]*>#', ' / ', $m);
						$m = preg_replace('#</option[^>]*>#', '', $m);
						return '<span'.$m.'</span';
					}, $html);
				}
				fwrite($pipes[0], $html);
				fclose($pipes[0]);
			}

			$pdf = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			if (!$log_file) {
				$error = stream_get_contents($pipes[2]);
				fclose($pipes[2]);
			}

			$code = proc_close($process);

			if ($log_file && is_file($log_file)) {
				$error = file_get_contents($log_file);
				unlink($log_file);
			}

			if ($code) {
				if ($xvfb) {
					pclose($xvfb);
					$error .= file_get_contents($this->_tmp . '.xvfb-err');
				}
				throw new \Exception($error, $code);
			}
		}

		if ($xvfb) {
			pclose($xvfb);
		}

		return $pdf;
	}

	protected function libConvert($html)
	{
		$obj = new \wkhtmltox\PDF\Converter(array(
			'colorMode'      => $this->options['grayscale'] ? 'Greyscale' : 'Color',
			'size.pageSize'  => $this->options['page-size'],
			'documentTitle'  => $this->options['title'],
			'orientation'    => ucfirst($this->options['orientation']),
			'margin.bottom'  => $this->options['margin-bottom'],
			'margin.left'    => $this->options['margin-left'],
			'margin.right'   => $this->options['margin-right'],
			'margin.top'     => $this->options['margin-top'],
			'useCompression' => true,
/*
			size.width 	with of the output document 	210mm
			size.height 	height of the output document 	297mm
			resolution 	resoluition of the output document 	most likely has no effect
			dpi 	dpi to use while printing 	80
			pageOffset 	integer added to page numbers generating headers, footers, and toc
			copies
			collate 	collate copies 	boolean
			outline 	generate PDF outline 	boolean
			outlineDepth 	maximum depth of outline
			dumpOutline 	path of file to dump outline XML
			out 	path of output file, if "-" stdout is used
			useCompression 	enable or disable lossless compression 	boolean
			imageDPI 	maximum DPI for images in the output document
			imageQuality 	the jpeg compression factor for images in the output document 	94
			load.cookieJar 	path of file used to load and store cookies 	/tmp/cookies.txt
*/
		));
		$obj->add(new \wkhtmltox\PDF\Object($html, array(
			'produceForms'   => $this->options['forms'],
			'header.htmlUrl' => $this->options['header-html'],
			'footer.htmlUrl' => $this->options['footer-html'],
			'load.username'  => $this->options['username'],
			'load.password'  => $this->options['password'],
			'web.background' => !$this->options['no-background'],
			'web.loadImages' => !$this->options['no-images'],
			'web.enableJavascript' => !$this->options['disable-javascript'],
			'web.printMediaType'   => $this->options['print-media-type'],
			'web.defaultEncoding'  => $this->options['encoding'],
			'web.enablePlugins'    => $this->options['enable-plugins'],
			'web.enableIntelligentShrinking' => !$this->options['disable-smart-shrinking'],
		)));
		return $obj->convert();
	}

	public function offsetExists($k)
	{
		return array_key_exists($k, $this->options);
	}

	public function offsetGet($k)
	{
		if (array_key_exists($k, $this->options)) return $this->options[$k];
		trigger_error('Unknown option '.$k);
	}

	public function offsetSet($k, $v)
	{
		if (!array_key_exists($k, $this->options)) {
			trigger_error('Unknown option '.$k);
			return;
		}
		switch (gettype($this->options[$k]))
		{
		case 'boolean': $this->options[$k] = !!$v; break;
		case 'integer': $this->options[$k] = (int) $v; break;
		default:
		case 'string' : $this->options[$k] = (string) $v; break;
		}
	}

	public function offsetUnset($k)
	{
		$this->offsetSet($k, null);
	}

}
