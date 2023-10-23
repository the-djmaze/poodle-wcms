<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Mozilla/5.0 (platform; rv:geckoversion) Gecko/geckotrail Firefox/firefoxversion
	BB10:      Mozilla/5.0 (BB10; Touch) AppleWebKit/537.10+ (KHTML, like Gecko) Version/10.1.0.2354 Mobile Safari/537.10+
	ChromeOS:  Mozilla/5.0 (X11; CrOS armv7l 4100.86.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1500.95 Safari/537.36
	FirefoxOS: Mozilla/5.0 (Mobile; rv:18.0) Gecko/18.0 Firefox/18.0
*/

namespace Poodle;

abstract class UserAgent
{
	public static function getInfo()
	{
		static $ua = null;
		if (!$ua) {
			$ua = self::application();
			$ua->bot    = ('HTTP/1.0' === $_SERVER['SERVER_PROTOCOL']); // Treat old agents as bots
			$ua->engine = self::engine();
			$ua->OS     = self::os();
			if (empty($ua->name) || empty($ua->engine->name) || empty($ua->OS->name) || preg_match('#[a-z0-9]+bot|spider|crawl#',self::ua()))
			{
				if (!self::ua()) {
					$ua->bot = true;
					$ua->name = 'empty UA';
				} else
				if (preg_match('#((?:[a-z0-9-_\\. :]+(?:bot|spider|crawl|search|lyzer|request|seeker|fetch)|ezooms|vagabondo|facebookexternalhit|lipperhey)(?:[a-z0-9-_ ]+)?)(?:/v?([0-9\\.]+))?#',self::ua(),$m)
				 || preg_match('#(google-site-verification|google_analytics_snippet_validator|google-structureddatatestingtool|google page speed insights|google favicon|appengine-google)#',self::ua(),$m)
				 || preg_match('#(plukkie|ia_archiver|spotinfluence|yeti|80legs'
					.'|pagesinventory|scrapy|shopwiki|w3c-checklink|wotbox'
					.'|openwebindex|secomp|ant\\.com|aramabeta|guoming|elefent|bingpreview'
					.'|abonti|butterfly|genieo|findlinks|webmasteraid|admantx'
					.'|dataprovider|proximic|exaleadcloudview|daumoa|eccp|updown_tester'
					.'|cakephp|tarantula|xenu|robosourcer|dreampassport'
					.'|flipboardrss|curious george|aboundex|freewebmonitoring'
					.'|yahoo! slurp|openstat|gvfs|embedly|add catalog|spider-ads'
					.'|seznam screenshot-generator|indonesiancoder|website explorer'
					.'|parsijoo|feedreader|validator.nu|dnsdelve|email exractor'
					.'|windows-rss-platform|synapse-http-async-service|prurl|netvibes'
					.'|curl|libwww-perl|python|python-urllib|java|pcore-http'
					.'|zend_http_client|wget|w3c_validator|wsr-agent'
					.'|manticore|clever internet suite|butterfly|integromedb'
					.'|jetbrains|prlog|yacy|kumkie|a6-indexer|icarus6|feedburner'
					.'|voltron|user_agent|seostats|wscheck.com|go 1.1 package http'
					.'|www-mechanize|kimengi|aria2|gigablastopensource|crowsnest'
					.'|metauri|ltx71|nikto|deadlinkchecker|trovator|larbin|seobility'
					.'|domainappender|tlsprober|websteamer|safaribookmarkchecker'
					.'|steeler|sqlmap|fimap|findthebest|broken link checker'
					.'|megaindex|instanews|typhoeus|iskanie|bubing|qwantify'
					.'|worldwebheritage|webripper|httpunit|ruby|exploratodo'
					.'|netcraftsurveyagent|cc metadata scr?aper|httpclient'
					.'|lingewoud-550-spyder|qwantify|pmoz.info|structuredweb'
					.'|mailchimp|pilican|cuwhois|linksaver|linkparser|coiparser'
					.'|yandeximages|yandexmetrika|scoutjet|seodiver'
					.'|coccoc|spbdyet.ru|moykrest.ru|apachebench|yahoocachesystem'
					.'|b-l-i-t-z-b-o-t|catexplorador|pinterest|riddler|stratagems'
					.'|araturka|go-http-client|gettor|whatsapp|binlar|heritrix'
					.'|yahoocachesystem|netcraft|insightscollector|gtmetrix'
					.'|omgili|wbsrch|garlik|nmap scripting engine|mlib_networklibrary'
					.'|seoscanners|daum|servernfo|skypeuripreview|bildersauger'
					.'|sitesucker|webcopier|webox|wmtips|htmlget|boardreader'
					.'|httrack|sysomos|statastico|sylera|netshelter|sitetruth'
					.'|easy-thumb|statastico|zgrab|checkmarknetwork|evc-batch'
					.'|barkrowler|wordpress|iframely|dragonfly|bpimagewalker'
					.'|linkwalker|grouphigh|stumbleupon|mollie.nl http client'
					.'|magic browser|kruipertje|emailextractor|checkmarknetwork'
					.'|webcapture|eright|siteexplorer|crawler4j|mixnode|tagvisit'
					.'|startpagina-linkchecker|woorank|pinterest|insightscollector'
					.'|wappalyzer|seoscanners|checkmarknetwork|ecatch|internetvista'
					.'|x9o|redirector|lcc|integrity|g-i-g-a-b-o-t|adscanner|tracemyfile'
					.'|commonscan)(?:/v?([0-9\\.]+))?#',self::ua(),$m)
				 || preg_match('#(nutch)-([0-9\\.]+)#',self::ua(),$m)) {
					$ua->bot = true;
					$ua->name = trim($m[1]);
					$ua->version = isset($m[2]) ? (float)$m[2] : 0;
				} else {
					try {
						\Poodle::getKernel()->SQL->TBL->log_ua->insertIgnore(array(
							'log_ua_name' => substr($_SERVER['HTTP_USER_AGENT'], 0, 255),
							'log_ua_time' => time()
						));
					} catch (\Throwable $e) {}
				}
				if (empty($ua->name)) { $ua->name = 'unknown'; }
				if (empty($ua->engine->name)) { $ua->engine->name = 'unknown'; }
				if (empty($ua->OS->name)) { $ua->OS->name = 'unknown'; }
			} else
			if (strpos(self::ua(),'baidu.com/search/spider')) {
				$ua->bot = true;
				$ua->name = 'baiduspider';
			}
		}
		return $ua;
	}

	private static function ua()
	{
		static $ua;
		return ($ua ?: $ua = strtolower($_SERVER['HTTP_USER_AGENT']));
	}

	public static function engine()
	{
		static $engine = null;
		if (!$engine) {
			$engine = array('name'=>null, 'version'=>0);
			$ua = self::ua();
			if (preg_match('#(edge)/([0-9]+)#', $ua, $match)
			 || preg_match('#(apachebench|dillo|gecko|khtml|presto|trident|up\\.browser|webkit|evolution|outlook)[/\\ ]([0-9]+(\\.[0-9]+)?)#', $ua, $match)
			 || preg_match('#(msie|opera|dalvik)[/\\ ]([0-9]+(\\.[0-9]+)?)#', $ua, $match))
			{
				if ('opera'  === $match[1]) { $match[1] = 'presto';  $match[2] = 1; }
				if ('msie'   === $match[1]) { $match[1] = 'trident'; $match[2] = 3; }
				if ('dalvik' === $match[1]) { $match[1] = 'webkit'; }
				if ('edge'   === $match[1]) {
					if (21 > $match[2]) { $match[2] = 12; }
					else { $match[2] = 13; }
				}
				$engine = array('name' => $match[1], 'version' => floatval($match[2]));
			}
		}
		return (object)$engine;
	}

	public static function application()
	{
		/**
		 * palemoon, epiphany, flock and galeon have "firefox" in their UA
		 * chimera is an on old name of camino
		 * cheshire, midori, omniweb, shiira and sunrise have "safari" in their UA
		 * We don't detect MyIE2, AOL and America Online branded versions of MSIE
		 * Sylera is a Japanese Gecko based browser
		 * Microsoft changed the string with IE 11
		 */
		static $app = null;
		if (!$app) {
			$app = array('name'=>null, 'version'=>0);
			$ua = self::ua();
			// Microsoft IE 11
			if (preg_match('#trident/.+; rv:(1[1-9]+(?:\\.[0-9]+)?)#', $ua, $match)) {
				$app = array('name' => 'msie', 'version' => floatval($match[1]));
			}
			// Now the others
			else if (preg_match('#(edge|focus|vivaldi)/([0-9]+(\\.[0-9])?)#', $ua, $match)
			 || preg_match('#(irider|crazy|netcaptor|maxthon|avant|webtv|ubvision|ucbrowser)#', $ua, $match)
			 || preg_match('#(elinks|opera|shiira|devontech|ibrowse|icab)#', $ua, $match)
			 || preg_match('#(brave|epiphany|flock|galeon|cheshire|midori|omniweb|swiftfox|palemoon|waterfox|crios)(?:/([0-9\\.]+))?#', $ua, $match)
			 || preg_match('#(konqueror|voyager|links|lynx|w3m|dillo|netscape|thunderbird|camino|seamonkey|linspire|multizilla|k-meleon|kazehakase|minimo)#', $ua, $match)
			 || preg_match('#(chrome|safari|firefox|netsurf|browserng)(?:/([0-9\\.]+))?#', $ua, $match)
			 || preg_match('#(msie) (1?[0-9](\\.[0-9])?)#', $ua, $match)
			 || preg_match('#(webwasher|dalvik|iphone|ipad)#', $ua, $match)
			 || preg_match('#(slimbrowser|swiftweasel|conkeror|chimera|classilla|gnuzilla|iceweasel|iceape)#', $ua, $match))
			{
				if ('ubvision' === $match[1]) { $match[1] = 'ultrabrowser'; }
				else if ('crios' === $match[1]) { $match[1] = 'chrome'; }
				$app['name'] = $match[1];
				if (!empty($match[2])) { $app['version'] = floatval($match[2]); }
				if ('safari' === $app['name'] && preg_match('#Version/([0-9\\.]+)#', $ua, $match)) {
					$app['version'] = floatval($match[1]);
				}
			}
			/**
			 * firebird and phoenix are old names for firefox
			 * bonecho, granparadiso, minefield are develop names for firefox
			 */
			else if (preg_match('#(firebird|phoenix|bonecho|granparadiso|minefield|shiretoko|namoroka|lorentz)#', $ua))
			{
				$app['name'] = 'firefox';
			}
			else if (preg_match('#^mozilla/5.+gecko/200#', $ua))
			{
				$app['name'] = 'mozilla';
			}
			else if (preg_match('#\\[FBAV/([0-9]+)#', $ua))
			{
				$app = array('name' => 'facebook', 'version' => floatval($match[1]));
			}
		}
		return (object)$app;
	}

	public static function os()
	{
		static $os = null;
		if (!$os) {
			$ua = self::ua();
			$os = array('name'=>null, 'version'=>0);
			if (preg_match('#(tizen)#', $ua, $match)
			 || preg_match('#(android'
				.'|iphone|ipad|ipod|os x'
				.'|blackberry|bb10|playbook'
				.'|bsd|sunos|syllable|irix|beos|aix|amiga|symbian|react|meego'
				.'|javafx|palm|nintendo|playstation|xbox)( [0-9\\.]+)?#', $ua, $match)
			 || preg_match('#(linux|x11|mac|win)#', $ua, $match)
			 || preg_match('#(cros|os/2)#', $ua, $match))
			{
				$os['name'] = $match[1];
				switch ($os['name'])
				{
				case 'android': $os['version'] = isset($match[2]) ? $match[2] : 0; break;
				case 'x11':     $os['name'] = 'linux'; break;
				case 'cros':    $os['name'] = 'chrome'; break;
				case 'ipad':
				case 'ipod':
				case 'iphone':
					$os['name'] = 'ios';
					if (preg_match('#os ([0-9_]+)#', $ua, $match)) {
						$os['version'] = strtr($match[1], '_', '.');
					}
					break;
				case 'os x':
					$os['name'] = 'osx';
					if (preg_match('#os x ([0-9_]+)#', $ua, $match)) {
						$os['version'] = strtr($match[1], '_', '.');
					}
					break;
				case 'playbook':
				case 'bb10':    $os['name'] = 'blackberry';
					if (preg_match('#version/([0-9\\.]+)#', $ua, $match)) {
						$os['version'] = (float)$match[1];
					}
					break;
				case 'win':    $os['name'] = 'windows';
				case 'windows':
					if (preg_match('#nt ([0-9\\.]+)#', $ua, $match)) {
						$os['version'] = (float)$match[1];
					}
					break;
				case 'symbian':
					if (preg_match('#symbianos/([0-9\\.]+)#', $ua, $match)) {
						$os['version'] = (float)$match[1];
					}
					break;
				}
			}
		}
		return (object)$os;
	}

	public static function isOld()
	{
		$client = static::getInfo();
		return ('msie' === $client->name
			|| (56 > $client->version && ('firefox' === $client->name || 'chrome' === $client))
		);
	}

	public static function isTablet()
	{
		static $mob = null;
		if (null !== $mob) { return $mob; }
		$ua = self::ua();
		return $mob = false !== strpos($ua, 'tablet')
			|| false !== strpos($ua, 'ipad')
			|| false !== strpos($ua, 'playbook');
	}

	public static function isMobile()
	{
		static $mob = null;
		if (null !== $mob) { return $mob; }
		$ua = self::ua();
		return $mob = false !== strpos($ua, 'mobile')
		|| false !== strpos($ua, 'phone')
		|| false !== strpos($ua, 'android')
		|| false !== strpos($ua, 'bb10')

		|| !empty($_SERVER['HTTP_X_OPERAMINI_PHONE'])
		|| false !== strpos(strtolower($_SERVER['HTTP_ACCEPT']), '/vnd.wap.') // xhtml+xml | wml
		|| false !== strpos($ua, 'symbian')
		|| false !== strpos($ua, 'windows ce')
		|| false !== strpos($ua, 'epoc')
		|| false !== strpos($ua, 'opera mini')
		|| false !== strpos($ua, 'opera mobi')
		|| false !== strpos($ua, 'minimo')
		|| false !== strpos($ua, 'nitro')
		|| false !== strpos($ua, 'j2me')
		|| false !== strpos($ua, 'midp-')
		|| false !== strpos($ua, 'cldc-')
		|| false !== strpos($ua, 'netfront')
		|| false !== strpos($ua, 'mot')
		|| false !== strpos($ua, 'up.browser')
		|| false !== strpos($ua, 'up.link')
		|| false !== strpos($ua, 'sony')
		|| false !== strpos($ua, 'nokia')
		|| false !== strpos($ua, 'samsung')
		|| false !== strpos($ua, 'audiovox')
		|| false !== strpos($ua, 'blackberry')
		|| false !== strpos($ua, 'ericsson')
		|| false !== strpos($ua, 'panasonic')
		|| false !== strpos($ua, 'philips')
		|| false !== strpos($ua, 'sanyo')
		|| false !== strpos($ua, 'sharp')
		|| false !== strpos($ua, 'sie-')
		|| false !== strpos($ua, 'portalmmm')
		|| false !== strpos($ua, 'blazer')
		|| false !== strpos($ua, 'avantgo')
		|| false !== strpos($ua, 'danger')
		|| false !== strpos($ua, 'palm')
		|| false !== strpos($ua, 'series60')
		|| false !== strpos($ua, 'palmsource')
		|| false !== strpos($ua, 'pocketpc')
		|| false !== strpos($ua, 'smartphone')
		|| false !== strpos($ua, 'rover')
		|| false !== strpos($ua, 'ipaq')
		|| false !== strpos($ua, 'au-mic')
		|| false !== strpos($ua, 'alcatel')
		|| false !== strpos($ua, 'ericy')
		|| false !== strpos($ua, 'vodafone/')
		|| false !== strpos($ua, 'wap1.')
		|| false !== strpos($ua, 'wap2.')
		|| false !== strpos($ua, 'portable')
		;
	}
/*
	public static function is_bot($where=false)
	{
		static $bot = null;
		if (null !== $bot) { return $bot; }
		# Identify bot by UA
		$SQL = \Poodle::getKernel()->SQL;
		$result = $SQL->query('SELECT agent_name, agent_detect FROM '.$SQL->TBL->security_agents
			.($where ? ' WHERE agent_name LIKE \''.$where.'%\'' : '')
			.' ORDER BY agent_name');
		while ($row = $result->fetch_row())
		{
			$row[1] = trim($row[1]);
			if (empty($row[1])) { continue; }
			if (false !== stripos($_SERVER['HTTP_USER_AGENT'], $row[1])) {
				$bot = $row[0];
			} else if ($bot && !$where) {
				break;
			}
		}
		$result->free();
		return ($bot === false) ? false : array('browser'=>$bot, 'engine'=>'bot');
	}
*/
}
