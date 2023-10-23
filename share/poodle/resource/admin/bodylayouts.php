<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource\Admin;

class BodyLayouts extends \Poodle\Resource\Admin
{
	public
		$title = 'Bodylayouts',
		$allowed_methods = array('GET','POST');

	public function GET()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;

		if (isset(\Poodle::$PATH[1]) && ctype_digit(\Poodle::$PATH[1]))
		{
			if (\Poodle::$PATH[1]) {
				$r = \Poodle\Resource\BodyLayouts::getLayout(\Poodle::$PATH[1]);
				if (!$r) {
					\Poodle\Report::error(404);
				}
				$OUT->bodylayout = $r;
			} else {
				$OUT->bodylayout = array(
					'name' => '',
					'body' => '<h1 tal:content="title">Title will be placed here</h1><div tal:content="structure body">body content will be placed here automatically</div><div>other text</div>',
				);
			}
		}

		$this->HEAD();
		$OUT->display('poodle/resource/admin/bodylayouts');
	}

	public function POST()
	{
		if (isset(\Poodle::$PATH[1]) && ctype_digit(\Poodle::$PATH[1]))
		{
			$layout_id = (int)\Poodle::$PATH[1];
			if (!$layout_id) {
				$layout_id = self::addLayout();
			}
			self::updateLayoutContent($layout_id, $_POST->text('bodylayout','name'), $_POST->html('bodylayout','body'));
			\Poodle\URI::redirect('/admin/poodle_resource_bodylayouts/'.$layout_id);
		}
		\Poodle\Report::error(404);
	}

	public static function addLayout($title=null, $body=null)
	{
		$K = \Poodle::getKernel();
		$layout_id = $K->SQL->TBL->resources->insert(array(
			'resource_parent_id' => self::getMainId(),
			'resource_type_id'   => 12,
			'resource_uri'   => '/admin/poodle_resource_bodylayouts/'.\Poodle\UUID::generate(),
			'resource_flags' => 15,
			'resource_ctime' => time(),
			'identity_id'    => $K->IDENTITY->id
		),'resource_id');
		self::updateLayoutContent($layout_id, $title, $body);
		return $layout_id;
	}

	public static function updateLayoutContent($layout_id, $title, $body)
	{
		if ($title || $body) {
			$K = \Poodle::getKernel();
			$K->SQL->TBL->resources_data->insert(array(
				'resource_id'    => (int)$layout_id,
				'identity_id'    => $K->IDENTITY->id,
				'resource_mtime' => time(),
				'resource_title' => $title,
				'resource_body'  => $body
			));
		}
	}

	public static function getMainId()
	{
		$SQL = \Poodle::getKernel()->SQL;
		$tbl = $SQL->TBL->resources;
		$layouts_id = $SQL->uFetchRow("SELECT resource_id FROM {$tbl} WHERE resource_uri='/admin/poodle_resource_bodylayouts/'");
		if ($layouts_id) {
			return (int)$layouts_id[0];
		}
		$admin_id = $SQL->uFetchRow("SELECT resource_id FROM {$tbl} WHERE resource_uri='/admin/'");
		$admin_id = (int)$admin_id[0];
		return $tbl->insert(array(
			'resource_parent_id' => $admin_id,
			'resource_type_id'   => 1,
			'resource_uri'       => '/admin/poodle_resource_bodylayouts/',
			'resource_flags'     => 15
		),'resource_id');
	}

}
