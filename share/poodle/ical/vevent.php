<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\ICal;

class VEVENT extends Component
{
	protected
		$db_table = 'resources_ical',
		$ical_properties = array(
		// The following are REQUIRED, but MUST NOT occur more than once.
		'DTSTAMP' => null,
		'UID' => null,

		// The following are optional, but MUST NOT occur more than once
		'CLASS' => null,
		'CREATED' => null,
		'DESCRIPTION' => null,
		'DTSTART' => null,
		'GEO' => null,
		'LAST-MODIFIED' => null,
		'LOCATION' => null,
		'ORGANIZER' => null,
		'PRIORITY' => null,
		'RECURRENCE-ID' => null,
		'SEQUENCE' => null,
		'STATUS' => null,
		'SUMMARY' => null,
		'TRANSP' => null,
		'URL' => null,

		// Either 'dtend' or 'duration' may appear but not both
		'DTEND' => null,
		'DURATION' => null,

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
		'RESOURCES' => null,
		'RRULE' => null,
		);
//		$rstatus;

	function __construct(array $data=array())
	{
		parent::__construct($data);
		$this['DTSTAMP'] = time();
		$tzid = null;
		if ($this->id) {
			$SQL = \Poodle::getKernel()->SQL;
			$r = $SQL->uFetchAssoc("SELECT
				ical_class    CLASS,
				ical_status   STATUS,
				ical_dtstart  DTSTART,
				ical_dtend    DTEND,
				ical_tzid     tzid,
				ical_priority PRIORITY,
				ical_sequence SEQUENCE,
				ical_uid      UID,
				ical_transp   TRANSP
			FROM {$SQL->TBL->{$this->db_table}}
			WHERE resource_id={$this->id}");
			if ($r) {
				$tzid = $r['tzid'];
				unset($r['tzid']);
				foreach ($r as $k => $v) {
					$this[$k] = $v;
				}
			}
		} else {
			$this['UID'] = \Poodle\UUID::generate();
			// group-scheduled calendar component
			$this['CLASS']  = 'PUBLIC';
			$this['STATUS'] = 'TENTATIVE';
		}
		$this->__set('tzid', $tzid);
		$this['DESCRIPTION']   = $this->body;
		$this['SUMMARY']       = $this->title;
		$this['CREATED']       = $this->ctime;
		$this['LAST-MODIFIED'] = $this->mtime;
	}

	function __get($k)
	{
		if ('tzid' === $k) {
			$v = $this['DTSTART']['TZID'];
			return is_null($v) ? false : $v;
		}
		return parent::__get($k);
	}

	function __set($k, $v)
	{
		if ('tzid' === $k) {
			$this['DTSTART']['TZID'] = $v;
			$this['DTEND']['TZID']   = $v;
			return;
		}
		parent::__set($k, $v);
	}

	public function isValidICalObject()
	{
		return isset($this['dtstamp'], $this['uid']);
	}

	public function save()
	{
		$id = $this->id;
		$this->title = $this['SUMMARY']->getValue();
		if (!$this->uri) {
//			$this->uri = $this['UID']->getValue();
		}
		if (parent::save()) {
			$tbl = \Poodle::getKernel()->SQL->TBL->{$this->db_table};

			$data = array(
				'ical_class'     => $this['class'],
				'ical_status'    => $this['status'],
				'ical_dtstart'   => $this['dtstart'],
				'ical_dtend'     => $this['dtend'],
				'ical_tzid'      => $this->__get('tzid'),
				'ical_priority'  => $this['priority'],
				'ical_sequence'  => $this['sequence'],
				'ical_uid'       => $this['uid'],
				'ical_transp'    => $this['transp'],
			);
			$where = array('resource_id' => $this->id);
			if ($tbl->count($where)) {
				++$data['ical_sequence'];
				$tbl->update($data, $where);
			} else {
				$data['resource_id'] = $this->id;
				$tbl->insert($data);
			}

			$this->addRevision(array(
			'l10n_id' => 0,
			'status'  => static::STATUS_PUBLISHED,
			'title'   => (string)$this['SUMMARY']->getValue(),
			'body'    => (string)$this['DESCRIPTION']->getValue(),
			'searchable' => true
			));

			return true;
		}
		return false;
	}

}
