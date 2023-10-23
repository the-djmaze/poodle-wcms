<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Examples:

		<form tal:attributes="data-p-challenge php:Poodle\AntiSpam\Captcha::generateHidden()">

		<div>
			<label>Question:</label>
			<input class="p-challenge" type="text" tal:attributes="name php:Poodle\AntiSpam\Captcha::generateQuestion()"/>
		</div>

		<div>
			<label>Hidden:</label>
			<input class="p-challenge" type="hidden" tal:attributes="name php:Poodle\AntiSpam\Captcha::generateHidden()"/>
		</div>

		<div>
			<label>Image:</label>
			<input class="p-challenge" type="text" tal:attributes="name php:Poodle\AntiSpam\Captcha::generateImage()"/>
		</div>

		\Poodle\AntiSpam\Captcha::validate($_POST)

*/

namespace Poodle\AntiSpam;

abstract class Captcha
{

	protected static function set($value, $uid)
	{
		$key = bin2hex(random_bytes(16));
		$_SESSION['POODLE_CAPTCHA'][($uid ? "{$uid}-" : "") . $key] = array($value, time());
		$_SESSION['POODLE_CAPTCHA'] = array_slice($_SESSION['POODLE_CAPTCHA'], -4, 4, true);
		foreach ($_COOKIE as $name => $value) {
			if (!isset($_SESSION['POODLE_CAPTCHA'][$name]) && preg_match('/^[a-z0-9]{32}=[hiq]:/', "{$name}={$value}")) {
				\Poodle\HTTP\Cookie::remove($name);
			}
		}
		return $key;
	}

	final public static function generateHidden($uid = '')
	{
		$val = \Poodle\UUID::generate();
		$key = self::set($val, $uid);
		\Poodle\HTTP\Cookie::setStrict($key, 'h:'.$val, 60);
		return $key;
	}

	final public static function generateImage($chars = 6, $uid = '')
	{
		$time = explode(' ', microtime());
		$key = self::set(substr(dechex($time[0]*3581692740), 0, $chars), $uid);
		\Poodle\HTTP\Cookie::setStrict($key, 'i:'.\URL::load('captcha&'.$key), 60);
		return $key;
	}

	final public static function generateQuestion($uid = '')
	{
		$key = self::set('value', $uid);
		\Poodle\HTTP\Cookie::setStrict($key, 'q:What is your username?', 60);
		return $key;
	}

	// On success it returns the amount of seconds from set() till validate()
	// Note: it could return 0, so when that is allowed, check for false
	final public static function validate($data, $uid = '')
	{
		$result = false;
		if (!empty($_SESSION['POODLE_CAPTCHA'])) {
			$l = strlen($uid);
			foreach ($_SESSION['POODLE_CAPTCHA'] as $name => $answer) {
				$key = $name;
				if ($l) {
					$key = explode('-', $key, 2);
					if ($uid !== $key[0]) {
						continue;
					}
					$key = $key[1];
				}
				if (isset($data[$key])) {
					unset($_SESSION['POODLE_CAPTCHA'][$name]);
					if ($answer[0] == $data[$key]) {
						$result = time() - $answer[1];
						break;
					}
				}
			}
		}
		if (!$result) {
			\Poodle\LOG::warning('Captcha failed', 'Captcha validation failed', true);
		}
		return $result;
	}

}
