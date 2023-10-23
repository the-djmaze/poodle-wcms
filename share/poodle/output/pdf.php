<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Output;

class PDF extends \Poodle\Output\HTML
{
	private
		$_PDF;

	function __construct()
	{
		parent::__construct();
		$this->http = array();
	}

	public function start() : bool
	{
		ob_start();
		$this->http = array();
		return parent::start();
	}

	public function finish() : void
	{
		try {
			$pdf = $this->getFinalData();
			$filename = \Poodle\Filesystem\File::fixName(\Poodle::getKernel()->RESOURCE->title);
			\Poodle::ob_clean();
			header('Content-Type: application/pdf');
			header('Content-Transfer-Encoding: binary');
			header("Content-Disposition: attachment; filename={$filename}.pdf");
			echo $pdf;
		} catch (\Throwable $e) {
			echo $e->getMessage();
		}
	}

	public function getFinalData()
	{
		parent::finish();
		$base = $_SERVER['REQUEST_SCHEME'].'://' . \Poodle\URI::host();
		$data = preg_replace('#(<head( [^>]*)?>)#', '$1<base href="'.$base.'"/>', ob_get_clean());
		$PDF = $this->__get('PDF');
		$PDF['disable-javascript'] = false;
//		$PDF->xvfb = true;
		if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
			$PDF->username = $_SERVER['PHP_AUTH_USER'];
			$PDF->password = $_SERVER['PHP_AUTH_PW'];
		}
		$PDF->encoding = \Poodle::CHARSET;
		$data = str_replace('<details', '<details open=""', $data);
		return $PDF->renderHTML($data);
	}

	public static function ob_handler($buffer, $mode) : string
	{
		return $buffer;
	}

	function __get($key)
	{
		switch ($key)
		{
		case 'PDF':
			if (!$this->_PDF) {
				$this->_PDF = new \Poodle\wkhtmltopdf();
//				$this->_PDF = new \Poodle\WeasyPrint();
			}
			return $this->_PDF;

		case 'bugs':
		case 'bugs_json':
		case 'memory_usage':
		case 'parse_time':
		case 'tpl_time':
		case 'debug_json':
			return null;
		}
		return parent::__get($key);
	}

}
