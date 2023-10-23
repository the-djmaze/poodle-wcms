<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\HTTP;

abstract class Client
{

	public static function application()
	{
		if (POODLE_CLI) {
			return (object)array(
				'name'=>null,
				'version'=>0,
				'engine'=>(object)array('name'=>null, 'version'=>0),
				'OS'=>(object)array('name'=>null, 'version'=>0)
			);
		}
		if (!isset($_SESSION['Poodle']['USER_AGENT'])) {
			$_SESSION['Poodle']['USER_AGENT'] = \Poodle\UserAgent::getInfo();
		}
		return $_SESSION['Poodle']['USER_AGENT'];
	}

	public static function os()
	{
		return self::application()->os;
	}

	public static function engine()
	{
		return self::application()->engine;
	}

	public static function ips()
	{
		static $ips = array();
		if (!$ips) {
			$ips[] = $_SERVER['REMOTE_ADDR'];
/*
			$ip_headers = array(
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'HTTP_CLIENT_IP',
				'HTTP_X_COMING_FROM',
				'HTTP_COMING_FROM',
				'HTTP_VIA',
				'HTTP_FORWARDED_FOR_IP',
				'HTTP_PROXY_CONNECTION'
			);
			for ($i = 0; $i < 7; ++$i)
			{
				if (!empty($_SERVER[$ip_headers[$i]]) && 'unknown' !== $_SERVER[$ip_headers[$i]])
				{
					$ips = array_merge($ips, explode(',', $_SERVER[$ip_headers[$i]]));
				}
			}
			$c = count($ips);
			for ($i = 0; $i < $c; ++$i)
			{
				$ips[$i] = trim($ips[$i]);
				# IPv4
				if (strpos($ips[$i], '.'))
				{
					# check for a deprecated hybrid IPv4-compatible address
					$pos = strrpos($ips[$i], ':');
					if (false !== $pos) { $ips[$i] = substr($ips[$i], $pos+1); }
					# Don't assign local network ip's
					if (preg_match('#^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$#', $ips[$i])
					 && !preg_match('#^(10|127.0.0|172.(1[6-9]|2\d|3[0-1])|192\.168)\.#', $ips[$i]))
					{
						$ip = $ips[$i];
						break;
					}
				}
				# IPv6
				else if (false !== strpos($ips[$i], ':'))
				{
					if (preg_match('#^([0-9A-F]{0,4}:){2,7}[0-9A-F]{0,4}$#Di', $ips[$i])
					# skip loopback, link-local, ULA and IPv4 mapped
					 && !preg_match('#^(::1[:$]|fe80:|fc00:|::ffff:)#D', $ips[$i]))
					{
						$ip = $ips[$i];
						break;
					}
				}
			}
*/
		}
		return $ips;
	}

	public static function supportsWebp()
	{
		return false !== stripos($_SERVER['HTTP_ACCEPT'], 'image/webp');
	}

}
