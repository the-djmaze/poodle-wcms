<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	<picture>
		<source srcset="logo.webp" type="image/webp"/>
		<img src="logo.png" alt=""/>
	</picture>
	<img src="logo.png" alt=""/>
*/

namespace Poodle\Output;

class Image
{

	public static function display(string $filename)
	{
		$file = $filename;

		// To webp
		if (\preg_match('#^(.+/)webp/([^/]+)\\.webp$#Di', $filename, $webp)) {
			$file = "{$webp[1]}{$webp[2]}";
		} else if (\preg_match('#^(.+)\\.webp$#Di', $filename, $img)) {
			if (is_file("{$img[1]}.png")) {
				$file = "{$img[1]}.png";
			} else if (is_file("{$img[1]}.jpeg")) {
				$file = "{$img[1]}.jpeg";
			} else if (is_file("{$img[1]}.jpg")) {
				$file = "{$img[1]}.jpg";
			}
		}

		// Auto-resize
		$resize = array();
		if (!\is_file($file) && \preg_match('#^(.+)\\.([0-9]+)(?:x([0-9]+))?\\.(jpe?g|png|gif|webp)$#Di', $file, $resize)) {
			$file = "{$resize[1]}.{$resize[4]}";
		}

		if (\is_file($file)) {
			$src = static::open($file);
			if ($webp && \Poodle\HTTP\Client::supportsWebp()) {
				$src->setImageFormat('webp');
			}
			if ($resize) {
				$src->thumbnailImage($resize[2], $resize[3] ?: 0);
			}
			$src->writeImage($filename);
//			\header('Content-Length: ' . \strlen($src));
			\header('Content-Type: '.$src->getImageMimeType());
			echo $src;
			exit;
		}
	}

	protected static function open(string $filename)
	{
		if (\extension_loaded('gmagick'))      { $handler = 'gmagick'; }
		else if (\extension_loaded('imagick')) { $handler = 'imagick'; }
		else if (\extension_loaded('gd'))      { $handler = 'gd2'; }
		else { throw new \Exception('No image handler found'); }
		$handler = 'Poodle\\Image\\Adapter\\'.$handler;
		return new $handler($filename);
	}

}
