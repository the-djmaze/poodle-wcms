<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Image
{
	public static function open($filename, $handler=null)
	{
		$info = getimagesize($filename);
		if (!is_array($info) || count($info) < 3) {
			throw new \InvalidArgumentException($filename.' is not an image');
		}
		$handler = self::getHandler($handler);
		try {
			return new $handler($filename);
		} catch (\Throwable $e) {
			Debugger::error(E_USER_WARNING, $e->getMessage(), $e->getFile(), $e->getLine());
		}
		return new Image\Adapter\GD2($filename);
	}

	public static function create($width, $height, $background='none', $format='', $handler=null)
	{
		$handler = self::getHandler($handler);
		$img = new $handler();
		$img->newImage($width, $height, $background, $format);
		return $img;
	}

	private static function getHandler($handler)
	{
		if (!$handler && ($K = \Poodle::getKernel()) && $K->CFG) {
			$handler = $K->CFG->image->handler;
		}
		if (!$handler || !\Poodle::getFile('poodle/image/adapter/'.strtolower($handler).'.php')) {
			if (extension_loaded('gmagick'))      { $handler = 'gmagick'; }
			else if (extension_loaded('imagick')) { $handler = 'imagick'; }
			else if (extension_loaded('gd'))      { $handler = 'gd2'; }
			else { throw new \Exception('No image handler found'); }
		}
		return 'Poodle\\Image\\Adapter\\'.$handler;
	}

	public static function getHandlers()
	{
		static $handlers = array();
		if (!$handlers) {
			foreach (glob(__DIR__ . '/adapter/*.php') as $file)
			{
				$file = basename($file,'.php');
				$handlers[$file] = $file;
			}
			if (!extension_loaded('gd')) { unset($handlers['gd2']); }
			if (!extension_loaded('gmagick')) { unset($handlers['gmagick']); }
			if (!extension_loaded('imagick')) { unset($handlers['imagick']); }
			foreach ($handlers as $name)
			{
				$handler = self::getHandler($name);
				$img = new $handler();
				$info = $img->getVersion();
				$info['name'] = $img->getPackageName();
				$info['formats'] = $img->queryFormats();
//				$info['copyright'] = $img->getCopyright();
				$handlers[$name] = $info;
			}
			ksort($handlers);
		}
		return $handlers;
	}

}
