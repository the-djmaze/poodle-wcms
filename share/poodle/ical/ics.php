<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\ICal;

class ICS
{

	public static function fetch($ics_uri)
	{
		$K = \Poodle::getKernel();
		$cache_key = 'Poodle/ICal/ICS/' . sha1($ics_uri);
		$months = $K->CACHE->get($cache_key);
		if (!$months) {
			$months = array();
			$ics = file_get_contents($ics_uri);
			if ($ics && preg_match_all('/BEGIN:VEVENT.*?END:VEVENT/s', $ics, $events)) {
				foreach ($events[0] as $event) {
					if (preg_match_all('/(?:^|\R)(DTSTART|DTEND|DESCRIPTION|LOCATION|SUMMARY)(?:[^:]*):([^\\r\\n]*)/', $event, $props, PREG_SET_ORDER)) {
						$event = array();
						foreach ($props as $prop) {
							$prop[2] = str_replace('\\n', "\n", $prop[2]);
							$event[strtolower($prop[1])] = str_replace('\\', '', $prop[2]);
						}
						if ($event['dtstart'] && $event['dtend']) {
							$event['dtstart'] = \Poodle\Date::createFromNumber($event['dtstart']);
							$event['dtend']   = \Poodle\Date::createFromNumber($event['dtend']);
							$event['dtend']->modify('-1 day');
							$dtend = clone $event['dtend'];
							if ($event['dtend']->isSameDay($event['dtstart'])) {
								$event['dtend'] = false;
							}
							$m = $event['dtstart']->format('Ym');
							$d = $event['dtstart']->format('Ymd') . $event['summary'];
							if (!isset($months[$m])) {
								$months[$m] = array(
									'name' => $K->L10N->date('F Y', $event['dtstart']),
									'events' => array()
								);
							}
							$me = $dtend->format('Ym');
							if ($m < $me) {
								$event['dtend'] = new \Poodle\Date($dtend->format('Y-m-01'));
								$event['dtend']->modify('-1 day');
							}
							$months[$m]['events'][$d] = $event;
							ksort($months[$m]['events']);

							if ($m < $me) {
								$event['dtstart'] = new \Poodle\Date($dtend->format('Y-m-01'));
								$event['dtend'] = $dtend;
								$m = $event['dtstart']->format('Ym');
								$d = $event['dtstart']->format('Ymd') . $event['summary'];
								if (!isset($months[$m])) {
									$months[$m] = array(
										'name' => $K->L10N->date('F Y', $event['dtstart']),
										'events' => array()
									);
								}
								$months[$m]['events'][$d] = $event;
								ksort($months[$m]['events']);
							}
						}
					}
				}
			}
			ksort($months);
			$K->CACHE->set($cache_key, $months, 86400);
		}
		return $months;
	}

	public static function upcoming($ics_uri)
	{
		$today = date('Ym');
		$months = static::fetch($ics_uri);
		foreach (array_keys($months) as $month) {
			if ($month >= $today) {
				break;
			}
			unset($months[$month]);
		}
		return $months;
	}

	public static function past($ics_uri)
	{
		$today = date('Ym');
		$months = array_reverse(static::fetch($ics_uri), true);
		foreach (array_keys($months) as $month) {
			if ($month < $today) {
				break;
			}
			unset($months[$month]);
		}
		return $months;
	}

}
