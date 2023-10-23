<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class Search
{
	/**
	 * options:
	 *    limit       : integer
	 *    offset      : integer
	 *    type_names  : array
	 *    type_ids    : array
	 *    body_length : integer
	 *    body_full   : boolean
	 *    no_highlight: boolean
	 *    metadata    : 'all' or array('resource_meta_name', 'resource_meta_name', ...)
	 */
	public static function query($query, array $options=array())
	{
		if (!empty($query))
		{
			$SQL = \Poodle::getKernel()->SQL;
			$like = $SQL->escape_string($query);
			$text = $query;
			$match_t = $SQL->search(array('resource_searchtitle'), $text);
			$text = $query;
			$match = $SQL->search(array('resource_searchtitle', 'resource_searchdata'), $text);

			$where = '';
			if (!empty($options['type_names'])) {
				foreach ($options['type_names'] as $k => $v) {
					$options['type_names'][$k] = $SQL->quote($v);
				}
				$where .= " AND type_name IN (".implode(',',$options['type_names']).")";
			}

			if (!empty($options['type_ids'])) {
				foreach ($options['type_ids'] as $k => $v) {
					$options['type_ids'][$k] = (int)$v;
				}
				$where .= " AND type_id IN (".implode(',',$options['type_ids']).")";
			}

			$limit = 20;
			$offset = 0;
			if (isset($options['limit'] )) { $limit  = max(5, (int)$options['limit']); }
			if (isset($options['offset'])) { $offset = max(0, (int)$options['offset']); }

			if (!empty($options['metadata'])) {
				$mdwhere = "resource_meta_value LIKE '%{$like}%'";
				if (is_array($options['metadata'])) {
					foreach ($options['metadata'] as &$name) {
						$name = $SQL->quote($name);
					}
					$mdwhere = "resource_meta_name IN (".implode(',',$options['metadata']).") AND {$mdwhere}";
				} else
				if ('all' !== $options['metadata']) {
					$mdwhere = null;
				}
				if ($mdwhere) {
					$where .= " AND resource_id IN (SELECT resource_id FROM {$SQL->TBL->resources_metadata} WHERE {$mdwhere})";
				}
			}

			// Store the query that was searched for
			$SQL->query("INSERT INTO {$SQL->TBL->searchqueries} (sq_querystring)
			VALUES ('{$like}')");
			$searchqueriesId = $SQL->insert_id;

			// Store the agent data of this query including the query id
			$SQL->query("INSERT INTO {$SQL->TBL->searchqueryagents} (sq_id, sqa_ip, sqa_sess_id, sqa_time)
			VALUES ({$searchqueriesId}, '".$_SERVER['REMOTE_ADDR']."', '".session_id()."', ".time().")");

			$count = $SQL->uFetchRow("SELECT
				COUNT(*)
			FROM {$SQL->TBL->resources_searchdata} s
			INNER JOIN {$SQL->TBL->view_latest_resources_data} r ON (resource_id=id AND r.l10n_id=s.l10n_id)
			LEFT JOIN {$SQL->TBL->resources_metadata} m ON (m.resource_id=s.resource_id AND m.l10n_id=r.l10n_id AND resource_meta_name='meta-description')
			WHERE ptime<=UNIX_TIMESTAMP()
			  AND (etime=0 OR etime>UNIX_TIMESTAMP())
			  AND (({$match}) OR (title LIKE '%{$like}%' OR resource_searchdata LIKE '%{$like}%'))
			  {$where}")[0];

			$result = $SQL->query("SELECT
				{$match_t} probability,
				{$match} probability_tc,
				id,
				uri,
				s.l10n_id,
				title,
				body,
				resource_meta_value description,
				type_id,
				type_name
			FROM {$SQL->TBL->resources_searchdata} s
			INNER JOIN {$SQL->TBL->view_latest_resources_data} r ON (resource_id=id AND r.l10n_id=s.l10n_id)
			LEFT JOIN {$SQL->TBL->resources_metadata} m ON (m.resource_id=s.resource_id AND m.l10n_id=r.l10n_id AND resource_meta_name='meta-description')
			WHERE ptime<=UNIX_TIMESTAMP()
			  AND (etime=0 OR etime>UNIX_TIMESTAMP())
			  AND (({$match}) OR (title LIKE '%{$like}%' OR resource_searchdata LIKE '%{$like}%'))
			  {$where}
			ORDER BY 1 DESC, 2 DESC
			LIMIT {$limit} OFFSET {$offset}");

			$regex = preg_replace_callback('#"([^"]+)"#', function($m){return strtr($m[1],'|',' ');}, (is_array($text) ? implode('|',$text) : $text));
			$items = array();
			$body_l = empty($options['body_length']) ? 200 : max(50, (int)$options['body_length']);

			while ($row = $result->fetch_assoc())
			{
//				$row['href']  = \Poodle\URI::index($row['uri']);
				$row['title'] = preg_replace("#({$regex})#iu", '<b>$1</b>', htmlspecialchars($row['title']));
				if (empty($options['body_full'])) {
					$row['body'] = trim(preg_replace('#<[^>]+>#s', ' ', $row['body']));
					$row['body'] = preg_replace('#\s+#', ' ', $row['body']);
					$l = mb_strlen($row['body']);
					if ($l > $body_l) {
						$i = $l - $body_l;
						foreach ($text as $t) {
							if (false !== ($p = stripos($row['body'],$t))) {
								$i = min($i, max(0, $p-20));
							}
						}
						if (0 < $i) {
							$i = stripos($row['body'], ' ', $i-1) + 1;
						}
						$row['body'] = mb_substr($row['body'], $i, $body_l);
					}
					$row['body'] = preg_replace('#(&[^;]+)$#Ds', '', $row['body']);
				}
				if (empty($options['no_highlight'])) {
					$row['body'] = preg_replace("#(\p{L}*{$regex}\p{L}*)#iu", '<b>$1</b>', $row['body']);
				}
				$items[] = $row;
			}
			return array(
				'count' => $count,
				'items' => $items,
				'pagination' => new \Poodle\Pagination(null, $count, $offset, $limit)
			);
		}
		return null;
	}
}
