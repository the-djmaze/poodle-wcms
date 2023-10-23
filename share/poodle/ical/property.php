<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://www.kanzaki.com/docs/ical/


	ACTION       in VALARM
	TRIGGER      in VALARM
	DESCRIPTION  in VALARM.dispprop, VALARM.emailprop, VALARM.procprop
	SUMMARY      in VALARM.emailprop
	TZID         in VTIMEZONE
*/

namespace Poodle\ICal;

class PropertyIterator extends \ArrayIterator
{
	protected
		$class,
		$parent;
	function __construct(Component $parent, $class)
	{
		$this->class  = $class;
		$this->parent = $parent;
	}

	public function getValue($ical_format=false)
	{
		$data = array();
		foreach ($this as $prop) {
			$data[] = $prop->getValue($ical_format);
		}
		return $data;
	}

	public function setValue($v)
	{
		$this->append($v);
	}

	public function remove($v)
	{
		foreach ($this as $i => $prop) {
			if ($v === $prop->value()) {
				$this->offsetUnset($i);
			}
		}
	}

	/* ArrayIterator */
	public function append($value)
	{
		foreach ($this as $i => $prop) {
			if ($value === $prop->getValue()) {
				return $prop;
			}
		}
		$prop = new $this->class($this->parent, $value);
		parent::offsetSet(null, $prop);
		return $prop;
	}

	public function offsetSet($index, $value)
	{
		foreach ($this as $i => $prop) {
			if ($value === $prop->getValue()) {
				return;
			}
		}
		if (is_null($index) || !$this->offsetExists($index)) {
			parent::offsetSet($index, new $this->class($this->parent, $value));
		} else {
			$this->offsetGet($index)->setValue($value);
		}
	}
/*
	public void asort ( void )
	public int count ( void )
	public mixed current ( void )
	public array getArrayCopy ( void )
	public void getFlags ( void )
	public mixed key ( void )
	public void ksort ( void )
	public void natcasesort ( void )
	public void natsort ( void )
	public void next ( void )
	public void offsetExists ( string $index )
	public mixed offsetGet ( string $index )
	public void offsetUnset ( string $index )
	public void rewind ( void )
	public void seek ( int $position )
	public string serialize ( void )
	public void setFlags ( string $flags )
	public void uasort ( string $cmp_function )
	public void uksort ( string $cmp_function )
	public string unserialize ( string $serialized )
	public bool valid ( void )
*/

	public function asICS()
	{
		$v = array();
		foreach ($this as $prop) {
			$v[] = $prop->asICS();
		}
		return implode("\n",$v);
	}

	public function asXML()
	{
		$v = '';
		foreach ($this as $prop) {
			$v .= $prop->asXML();
		}
		return $v;
	}
}

/**
 * 4.5 Property
 * http://tools.ietf.org/html/rfc2445#section-4.5
 */
abstract class Property implements \ArrayAccess
{
	protected
		$parent,
		$name,
		$value  = null,
		$params = array();

	static protected
		$multiple_values = false, // TODO: handle multiple
		$known_params    = array();

	function __construct(Component $parent, $value=null)
	{
		$this->setParent($parent);
		$this->name = strtr(str_replace('Poodle\\ICal\\Property_','',get_class($this)), '_', '-');
		if (!is_null($value)) {
			$this->setValue($value);
		}
	}

	public function __toString()
	{
		return (string)$this->getValue();
	}

	public function asICS()
	{
		if (!$this->value) { return null; }
		$v = $this->name;
		foreach ($this->params as $k => $pv) {
			if ('RSVP' === $k) {
				$pv = $pv ? 'TRUE' : 'FALSE';
			}
			$v .= ";{$k}={$pv}";
		}
		return "{$v}:{$this->getValue(1)}";
	}

	// http://tools.ietf.org/html/rfc6321#section-3.4
	public function asXML()
	{
		if (!$this->value) { return null; }
		$n = strtolower($this->name);
		$a = $this->paramsXMLFormatted();

		$t = 'text';
//		if ($this instanceof Property_Binary)     $t = 'binary';
		if ($this instanceof Property_Boolean)    $t = 'boolean';
		if ($this instanceof Property_CalAddress) $t = 'cal-address';
//		if ($this instanceof Property_Date)       $t = 'date';
		if ($this instanceof Property_DateTime)   $t = 'date-time';
		if ($this instanceof Property_DURATION)   $t = 'duration';
		if ($this instanceof Property_Float)      $t = 'float';
		if ($this instanceof Property_Integer)    $t = 'integer';
		if ($this instanceof Property_Period)     $t = 'period';
		if ($this instanceof Property_Recur)      $t = 'recur';
//		if ($this instanceof Property_Text)       $t = 'text';
//		if ($this instanceof Property_Time)       $t = 'time';
		if ($this instanceof Property_Uri)        $t = 'uri';
		if ($this instanceof Property_UTC)        $t = 'date-time';
		if ($this instanceof Property_UTCOffset)  $t = 'utc-offset';

		if (isset($this->params['VALUE'])) $t = strtolower($this->params['VALUE']);

		$a = count($a) ? "<parameters>".implode($a)."</parameters>" : '';
		return "<{$n}>{$a}<{$t}>{$this->getValue(1)}</{$t}></{$n}>";
	}

	protected function paramsXMLFormatted()
	{
		$a = array();
		foreach ($this->params as $k => $v) {
			if ('VALUE'!==$k) {
				$n = strtolower($k);
				if ('rsvp' === $n) {
					$a[$k] = "<{$n}><boolean>".($v ? 'true' : 'false')."</boolean></{$n}>";
				} else {
					$a[$k] = "<{$n}><text>{$v}</text></{$n}>";
				}
			}
		}
		return $a;
	}

	public function getValue($ical_format=false) { return $this->value; }
	abstract public function setValue($v);
//	public function setParam($k, $v) { $this->offsetSet($k, $v); }

	public function setParent(Component $parent)
	{
		$this->parent = $parent;
	}

	# ArrayAccess
	public function offsetExists($k)  { return array_key_exists($k, $this->params); }
	public function offsetGet($k)     { return isset($this->params[$k]) ? $this->params[$k] : null; }
	public function offsetUnset($k)   { unset($this->params[$k]); }
	public function offsetSet($k, $v)
	{
		if ('RSVP' === $k) {
			$v = ($v && 'FALSE' !== $v);
		}
		if (!$v) {
			unset($this->params[$k]);
		} else {
			if (!in_array($k, static::$known_params) && !self::is_xname($k)) {
				trigger_error('Unknown '.get_class($this).' parameter '.$k);
			}
			if (self::is_valid_param_value($k, $v)) {
				$this->params[$k] = $v;
			} else {
				trigger_error('Invalid value for '.get_class($this).' parameter '.$k);
			}
		}
	}

	/**
	 * 4.1 Content Lines
	 * tools.ietf.org/html/rfc2445#section-4.1
	 */
	const
		IANA_TOKEN  = '[a-zA-Z0-9\-]+', # registered iCalendar type
		X_NAME      = '[xX]\-[a-zA-Z0-9\-]+',

		QSAFE_CHAR  = '[^\x00-\x08\x0A-\x1F\x7F"]*',    # quoted-string = DQUOTE *QSAFE-CHAR DQUOTE
		SAFE_CHAR   = '[^\x00-\x08\x0A-\x1F\x7F";:,]*', # paramtext     = *SAFE-CHAR
		VALUE_CHAR  = '[^\x00-\x08\x0A-\x1F\x7F]*',     # value         = *VALUE-CHAR
		PARAM_VALUE = '[^\x00-\x08\x0A-\x1F\x7F";:,]*|"[^\x00-\x08\x0A-\x1F\x7F"]*"', # param-value = paramtext / quoted-string

	# ('.IANA_TOKEN.')=('.PARAM_VALUE.'(?:,(?:'.PARAM_VALUE.'))*)[;:]
		REGEX_PARAM = '#([a-zA-Z0-9\-]+)=([^\x00-\x08\x0A-\x1F\x7F";:,]*|"[^\x00-\x08\x0A-\x1F\x7F"]*"(?:,(?:[^\x00-\x08\x0A-\x1F\x7F";:,]*|"[^\x00-\x08\x0A-\x1F\x7F"]*"))*)[;:]#',

	# ^(IANA_TOKEN)((?:;IANA_TOKEN=PARAM_VALUE(?:,(?:PARAM_VALUE))*)*:)(VALUE_CHAR)$
		REGEX_PROP  = '#^([a-zA-Z0-9\-]+)((?:;[a-zA-Z0-9\-]+=[^\x00-\x08\x0A-\x1F\x7F";:,]*|"[^\x00-\x08\x0A-\x1F\x7F"]*"(?:,(?:[^\x00-\x08\x0A-\x1F\x7F";:,]*|"[^\x00-\x08\x0A-\x1F\x7F"]*"))*)*:)([^\x00-\x08\x0A-\x1F\x7F]*)$#D';

	# UTF-8 use mb_strcut($string, 0, 74, 'UTF-8') ?
	public static function fold($string)   { return substr(chunk_split($string, 74, "\r\n "),0,-3); }
	public static function unfold($string) { return preg_replace('#\r?\n[\t ]#s','',$string); }

	public static function is_xname($name) { return preg_match('#^'.self::X_NAME.'$#D', $name); }

	public static function seconds2duration($s)
	{
		if (!$s) return;
		$m = array('W'=>604800,'D'=>86400,'H'=>3600,'M'=>60,'S'=>0);
		$r = (0>$s?'-':'') . 'P';
		$s = abs($s);
		foreach ($m as $c => $d) {
			$t = floor($i->value / $d);
			if ($t) {
				$r .= $t.$c;
				$s -= ($t * $d);
			}
		}
		return $r;
	}

	public static function duration2seconds($v)
	{
		$v = 0;
		if (is_string($v) && 2 < strlen($v)
		 && preg_match('#^([+\-])?P([0-9]+W)?([0-9]+D)?(?:T([0-9]+H)?([0-9]+M)?([0-9]+S)?)?#', $v, $m))
		{
			$v = (int)$m[6]
			  + ($m[5] * 60)
			  + ($m[4] * 3600)
			  + ($m[3] * 86400)
			  + ($m[2] * 604800);
			if ('-'===$m[1]) $v = 0 - $v;
		}
		return $v;
	}

	public static function is_valid_param_value($name, $value)
	{
		if (!is_string($value)) { return false; }
		$safe_value = '("'.self::QSAFE_CHAR.'"|'.self::SAFE_CHAR.')';
		switch (strtoupper($name))
		{
			case 'ALTREP': return preg_match('#^"'.self::QSAFE_CHAR.'"$#D', $value);
			case 'CN':     return preg_match('#^'.$safe_value.'$#D', $value);
			case 'CUTYPE': return preg_match('#^(INDIVIDUAL|GROUP|RESOURCE|ROOM|UNKNOWN|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $value);

			case 'DELEGATED-FROM':
			case 'DELEGATED-TO':
			case 'MEMBER':
				if (!preg_match('#^"'.self::QSAFE_CHAR.'"(,"'.self::QSAFE_CHAR.'")*$#D', $value)) { return false; }
				preg_match_all('#"([^"]+)"#', $value, $matches, PREG_SET_ORDER);
				foreach ($matches as $match) {
					if (!self::isValidValue($match[1], self::TYPE_CAL_ADDRESS)) { return false; }
				}
				return true;

			case 'DIR':
				if (!preg_match('#^"('.self::QSAFE_CHAR.')"$#D', $value, $match)) { return false; }
				return self::isValidValue($match[1], self::TYPE_URI);

			# If ;VALUE=BINARY then it must be BASE64
			case 'ENCODING': return preg_match('#^(8BIT|BASE64|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $value);
			case 'FMTTYPE':  return preg_match('#^((application|audio|image|message|multipart|text|video)/[a-z0-9+\-\.]+|'.self::X_NAME.')$#Di', $value);
			case 'FBTYPE':   return preg_match('#^(FREE|BUSY|BUSY-UNAVAILABLE|BUSY-TENTATIVE|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $value);
			case 'LANGUAGE': return preg_match('#^'.$safe_value.'(,'.$safe_value.')*)$#D', $value);

			case 'VALUE':    return preg_match('#^(BINARY|BOOLEAN|CAL-ADDRESS|DATE|DATE-TIME|DURATION|FLOAT|INTEGER|PERIOD|RECUR|TEXT|TIME|URI|UTC-OFFSET|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $value);

			case 'PARTSTAT':
				static $partstats = array(
					 # VEVENT / VTODO / VJOURNAL
					 'NEEDS-ACTION',   # Event needs action, DEFAULT
					 'ACCEPTED',       # Event accepted
					 'DECLINED',       # Event declined
					 self::X_NAME,     # Experimental status
					 self::IANA_TOKEN, # Other IANA registered status
					 # VEVENT / VTODO
					 'TENTATIVE',      # Event tentatively
					 'DELEGATED',      # Event delegated
					 # VTODO
					 'COMPLETED',      # To-do completed. COMPLETED property has date/time completed.
					 'IN-PROCESS',     # To-do in process of being completed
				);
				return preg_match('#^('.implode('|',$partstats).')$#D', $value);

			# rangeparam
			case 'RANGE':    return preg_match('#^(THISANDPRIOR|THISANDFUTURE)$#D', $value);
			# trigrelparam
			case 'RELATED':  return preg_match('#^(START|END)$#D', $value);
			# reltypeparam
			case 'RELTYPE':  return preg_match('#^(PARENT|CHILD|SIBLING|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $value);

			case 'ROLE':
				static $roles = array(
					'CHAIR',           # Indicates chair of the calendar entity
					'REQ-PARTICIPANT', # Required
					'OPT-PARTICIPANT', # Optional
					'NON-PARTICIPANT', # For information purposes only
					 self::X_NAME,     # Experimental status
					 self::IANA_TOKEN, # Other IANA role
				);
				return preg_match('#^('.implode('|',$roles).')$#D', $value);

			case 'RSVP':     return self::isValidValue($value, self::TYPE_BOOLEAN);
			case 'SENT-BY':  return self::isValidValue($value, self::TYPE_CAL_ADDRESS);
			# tzidparam
			case 'TZID':     return preg_match('#^/?'.self::SAFE_CHAR.'$#D', $value);
		}
		# xparam
		return (self::is_xname($name) && preg_match('#^'.$safe_value.'(,'.$safe_value.')*$#D', $value));
	}

	/**
	 * 4.3 Property Value Data Types
	 * tools.ietf.org/html/rfc2445#section-4.3
	 */
	const
		TYPE_BINARY      = 1,
		TYPE_BOOLEAN     = 2,
		TYPE_CAL_ADDRESS = 3, # URI
		TYPE_DATE        = 4,
		TYPE_DATE_TIME   = 5,
		TYPE_DURATION    = 6,
		TYPE_FLOAT       = 7,
		TYPE_INTEGER     = 8,
		TYPE_PERIOD      = 9,
		TYPE_RECUR       = 10,
		TYPE_TEXT        = 11,
		TYPE_TIME        = 12,
		TYPE_URI         = 13,
		TYPE_UTC_OFFSET  = 14,

	# tools.ietf.org/html/rfc2445#section-4.3.11
//		TSAFE_CHAR   = SAFE_CHAR, // %x20-21 / %x23-2B / %x2D-39 / %x3C-5B / %x5D-7E / NON-US-ASCII
		ESCAPED_CHAR = '\\\\[nN;,\\\\]';

	public static function isValidValue($value, $type)
	{
		# This branch should only be taken with xname values
		if (is_null($type)) { return true; }

		switch ($type)
		{
			case self::TYPE_BINARY:
				if (!is_string($value) || strlen($value) % 4 != 0) { return false; }
				return preg_match('#^([A-Za-z0-9+/]{4})*(|[A-Za-z0-9+/]{2}(==|[A-Za-z0-9+/]=)$#Di');

			case self::TYPE_BOOLEAN:
				if (is_bool($value)) { return true; }
				if (is_string($value)) {
					$value = strtoupper($value);
					return ('TRUE' === $value || 'FALSE' === $value);
				}
				return false;

			case self::TYPE_CAL_ADDRESS: # tools.ietf.org/html/rfc1738
			case self::TYPE_URI:
				# php.net/manual/en/regexp.reference.php
				if (!is_string($value) || !preg_match('#^([a-z0-9+\-\.]+):(\P{Cc}+)$#Diu', $value, $match)) {
					return false;
				}
				$scheme = strtolower($match[1]);
				$value  = $match[2];
				# tools.ietf.org/html/rfc1738#section-5
				$mail_chars = '[a-z0-9!#$%&\'*+\-/=?^_`{|}~]+';
				$domain = '([\p{L}\p{N}\.\-]+\.)+[a-z]{2,6}';
				$IPv4   = '([0-9]{1,3}\.){3}[0-9]{1,3}';
				$IPv6   = '\[([a-f0-9]{0,4}:){1,7}[a-f0-9]{1,4}\]';
				$login  = '[^:@/]*(:[^:@/]+)?@';
				$host   = "{$domain}|{$IPv4}|{$IPv6}";
				$port   = ":[1-9][0-9]*";
				$schemes = array(
					'www'    => "%^//({$login})?({$host}({$port})?)?(/.*)?$%Diu",
					'http'   => "%^//({$login})?({$host}({$port})?)?(/[^?#]*)(\?[^#]*)?(#.*)?$%Diu",
					'ftp'    => "%^//({$login})?({$host}({$port})?)?(/[^;]*(;type=[aid])?)?$%Diu",
					# RFC 5322
					'mailto' => "@^({$mail_chars}(\.?{$mail_chars})*)\\@(([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)*[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)$@Di",
					'news'   => '.*',
					'nntp'   => "({$host}({$port})?)/[^/]*/[0-9]+",
					'telnet' => "%^//({$login})?{$host}({$port})?/?$%Diu",
					'file'   => "%^//({$host})?/.*$%Diu",
					'urn'    => "%^[a-z0-9][a-z0-9-]{0,31}:[a-z0-9()+,\\-.:=@;\$_!*'\\%/?#]+$%iu",
				);
				if (isset($schemes[$scheme])) {
					if (preg_match($schemes[$scheme], $value, $match)) {
						if ('mailto' === $scheme) {
							return (strlen($match[1]) <= 64 && strlen($match[3]) <= 255);
						}
						return true;
					}
					return false;
				}
//				if ($match = parse_url($value)) { return (!empty($match['scheme']) && !empty($match['path'])); }
				return preg_match($schemes['www'], $value);

			case self::TYPE_DATE: # 20090624
				if (!is_int($value) && !is_string($value)) { return false; }
				return (preg_match('#^([0-9]{4})([0-9]{2})([0-9]{2})$#D', (string)$value, $value)
				    && checkdate($value[2], $value[3], $value[1]));

			case self::TYPE_DATE_TIME: # 20090624T233000
				if (!is_string($value) || strlen($value) < 15) { return false; }
				return (preg_match('#^([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2})([0-9]{2})([0-9]{2})Z?$#D', $value, $value)
				    && checkdate($value[2], $value[3], $value[1])
					&& $value[4] < 24 && $value[5] < 60 && $value[6] <= 60);

			# dur-value
			case self::TYPE_DURATION:
				return 0 !== self::duration2seconds($v);

			case self::TYPE_FLOAT:
				if (is_float($value)) { return true; }
				if (!is_string($value) || '' === $value) { return false; }
				return preg_match('#^[+\-]?[0-9]+(\.[0-9]+)?$#D', $value);

			case self::TYPE_PERIOD:
				if (!is_string($value) || empty($value)) { return false; }
				$parts = explode('/', $value);
				if (count($parts) !== 2) { return false; }
				if (!self::isValidValue($parts[0], self::TYPE_DATE_TIME)) { return false; }
				if (self::isValidValue($parts[1], self::TYPE_DATE_TIME)) {
					# It has to be after the start time, so
					return ($parts[1] > $parts[0]);
				} else if (self::isValidValue($parts[1], self::TYPE_DURATION)) {
					# The period MUST NOT be negative
					return ('-' !== $parts[1][0]);
				}
				return false;

			case self::TYPE_RECUR:
				if (!is_string($value)) { return false; }
				$weekday    = '(MO|TU|WE|TH|FR|SA|SU)';
				$weekdaynum = '[+\-]?[0-9]{1,2}'.$weekday;
				$yeardaynum = '[+\-]?[0-9]{1,3}';
				$regex = array(
				'UNTIL'      => '([0-9]{4})([0-9]{2})([0-9]{2})(T([0-9]{2})([0-9]{2})([0-9]{2}))?',
				'COUNT'      => '[0-9]+',
				'INTERVAL'   => '[0-9]+',
				'BYSECOND'   => '[0-9]{1,2}(,[0-9]{1,2})*',
				'BYMINUTE'   => '[0-9]{1,2}(,[0-9]{1,2})*',
				'BYHOUR'     => '[0-9]{1,2}(,[0-9]{1,2})*',
				'BYDAY'      => "{$weekdaynum}(,{$weekdaynum})*",
				'BYMONTHDAY' => '[+\-]?[0-9]{1,2}(,[+\-]?[0-9]{1,2})*',
				'BYYEARDAY'  => "{$yeardaynum}(,{$yeardaynum})*",
				'BYWEEKNO'   => '[+\-]?[0-9]{1,2}(,[+\-]?[0-9]{1,2})*',
				'BYMONTH'    => '[0-9]{1,2}(,[+\-]?[0-9]{1,2})*',
				'BYSETPOS'   => "{$yeardaynum}(,{$yeardaynum})*",
				'WKST'       => $weekday,
				);

				if (!preg_match('#^FREQ=(SECONDLY|MINUTELY|HOURLY|DAILY|WEEKLY|MONTHLY|YEARLY)((;([A-Z\-]+)=([A-Z0-9+\-]+,?)+)*)$#D', $value, $match))
				{
					return false;
				}
				$freq = $match[1];
/*
				preg_match_all('#;([A-Z]+)=([A-Z0-9,+\-]+)#', $match[2], $match, PREG_SET_ORDER);
				foreach ($match as $part)
*/
				# BYSETPOS is only valid if another BY option is specified
				$parts = array();
				$allow_bysetpos = false;
				$match = preg_split('#[;=]#', $match[2]);
				$c = count($match);
				for ($i=1; $i<$c; ++$i)
				{
					$k = $match[$i++];
					if (isset($parts[$k])) { return false; }
					if (!isset($regex[$k]))
					{
						if (!self::is_xname($name)) { return false; }
						continue;
					}
					$parts[$k] = 1;
					$v = $match[$i];
					if (!preg_match("#^{$regex[$k]}$#D", $v, $values))
					{
						return false;
					}
					switch ($k)
					{
					# It's illegal to have both UNTIL and COUNT appear
					case 'UNTIL':
						if (isset($parts['COUNT'])) { return false; }
						if (!checkdate($values[2], $values[3], $values[1])) { return false; }
						if (!empty($values[4]) && ($value[5] > 23 || $value[6] > 59 || $value[7] > 59)) { return false; }
						break;

					case 'COUNT': if (isset($parts['UNTIL'])) { return false; }
					case 'INTERVAL':
						$v = 0;
						if (empty($v)) { return false; }
						break;

					case 'BYSECOND':
					case 'BYMINUTE':
						$values = explode(',', $v);
						foreach ($values as $t) { if ($t > 59) { return false; } }
						$allow_bysetpos = true;
						break;

					case 'BYHOUR':
						$values = explode(',', $v);
						foreach ($values as $t) { if ($t > 23) { return false; } }
						$allow_bysetpos = true;
						break;

					case 'BYWEEKNO': if ('YEARLY' !== $freq) { return false; }
					case 'BYDAY':
						$values = explode(',', $v);
						foreach ($values as $t) {
							$t = abs((int)$t);
							if (!$t || $t > 53) { return false; }
						}
						$allow_bysetpos = true;
						break;

					case 'BYMONTHDAY':
						$values = explode(',', $v);
						foreach ($values as $t) {
							$t = abs((int)$t);
							if (!$t || $t > 23) { return false; }
						}
						$allow_bysetpos = true;
						break;

					case 'BYYEARDAY':
					case 'BYSETPOS':
						$values = explode(',', $v);
						foreach ($values as $t) {
							$t = abs((int)$t);
							if (!$t || $t > 366) { return false; }
						}
						$allow_bysetpos = true;
						break;

					case 'BYMONTH':
						$values = explode(',', $values[0]);
						foreach ($values as $t) { if ($t < 1 || $t > 12) { return false; } }
						$allow_bysetpos = true;
						break;

					case 'WKST':
						break;
					}
				}
				if (!$allow_bysetpos && !empty($parts['BYSETPOS'])) { return false; }
				return true;

			case self::TYPE_TEXT: # Folded
				return preg_match('#^('.self::SAFE_CHAR.'|[:"]|'.self::ESCAPED_CHAR.')*$#D', $value);

			case self::TYPE_UTC_OFFSET:
				if (!is_string($value)) { return false; }
				if ('-0000' === substr($value,0,5)) { return false;}
				return (preg_match('#^[+\-]([0-9]{2})([0-9]{2})([0-9]{2})?$#D', (string)$value, $value)
					&& $value[1] < 24 && $value[2] < 60 && (empty($value[3]) || $value[3] <= 60));
		}
		throw new \Exception('Incorrect value type: '.$type);
	}
}

abstract class Property_UTC extends Property
{
	protected static $UTC_TZ;

	function __construct(Component $parent, $value=null)
	{
		if (!self::$UTC_TZ) {
			self::$UTC_TZ = new \DateTimeZone('UTC');
		}
		parent::__construct($parent, $value);
	}

	public function getValue($ical_format=false)
	{
		if (!$this->value) return null;
		if ($ical_format) return $this->value->format('Ymd\\THis\\Z');
		return $this->value->getTimestamp();
	}

	public function format($f)
	{
		if (!$this->value) return null;
		return $this->value->format($f);
	}

	public function setValue($v)
	{
		if (!$v) {
			$this->value = null;
			return true;
		}
		if (!$this->value) {
			$this->value = new \DateTime('now', self::$UTC_TZ);
		}
		if (is_string($v) && (
		    preg_match('#([0-9]{4})([0-9]{2})([0-9]{2})T([0-9]{2})([0-9]{2})([0-9]{2})#', $v, $m)
		 || preg_match('#([0-9]{4})-([0-9]{2})-([0-9]{2})[T ]([0-9]{2}):([0-9]{2}):([0-9]{2})#', $v, $m)))
		{
			return (false !== $this->value->setDate($m[1], $m[2], $m[3])
			     && false !== $this->value->setTime($m[4], $m[5], $m[6]));
		}
		else if (is_int($v) || ctype_digit($v)) {
			return false !== $this->value->setTimestamp($v);
		}
		else if ($v instanceof \DateTime) {
			return false !== $this->value->setTimestamp($v->getTimestamp());
		}
		$this->value = null;
		return false;
	}

	public function asICS()
	{
		if ($this->value) return $this->name.":".$this->getValue(1);
	}
}

/**
 * When \Poodle\ICal\Property_DateTime['TZID'] is not set, the time is floating (without time zone)
 * \Poodle\ICal\Property_DateTime['VALUE'] = DATE-TIME/DATE (/PERIOD)
 */
abstract class Property_DateTime extends Property_UTC
{
	static protected
		$known_params = array('VALUE','TZID');

	public function getValue($ical_format=false)
	{
		if ($ical_format && $this->value && 'UTC'!==$this->offsetGet('TZID')) {
			if (isset($this['VALUE']) && 'DATE' === $this['VALUE']) {
				return $this->value->format('Ymd');
			} else {
				return $this->value->format('Ymd\\THis');
			}
		}
		return parent::getValue($ical_format);
	}

	public function setValue($v)
	{
		if (!$v) {
			$this->value = null;
			return true;
		}
		if (!$this->value) {
			$tzid = $this['TZID'] ? new \DateTimeZone($this['TZID']) : self::$UTC_TZ;
			$this->value = new \DateTime('now', $tzid);
		}
		if (is_string($v) && (
		    preg_match('#^([0-9]{4})([0-9]{2})([0-9]{2})(?:T([0-9]{2})([0-9]{2})([0-9]{2})(Z)?)?$#D', $v, $m)
		 || preg_match('#([0-9]{4})-([0-9]{2})-([0-9]{2})(?:[T ]([0-9]{2}):([0-9]{2}):([0-9]{2})(Z)?)?#', $v, $m)))
		{
			if (!empty($m[7])) {
				$this->value->setTimezone(self::$UTC_TZ);
				//$this->value->getTimezone()->getName();
				$this['TZID'] = 'UTC';
			}
			if (false !== $this->value->setDate($m[1], $m[2], $m[3]))
			{
				if (isset($m[6])) {
					unset($this['VALUE']);
					return (false !== $this->value->setTime($m[4], $m[5], $m[6]));
				} else {
					$this['VALUE'] = 'DATE';
					return true;
				}
			}
		}
		else if (is_int($v) || ctype_digit($v)) {
			return false !== $this->value->setTimestamp($v);
		}
		else if ($v instanceof \DateTime) {
			$this->value = $v;
			return true;
		}
		$this->value = null;
		return false;
	}

	public function offsetGet($k)
	{
		$v = parent::offsetGet($k);
		if ($v && 'TZID' === $k && $this->value) {
			return $this->value->getTimezone()->getName();
		}
		return $v;
	}

	public function offsetSet($k, $v)
	{
		if ('TZID' === $k) {
			if ($this->value && $v !== $this->value->getTimezone()->getName()) {
				if (isset($this['VALUE']) && 'DATE' === $this['VALUE']) {
					$t = $this->value->format('Ymd');
				} else {
					$t = $this->value->format('Ymd\\THis');
				}
//				$o = $this->value->getOffset();
				$tzid = $v ? new \DateTimeZone($v) : self::$UTC_TZ;
				$this->value->setTimezone($tzid);
//				$this->value->setTimestamp($this->value->getTimestamp()+$o);
				$this->setValue($t);
			}
		}
		parent::offsetSet($k, $v);
	}

	public function asICS()
	{
		if (!$this->value) { return null; }
		$v = $this->name;
		if (isset($this['VALUE']) && 'DATE-TIME' !== $this['VALUE']) {
			$v .= ";VALUE={$this['VALUE']}";
		}
		$tz = $this->offsetGet('TZID');
		if ($tz && 'UTC' !== $tz) {
			$v .= ";TZID={$tz}";
		}
		$v .= ":".$this->getValue(1);
		return $v;
	}

	protected function paramsXMLFormatted()
	{
		$a = parent::paramsXMLFormatted();
		if ('UTC'===$this->offsetGet('TZID')) { unset($a['TZID']); }
		return $a;
	}
}

abstract class Property_Integer extends Property
{
	protected
		$value = 0;

	public function setValue($v)
	{
		$this->value = min(2147483647,max(0, (int)$v));
		return true;
	}
}

abstract class Property_Text extends Property
{
	public function setValue($v)
	{
		$v = preg_replace('#\R#','\n',$v);
		if (is_null($v) || self::isValidValue($v, self::TYPE_TEXT)) {
			$this->value = $v;
			return true;
		}
		$this->value = null;
		return false;
	}

	public function getValue($ical_format=false)
	{
		if (!$this->value) return null;
		return $ical_format ? $this->value : str_replace('\\n', "\n", $this->value);
	}
}

//abstract class Property_Binary extends Property {}
//abstract class Property_Boolean extends Property {}

abstract class Property_CalAddress extends Property
{
	public function setValue($v)
	{
		if (is_null($v) || self::isValidValue($v, self::TYPE_CAL_ADDRESS)) {
			$this->value = $v;
			return true;
		}
		$this->value = null;
		return false;
	}
}

//abstract class Property_Date extends Property {}
//abstract class Property_Duration extends Property {}
abstract class Property_Float extends Property {}
abstract class Property_Period extends Property {}

abstract class Property_Recur extends Property
{
	public function setValue($v)
	{
		if (is_null($v) || self::isValidValue($v, self::TYPE_RECUR)) {
			$this->value = $v;
			return true;
		}
		$this->value = null;
		return false;
	}
}

//abstract class Property_Text extends Property {}
//abstract class Property_Time extends Property {}

abstract class Property_Uri extends Property
{
	public function setValue($v)
	{
		if (is_null($v) || self::isValidValue($v, self::TYPE_URI)) {
			$this->value = $v;
			return true;
		}
		$this->value = null;
		return false;
	}
}

abstract class Property_UTCOffset extends Property
{
	public function setValue($v)
	{
		$this->value = $v;
		return true;
	}
}

/**
 * 4.8.1.1 Attachment
 * ATTACH<fmttypeparam|xparam>:uri (DEFAULT)
 * ATTACH<fmttypeparam|xparam>;ENCODING=BASE64;VALUE=BINARY:binary
 */
class Property_ATTACH extends Property
{
	static protected
		$known_params = array('FMTTYPE','ENCODING','VALUE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VALARM)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		if (is_null($v) || self::isValidValue($v, self::TYPE_URI)) {
			$this->value = $v;
			unset($this['VALUE']);
			unset($this['ENCODING']);
			return true;
		} else
		if (self::isValidValue($v, self::TYPE_BINARY)) {
			$this->value = $v;
			$this['VALUE'] = 'BINARY';
			$this['ENCODING'] = 'BASE64';
		}
		$this->value = null;
		return false;
	}

	/*
	# If ;VALUE=BINARY then it must be BASE64
	case 'ENCODING': return preg_match('#^(8BIT|BASE64|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $value);
	case 'FMTTYPE':  return preg_match('#^((application|audio|image|message|multipart|text|video)/[a-z0-9+\-\.]+|'.self::X_NAME.')$#Di', $value);
	*/
}

/**
 * 4.8.1.2 Categories
 * CATEGORIES<languageparam|xparam>:text(,text)*
 */
class Property_CATEGORIES extends Property_Text
{
	static protected
		$known_params = array('LANGUAGE'),
		$multiple_values = true;

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.1.3 Classification
 * CLASS<xparam>:<PUBLIC|PRIVATE|CONFIDENTIAL|iana-token|x-name>
 */
class Property_CLASS extends Property
{
	protected
		$value  = 'PUBLIC';

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		if (is_null($v) || preg_match('#^(PUBLIC|PRIVATE|CONFIDENTIAL|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $v)) {
			$this->value = $v;
			return true;
		}
		$this->value = null;
		return false;
	}
}

/**
 * 4.8.1.4 Comment
 * COMMENT<altrepparam|languageparam|xparam>:text
 */
class Property_COMMENT extends Property_Text
{
	static protected
		$known_params = array('ALTREP','LANGUAGE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VTIMEZONE)
		 && !($parent instanceof VFREEBUSY)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.1.5 Description
 * DESCRIPTION<altrepparam|languageparam|xparam>:text
 */
class Property_DESCRIPTION extends Property_Text
{
	static protected
		$known_params = array('ALTREP','LANGUAGE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VALARM)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.1.6 Geographic Position
 * GEO<xparam>:float;float
 */
class Property_GEO extends Property_Float
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		$geo = explode(';',$v);
		if (is_null($v) || (2 === count($geo)
		 && self::isValidValue($geo[0], self::TYPE_FLOAT)
		 && self::isValidValue($geo[1], self::TYPE_FLOAT)))
		{
			$this->value = $v;
			return true;
		}
		$this->value = null;
		return false;
	}
}

/**
 * 4.8.1.7 Location
 * LOCATION<altrepparam|languageparam|xparam>:text
 */
class Property_LOCATION extends Property_Text
{
	static protected
		$known_params = array('ALTREP','LANGUAGE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.1.8 Percent Complete
 * PERCENT-COMPLETE<xparam>:integer
 */
class Property_PERCENT_COMPLETE extends Property_Integer
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTODO)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		$this->value = min(100,max(0, (int)$v));
		if (100 == $this->value) {
			if (!isset($parent['completed'])) {
				$parent['completed'] = time();
			}
			$parent['status'] = 'COMPLETED';
		}
		return true;
	}
}

/**
 * 4.8.1.9 Priority
 * PRIORITY<xparam>:integer
 */
class Property_PRIORITY extends Property_Integer
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		$this->value = min(9,max(0, (int)$v));
		return true;
	}
}

/**
 * 4.8.1.10 Resources
 * RESOURCES<altrepparam|languageparam|xparam>:text(,text)*
 */
class Property_RESOURCES extends Property_Text
{
	static protected
		$known_params = array('ALTREP','LANGUAGE'),
		$multiple_values = true;

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.1.11 Status
 * VEVENT  : STATUS<xparam>:(TENTATIVE|CONFIRMED|CANCELLED)
 * VTODO   : STATUS<xparam>:(NEEDS-ACTION|IN-PROCESS|COMPLETED|CANCELLED)
 * VJOURNAL: STATUS<xparam>:(DRAFT|FINAL|CANCELLED)
 */
class Property_STATUS extends Property
{
	function __construct(Component $parent, $value=null)
	{
		parent::__construct($parent, $value);
		if ($parent instanceof VEVENT) {
			$this->value = 'CONFIRMED';
		} else
		if ($parent instanceof VTODO) {
			$this->value = 'NEEDS-ACTION';
		} else
		if ($parent instanceof VJOURNAL) {
			$this->value = 'DRAFT';
		} else {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
	}

	public function setValue($v)
	{
		if ($v === \Poodle\Resource::STATUS_DELETED) { $v = 'CANCELLED'; }

		if ($this->parent instanceof VEVENT) {
			if ($v === \Poodle\Resource::STATUS_PENDING)   { $v = 'TENTATIVE'; }
			if ($v === \Poodle\Resource::STATUS_PUBLISHED) { $v = 'CONFIRMED'; }
			if (preg_match('#^(TENTATIVE|CONFIRMED|CANCELLED)$#D', $v)) {
				$this->value = $v;
				return true;
			}
		} else
		if ($this->parent instanceof VTODO) {
			if ($v === \Poodle\Resource::STATUS_PENDING)   { $v = 'NEEDS-ACTION'; }
			if ($v === \Poodle\Resource::STATUS_PUBLISHED) { $v = 'IN-PROCESS'; }
			if (preg_match('#^(NEEDS-ACTION|IN-PROCESS|COMPLETED|CANCELLED)$#D', $v)) {
				$this->value = $v;
				return true;
			}
		} else
		if ($this->parent instanceof VJOURNAL) {
			if ($v === \Poodle\Resource::STATUS_DRAFT)     { $v = 'DRAFT'; }
			if ($v === \Poodle\Resource::STATUS_PUBLISHED) { $v = 'FINAL'; }
			if (preg_match('#^(DRAFT|FINAL|CANCELLED)$#D', $v)) {
				$this->value = $v;
				return true;
			}
		}
		$this->value = null;
		return false;
	}
}

/**
 * 4.8.1.12 Summary
 * SUMMARY<altrepparam|languageparam|xparam>:text
 */
class Property_SUMMARY extends Property_Text
{
	static protected
		$known_params = array('ALTREP','LANGUAGE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VALARM)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.2.1 Date/Time Completed
 * COMPLETED<xparam>:date-time
 */
class Property_COMPLETED extends Property_UTC
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTODO)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		$ret = parent::setValue($v);
		if ($ret && $this->getValue() && 100 != $parent['percent-completed']) {
			$parent['percent-completed'] = 100;
		}
		return $ret;
	}
}

/**
 * 4.8.2.2 Date/Time End
 * VFREEBUSY MUST be specified in the UTC time format
 * DTEND<VALUE=DATE-TIME/DATE|tzidparam|xparam>:(date-time|date)
 * The value type of this property MUST be the same as the "DTSTART" property,
 * and its value MUST be later in time than the value of the "DTSTART" property.
 * Furthermore, this property MUST be specified as a date with local time if and
 * only if the "DTSTART" property is also specified as a date with local time.
 */
class Property_DTEND extends Property_DateTime
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VFREEBUSY)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		if ($v && isset($parent['duration'])) {
			throw new \Exception('DURATION already set');
		}
		return parent::setValue($v);
	}
}

/**
 * 4.8.2.3 Date/Time Due
 * DUE<VALUE=DATE-TIME/DATE|tzidparam|xparam>:(date-time|date)
 */
class Property_DUE extends Property_DateTime
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTODO)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		if ($v && isset($parent['duration'])) {
			throw new \Exception('DURATION already set');
		}
		return parent::setValue($v);
	}
}

/**
 * 4.8.2.4 Date/Time Start
 * VFREEBUSY MUST be specified in the UTC time format
 * DTSTART<VALUE=DATE-TIME/DATE|tzidparam|xparam>:(date-time|date)
 */
class Property_DTSTART extends Property_DateTime
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VTIMEZONE)
		 && !($parent instanceof VFREEBUSY)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.2.5 Duration
 * DURATION<xparam>:duration
 */
class Property_DURATION extends Property
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VFREEBUSY)
		 && !($parent instanceof VALARM)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function getValue($ical_format=false)
	{
		if (!$this->value) return null;
		if ($ical_format) return self::seconds2duration($this->value);
		return $this->value;
	}

	public function setValue($v)
	{
		if (is_string($v) && !ctype_digit($v)) {
			$v = self::duration2seconds($v);
		}
		$v = (int)$v;
		if ($v && isset($parent['due'])) {
			throw new \Exception('DUE already set');
		}
		$this->value = (0<$v ? $v : null);
		return true;
	}
}

/**
 * 4.8.2.6 Free/Busy Time
 * Time MUST be specified in the UTC time format
 * FREEBUSY<fbtypeparam|xparam>:period(,period)*
 */
class Property_FREEBUSY extends Property_Period
{
	static protected
		$known_params = array('FBTYPE'),
		$multiple_values = true;

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VFREEBUSY)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		$periods = explode(',',$v);
		if (1>count($periods)) { $v = null; }
		else {
			foreach ($periods as $period) {
				if (!self::isValidValue($geo[0], self::TYPE_PERIOD))
					return false;
			}
		}
		$this->value = $v;
		return true;
	}
}

/**
 * 4.8.2.7 Time Transparency
 * TRANSP<xparam>:(OPAQUE|TRANSPARENT)
 */
class Property_TRANSP extends Property
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function getValue($ical_format=false)
	{
		return $ical_format ? $this->value : ('TRANSPARENT' === $this->value);
	}

	public function setValue($v)
	{
		$this->value = ($v && 'OPAQUE' !== $v) ? 'TRANSPARENT' : null;
		return true;
	}
}

/**
 * 4.8.3.1 Time Zone Identifier
 * TZID<xparam>:text
 */
class Property_TZID extends Property_Text
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTIMEZONE)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.3.2 Time Zone Name
 * TZNAME<languageparam|xparam>:text
 */
class Property_TZNAME extends Property_Text
{
	static protected
		$known_params = array('LANGUAGE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTIMEZONE)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.3.3 Time Zone Offset From
 * TZOFFSETFROM<xparam>:utc-offset
 */
class Property_TZOFFSETFROM extends Property_UTCOffset
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTIMEZONE)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.3.4 Time Zone Offset To
 * TZOFFSETTO<xparam>:utc-offset
 */
class Property_TZOFFSETTO extends Property_UTCOffset
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTIMEZONE)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.3.5 Time Zone URL
 * TZURL<xparam>:uri
 */
class Property_TZURL extends Property_Uri
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VTIMEZONE)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.4.1 Attendee
 * ATTENDEE<cutypeparam|memberparam|roleparam|partstatparam|rsvpparam|deltoparam|delfromparam|sentbyparam|cnparam|dirparam|languageparam|xparam>:cal-address
 */
class Property_ATTENDEE extends Property_CalAddress
{
	static protected
		$known_params = array('CUTYPE','MEMBER','ROLE','PARTSTAT','RSVP','DELEGATED-TO','DELEGATED-FROM','SENT-BY','CN','DIR','LANGUAGE');
}

/**
 * 4.8.4.2 Contact
 * CONTACT<altrepparam|languageparam|xparam>:text
 */
class Property_CONTACT extends Property_Text
{
	static protected
		$known_params = array('ALTREP','LANGUAGE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VFREEBUSY)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.4.3 Organizer
 * ORGANIZER<cnparam|dirparam|sentbyparam|languageparam|xparam>:cal-address
 */
class Property_ORGANIZER extends Property_CalAddress
{
	static protected
		$known_params = array('CN','DIR','SENT-BY','LANGUAGE');
}

/**
 * 4.8.4.4 Recurrence ID
 * RECURRENCE-ID<VALUE=DATE-TIME/DATE|tzidparam|rangeparam|xparam>:(date-time|date)
 */
class Property_RECURRENCE_ID extends Property_DateTime
{
	static protected
		$known_params = array('VALUE','TZID','RANGE');
}

/**
 * 4.8.4.5 Related To
 * RELATED-TO<reltypeparam|xparam>:text
 */
class Property_RELATED_TO extends Property_Text
{
	static protected
		$known_params = array('RELTYPE');

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.4.6 Uniform Resource Locator
 * URL<xparam>:uri
 */
class Property_URL extends Property_Uri
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VFREEBUSY)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.4.7 Unique Identifier
 * UID<xparam>:text
 */
class Property_UID extends Property_Text
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VFREEBUSY)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.5.1 Exception Date/Times
 * EXDATE<VALUE=DATE-TIME/DATE|tzidparam|xparam>:(date-time|date(,date-time|date)*)
 */
class Property_EXDATE extends Property_DateTime
{
	static protected
		$multiple_values = true;
}

/**
 * 4.8.5.2 Exception Rule
 * EXRULE<xparam>:recur
 */
class Property_EXRULE extends Property_Recur
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.5.3 Recurrence Date/Times
 * RDATE<VALUE=DATE-TIME/DATE/PERIOD|tzidparam|xparam>:(date-time|date|period(,date-time|date|period)*)
 */
class Property_RDATE extends Property_DateTime
{
	static protected
		$multiple_values = true;

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VTIMEZONE)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		if (self::isValidValue($v, self::TYPE_PERIOD)) {
			$this->value = $v;
			$this['VALUE'] = 'PERIOD';
			return true;
		}
		return parent::setValue($v);
	}
}

/**
 * 4.8.5.4 Recurrence Rule
 * RRULE<xparam>:recur
 */
class Property_RRULE extends Property_Recur
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VTIMEZONE)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.6.1 Action
 * ACTION<xparam>:(AUDIO|DISPLAY|EMAIL|PROCEDURE|iana-token|x-name)
 */
class Property_ACTION extends Property
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VALARM)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function setValue($v)
	{
		if (preg_match('#^(AUDIO|DISPLAY|EMAIL|PROCEDURE|'.self::X_NAME.'|'.self::IANA_TOKEN.')$#D', $v)) {
			$this->value = $v;
			return true;
		}
		return false;
	}
}

/**
 * 4.8.6.2 Repeat Count
 * REPEAT<xparam>:integer
 */
class Property_REPEAT extends Property_Integer
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VALARM)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.6.3 Trigger
 * TRIGGER<VALUE=DURATION|trigrelparam|xparam>:dur-value (default)
 * TRIGGER<VALUE=DATE-TIME|xparam>:date-time
 */
class Property_TRIGGER extends Property
{
	static protected
		$known_params = array('VALUE','RELATED');

	function __construct(VALARM $parent, $value=null)
	{
		parent::__construct($parent, $value);
		$this['VALUE'] = 'DURATION';
	}

	public function setParent(Component $parent)
	{
		if (!($parent instanceof VALARM)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}

	public function getValue($ical_format=false)
	{
		if (!$this->value) return null;
		if ($ical_format) {
			if ('DURATION' === $this['VALUE']) {
				return self::seconds2duration($this->value);
			}
			return null;
		}
		return $this->value;
	}

	public function setValue($v)
	{
		if (is_string($v) && !ctype_digit($v)) {
			$v = self::duration2seconds($v);
			if ($v) { $this['VALUE'] = 'DURATION'; }
		}
		$v = (int)$v;
		$this->value = ($v ? $v : null);
		return true;
	}
}

/**
 * 4.8.7.1 Date/Time Created
 * CREATED<xparam>:date-time
 */
class Property_CREATED extends Property_UTC
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.7.2 Date/Time Stamp
 * DTSTAMP<xparam>:date-time
 */
class Property_DTSTAMP extends Property_UTC
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VFREEBUSY)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.7.3 Last Modified
 * LAST-MODIFIED<xparam>:date-time
 */
class Property_LAST_MODIFIED extends Property_UTC
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		 && !($parent instanceof VTIMEZONE)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.7.4 Sequence Number
 * SEQUENCE<xparam>:integer
 */
class Property_SEQUENCE extends Property_Integer
{
	public function setParent(Component $parent)
	{
		if (!($parent instanceof VEVENT)
		 && !($parent instanceof VTODO)
		 && !($parent instanceof VJOURNAL)
		) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
		parent::setParent($parent);
	}
}

/**
 * 4.8.8.1 Non-standard Properties
 * X-[a-zA-Z0-9\-]+<languageparam|xparam>:text
 */
class Property_X extends Property_Text
{
	static protected
		$known_params = array('LANGUAGE');
}

/**
 * 4.8.8.2 Request Status
 * REQUEST-STATUS<languageparam|xparam>:[0-9]+(\.[0-9]+)*
 */
class Property_REQUEST_STATUS extends Property
{
	static protected
		$known_params = array('LANGUAGE');

	public function setValue($v)
	{
	}
}
