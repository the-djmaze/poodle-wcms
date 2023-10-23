<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	ban_type: 0 = just ban a ip
	          1 = it's a bot
	          2 = email
	          3 = referer
	          4 = email and referer
	          5 = disallowed usernames
	          6 = MAC address
*/

namespace Poodle;

abstract class Security
{
	const
		EMAIL_DB   = 1,
		EMAIL_MX   = 2,
		EMAIL_UTF8 = 4;

	public static function init()
	{
		if (!empty($_SESSION['SECURITY']['banned'])) { \Poodle\Report::error($_SESSION['SECURITY']['banned']); }
		# If not a member check for bot or ban
		$K = \Poodle::getKernel();
		if ($K && $K->SESSION->is_new()) { $_SESSION['SECURITY']['banned'] = false; }
		if (!empty($_SESSION['SECURITY']['banned'])) { \Poodle\Report::error($_SESSION['SECURITY']['banned']); }
	}

	public static function check()
	{
		if (!empty($_SESSION['SECURITY']['banned'])) { return; }
		# anti-flood protection
//		self::flood();
	}

	public static function checkDomain($domain, $full=false)
	{
		$host = parse_url($domain, PHP_URL_HOST);
		if (!$host) {
			// IPv6: [3ffe:2a00:100:7031::1]
			if (!preg_match('~@([^@\\[\\]/:?&#]+|\\[[a-zA-Z0-9:]+\\])~', $domain, $match)
			 && !preg_match('~//([^@\\[\\]/:?&#]+|\\[[a-zA-Z0-9:]+\\])~', $domain, $match))
			{
				return false;
			}
			$host = $match[1];
		}
		$domain = mb_strtolower($host);

		if ($full) {
			if (!($fp = fsockopen($domain, 80, $errno, $errstr, 2))) { return false; }
			fclose($fp);
		}

		static $domains = null;
		$K = \Poodle::getKernel();
		if (is_null($domains) && $K && $K->SQL && isset($K->SQL->TBL->security)) {
			$domains = '';
			if ($result = $K->SQL->query('SELECT ban_string FROM '.$K->SQL->TBL->security.' WHERE ban_type IN (3,4)')) {
				while ($e = $result->fetch_row()) { $domains .= '|'.preg_quote($e[0]); }
			}
			if ($domains) { $domains = '#('.substr($domains,1).')#'; };
		}
		return !($domains && preg_match($domains, $domain));
	}

	protected static
		$re_email,
		$re_uri,
		$re_uri_uni;

	protected static function initRegEx()
	{
		if (self::$re_email) return;

			$ub = "25[0-5]|2[0-4]\\d|[01]?\\d\\d?";  // IPv4 part, unsigned byte (0-255)
			$h4 = "[0-9A-Fa-f]{1,4}";                // IPv6 part, hex
			$dp = "[a-z0-9](?:[a-z0-9-]*[a-z0-9])?"; // domain part
		   $loc = "[a-z0-9!#$%&'*+\\/=?^_`{|}~-]+";  // e-mail local-part part
		 $local = "{$loc}(?:\\.{$loc})*";            // e-mail local-part
		$domain = "(?:{$dp}\\.)+{$dp}";
		  $IPv4 = "(?:(?:{$ub})\\.){3}{$ub}";
		  $IPv6 = "\\[".implode('|',array(
			"(?:(?:{$h4}:){7}(?:{$h4}|:))",
			"(?:(?:{$h4}:){6}(?::{$h4}|{$IPv4}|:))",
			"(?:(?:{$h4}:){5}(?:(?:(?::{$h4}){1,2})|:{$IPv4}|:))",
			"(?:(?:{$h4}:){4}(?:(?:(?::{$h4}){1,3})|(?:(?::{$h4})?:{$IPv4})|:))",
			"(?:(?:{$h4}:){3}(?:(?:(?::{$h4}){1,4})|(?:(?::{$h4}){0,2}:{$IPv4})|:))",
			"(?:(?:{$h4}:){2}(?:(?:(?::{$h4}){1,5})|(?:(?::{$h4}){0,3}:{$IPv4})|:))",
			"(?:(?:{$h4}:){1}(?:(?:(?::{$h4}){1,6})|(?:(?::{$h4}){0,4}:{$IPv4})|:))",
			"(?::(?:(?:(?::{$h4}){1,7})|(?:(?::{$h4}){0,5}:{$IPv4})|:))"
		  ))."\\]";
		  $host = "({$domain}|{$IPv4}|{$IPv6})";
		$host_uni = "(\\p{L}(?:[0-9\\p{L}]+\\.)(?:[0-9\\p{L}]+)|{$IPv4}|{$IPv6})";

		# RFC 2822 is used as
		# RFC 1035 doesn't allow 1 char subdomains, we allow it due to some bugs in mail servers
		# RFC 5321 discourages case-sensitivity
		# RFC 6530 SMTPUTF8 not supported, like: To: "=?utf-8?q?j=E2=9C=82sper?=" <=?utf-8?q?j=E2=9C=82sper?=@example.org>
// 		self::$re_email = ';^((?:"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")(?:'.$local.')?|'.$local.')@'.$host.'$;i';
		self::$re_email = ';^((?:"[\\w\\s-]+")(?:'.$local.')?|'.$local.')@'.$host.'$;i';

		self::$re_uri     = ';^([a-z][a-z0-9\\+\\.\\-]+):\\/\\/'.$host.'(:[0-9]+)?(\\/[^\\x00-\\x1F#?]+)?(\\?[^\\x00-\\x1F#]+)?(#[^\\x00-\\x1F]+)?$;i';
		self::$re_uri_uni = ';^([a-z][a-z0-9\\+\\.\\-]+):\\/\\/'.$host_uni.'(:[0-9]+)?(\\/[^\\x00-\\x1F#?]+)?(\\?[^\\x00-\\x1F#]+)?(#[^\\x00-\\x1F]+)?$;ui';
	}

	public static function checkEmail($email, $flags=1)
	{
		static $domains = null, $mx_domains = null;

		$K = \Poodle::getKernel();

		if (strlen($email) < 6) {
			throw new \Exception(sprintf($K->L10N['%s is too short.'], $K->L10N['Email address']));
		}
		if (strlen($email) > 254) {
			throw new \Exception(sprintf($K->L10N['%s is too long.'], $K->L10N['Email address']));
		}

		self::initRegEx();
		if (!preg_match(self::$re_email, $email, $domain)) {
			throw new \Exception(sprintf($K->L10N['Invalid %s'], $K->L10N['Email address']));
		}
		$domain = $domain[2];

//		filter_var($email, FILTER_VALIDATE_EMAIL)

		// Check disallowed domains
		if ($flags & self::EMAIL_DB && $K->SQL && isset($K->SQL->TBL->security_domains)) {
			if (is_null($domains)) {
				$domains = preg_quote('example.');
				if ($result = $K->SQL->query('SELECT ban_domain FROM '.$K->SQL->TBL->security_domains.' WHERE NOT ban_email = 0')) {
					while ($e = $result->fetch_row()) {
						$domains .= '|'.preg_quote($e[0]);
					}
				}
				$domains = '#('.$domains.')#';
			}
			if (preg_match($domains, $domain, $match)) {
				throw new \Exception(sprintf($K->L10N['The mail domain "%s" is disallowed.'], $match[1]), self::EMAIL_DB);
			}
		}

		// Does domain have a valid MX?
		if ($flags & self::EMAIL_MX) {
			$mx = \Poodle\DNS::getMX($domain);
			if ($mx && $K->SQL && isset($K->SQL->TBL->security_domains)) {
				// https://dragonflycms.org/Forums/viewtopic/t=25667.html
				// https://support.tilaa.com/hc/nl/articles/115001924471-Microsoft-Hotmail-Live-com-is-blocking-my-email
				// https://gathering.tweakers.net/forum/list_messages/1830447
				// https://www.google.nl/search?q=microsoft+mail+blackhole
				if (is_null($mx_domains)) {
					$mx_domains = preg_quote('example.');
					if ($result = $K->SQL->query('SELECT ban_domain FROM '.$K->SQL->TBL->security_domains.' WHERE NOT ban_dns_mx = 0')) {
						while ($e = $result->fetch_row()) {
							$mx_domains .= '|'.preg_quote($e[0]);
						}
					}
					$mx_domains = '#('.$mx_domains.')#';
				}
				if (preg_match($mx_domains, $mx[0], $match)) {
					throw new \Exception(sprintf($K->L10N['The mail domain "%s" is disallowed.'], $domain), self::EMAIL_MX);
				}
			}
			if (!($mx
				|| \Poodle\DNS::checkRecord($domain, DNS_A)
				|| \Poodle\DNS::checkRecord($domain, DNS_AAAA)
			)) {
				throw new \Exception(sprintf($K->L10N['The mail domain "%s" does not exist.'], $domain), self::EMAIL_MX);
			}
		}

		return true;
	}

	public static function checkURI($uri, $unicode = true)
	{
		// Shortest possible uri is: tn://x.nu/
		if (strlen($uri) < 10) return false;

		self::initRegEx();
		if (!preg_match($unicode ? self::$re_uri_uni : self::$re_uri, $uri, $domain)) {
			return false;
		}
		$domain = $domain[2];

		return true;
	}

	// $_SERVER['REMOTE_ADDR']
	public static function isTorExitNode($ip)
	{
		// https://check.torproject.org/cgi-bin/TorBulkExitList.py?ip={$_SERVER['SERVER_ADDR']}&port={$_SERVER['SERVER_PORT']}
		// https://www.torproject.org/projects/tordnsel.html.en
		// https://check.torproject.org/exit-addresses
		$rev_ip = implode('.',array_reverse(explode('.',$ip)));
		$s_addr = implode('.',array_reverse(explode('.',$_SERVER['SERVER_ADDR'])));
		return ('127.0.0.2' == Poodle\DNS::getIP("{$rev_ip}.{$_SERVER['SERVER_PORT']}.{$s_addr}.ip-port.exitlist.torproject.org"));
	}

	private static function flood()
	{
		$K = \Poodle::getKernel();
		if (!$K || !$K->SQL) { return; }

		$SQL = \Poodle::getKernel()->SQL;
		$ip = $SQL->quote($_SERVER['REMOTE_ADDR']);
		$flood_time = $flood_count = 0;
		if (!empty($_SESSION['SECURITY']['flood_start'])) {
			$SQL->query('DELETE FROM '.$SQL->TBL->security_flood.' WHERE flood_time <= '.time());
			unset($_SESSION['SECURITY']['flood_start']);
		}
		if (empty($_SESSION['SECURITY']['flood_time'])) {
			# try to load time from log
			if ($row = $SQL->uFetchAssoc('SELECT * FROM '.$SQL->TBL->security_flood.' WHERE flood_ip = '.$ip)) {
				if (!empty($row)) {
					$flood_time = $row['flood_time'];
					$flood_count = $row['flood_count'];
				}
			}
		} else {
			$flood_time = $_SESSION['SECURITY']['flood_time'];
			$flood_count = $_SESSION['SECURITY']['flood_count'];
		}
		if ($flood_time > time()) {
			# die with message and report
			++$flood_count;
			if ($flood_count <= 5) {
				self::flood_log($ip, !empty($row), $flood_count);
				if ($flood_count > 2 && $flood_count <= 5) {
					$L10N = \Poodle::getKernel()->L10N;
					$L10N->load('class_report');
					$flood_time = ($flood_count+1)*2;
					\Poodle\HTTP\Status::set(503);
					header('Retry-After: '.$flood_time);
					$msg = sprintf($L10N['_SECURITY_MSG']['_FLOOD'], $flood_time);
					if (5 === $flood_count) { $msg .= '<p>'.$L10N['_SECURITY_MSG']['Last_warning'].'</p>'; }
					\Poodle\Report::error('Flood Protection', $msg);
				}
			} else {
				if ($SQL->count('security', 'ban_ipn='.$ip.' LIMIT 0,1') < 1) {
					$SQL->query('INSERT INTO '.$SQL->TBL->security." (ban_ipn, ban_time, ban_details) VALUES ($ip, '".(time()+86400).'\', \'Flooding detected by User-Agent:'.EOL.$_SERVER['HTTP_USER_AGENT'].'\')');
				}
				\Poodle\Report::error(803);
			}
		}
		self::flood_log($ip, !empty($row));
		unset($flood_time, $flood_count);
	}

	private static function flood_log($ip, $update=false, $times=0)
	{
		$timeout = (($times+1)*2)+time();
		# maybe the UA doesn't accept cookies so we use another session log as well
		if (empty($_SESSION['SECURITY']['flood_time'])) {
			$SQL = \Poodle::getKernel()->SQL;
			if ($update) {
				$SQL->query('UPDATE '.$SQL->TBL->security_flood." SET flood_time = '$timeout', flood_count = '$times' WHERE flood_ip = $ip");
			} else {
				$SQL->query('INSERT INTO '.$SQL->TBL->security_flood." VALUES ($ip, '$timeout', '$times')");
			}
			$_SESSION['SECURITY']['flood_start'] = true;
		}
		$_SESSION['SECURITY']['flood_time'] = $timeout;
		$_SESSION['SECURITY']['flood_count'] = $times;
	}

}
