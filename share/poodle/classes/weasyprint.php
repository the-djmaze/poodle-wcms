<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	dnf install weasyprint python3-cairocffi python3-weasyprint python3-cairosvg python3-pyphen python3-tinycss python3-xcffib
	pip3 install --upgrade --force-reinstall WeasyPrint
		Else we get TypeError: __init__() got an unexpected keyword argument 'encoding'
*/

namespace Poodle;

class WeasyPrint implements \ArrayAccess
{
	public
		$tmp = '/tmp';

	public static
		$binary = 'weasyprint';

	protected
		$options = array(
			'format'     => 'pdf',
/*
			'encoding'   => 'utf8',
			'stylesheet' => 'path/to/style.css',
			'media-type' => 'print',
			'resolution' => 'print', // dpi
			'base-url'   => '',
*/
		);

	function __construct()
	{
		$this->tmp = sys_get_temp_dir() . '/pdf-' . $_SERVER['HTTP_HOST'] . '-' . microtime(1);
//		self::$binary = `which weasyprint`;
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
	}

	protected function render($html, $uri = false)
	{
		$cmd = self::$binary;
		# Add parameters
		foreach ($this->options as $k => $v) {
			if (!$v) {
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
		if (!$uri) {
			file_put_contents($this->tmp . '.htm', $html);
			$html = $this->tmp . '.htm';
		}
		$stdin = escapeshellarg($html);
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

//		exec('weasyprint '.$path['tmp'].DIRECTORY_SEPARATOR.'pdf.htm '$path['tmp'].DIRECTORY_SEPARATOR.$pdf_name.' -s ./css/bootstrap.min.css -s ./css/style.css');

		$pdf = null;
		$process = proc_open($cmd, $descriptorspec, $pipes, null, $env);
		if (is_resource($process)) {
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

			if (!$uri) {
				unlink($html);
			}

			if ($code) {
				throw new \Exception($error, $code);
			}
		}

		if (!$uri) {
			unlink($html);
		}

		return $pdf;
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
