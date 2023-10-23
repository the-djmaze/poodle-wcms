<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

abstract class BodyLayouts
{

	public static function getLayout($id)
	{
		$id = (int)$id;
		if ($id) {
			$SQL = \Poodle::getKernel()->SQL;
			if (isset($SQL->TBL->view_latest_resources_data)) {
				return $SQL->uFetchAssoc("SELECT
					id,
					creator_identity_id AS identity_id,
					ctime,
					title AS name,
					body,
					mtime
				FROM {$SQL->TBL->view_latest_resources_data}
				WHERE id={$id} AND type_id=12");
			} else {
				return $SQL->uFetchAssoc("SELECT
					r.resource_id AS id,
					r.identity_id,
					r.resource_ctime AS ctime,
					rd.resource_title AS name,
					rd.resource_body AS body,
					rd.resource_mtime AS mtime
				FROM {$SQL->TBL->resources} AS r
				LEFT JOIN {$SQL->TBL->resource_types} AS rt USING (resource_type_id)
				LEFT JOIN {$SQL->TBL->resources_data} AS rd ON (rd.resource_id=r.resource_id
					AND resource_mtime = (SELECT MAX(resource_mtime) AS m FROM {$SQL->TBL->resources_data} AS rdt WHERE rdt.resource_id=rd.resource_id AND rdt.l10n_id=rd.l10n_id AND resource_status=2))
				WHERE r.resource_id={$id} AND r.resource_type_id=12");
			}

		}
		return null;
	}

	public static function getLayoutName($id)
	{
		$id = (int)$id;
		if ($id) {
			$list = self::getList();
			if (isset($list[$id])) {
				return $list[$id]['name'];
			}
		}
		return null;
	}

	public static function getList()
	{
		static $layouts = null;
		if (is_null($layouts)) {
			$SQL = \Poodle::getKernel()->SQL;
			if (isset($SQL->TBL->view_latest_resources_data)) {
				$qr = $SQL->query("SELECT
					id,
					title AS name
				FROM {$SQL->TBL->view_latest_resources_data}
				WHERE type_id=12
				ORDER BY 2 ASC");
			} else {
				$qr = $SQL->query("SELECT
					r.resource_id AS id,
					rd.resource_title AS name
				FROM {$SQL->TBL->resources} AS r
				LEFT JOIN {$SQL->TBL->resource_types} AS rt USING (resource_type_id)
				LEFT JOIN {$SQL->TBL->resources_data} AS rd ON (rd.resource_id=r.resource_id
					AND resource_mtime = (SELECT MAX(resource_mtime) AS m FROM {$SQL->TBL->resources_data} AS rdt WHERE rdt.resource_id=rd.resource_id AND rdt.l10n_id=rd.l10n_id AND resource_status=2))
				WHERE r.resource_type_id=12
				ORDER BY 2 ASC");
			}
			while ($r = $qr->fetch_assoc()) {
				$layouts[$r['id']] = $r;
			}
		}
		return $layouts;
	}

}
