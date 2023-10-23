<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource\Admin;

class Resources extends \Poodle\Resource\Admin
{

	public
		$title = 'Content',
		$allowed_methods = array('GET','HEAD','POST');

	function __construct()
	{
		parent::__construct();
		if (isset(\Poodle::$PATH[1]) && ctype_digit(\Poodle::$PATH[1])) {
			$this->id = max(0, (int)\Poodle::$PATH[1]);
		}
	}

	public static function getChildren($parent_id=0, $path = '/', array $options=array())
	{
		$SQL = \Poodle::getKernel()->SQL;

		$l10n  = '';
		$where = "(resource_parent_id={$parent_id}". ($parent_id ? '' : ' OR resource_parent_id=1') . ")";
		if (empty($options['showhidden'])) {
			$where .= ' AND (resource_type_flags IS NULL OR NOT resource_type_flags & 1)';
		}
		if (empty($options['showexpired'])) {
			$where .= ' AND (resource_etime=0 OR resource_etime>UNIX_TIMESTAMP())';
		}
		if (!empty($options['l10n'])) {
			$l10n = ' AND l10n_id='.(int)$options['l10n'];
		}
		if (!$parent_id) {
			$where = "resource_id = 1 OR ({$where})";
		}

		return $SQL->query("SELECT
			resource_id id,
			resource_uri uri,
			resource_type_id type_id,
			resource_ctime ctime,
			resource_ptime ptime,
			resource_etime etime,
			identity_id
			,CASE WHEN resource_id=1 THEN '[home]' ELSE REPLACE(resource_uri,'{$path}','') END name
			,resource_type_cssclass class
			,(SELECT COUNT(resource_id) FROM {$SQL->TBL->resources_data} rd WHERE rd.resource_id = r.resource_id {$l10n}) revisions
			,CASE WHEN resource_id=1
				THEN 0
				ELSE (SELECT resource_id FROM {$SQL->TBL->resources} sr WHERE sr.resource_parent_id = r.resource_id LIMIT 1)
			END has_children
		FROM {$SQL->TBL->resources} r
		LEFT JOIN {$SQL->TBL->resource_types} rt USING (resource_type_id)
		WHERE {$where}
		ORDER BY resource_uri");
	}

	public function GET()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;

		if (isset($_GET['cleanup']) && $K->IDENTITY->inGroup(4)) {
			$result = $SQL->query('SELECT resource_id, MAX(resource_mtime), l10n_id
			FROM '.$SQL->TBL->resources_data.'
			GROUP BY resource_id, l10n_id
			HAVING 1<COUNT(resource_id)');
			while ($row = $result->fetch_row()) {
				$SQL->exec('DELETE FROM '.$SQL->TBL->resources_data.'
				WHERE resource_id='.$row[0].'
				  AND resource_mtime<'.$row[1].'
				  AND l10n_id='.$SQL->quote($row[2]));
			}
			\Poodle\Report::error('Old content revisions removed', 'Old content revisions removed');
		}

		$pid = 0;
		$path = '/';

		if (isset(\Poodle::$PATH[1]) && ctype_digit(\Poodle::$PATH[1])) {
			if ('getChildren' === $_GET['tree']) {
				$uri = $SQL->uFetchRow("SELECT resource_uri FROM {$SQL->TBL->resources} WHERE resource_id={$this->id}");
				if ($uri) $path = rtrim($uri[0], '/').'/';
				$pid = $this->id;
			}
			else
			{
				return $this->callResource('GET');
			}
		}

		$this->HEAD();
		$OUT = $K->OUT;

		if (XMLHTTPRequest || 'getChildren' == $_GET['tree']) {
			$resources = self::getChildren($pid, $path, array(
				'showhidden'  => isset($_GET['showhidden']),
				'showexpired' => isset($_GET['showexpired']),
				'l10n'        => $_GET->int('l10n'),
			));
			$ul = array('ul',null);
			while ($r = $resources->fetch_assoc()) {
				$ul[] = array(
					'li',
					array('class'=> $r['has_children']?'unfolds':$r['class']),
					array('a',array('href'=>$r['id']),$r['name']),
					array('span',array('class'=>'details'),
						array('span',array('class'=>'revisions'),$r['revisions']),
//						array('a',array('href'=>'#'),$OUT->L10N['view'])
					)
				);
			}
			header('Content-Type: application/json');
			echo \Poodle::dataToJSON(array('DOM'=>$ul));
			return;
		}

		$OUT->head
			->addCSS('poodle_tree')
			->addCSS('poodle_resource')
			->addScript('poodle_tree')
			->addScript('poodle_tablesort');
		$OUT->title = $OUT->L10N['Contents'];

		$OUT->resource_types = array();
		foreach (Types::getList(0, 0) as $type) {
			if (!($type->flags & \Poodle\Resource\Type::FLAG_HIDDEN) || $type->flags & \Poodle\Resource\Type::FLAG_ADMIN_MENU) {
				$OUT->resource_types[] = $type;
			}
		}

		if (!isset($_GET['q'])
		 && !isset($_GET['recent'])
		 && !isset($_GET['type'])
		 && (isset($_GET['advanced']) || (!isset($_GET['simple']) && $K->IDENTITY->inGroup(4)))
		){
			$file = 'poodle/resource/admin/resources-tree';
			$OUT->resources = self::getChildren($pid, $path, array(
				'showhidden'  => isset($_GET['showhidden']),
				'showexpired' => isset($_GET['showexpired']),
				'l10n'        => $_GET->int('l10n'),
			));
		} else {
			$recent = isset($_GET['recent']) ? $_GET['recent'] : '';

			$file  = 'poodle/resource/admin/resources-list';
			$where = array();

			if (isset($_GET['type'])) {
				$where[] = 'resource_type_id='.(int)$_GET['type'];
			}

			if (in_array($recent, array('ctime','etime','ptime','mtime'))) {
				$file .= '-'.$recent;
				$sort_field = $recent;
				if ('etime' === $recent) {
					$where[] = "resource_{$recent}>0 AND resource_{$recent}<".time();
				} else {
					$where[] = "resource_{$recent}<".time();
				}
				if ('uri' === $_GET->raw('sort', 'field')) {
					$sort_field = 'uri';
				}
				$sort_order = ('asc' === $_GET->raw('sort', 'order')) ? 'ASC' : 'DESC';
			} else {
				$sort_field = 'uri';
				if ('ptime' === $_GET->raw('sort', 'field')) {
					$sort_field = 'ptime';
				}
				$sort_order = ('desc' === $_GET->raw('sort', 'order')) ? 'DESC' : 'ASC';
			}

			$order_uri = parse_url($_SERVER['REQUEST_URI']);
			$query = $_GET->getArrayCopy();
			$query['sort'] = array('field' => '', 'order' => strtolower($sort_order));
			$order_uri = $order_uri['path'].'?'.str_replace('field%5D=&', 'field%5D=%s&', http_build_query($query, '', '&'));
			$OUT->sortorder_fields = array(
				'uri' => array('class' => null, 'href' => str_replace('%s', 'uri', $order_uri)),
				'ctime' => array('class' => null, 'href' => str_replace('%s', 'ctime', $order_uri)),
				'etime' => array('class' => null, 'href' => str_replace('%s', 'etime', $order_uri)),
				'ptime' => array('class' => null, 'href' => str_replace('%s', 'ptime', $order_uri)),
				'mtime' => array('class' => null, 'href' => str_replace('%s', 'mtime', $order_uri))
			);
			$order_uri = str_replace('%s', $sort_field, $order_uri);
			$OUT->sortorder_fields[$sort_field] = array(
				'class' => strtolower($sort_order),
				'href' => ('ASC' === $sort_order) ? str_replace('=asc', '=desc', $order_uri) : str_replace('=desc', '=asc', $order_uri)
			);

			if (isset($_GET['q'])) {
				$q = $SQL->escape_string(mb_strtolower($_GET['q']));
				$where[] = "(resource_uri LIKE '%{$q}%' OR
				r.resource_id IN (SELECT resource_id FROM {$SQL->TBL->resources_data}
					WHERE l10n_id IN (0,1,".$OUT->L10N->id.")
					  AND LOWER(resource_title) LIKE '%{$q}%'))";
			}

			$where = $where ? 'WHERE '.implode(' AND ', $where) : '';

			if ('mtime' === $recent) {
				$OUT->resources = $SQL->query("SELECT
					r.resource_id id,
					u.user_nickname author,
					resource_uri uri,
					resource_ctime ctime,
					resource_ptime ptime,
					resource_etime etime,
					resource_mtime mtime,
					rd.identity_id modifier_identity_id,
					rdu.user_nickname modifier,
					resource_type_name type_name,
					resource_type_label type_label
				FROM {$SQL->TBL->resources} AS r
				LEFT JOIN {$SQL->TBL->resources_data} AS rd ON (rd.resource_id=r.resource_id
					AND resource_mtime = (SELECT MAX(resource_mtime) AS m FROM {$SQL->TBL->resources_data} AS rdt WHERE rdt.resource_id=rd.resource_id/* AND resource_status=2*/)
				)
				LEFT JOIN {$SQL->TBL->users} u ON (u.identity_id=r.identity_id)
				LEFT JOIN {$SQL->TBL->users} rdu ON (rdu.identity_id=rd.identity_id)
				LEFT JOIN {$SQL->TBL->resource_types} USING (resource_type_id)
				{$where}
				ORDER BY resource_{$sort_field} {$sort_order}");
			} else {
				$OUT->resources = $SQL->query("SELECT
					r.resource_id id,
					u.user_nickname author,
					resource_uri uri,
					resource_ctime ctime,
					resource_ptime ptime,
					resource_etime etime,
					(SELECT resource_title
						FROM {$SQL->TBL->resources_data} d
						WHERE d.resource_id = r.resource_id
						  AND l10n_id IN (0,1,".$OUT->L10N->id.")
						ORDER BY l10n_id DESC, resource_mtime DESC LIMIT 1) title,
					resource_type_name type_name,
					resource_type_label type_label
				FROM {$SQL->TBL->resources} AS r
				LEFT JOIN {$SQL->TBL->users} u ON (u.identity_id=r.identity_id)
				LEFT JOIN {$SQL->TBL->resource_types} USING (resource_type_id)
				{$where}
				ORDER BY resource_{$sort_field} {$sort_order}");
			}
		}
		$OUT->leftside_content = $OUT->toString('poodle/resource/admin/resources-leftside');
		$OUT->display($file);
	}

	public function POST()
	{
		if (isset(\Poodle::$PATH[1]) && ctype_digit(\Poodle::$PATH[1])) {
			$this->callResource('POST');
		}
	}

	public function callResource($method)
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		if ($this->id) {
			$row = $SQL->uFetchAssoc("SELECT
				resource_id id,
				resource_uri uri,
				resource_parent_id parent_id,
				resource_ctime ctime,
				resource_ptime ptime,
				resource_etime etime,
				resource_flags flags,
				identity_id creator_identity_id,
				r.resource_bodylayout_id bodylayout_id,
				resource_type_id type_id,
				resource_type_class type_class,
				resource_type_flags type_flags
			FROM {$SQL->TBL->resources} r
			LEFT JOIN {$SQL->TBL->resource_types} USING (resource_type_id)
			WHERE resource_id={$this->id}");
		} else {
			$row = array(
				'type_id'    => $_GET->uint('type') ?: 0,
				'parent_id'  => $_GET->uint('parent') ?: 0,
				'type_class' => null
			);
			$r = $SQL->uFetchRow("SELECT resource_type_class, resource_type_flags FROM {$SQL->TBL->resource_types} WHERE resource_type_id={$row['type_id']}");
			if ($r) {
				$row['type_class'] = $r[0];
				$row['type_flags'] = $r[1];
			}
		}
		if ($row) {
			// Change resource class in admin class, example:
			//     MySite_Comment_Single => MySite_Comment_Admin_Single
			$class = $row['type_class'] ? preg_replace('#^(\\\\?[a-zA-Z0-9]+([_\\\\])[a-zA-Z0-9]+)#','$1$2Admin',$row['type_class']) : '';
			unset($row['type_class']);
			if (!$class || !class_exists($class)) {
//				if ($class) { trigger_error("Class '{$class}' not found"); }
				$class = 'Poodle\\Resource\\Admin\\Basic';
			}

			// Get selected language
			$row['l10n_id'] = $_GET->uint('l10n_id');

			try {
				$K->RESOURCE = new $class($row);
				\Poodle::getKernel()->L10N->load(preg_replace('#^([^_]+_[^_]+).*$#D','$1',$class),true);
			} catch (\Throwable $e) {
				trigger_error($e->getMessage());
				$K->RESOURCE = new \Poodle\Resource\Admin\Basic($row);
			}

			if (in_array($method, $K->RESOURCE->allowed_methods) && method_exists($K->RESOURCE, $method)) {
				$K->RESOURCE->$method();
			} else {
				\Poodle\HTTP\Status::set(405);
				header('Allow: '.implode(', ', $K->RESOURCE->allowed_methods));
				echo $method.' method not allowed';
				exit(2);
			}
		} else {
			throw new \Exception("Resource {$this->id} not found!");
		}
	}
}
