<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\ICal;

class VFREEBUSY extends Component
{
	protected
		$ical_properties = array(
		// The following are REQUIRED, but MUST NOT occur more than once.
		'DTSTAMP' => null,
		'UID' => null,

		// The following are optional, but MUST NOT occur more than once
		'CONTACT' => null,
		'DTSTART' => null,
		'DTEND' => null,
		'DURATION' => null,
		'ORGANIZER' => null,
		'URL' => null,

		// Either 'due' or 'duration' may appear but not both
		'DUE' => null,
		'DURATION' => null,

		// the following are optional, and MAY occur more than once
		'ATTACH' => null,
		'COMMENT' => null,
		'FREEBUSY' => null,
		);
//		$rstatus;

	function __construct($id=0)
	{
		parent::__construct($data);
		$this['DTSTAMP'] = time();
		$tzid = null;
		if ($id) {
		}
	}

	public function isValidICalObject()
	{
		return isset($this['dtstamp'], $this['uid']);
	}
}
