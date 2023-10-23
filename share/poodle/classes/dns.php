<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class DNS
{
	public static function getHostName($ip_address)
	{
		$value = gethostbyaddr($ip_address);
//		$value = trim(exec('dig -x '.escapeshellarg($ip_address).' +short'));
		// Prevent HTML inside hostname, IDN doesn't allow them anyway
		// http://unicode.org/reports/tr36/idn-chars.html
		if (false === strpos($value,'<') && false === strpos($value,'>')) {
			return $value;
		}
		return $ip_address;
	}

	public static function getIP($hostname, $ipv6 = true)
	{
		$ip4 = array();
		$dns = dns_get_record($hostname, $ipv6 ? DNS_A | DNS_AAAA : DNS_A) ?: array();
		foreach ($dns as $record) {
			if ('A' === $record['type']) {
				$ip4[] = $record['ip'];
			}
			if ('AAAA' === $record['type']) {
				return $record['ipv6'];
			}
		}
		return $ip4 ? $ip4[0] : gethostbyname($hostname);
	}

	/*
	 * This function should not be used for the purposes of address verification.
	 * Only the mailexchangers found in DNS are returned, however, according to
	 * RFC 5321 5.1 when no mail exchangers are listed, hostname itself should
	 * be used as the only mail exchanger with a priority of 0.
	 */
	public static function getMX($hostname)
	{
		$mxhosts = array();
		$dns = dns_get_record($hostname, DNS_MX) ?: array();
		foreach ($dns as $record) {
			$mxhosts[$record['pri']] = $record['target'];
		}
		if (!$mxhosts) {
			getmxrr($hostname, $mxhosts);
		}
		if (!$mxhosts) {
			return false;
		}
		ksort($mxhosts);
		return array_values($mxhosts);
	}

	public static function checkRecord($host, $type = DNS_MX)
	{
		static $map = array(
			DNS_A     => 'A',
			DNS_CNAME => 'CNAME',
//			DNS_HINFO => 'HINFO',
			DNS_MX    => 'MX',
			DNS_NS    => 'NS',
			DNS_PTR   => 'PTR',
			DNS_SOA   => 'SOA',
			DNS_TXT   => 'TXT',
			DNS_AAAA  => 'AAAA',
			DNS_SRV   => 'SRV',
			DNS_NAPTR => 'NAPTR',
			DNS_A6    => 'A6',
//			DNS_ALL   => 'ALL',
			DNS_ANY   => 'ANY',
		);
		$host = rtrim(idn_to_ascii($host), '.').'.';
		return checkdnsrr($host, $map[$type]);
	}

	public static function getSPF($hostname)
	{
		$records = dns_get_record($hostname, DNS_TXT);
		foreach ($records as $record) {
			if (0 === strpos($record['txt'], 'v=spf')) {
				return $record['txt'];
			}
		}
		return false;
	}

	public static function getDKIM($hostname)
	{
		$records = dns_get_record("default._domainkey.{$hostname}", DNS_TXT);
		foreach ($records as $record) {
			if (0 === strpos($record['txt'], 'v=DKIM')) {
				return $record['txt'];
			}
		}
		return false;
	}

	public static function getDMARC($hostname)
	{
		$records = dns_get_record("_dmarc.{$hostname}", DNS_TXT);
		foreach ($records as $record) {
			if (0 === strpos($record['txt'], 'v=DMARC')) {
				return $record['txt'];
			}
		}
		return false;
	}

}
