<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class Comments extends \Poodle\Resource\Basic
{
	public
		$allowed_methods = array('GET','HEAD','POST');

	public function POST()
	{
		if (isset($_POST['add_comment'])) {
			if (\Poodle\AntiSpam\Captcha::validate($_POST) < 5) {
				exit('Failure');
			}

			$resource_id  = $_POST->uint('comment','resource_id');
			$resource_uri = $_POST['comment']['resource_uri'];
			$parent = \Poodle::getKernel()->SQL->TBL->resources->uFetchRow(array('resource_uri'), array('resource_id' => $resource_id));
			if (!$parent || $parent[0] !== $resource_uri) {
				\Poodle\Report::error(404);
			}

			$body = trim($_POST['comment']['body']);
			if (!empty($body)) {
				$body = nl2br(htmlspecialchars($body));
				$comment = new \Poodle\Comments\Comment();
				$comment->parent_id = $resource_id;
				if (!\Poodle::getKernel()->IDENTITY->isMember()) {
					if (!empty($_POST['comment']['author'])) {
						$comment->getMetadata()->append(0, 'author', $_POST->text('comment','author'));
					}
					if (!empty($_POST['comment']['email'])) {
						$comment->getMetadata()->append(0, 'author-email', $_POST->email('comment','email'));
					}
				}
				$comment->save();
				$comment->addRevision(array(
					'l10n_id' => 0,
					'status' => static::STATUS_PENDING,
					'title' => 'comment '.$comment->uri,
					'body' => $body,
				));
			}

			\Poodle\URI::redirect($resource_uri);
		}
	}

	protected static function getResourceURI($resource_id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$resource_uri = $SQL->uFetchRow("SELECT resource_uri FROM {$SQL->TBL->resources} WHERE resource_id=".(int)$resource_id);
		return $resource_uri ? $resource_uri[0] : null;
	}

	public static function getFor($resource_id)
	{
		$resource_uri = self::getResourceURI($resource_id);
		if (!$resource_uri) {
			return null;
		}

		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->query("SELECT
			id,
			uri,
			parent_id,
			ctime,
			flags,
			creator_identity_id,
			mtime,
			modifier_identity_id,
			r.l10n_id,
			title,
			body,
			COALESCE(user_nickname, resource_meta_value) as author,
			user_givenname as givenname,
			user_surname as surname
		FROM {$SQL->TBL->view_latest_resources_data} r
		LEFT JOIN {$SQL->TBL->users} ON (identity_id=creator_identity_id)
		LEFT JOIN {$SQL->TBL->resources_metadata} ON (resource_id=id AND resource_meta_name='author')
		WHERE ptime<=UNIX_TIMESTAMP()
		  AND (etime=0 OR etime>UNIX_TIMESTAMP())
		  AND uri LIKE '".$SQL->escape_string(rtrim($resource_uri,'/'))."/%'
		  AND type_id=7
		ORDER BY ctime DESC");

		// Sorting oldest first with replies in line: ORDER BY uri
		// Sorting on date: ORDER BY ctime DESC
	}

	public static function countFor($resource_id)
	{
		$resource_uri = self::getResourceURI($resource_id);
		if (!$resource_uri) {
			return 0;
		}
		$SQL = \Poodle::getKernel()->SQL;
		return $SQL->count('view_latest_resources_data',"ptime<=UNIX_TIMESTAMP()
		  AND (etime=0 OR etime>UNIX_TIMESTAMP())
		  AND uri LIKE '".$SQL->escape_string(rtrim($resource_uri,'/'))."/%'
		  AND type_id=7");
	}

}
