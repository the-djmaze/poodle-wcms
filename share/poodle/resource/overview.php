<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Overview extends \Poodle\Resource
{
	public $allowed_methods = array('GET','HEAD');

	public function GET()
	{
		$type_id = $this->getMetadata('resource_type_id');
		$limit   = $this->getMetadata('overview_limit');
		$offset  = (int)$_GET->uint('offset');

		$OUT = \Poodle::getKernel()->OUT;
		$OUT->items = Search::latestOf($type_id, $limit, $offset);

		if ('rss' === \Poodle::getKernel()->mlf) {
			$this->HEAD();
			$OUT->display('poodle/output/rss-2.0');
		} else {
			$this->HEAD();
			$OUT->head->addLink('alternate', array(
				'href' => \Poodle\URI::abs(\Poodle\URI::index(rtrim($this->uri,'/').'/feed.rss')),
				'type' => 'application/rss+xml'
			));
			$this->display();
		}
	}

	public function HEAD()
	{
		\Poodle\HTTP\Headers::setLastModified($this->mtime);
		\Poodle::getKernel()->OUT->send_headers();
	}

	public static function getTypes()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$qr = $SQL->query("SELECT
			resource_type_name,
			resource_type_label,
			resource_type_id
		FROM {$SQL->TBL->resource_types}
		WHERE NOT resource_type_flags & 1,
		ORDER BY resource_type_label");
		$o = array();
		while ($r = $qr->fetch_row()) {
			$o[] = array(
				'value' => $r[0],
				'label' => $K->OUT->L10N->dbget($r[1]),
			);
		}
		return array('options' => $o);
	}
}
