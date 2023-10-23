<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Image\Adapter;

if (!class_exists('Gmagick',false)) { return; }

class GMagick extends \Gmagick
{
	function __construct($file=null)
	{
		parent::__construct($file);
		// Strip meta data
		if ($file) { parent::stripImage(); }
	}

	function __destruct()
	{
		$this->clear();
	}

	public function free()
	{
		$this->clear();
	}

	public function newPixelObject($color = null)
	{
		return new \GmagickPixel($color);
	}

	public function add_text($params)
	{
		$default_params = array(
			'text'  => 'Default text',
			'x'     => 10,
			'y'     => 20,
			'size'  => 12,
			'color' => '#000000',
			'font'  => dirname(__DIR__).'/fonts/default.ttf',
			'angle' => 0,
		);
		$params = array_merge($default_params, $params);
		$params['color']= strtolower($params['color']);
		$draw  = new \GmagickDraw();
		$pixel = new \GmagickPixel($params['color']);
		$draw->setfillcolor($pixel);
		$draw->setfontsize($params['size']);
		$draw->setfont($params['font']);
		return $this->annotateimage($draw, $params['x'], $params['y'], $params['angle'], $params['text']);
	}

	public function readImage($file)
	{
		throw new \BadMethodCallException('readImage() not supported');
	}

	public function writeImage($filename) : bool
	{
		if ($filename) {
			$dir = \dirname($filename);
			if (!\is_dir($dir) && !\mkdir($dir, 0777, true)) {
				throw new \Exception("Failed to create directory {$dir}");
			}
		}
		return !!parent::writeImage($filename);
	}

	public function rotate($degrees)
	{
		return $this->rotateImage(new \GmagickPixel(), $degrees);
	}

	// Fatal error: Call to undefined method GMagick::setImageCompression()
	public function setImageCompression($q) {}

	// Fatal error: Call to undefined method GMagick::setImageCompressionQuality()
	public function setImageCompressionQuality($q) {}

	public function getImageFormat()
	{
		return strtolower(parent::getImageFormat());
	}

	public function getImageMimeType()
	{
		switch ($this->getImageFormat())
		{
		case 'png':
		case 'png8':
		case 'png24':
		case 'png32':
			return 'image/png';
		case 'jpeg':
			return 'image/jpeg';
		case 'gif':
			return 'image/gif';
		case 'webp':
			return 'image/webp';
		}
		return false;
	}

}
