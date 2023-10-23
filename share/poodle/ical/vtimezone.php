<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	ftp://ftp.iana.org/tz/tzdata-latest.tar.gz
*/

namespace Poodle\ICal;

class VTIMEZONE extends Component
{
	protected
		$ical_properties = array(
		// The following are REQUIRED, but MUST NOT occur more than once.
		'TZID' => null,

		// The following are optional, but MUST NOT occur more than once
		'LAST-MODIFIED' => null,
		'TZURL' => null,

		// the following are optional, and MAY occur more than once
//		'STANDARD' => null,
//		'DAYLIGHT' => null,
		);

	function __construct($id=0)
	{
		parent::__construct($data);
		if ($id) {
		}
	}

	public function isValidICalObject()
	{
		return isset($this['tzid']);
	}
}

abstract class VTIMEZONE_C extends Component
{
	protected
		$ical_properties = array(
		// The following are REQUIRED, but MUST NOT occur more than once.
		'DTSTART' => null,
		'TZOFFSETTO' => null,
		'TZOFFSETFROM' => null,

		// the following are optional, and MAY occur more than once
		'COMMENT' => null,
		'RDATE' => null,
		'RRULE' => null,
		'TZNAME' => null,
		);

	function __construct(VTIMEZONE $parent)
	{
		if (!($parent instanceof VTIMEZONE)) {
			throw new \InvalidArgumentException('Invalid parent '.get_class($parent));
		}
	}

	public function isValidICalObject()
	{
		return isset($this['DTSTART'], $this['tzoffsetto'], $this['tzoffsetfrom']);
	}
}

class VTIMEZONE_Standard extends VTIMEZONE_C {}
class VTIMEZONE_Daylight extends VTIMEZONE_C {}
