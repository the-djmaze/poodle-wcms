<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource\Admin;

class Basic extends \Poodle\Resource\Admin
{
	public
		$title = '',
		$allowed_methods = array('GET','POST');

	// Used by resource_types_fields.rtf_attributes
	public static function getResources() : array
	{
		$SQL = \Poodle::getKernel()->SQL;
		$qr = $SQL->query("SELECT
			resource_uri,
			RIGHT(resource_uri,LOCATE('/',REVERSE(resource_uri),1)-1)
		FROM {$SQL->TBL->resources}
		WHERE (resource_etime=0 /*OR resource_etime>UNIX_TIMESTAMP()*/)
		ORDER BY resource_uri");
		$o = array();
		while ($r = $qr->fetch_row()) {
			$o[] = array(
			'value'=>$r[0],
			'label'=>$r[1]?$r[1]:'[home]',
			'class'=>$r[1]?'lvl'.(substr_count($r[0],'/')-1):null
			);
		}
		return array('options'=>$o);

		return array('options'=>$SQL->uFetchAll("SELECT
			resource_uri value,
			RIGHT(resource_uri,LOCATE('/',REVERSE(resource_uri),1)-1) label
		FROM {$SQL->TBL->resources}
		WHERE (resource_etime=0 OR resource_etime)"));
	}

	// Used by resource_types_fields.rtf_attributes
	public static function getPublicResources() : array
	{
		$SQL = \Poodle::getKernel()->SQL;
		$qr = $SQL->query("SELECT
			resource_uri,
			RIGHT(resource_uri,LOCATE('/',REVERSE(resource_uri),1)-1)
		FROM {$SQL->TBL->resources}
		LEFT JOIN {$SQL->TBL->resource_types} USING (resource_type_id)
		WHERE resource_ptime<=UNIX_TIMESTAMP()
		  AND (resource_etime=0 OR resource_etime>UNIX_TIMESTAMP())
		  AND NOT resource_type_flags & 1
		ORDER BY resource_uri");
		$o = array();
		while ($r = $qr->fetch_row()) {
			$o[] = array(
			'value'=>$r[0],
			'label'=>$r[1]?$r[1]:'[home]',
			'class'=>$r[1]?'lvl'.(substr_count($r[0],'/')-1):null
			);
		}
		return array('options'=>$o);
	}

	protected function initOutput()
	{
		$OUT = \Poodle::getKernel()->OUT;

		$this->setUriLists();

		/**
		 * Resource types
		 */
		if ($this->hasFixedType()) {
			$OUT->resource_types = Types::getFixedList($this->type_id);
		} else {
			$OUT->resource_types = Types::getList($this->type_id);
		}

		/**
		 * Resource metadata
		 */
		$this->metadata = $this->getMetadata()->getMergedArrayCopy();

		/**
		 * Permissions
		 */
		$OUT->acl_actions = \Poodle\ACL::getActions();

		/**
		 * Body
		 */
		if (isset($_GET['revision'])) {
			$SQL = \Poodle::getKernel()->SQL;
			$row = $SQL->uFetchRow("SELECT
				resource_title, resource_body, resource_status, rollback_of, identity_id
			FROM {$SQL->TBL->resources_data}
			WHERE resource_id = {$this->id}
			  AND resource_mtime = {$_GET->uint('revision')}
			  AND l10n_id = {$_GET->uint('l10n')}");
			if (!$row) {
				\Poodle\Report::error(404);
			}
			$this->title = $row[0];
			$this->body  = $row[1];
		}
		$this->body = preg_replace('#(href|src|action)="(/[^"/][^"]*)"#', '$1="'.\Poodle::$URI_BASE.'$2"', $this->body);
		$this->body = preg_replace('#(href|src|action)="/"#', '$1="'.\Poodle::$URI_BASE.'/"', $this->body);

		/**
		 * Head
		 */
		$OUT->head
			->addCSS('poodle_resource')
//			->addCSS('poodle_tree')
			->addCSS('poodle_areaselector')
			->addScript('poodle_resource')
			->addScript('poodle_resource_acl')
			->addScript('poodle_resource_attachments')
//			->addScript('poodle_tree')
			->addScriptData('Poodle_Resource.types='.json_encode($OUT->resource_types).';')
			->addScriptData('Poodle_Resource.data='.json_encode($this).';')
//			->addScriptData('Poodle_Resource.timezones='.json_encode($OUT->L10N->timezones()).';')
//			->addScriptData('Poodle_Resource.countries='.json_encode($OUT->L10N->getCountries()).';')
			->addScriptData('Poodle_Resource.ext2mime='.json_encode(\Poodle\Media::mapExtensionsToMime()).';');
		$OUT->title = $OUT->L10N['Content'].': '.$this->uri;
	}

	public function GET()
	{
		if (isset($_GET['rollback'])) {
			if ($this->rollback($_GET['rollback'], $_GET['l10n'])) {
				$this->closeRequest('Rollback succeeded');
			}
			\Poodle\Report::error('Rollback failed');
		}

		$this->HEAD();

		$this->initOutput();

		$OUT = \Poodle::getKernel()->OUT;
		$OUT->crumbs->append($this->uri, '/admin/resources/'.$this->id);
		if (isset($_GET['revision'])) {
			$OUT->crumbs->append("Revision #{$_GET->uint('revision')}");
		}

		if ($this->id) {
			$OUT->display('poodle/resource/admin/default');
		} else {
			$OUT->display('poodle/resource/admin/default-new');
		}
	}

	public function POST()
	{
		$admin_uri = \Poodle\URI::admin("/resources/{$this->id}#resource-attachments");

		if ($this->id) {
			if (isset($_POST['remove_selected_attachments'])) {
				$this->removeAttachments($_POST['attachments']);
				$this->closeRequest('Attachment(s) removed', $admin_uri);
			}

			if (!empty($_POST['attachment_media_item'])) {
				$file = \Poodle\Media\Item::createFromPath($_POST['attachment_media_item']);
				if ($file && $this->addAttachment($file, $_POST['attachment_type_id'], $_POST['attachment_l10n_id'])) {
					$this->attachments->save();
					$this->closeRequest('Attachment added', $admin_uri);
				}
				$this->closeRequest('Failed to add attachment', $admin_uri);
			}
		}

		/**
		 * Process $_POST['resource']
		 */
		foreach ($_POST['resource'] as $k => $v) {
			$this->$k = $v;
		}
		$this->title = $_POST['resource_data']['title'];
		if ($this->save()) {
			/**
			 * Process $_POST['resource_data'] revision
			 */
			$data = $_POST['resource_data'];
			if (array_key_exists('body', $data)) {
				$data['body'] = \Poodle\Input\HTML::fix($data['body']);
				$this->addRevision($data);
			}

			/**
			 * Process $_FILES
			 */
			$metadata = $_POST['resource_metadata'];
			if (false !== strpos($_SERVER['CONTENT_TYPE'],'multipart/form-data')) {
				if (isset($_FILES['resource_attachment'])) {
					$file = $_FILES->getAsFileObject('resource_attachment');
//					$file->validateType(array('text/csv'));
					if ($this->addAttachment($file, $_POST['attachment_type_id'], $_POST['attachment_l10n_id'])) {
						$this->attachments->save();
						$this->closeRequest('Attachment added', $admin_uri);
					} else if (UPLOAD_ERR_NO_FILE != $file->errno) {
						\Poodle\Report::error($file->error);
					}
				}
				if (isset($_FILES['resource_metadata'])) {
					foreach ($_FILES['resource_metadata'] as $l10n_id => $files) {
						foreach ($files as $name => $file) {
							$file = $_FILES->getAsFileObject('resource_metadata',$l10n_id,$name);
							if (!$file->errno) {
								$item = \Poodle\Media\Item::createFromUpload($file);
								if ($item) {
									$metadata[$l10n_id][$name] = $item->file;
								}
							}
						}
					}
				}
			}

			/**
			 * Process $_POST['resource_metadata'][{l10n_id}]
			 */
			$this->setMetadata($metadata);

			/**
			 * Process $_POST['acl'][{group_id}]
			 */
			$this->setACL($_POST['acl']?:array());

			$this->closeRequest();
		}
		header('Content-Type: text/plain');
		print_r($_POST);
	}

	// $ids single integer or array of integers
	public function removeAttachments($ids)
	{
		if (!is_array($ids)) {
			$ids = array($ids);
		}
		if (count($ids)) {
			$SQL = \Poodle::getKernel()->SQL;
			$SQL->exec("DELETE FROM {$SQL->TBL->resources_attachments}
			WHERE resource_id={$this->id}
			  AND resource_attachment_id IN (".implode(',',$ids).")");
		}
	}

	protected function setUriLists()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;

		$OUT->parent_uris = array();
		$OUT->resources_uris = array();
		if (!$this->hasFixedURI()) {
			$where = '';//$this->id ? 'WHERE resource_uri NOT LIKE '.$SQL->quote($this->uri.'%') : '';
			$result = $SQL->query("SELECT
				resource_id id,
				resource_uri uri,
				resource_id || ' ' || resource_uri value,
				resource_type_id type_id
			FROM {$SQL->TBL->resources}
			WHERE NOT resource_flags & ".self::FLAG_SUB_LOCKED."
			ORDER BY resource_uri");
	//		$result = $SQL->query('SELECT page_id, page_uri, REPLACE(page_uri, \'/\', \' \') FROM '.$SQL->TBL->pages.' ORDER BY 3');
			$parent = '/';
			while ($row = $result->fetch_assoc()) {
				$p = preg_replace('#([^/]+)/?$#', '', $row['uri']);
				if ($parent != $p) {
					$c = count($OUT->parent_uris)-1;
					if (-1<$c) {
						$r = &$OUT->parent_uris[$c];
						$r['text'] = str_replace(\Poodle\Tree::LIGHT_V_RIGHT,\Poodle\Tree::LIGHT_UP_RIGHT,$r['text']);
					}
					$parent = $p;
				}
				# Gecko supports background-image
				$text = \Poodle\Tree::convertURI($row['uri']);
				$row['id'] = (int)$row['id'];
				$row['text'] = $text?$text:'[home]';
				$row['class'] = $text?'lvl'.(substr_count(rtrim($row['uri'],'/'),'/')-1):null;
				$row['disabled'] = $row['id'] == $this->id;

				$OUT->resources_uris[] = $row;
				if (!$this->uri || ($row['id']!=$this->id && 0!==strpos($row['uri'],$this->uri))) {
					$OUT->parent_uris[] = $row;
				}
			}
		}
	}

}
