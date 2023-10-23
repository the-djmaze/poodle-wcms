<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://www.kanzaki.com/docs/ical/
	vevent | vtodo | vjournal | vfreebusy | vtimezone | valarm
*/

namespace Poodle\ICal;

// Pre-load all property classess
class_exists('Poodle\\ICal\\Property');

abstract class Component extends \Poodle\Resource\Edit
{
	protected
		$db_table = null,
		$ical_properties = array();

	protected static
		$_props_map = array(
		// the following are optional, but MUST NOT occur more than once
			// Change Management Component Properties
			'DTSTAMP'          => 'Poodle\\ICal\\Property_DTSTAMP',
			'CREATED'          => 'Poodle\\ICal\\Property_CREATED',
			'SEQUENCE'         => 'Poodle\\ICal\\Property_SEQUENCE',
			'LAST-MODIFIED'    => 'Poodle\\ICal\\Property_LAST_MODIFIED',    // resources_data.resource_mtime
			// Relationship Component Properties
			'ORGANIZER'        => 'Poodle\\ICal\\Property_ORGANIZER',
			'UID'              => 'Poodle\\ICal\\Property_UID',
			'URL'              => 'Poodle\\ICal\\Property_URL',
			'RECURRENCE-ID'    => 'Poodle\\ICal\\Property_RECURRENCE_ID',
			// Descriptive Component Properties
			'CLASS'            => 'Poodle\\ICal\\Property_CLASS',
			'DESCRIPTION'      => 'Poodle\\ICal\\Property_DESCRIPTION',      // resources_data.resource_body
			'LOCATION'         => 'Poodle\\ICal\\Property_LOCATION',
			'PERCENT-COMPLETE' => 'Poodle\\ICal\\Property_PERCENT_COMPLETE', // 0-100
			'PRIORITY'         => 'Poodle\\ICal\\Property_PRIORITY',         // 0-9, 1-4 = high, 5 = normal, 6-9 = low
			'STATUS'           => 'Poodle\\ICal\\Property_STATUS',
			'SUMMARY'          => 'Poodle\\ICal\\Property_SUMMARY',          // resources_data.resource_title
			// Time Zone Component Properties
			'TZID'             => 'Poodle\\ICal\\Property_TZID',
			'TZNAME'           => 'Poodle\\ICal\\Property_TZNAME',
			'TZOFFSETFROM'     => 'Poodle\\ICal\\Property_TZOFFSETFROM',
			'TZOFFSETTO'       => 'Poodle\\ICal\\Property_TZOFFSETTO',
			'TZURL'            => 'Poodle\\ICal\\Property_TZURL',
			// Date and Time Component Properties
			'COMPLETED'        => 'Poodle\\ICal\\Property_COMPLETED',        // VTODO
			'DTEND'            => 'Poodle\\ICal\\Property_DTEND',            // VEVENT
			'DTSTART'          => 'Poodle\\ICal\\Property_DTSTART',
			'DUE'              => 'Poodle\\ICal\\Property_DUE',              // VTODO
			'DURATION'         => 'Poodle\\ICal\\Property_DURATION',         // DTEND-DTSTART
			'TRANSP'           => 'Poodle\\ICal\\Property_TRANSP',           // VEVENT, OPAQUE/TRANSPARENT
		// the following are optional, and MAY occur more than once
//			'STANDARD'         => 'Poodle\\ICal\\VTIMEZONE_Standard'
//			'DAYLIGHT'         => 'Poodle\\ICal\\VTIMEZONE_Daylight',
//			'VALARM'           => 'Poodle\\ICal\\VALARM',
			// Relationship Component Properties
			'ATTENDEE'         => 'Poodle\\ICal\\Property_ATTENDEE',
			'CONTACT'          => 'Poodle\\ICal\\Property_CONTACT',
			'RELATED-TO'       => 'Poodle\\ICal\\Property_RELATED_TO',
			// Descriptive Component Properties
			'ATTACH'           => 'Poodle\\ICal\\Property_ATTACH',           // \Poodle\Resource\Attachments
			'CATEGORIES'       => 'Poodle\\ICal\\Property_CATEGORIES',       // comma seperated text
			'COMMENT'          => 'Poodle\\ICal\\Property_COMMENT',
			'RESOURCES'        => 'Poodle\\ICal\\Property_RESOURCES',        // comma seperated text
			// Recurrence Component Properties
			'EXDATE'           => 'Poodle\\ICal\\Property_EXDATE',
			'EXRULE'           => 'Poodle\\ICal\\Property_EXRULE',
			'RDATE'            => 'Poodle\\ICal\\Property_RDATE',
			'RRULE'            => 'Poodle\\ICal\\Property_RRULE',
			// Date and Time Component Properties
			'FREEBUSY'         => 'Poodle\\ICal\\Property_FREEBUSY',         // FREEBUSY, VFREEBUSY
		),
		$_multiprops = array('ATTENDEE','CONTACT','RELATED-TO','ATTACH','CATEGORIES','COMMENT','RESOURCES','EXDATE','EXRULE','RDATE','RRULE','FREEBUSY');

	function __get($k)
	{
		if ('ical_status'===$k) {
			return $this['status'];
		}
		return parent::__get($k);
	}

	public function save()
	{
		if (!$this->db_table) {
			throw new \Exception('Save ' . static::class . ' not implemented');
		}
		parent::save();
	}

	public function getICalProperty($k)
	{
		if (isset(static::$_props_map[$k]) && array_key_exists($k, $this->ical_properties)) {
			if (!isset($this->ical_properties[$k])) {
				$class = static::$_props_map[$k];
				if (!class_exists($class)) {
					$class = 'Poodle\\ICal\\Property_X';
				}
				if (in_array($k, static::$_multiprops)) {
					if (!($this->ical_properties[$k] instanceof PropertyIterator)) {
						$this->ical_properties[$k] = new PropertyIterator($this, $class);
					}
				} else {
					if (!($this->ical_properties[$k] instanceof Property)) {
						$this->ical_properties[$k] = new $class($this, $this->ical_properties[$k]);
					}
				}
			}
			return $this->ical_properties[$k];
		}
	}

	abstract public function isValidICalObject();

	# ArrayAccess
	public function offsetExists($k)
	{
		return array_key_exists($k, $this->ical_properties)
		    || array_key_exists(strtoupper($k), $this->ical_properties)
		    || parent::offsetExists($k);
	}

	public function offsetGet($k)
	{
		$o = $this->getICalProperty($k);
		if ($o) { return $o; }
		$o = $this->getICalProperty(strtoupper($k));
		if ($o) { return $o->getValue(); }
		return parent::offsetGet($k);
	}

	public function offsetSet($k, $v)
	{
		$o = $this->getICalProperty(strtoupper($k));
		if ($o instanceof Property) {
			$o->setValue($v);
		} else {
			parent::offsetSet($k, $v);
		}
	}

	public function offsetUnset($k)
	{
		$p = strtoupper($k);
		if ($this->getICalProperty($p) instanceof Property) {
			unset($this->ical_properties[$p]);
		} else {
			parent::offsetUnset($k);
		}
	}

	public function getName()
	{
		if      ($this instanceof VEVENT)    { return 'VEVENT'; }
		else if ($this instanceof VTODO)     { return 'VTODO'; }
		else if ($this instanceof VJOURNAL)  { return 'VJOURNAL'; }
		else if ($this instanceof VFREEBUSY) { return 'VFREEBUSY'; }
		else if ($this instanceof VTIMEZONE) { return 'VTIMEZONE'; }
		else if ($this instanceof VTIMEZONE_Standard) { return 'STANDARD'; }
		else if ($this instanceof VTIMEZONE_Daylight) { return 'DAYLIGHT'; }
		else if ($this instanceof VALARM)    { return 'VALARM'; }
		else { throw new \Exception('Invalid iCalendar object'); }
	}

	public function __toString()
	{
		return $this->asICS();
	}

	public function asICS()
	{
		$name = $this->getName();
		return "BEGIN:{$name}\r\n" . self::formatProps('ics') . "\r\nEND:{$name}\r\n";
	}

	// http://tools.ietf.org/html/rfc6321#section-3.3
	public function asXML()
	{
		$name = strtolower($this->getName());
		return "<{$name}>" . self::formatProps('xml') . "</{$name}>";
	}

	protected function formatProps($type='ics')
	{
		$fn    = 'as'.strtoupper($type);
		$glue  = ('ics'===$type ? "\r\n" : '');
		$lines = array();
		foreach ($this->ical_properties as $k => $v)
		{
			if (isset($v)) {
				if ($v instanceof Property) {
					$v = $v->$fn();
					if ($v) { $lines[] = $v; }
				} else
				if ($v instanceof PropertyIterator) {
					foreach ($v as $p)
					if ($p instanceof Property) {
						$p = $p->$fn();
						if ($p) { $lines[] = $p; }
					}
				}
			}
		}
		return implode($glue, $lines);
	}
}
