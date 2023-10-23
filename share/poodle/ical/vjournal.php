<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\ICal;

class VJOURNAL extends Component
{
	protected
		$ical_properties = array(
		// The following are REQUIRED, but MUST NOT occur more than once.
		'DTSTAMP' => null,
		'UID' => null,

		// The following are optional, but MUST NOT occur more than once
		'CLASS' => null,
		'CREATED' => null,
		'DESCRIPTION' => null,
		'DTSTART' => null,
		'LAST-MODIFIED' => null,
		'ORGANIZER' => null,
		'RECURRENCE-ID' => null,
		'SEQUENCE' => null,
		'STATUS' => null,
		'SUMMARY' => null,
		'URL' => null,

		// the following are optional, and MAY occur more than once
		'ATTACH' => null,
		'ATTENDEE' => null,
		'CATEGORIES' => null,
		'COMMENT' => null,
		'CONTACT' => null,
		'EXDATE' => null,
		'EXRULE' => null,
		'RDATE' => null,
		'RELATED-TO' => null,
		'RRULE' => null,
		);
//		$rstatus;

	function __construct($id=0)
	{
		parent::__construct($data);
		$this['DTSTAMP'] = time();
		$tzid = null;
		parent::__construct($id);
		if ($id) {
		}
	}

	public function isValidICalObject()
	{
		return isset($this['dtstamp'], $this['uid']);
	}
/*
	function __toString()
	{
		$lines = array_merge(array('BEGIN:VEVENT'), self::props2ical());
		$lines[] = 'END:VEVENT';
		return implode("\r\n", $lines);
	}
*/
}
