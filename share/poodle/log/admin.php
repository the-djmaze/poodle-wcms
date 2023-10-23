<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	log_id
	log_time
	log_level
	log_type
	identity_id
	log_ip
	log_request_uri
	log_msg
	log_request_method
	log_request_headers
*/

namespace Poodle\LOG;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'LOG',
		$allowed_methods = array('GET','HEAD','POST');

	protected
		$limit = 40;

	public function GET()
	{
		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		$SQL = $K->SQL;

		if (isset(\Poodle::$PATH[1])) {
			if (ctype_digit(\Poodle::$PATH[1])) {
				$level = (int)\Poodle::$PATH[1];
				$OUT->crumbs->append('Level: '.$K->L10N->get('_log_levels', $level), '/admin/poodle_log/'.\Poodle::$PATH[1]);

				if (!empty(\Poodle::$PATH[2])) {
					return $this->displayEntry(\Poodle::$PATH[2]);
				}

				$offset = max(0, $_GET->uint('offset'));
				$result = $SQL->query("SELECT
					log_id,
					log_time,
					log_request_uri,
					log_type
				FROM {$SQL->TBL->log} WHERE log_level={$level}
				ORDER BY log_type DESC, log_time DESC LIMIT {$this->limit} OFFSET {$offset}");
				$items = array();
				while ($row = $result->fetch_row()) {
					$items[] = array(
						'type'        => $K->L10N->get('_log_types', $row[3]),
						'date'        => $K->L10N->date('Y-m-d', $row[1]),
						'request_uri' => $row[2],
						'uri_details' => "/admin/poodle_log/{$level}/{$row[0]}"
					);
				}
				$OUT->logitems = $items;
				$result->free();

				$OUT->logitems_pagination = new \Poodle\Pagination(
					$_SERVER['REQUEST_PATH'].'?offset=${offset}',
					$SQL->count('log', 'log_level='.$SQL->quote($level)), $offset, $this->limit);

				return $this->display('poodle/log/level');
			}

			if ('all' === \Poodle::$PATH[1]) {
				if (!empty(\Poodle::$PATH[2]) && 'level' === \Poodle::$PATH[2]) {
					return $this->displayAll(true);
				}
				return $this->displayAll(false);
			}
		}

		if (!empty(\Poodle::$PATH[1]))
		{
			$type = \Poodle\Base64::urlDecode(\Poodle::$PATH[1]);
			$OUT->crumbs->append($K->L10N->get('_log_types', $type), '/admin/poodle_log/'.\Poodle::$PATH[1]);

			if (!empty(\Poodle::$PATH[2])) {
				return $this->displayEntry(\Poodle::$PATH[2]);
			}

			$levels = $K->L10N->get('_log_levels');
			$offset = max(0, $_GET->uint('offset'));
			$result = $SQL->query("SELECT
				log_id,
				log_time,
				log_request_uri,
				log_level
			FROM {$SQL->TBL->log} WHERE log_type=".$SQL->quote($type)."
			ORDER BY log_level, log_time DESC LIMIT {$this->limit} OFFSET {$offset}");
			$items = array();
			while ($row = $result->fetch_row()) {
				$items[] = array(
					'level'       => $levels[$row[3]],
					'date'        => $K->L10N->date('Y-m-d', $row[1]),
					'request_uri' => $row[2],
					'uri_details' => '/admin/poodle_log/'.\Poodle::$PATH[1].'/'.$row[0]
				);
			}
			$OUT->logitems = $items;
			$result->free();

			$OUT->logitems_pagination = new \Poodle\Pagination(
				$_SERVER['REQUEST_PATH'].'?offset=${offset}',
				$SQL->count('log', 'log_type='.$SQL->quote($type)), $offset, $this->limit);

			return $this->display('poodle/log/type');
		}

		$OUT->loglevels = array();
		$result = $SQL->query("SELECT log_level as level, COUNT(*) as count FROM {$SQL->TBL->log}
		GROUP BY log_level ORDER BY log_level");
		while ($row = $result->fetch_assoc()) {
			$row['uri']   = "/admin/poodle_log/{$row['level']}/";
			$row['level'] = $K->L10N->get('_log_levels', $row['level']);
			$OUT->loglevels[]  = $row;
		}

		$l10n_logtypes = $K->L10N->get('_log_types');
		$OUT->logtypes = array();
		$result = $SQL->query("SELECT log_type as type, COUNT(*) as count FROM {$SQL->TBL->log}
		GROUP BY log_type ORDER BY log_type");
		while ($row = $result->fetch_assoc()) {
			$row['uri']  = '/admin/poodle_log/'.\Poodle\Base64::urlEncode($row['type']).'/';
			if (isset($l10n_logtypes[$row['type']])) {
				$row['type'] = $l10n_logtypes[$row['type']];
			}
			$OUT->logtypes[]  = $row;
		}

		$result->free();

		return $this->display('poodle/log/index');
	}

	public function POST()
	{
		if (isset($_POST['save'])) {
			\Poodle::getKernel()->CFG->set('poodle', 'log_levels', implode(',', $_POST['log_levels']));
		} else if (isset(\Poodle::$PATH[1])) {
			$SQL = \Poodle::getKernel()->SQL;
			if (!empty(\Poodle::$PATH[2]) && isset($_POST['delete'])) {
				$SQL->exec("DELETE FROM {$SQL->TBL->log} WHERE log_id = ".intval(\Poodle::$PATH[2]));
			} else if (isset($_POST['clear'])) {
				$SQL = \Poodle::getKernel()->SQL;
				if (ctype_digit(\Poodle::$PATH[1])) {
					$SQL->exec("DELETE FROM {$SQL->TBL->log} WHERE log_level = ".intval(\Poodle::$PATH[1]));
				} else {
					$SQL->exec("DELETE FROM {$SQL->TBL->log} WHERE log_type = ".$SQL->quote(\Poodle\Base64::urlDecode(\Poodle::$PATH[1])));
				}
			}
		}
		\Poodle\URI::redirect('/admin/poodle_log/');
	}

	protected function displayEntry($id)
	{
		if (!ctype_digit($id)) {
			\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[1].'/', 303);
		}
		$id = (int)$id;
		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		$SQL = $K->SQL;
		$OUT->crumbs->append($K->L10N->get('Log entry').": {$id}", '/admin/poodle_log/'.\Poodle::$PATH[1].'/'.$id);
		$result = $SQL->query("SELECT
			log_id id,
			log_time time,
			log_level level,
			log_type type,
			identity_id,
			log_ip ip,
			log_msg msg,
			log_request_uri request_uri,
			log_request_method request_method,
			log_request_headers request_headers
		FROM {$SQL->TBL->log} WHERE log_id={$id}");
		if ($row = $result->fetch_assoc()) {
			if ($row['identity_id'] > 0 && $member = \Poodle\Identity\Search::byID($row['identity_id'])) {
				$row['identity_nick'] = $member->nickname;
			} else {
				$row['identity_nick'] = 'unknown';
			}
			if ($row['request_headers'])
			{
				$row['request_headers'] = str_replace('; ',";\n\t",$row['request_headers']);
			}
			$row['date'] = $K->L10N->date('DATE_F', $row['time']);
			$row['level'] = $K->L10N->get('_log_levels', $row['level']);

			$OUT->logentry = $row;
		}
		return $this->display('poodle/log/entry');
	}

	protected function displayAll($by_level)
	{
		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		$SQL = $K->SQL;

		$OUT->crumbs->append($K->L10N->get('Date'));

		$levels = $K->L10N->get('_log_levels');
		$offset = max(0, $_GET->uint('offset'));
		$result = $SQL->query("SELECT
			log_id,
			log_time,
			log_request_uri,
			log_type,
			log_level
		FROM {$SQL->TBL->log}
		ORDER BY ".($by_level?'log_level ASC,':'')." log_time DESC LIMIT {$this->limit} OFFSET {$offset}");
		$items = array();
		while ($row = $result->fetch_row()) {
			$items[] = array(
				'level'       => $levels[$row[4]],
				'type'        => $K->L10N->get('_log_types', $row[3]),
				'date'        => $K->L10N->date('Y-m-d', $row[1]),
				'request_uri' => $row[2],
				'uri_details' => "/admin/poodle_log/{$row[4]}/{$row[0]}"
			);
		}
		$OUT->logitems = $items;
		$result->free();

		$OUT->logitems_pagination = new \Poodle\Pagination(
			$_SERVER['REQUEST_PATH'].'?offset=${offset}',
			$SQL->count('log'), $offset, $this->limit);

		return $this->display('poodle/log/list');
	}

}
