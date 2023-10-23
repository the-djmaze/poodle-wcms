<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	locale examples: en, en-US, nl, nl-NL
		2*3ALPHA            ; shortest ISO 639 code
		["-" extlang]       ; sometimes followed by extended language subtags

	DB table l10n_translate fields are named 'v_[bcp47]' where [bcp47] is
	lowercase and '_' instead of '-', for example: v_en_us.
	The 'v_' is there to prevent SQL language issues,
	for example the language 'is' is a preserved word (IS NULL).

	https://tools.ietf.org/html/rfc1766
	https://tools.ietf.org/html/rfc3066
	https://tools.ietf.org/html/bcp47
	https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
	https://en.wikipedia.org/wiki/IETF_language_tag
	http://www.unc.edu/~rowlett/units/codes/country.htm
	http://www.w3.org/WAI/ER/IG/ert/iso639.htm
	http://www.loc.gov/standards/iso639-2/
	http://www.unicode.org/onlinedat/countries.html
	https://www.iana.org/assignments/language-subtag-registry
*/

namespace Poodle;

class L10N implements \ArrayAccess
{
	protected
		$id = null,
		$lng = null,
		$default_id = 0,
		$default_lng = '';

	private static
		$ua_lng = null,
		$data = array();

	const
		REGEX = '#^([a-z]{1,3})(-[a-z]{1,8})?$#D';

	function __construct()
	{
		if (\is_null(self::$ua_lng)) {
			$K = \Poodle::getKernel();
			self::$ua_lng = false;
			$lngs = array();

			# get user session language
			if (!empty($_SESSION['Poodle']['lang'])) {
				$lngs[7] = $_SESSION['Poodle']['lang'];
			}

			# developers.facebook.com/docs/opengraph/guides/internationalization
			if (!empty($_SERVER['HTTP_X_FACEBOOK_LOCALE'])) {
				$lngs[2] = $_SERVER['HTTP_X_FACEBOOK_LOCALE'];
			} else if (!empty($_GET['fb_locale'])) {
				$lngs[2] = $_GET['fb_locale'];
			}

			# get user agent languages
			if (!empty(\Poodle::$UA_LANGUAGES)) {
				# split and sort accepted languages by rank (q)
				if (\preg_match_all('#(?:^|,)([a-z\-]+)(?:;\s*q=([0-9.]+))?#', \Poodle::$UA_LANGUAGES, $accepted_languages, PREG_SET_ORDER)) {
					foreach ($accepted_languages as $lang) {
						if (!isset($lang[2])) { $lang[2] = 1; }
						if (2 > $lang[2]) {
							$lngs[\sprintf('%f%d', $lang[2], \rand(0,9999))] = $lang[1];
						}
						else { $lngs[$lang[2]] = $lang[1]; }
					}
				}
			}

			# get default language
			if (\is_object($K) && \is_object($K->CFG)) {
				$lngs[0] = $K->CFG->poodle->l10n_default;
			}

			# check acceptance
			\krsort($lngs);
			foreach ($lngs as $lng) {
				if (self::setGlobalLanguage($lng)) {
					break;
				}
			}
			if (!empty($lngs[9]) && $lngs[9] !== self::$ua_lng) {
				\Poodle\URI::redirect(\str_replace("/{$lngs[9]}/", '/', $_SERVER['REQUEST_URI']));
			}
		}

		$this->__set('lng', self::$ua_lng ?: 'en');
	}

	function __get($key)
	{
		if ('default_id' === $key) {
			if (!$this->default_id) {
				$CFG = \Poodle::getKernel()->CFG;
				$SQL = \Poodle::getKernel()->SQL;
				if ($SQL && isset($SQL->TBL->l10n)
				 && $id = static::getIdByBCP47($CFG->poodle->l10n_default))
				{
					$this->default_id = $id;
				}
			}
			return $this->default_id;
		}
		if ('default_lng' === $key) { return \Poodle::getKernel()->CFG->poodle->l10n_default; }
		if ('ua_lng' === $key) { return self::$ua_lng; }
		if ('id' === $key) {
			if (!\is_int($this->id)) {
				$this->id = 0;
				$SQL = \Poodle::getKernel()->SQL;
				if ($SQL && isset($SQL->TBL->l10n)
				 && $id = static::getIdByBCP47($this->lng))
				{
					$this->id = $id;
				}
			}
			return $this->id;
		}
		if ('lng' === $key) { return $this->lng; }
		if ('array' === $key) { return $this->getArrayCopy(); }
		if ('multilingual' === $key) { return 1 < \count(self::active()); }
		return $this->get($key);
	}

	function __set($key, $value)
	{
		if ('lng' === $key && \preg_match(self::REGEX, $value)) {
			$this->id  = null;
			$this->lng = $value;
			$this->initCore();
		} else if (isset(self::$data[$this->lng][$key])) {
			self::$data[$this->lng][$key] = $value;
		}
	}

	function __toString() { return $this->lng; }

	public function getArrayCopy() : array { return self::$data[$this->lng]; }

	private function initCore() : void
	{
		if (!isset(self::$data[$this->lng])) {
			self::$data[$this->lng] = array();
			$this->load('core');
			if (POODLE_BACKEND) {
				$this->load('admin');
			}
		}
	}

	public static function setGlobalLanguage(string $lng) : bool
	{
		if (\strpos($lng, '-') && !self::active($lng)) {
			$lng = \explode('-', $lng)[0];
		}
		if (!self::active($lng)) {
			return false;
		}

		self::$ua_lng = $lng;

		if (!empty($_SESSION)) {
			if (isset($_SESSION['Poodle']['lang']) && self::$ua_lng !== $_SESSION['Poodle']['lang']) {
				unset($_SESSION['L10N']);
			}
			$_SESSION['Poodle']['lang'] = self::$ua_lng;
		}

		return true;
	}

	public static function active($lng=null)
	{
		$K = \Poodle::getKernel();
		static $languages = array();
		if (!$K || !$K->SQL || !$K->SQL->TBL || !isset($K->SQL->TBL->l10n)) {
			return !!self::getIniFile($lng);
		}
		if (!$languages && (!$K->CACHE || !($languages = $K->CACHE->get('l10n_active')))) {
			$result = $K->SQL->query("SELECT l10n_bcp47, l10n_id FROM {$K->SQL->TBL->l10n} WHERE l10n_active > 0");
			while ($row = $result->fetch_row()) {
				if (self::getIniFile($row[0])) {
					$languages[$row[0]] = (int)$row[1];
				}
			}
			$result->free();
			if (!$languages) { $languages = array('en' => 1); }
			if ($K->CACHE) { $K->CACHE->set('l10n_active', $languages); }
		}
		return \is_null($lng) ? $languages : isset($languages[$lng]);
	}

	public static function getIniFile(string $lng) : ?string
	{
		$file = \Poodle::getFile("poodle/l10n/locales/{$lng}/l10n.ini");
		return \is_readable($file) ? $file : null;
	}

	static $ids = array();

	private static function loadIds() : void
	{
		if (!static::$ids) {
			$K = \Poodle::getKernel();
			if ($K && $K->SQL && isset($K->SQL->TBL->l10n)) {
				try {
					$qr = $K->SQL->query("SELECT l10n_id, l10n_bcp47 FROM {$K->SQL->TBL->l10n}");
				} catch (\Throwable $e) {
					$qr = $K->SQL->query("SELECT l10n_id, l10n_rfc1766 FROM {$K->SQL->TBL->l10n}");
				}
				while ($r = $qr->fetch_row()) {
					$ids[(int)$r[0]] = $r[1];
				}
			}
		}
	}

	public static function getBCP47ByID($l10n_id) : ?string
	{
		static::loadIds();
		return static::$ids[$l10n_id] ?? null;
	}

	public static function getIdByBCP47(string $bcp47) : int
	{
		static::loadIds();
		return \array_search($bcp47, static::$ids, true) ?: 0;
	}

	public function getNameByID($l10n_id) : ?string
	{
		$bcp47 = self::getBCP47ByID($l10n_id);
		return $bcp47 ? $this->dbget('L10N_'.$bcp47) : null;
	}

	public function getActiveList() : array { return $this->getList(1); }

	public function getInactiveList() : array { return $this->getList(0); }

	protected function getList($active) : array
	{
		static $list = array();
		$K = \Poodle::getKernel();
		$cache_key = 'l10n_'.$this->lng.'_'.($active?'':'in').'active';
		if (!isset($list[$cache_key])) { $list[$cache_key] = array(); }
//		if ($K && $K->SQL && (!$K->CACHE || !($list[$cache_key] = $K->CACHE->get($cache_key)))) {
		if ($K && $K->SQL && empty($list[$cache_key])) {
			$result = $K->SQL->query("SELECT l10n_id, l10n_bcp47, t.* FROM {$K->SQL->TBL->l10n}
			LEFT JOIN {$K->SQL->TBL->l10n_translate} t ON (msg_id = 'l10n_'||l10n_bcp47)
			WHERE l10n_active".($active?'>0':'<1')."
			ORDER BY v_".strtr($this->lng,'-','_'));
			while ($row = $result->fetch_assoc()) {
				$list[$cache_key][] = array(
					'id'    => $row['l10n_id'],
					'label' => $row['v_'.strtr($this->lng,'-','_')],
					'title' => $row['v_'.strtr($row['l10n_bcp47'],'-','_')],
					'value' => $row['l10n_bcp47']
				);
			}
//			if ($K->CACHE) { $K->CACHE->set($cache_key, $list[$cache_key]); }
		}
		return $list[$cache_key];
	}

	protected static $loaded_files = array();
	public function load(string $name, bool $skip_error=false) : bool
	{
		if (!$name) {
			if (!$skip_error) {
				\Poodle\Debugger::trigger(sprintf($this->get('_NO_PARAM'), __CLASS__ . '::load()', 'filename'), __FILE__, E_USER_NOTICE);
			}
			return false;
		}

		$name = strtr(strtolower($name),'\\','/');
		if ('exception' === $name) { return false; }

		$lng = $this->lng;
		$path = \strpos($name,'/') ? \explode('/', $name) : \explode('_', $name, 2);
		if (!isset($path[1])) { $path = array('poodle',$path[0]); }
		$path = \array_slice($path, 0, 2);

		$file = "{$lng}/".\implode('/',$path);
		if (\in_array($file,self::$loaded_files)) {
//			\Poodle\Debugger::trigger("L10N::load($name) again", __FILE__, E_USER_NOTICE);
			return true;
		}
		self::$loaded_files[] = $file;

		$files = array();
		while ($lng) {
			$files[] = \implode('/', $path)."/l10n/{$lng}.php";
			if ('poodle' === $path[0]) {
				$files[] = "poodle/l10n/locales/{$lng}/{$path[1]}.php";
			}
			if (!\strpos($name,'/')) {
				$files[] = "{$name}/l10n/{$lng}.php";
			}

			// Also load language files for the root template (e.g. /tpl/[project_name]/l10n/en.php)
			if ('tpl' === $path[0]) {
				$files[] = "tpl/{$path[1]}/l10n/{$lng}.php";
			}
			if (\strpos($lng, '-')) {
				$lng = \explode('-', $lng)[0];
			}
			else if ('en' !== $lng) { $lng = 'en'; }
			else $lng = null;
		}

		foreach ($files as $file) {
			if (\Poodle::getFile($file)) {
				include($file);
				if (isset($LNG))
					break;
			}
		}

		if (!isset($LNG)) {
			if (!$skip_error) {
				\Poodle\Debugger::trigger(\sprintf($this->get('_NO_L10NF'), $name), __FILE__, E_USER_NOTICE);
			}
			return false;
		}

		self::$data[$this->lng] = \array_merge(self::$data[$this->lng], $LNG);
		return true;
	}

	public function get($var, $var2=null)
	{
		if (!\strlen($var)) { return ''; }
		$LNG = &self::$data[$this->lng];
		$cf = false;
		$txt = $var;
		if (!isset($LNG[$txt])) {
			if (!$var2) {
				list($txt,$cf) = self::cfirst($txt);
			}
			if (!isset($LNG[$txt])) {
				\Poodle\Debugger::trigger(sprintf($LNG['_NO_L10NV'], $var), __FILE__, E_USER_NOTICE);
				$LNG[$var] = $var;
				return $var;
			}
		}
		$txt = &$LNG[$txt];
		if (isset($var2)) {
			if (!isset($txt[$var2])) {
				list($var2,$cf) = self::cfirst($var2);
				if (!isset($txt[$var2])) {
					\Poodle\Debugger::trigger(sprintf($LNG['_NO_L10NV'], $var.']['.$var2), __FILE__, E_USER_NOTICE);
					return ($cf ? $cf($var2) : $var2);
				}
			}
			$txt = &$txt[$var2];
		}
		return $cf ? \Poodle\Unicode::$cf($txt) : $txt;
	}

	protected static function cfirst(string $str) : array
	{
		$i = \ord($str);
		if ($i >= 0x41 && $i <= 0x5A)
			return array(\lcfirst($str), 'ucfirst');
		if ($i >= 0x61 && $i <= 0x7A)
			return array(\ucfirst($str), 'lcfirst');
		return array($str, false);
	}

	/*
	 * About Plural array's:
	 * If your language is not compatible with 2 options (default output: 1 comment, 0 comments, 10 comments)
	 * then add a callback function as:	'plural_cb'=>'function_name'
	 * where function_name($number) should return the value index, Russian example:
	 *
	 * $LNG['%d noteboook'] = array('%d тетрадь', '%d тетради', '%d тетрадей', 'plural_cb'=>'ru_plural_noteboook');
	 * function ru_plural_noteboook($n) { return $n%10==1 && $n%100!=11 ? 0 : $n%10>=2 && $n%10<=4 && ($n%100<10 || $n%100>=20) ? 1 : 2; }
	 *
	 * Another example is 'sheep' which is in english 1 sheep, 2 sheep.
	 * In Dutch for example it is: 1 schaap, 2 schapen
	 * This is easily achieved as:
	 *     en: $LNG['%d sheep'] = '%d sheep';
	 *     nl: $LNG['%d sheep'] = array('%d schaap','%d schapen');
	 */
	public function nget($n, $var, $var2=null) : string
	{
		$str = $this->get($var, $var2);
		if (\is_array($str)) {
			$i = (1 == $n ? 0 : 1);
			if (!empty($str['plural_cb']) && \is_callable($str['plural_cb'])) {
				$i = $str['plural_cb']($n);
			}
			unset($str['plural_cb']);
			$str = $str[\min(\max(0,$i),\count($str)-1)];
		}
		return $str;
	}

	public function plural($n, $var, $var2=null) : string
	{
		return \sprintf($this->nget($n, $var, $var2), $n);
	}

	public function sprintf($var, ...$args) : string
	{
		return \sprintf($this->get($var), ...$args);
	}

	public function dbget($msg_id)
	{
		if (!\strlen($msg_id)) {
			return '';
		}
		if (64 < \strlen($msg_id) || \is_numeric($msg_id)) {
			return $msg_id;
		}
		$LNG = &self::$data[$this->lng];
		if (isset($LNG[$msg_id])) {
			return $LNG[$msg_id];
		}
		$id = \mb_strtolower($msg_id);
		if (isset($LNG[$id])) {
			return $LNG[$id];
		}
		list($txt, $cf) = self::cfirst($msg_id);
		if (isset($LNG[$txt])) {
			return \Poodle\Unicode::$cf($txt);
		}
		$SQL = \Poodle::getKernel()->SQL;
		$LNG[$id] = $msg_id;
		if (isset($SQL->TBL->l10n_translate)) {
			$msg = $SQL->uFetchRow('SELECT v_'.\strtr($this->lng,'-','_').', v_en
				FROM '.$SQL->TBL->l10n_translate.' WHERE msg_id='.$SQL->quote($id));
			if ($msg) {
				$LNG[$id] = $msg[0] ?: $msg[1];
				if (empty($LNG[$id])) {
					\Poodle\Debugger::trigger(\sprintf($LNG['_NO_L10NDB'], $msg_id, $this->lng), __FILE__, E_USER_NOTICE);
					$LNG[$id] = $msg_id;
				}
			} else {
				\Poodle\Debugger::trigger(\sprintf($LNG['_NO_L10NDBC'], $msg_id), __FILE__, E_USER_NOTICE);
				$SQL->insert('l10n_translate', array(
					'msg_id' => $id,
					'v_en' => $LNG[$id]
				));
			}
		}
		return $LNG[$id];
	}

	# crop filesize to human readable format
	public function filesizeToHuman($size, int $precision=2) : ?string
	{
		if (!\is_numeric($size)) { return null; }
		$size = \max($size, 0);
		$i = $size ? \floor(\log($size, 1024)) : 0;
		if ($i > 0) { $size /= \pow(1024, $i); }
		else { $precision = 0; }
		return \sprintf($this->get('_FILESIZES', $i), $this->round($size, \max(0, $precision)));
	}

	public function metricLengthToHuman($mm, int $precision=2) : ?string
	{
		if (!\is_numeric($mm)) { return null; }
		$i = $mm ? \min(6, \floor(\log(\abs($mm), 10))) : 0;
		switch ($i)
		{
		case 0:
			break;
		case 6: // km
			$i = 4;
			$mm /= \pow(10, 6);
			break;
		case 5: // hm
		case 4: // dam
			$i = 3; // meter
		default:
			$mm /= \pow(10, $i);
		}
		return \sprintf($this->get('_LENGTHS', 'metric')[$i], $this->round($mm, \max(0, $precision)));
	}

	public function metricWeightToHuman($g, int $precision=2) : ?string
	{
		if (!\is_numeric($g)) { return null; }
		$i = $g ? \min(4, \floor(\log(\abs($g), 1000))) : 0;
		if ($i > 0) { $g /= \pow(1000, $i); }
		return \sprintf($this->get('_WEIGHTS', 'metric')[$i], $this->round($g, \max(0, $precision)));
	}

	# language specific number format
	public function round($number, int $precision=0) : string
	{
		if ($number instanceof \Poodle\Number) {
			return $number->format($precision, $this->get('_seperator', 0), $this->get('_seperator', 1));
		}
		return \number_format(\floatval($number), $precision, $this->get('_seperator', 0), $this->get('_seperator', 1));
	}

	/* Date-Time Methods */

	public static function ISO_d($time=false)  : string { return \gmdate('Y-m-d', ($time?$time:time())); }
	public static function ISO_t($time=false)  : string { return \gmdate('H:i:s', ($time?$time:time())); }
	public static function ISO_dt($time=false) : string { return \gmdate('Y-m-d H:i:s', ($time?$time:time())); }

	public function date(string $format, $time=null, $timezone=null) : string
	{
		if (isset(self::$data[$this->lng]['_time']['formats'][$format])) {
			$format = self::$data[$this->lng]['_time']['formats'][$format];
		}
		$count  = 0;
		$format = \str_replace(array('D', 'l', 'F', 'M'), array('_\Dw', '_\lw', '_\Fn', '_\Mn'), $format, $count);
		$time   = \is_null($time) ? \time() : $time;
		if (!$timezone && \is_numeric($time) && 2147483647 >= $time) {
			$time = \date($format, $time);
		} else {
			$time = new DateTime($time, $timezone ?: \date_default_timezone_get());
			$time = $time->format($format);
		}
		return (0 === $count) ? $time : \preg_replace_callback('#_([DlFM])(\d{1,2})#', array($this, 'date_cb'), $time);
	}
	protected function date_cb($params) { return self::$data[$this->lng]['_time'][$params[1]][(int)$params[2]]; }
/*
	public function strftime($time, $format='%x')
	{
		'%a' => 'D',
		'%A' => 'l',
		'%d' => 'd',
		'%e' => 'j',
		'%j' => 'z',
		'%u' => 'N',
		'%w' => 'w',
		'%U' => '', // Week number of the given year, starting with the first Sunday as the first week => '', // 13 (for the 13th full week of the year)
		'%V' => 'W',
		'%W' => '', // A numeric representation of the week of the year, starting with the first Monday as the first week => '', // 46 (for the 46th week of the year beginning with a Monday)
		'%b' => 'M',
		'%B' => 'F',
		'%h' => 'M',
		'%m' => 'm',
		'%C' => '', // Two digit representation of the century (year divided by 100, truncated to an integer) => '', // 19 for the 20th Century
		'%g' => '', // Two digit representation of the year going by ISO-8601:1988 standards (see %V) => '', // Example: 09 for the week of January 6, 2009
		'%G' => 'o',
		'%y' => 'y',
		'%Y' => 'Y',
		'%H' => 'H',
		'%k' => 'G',
		'%I' => 'h',
		'%l' => 'g',
		'%M' => 'i',
		'%p' => 'A',
		'%P' => 'a',
		'%r' => '', // Same as "%I:%M:%S %p" => '', // Example: 09:34:17 PM for 21:34:17
		'%R' => '', // Same as "%H:%M" => '', // Example: 00:35 for 12:35 AM, 16:44 for 4:44 PM
		'%S' => 's',
		'%T' => '', // Same as "%H:%M:%S" => '', // Example: 21:34:17 for 09:34:17 PM
		'%X' => '', // Preferred time representation based on locale, without the date => '', // Example: 03:59:16 or 15:59:16
		'%z' => '', // The time zone offset. Not implemented as described on Windows. See below for more information. => '', // Example: -0500 for US Eastern Time
		'%Z' => '', // The time zone abbreviation. Not implemented as described on Windows. See below for more information. => '', // Example: EST for Eastern Time
		'%c' => '', // Preferred date and time stamp based on locale => '', // Example: Tue Feb 5 00:45:10 2009 for February 5, 2009 at 12:45:10 AM
		'%D' => '', // Same as "%m/%d/%y" => '', // Example: 02/05/09 for February 5, 2009
		'%F' => '', // Same as "%Y-%m-%d" (commonly used in database datestamps) => '', // Example: 2009-02-05 for February 5, 2009
		'%s' => 'U',
		'%x' => '', // Preferred date representation based on locale, without the time => '', // Example: 02/05/09 for February 5, 2009
		'%n' => "\n", // A newline character ("\n") => '', // ---
		'%t' => "\t", // A Tab character ("\t") => '', // ---
		'%%' => '%', // A literal percentage character ("%")
	}
*/
	public function timeReadable($time, string $format='%x', bool $show_0=false) : string
	{
		if ($time instanceof \DateTime) {
			$time = \time() - $time->getTimestamp();
		}
		$rep  = array();
		$desc = array(
			'%y' => array(31536000, 'years'),
			'%m' => array(2628000, 'months'),
			'%w' => array(604800, 'weeks'),
			'%d' => array(86400, 'days'),
			'%h' => array(3600, 'hours'),
			'%i' => array(60, 'minutes'),
			'%s' => array(1, 'seconds')
		);
		$is_x = (false !== \strpos($format,'%x'));
		foreach ($desc as $k => $s) {
			$val = '';
			if ($is_x || false !== \strpos($format,$k)) {
				$i = \floor($time/$s[0]);
				if ($show_0 || $i > 0) {
					$time -= ($i*$s[0]);
					$val = self::plural($i, '%d '.$s[1]);
					if ($is_x && $i > 0) {
						return \str_replace('%x', $val, $format);
					}
				}
			}
			$rep[$k] = $val;
		}
		return ('%x' === $format) ? '' : \trim(\str_replace(\array_keys($rep), \array_values($rep), $format));
	}

	public function timezones(/*$zone*/) : array
	{
		$tz = \DateTimeZone::listIdentifiers(1023);
		\sort($tz);
		$timezones = array('UTC'=>'UTC');
		foreach ($tz as $v) {
			$m = \explode('/', $v, 2);
			$timezones[$m[0]][''] = $m[0];
			$timezones[$m[0]][$v] = \strtr($m[1], '_', ' ');
		}
		self::$data[$this->lng]['_timezones'] = $timezones;
//		$K->CACHE->set('l10n_time_zones_'.$this->lng, $timezones);
		return self::$data[$this->lng]['_timezones'];
/*
		$str = '';
		foreach (self::$data[$this->lng]['_timezones'] as $location => $area)
		{
			if (is_array($area)) {
				$str .= '<optgroup label="'.$location.'">';
				foreach (self::$data[$this->lng]['_timezones'][$location] as $area) { $str .= self::timezone_option($zone, "$location/$area"); }
				$str .= "</optgroup>\n";
			} else {
				$str .= self::timezone_option($zone, $area);
			}
		}
		return $str;
	}
	private static function timezone_option(&$zone, $area)
	{
		return (($zone == $area) ? '<option selected="selected">' : '<option>').$area.'</option>';
*/
	}

	private static $db_countries;
	private static function loadDBCountries() : void
	{
		if (!self::$db_countries) {
			$SQL = \Poodle::getKernel()->SQL;
			$qr = $SQL->query("SELECT
				country_code,
				country_name,
				country_iso2,
				country_iso3
			FROM {$SQL->TBL->countries}
			ORDER BY country_name ASC");
			self::$db_countries = array();
			while ($c = $qr->fetch_row()) {
				self::$db_countries[$c[0]] = array(
					'value' => $c[0],
					'label' => $c[1],
					'iso2'  => $c[2],
					'iso3'  => $c[3]
				);
			}
		}
	}

	private $countries;
	public function getCountries() : array
	{
		static::loadDBCountries();
		if ('en' == $this->lng) {
			return \array_values(self::$db_countries);
		}
		if (!$this->countries) {
			$this->load('countries');
			$this->countries = array();
			foreach (self::$db_countries as $country) {
				$country['label'] = $this->get($country['label']);
				$this->countries[] = $country;
			}
			\usort($this->countries, function($a,$b){return \strnatcasecmp($a['label'], $b['label']);});
		}
		return $this->countries;
	}

	public function getCountryName(string $code) : string
	{
		if (!$code) {
			return '';
		}
		static::loadDBCountries();
		$this->load('countries');
		return $this->get(self::$db_countries[$code]['label']);
	}

	# ArrayAccess
	public function offsetExists($k)  { return \array_key_exists($k, self::$data[$this->lng]); }
	public function offsetGet($k)     { return $this->__get($k); }
	public function offsetSet($k, $v) { $this->__set($k, $v); }
	public function offsetUnset($k)   {}
}
