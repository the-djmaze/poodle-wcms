<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Identity;

class Search
{
	public static function byID($id, $any=false)         { return static::find(array('id'=>$id), $any); }

	public static function byEmail($email, $any=false)   { return static::find(array('email'=>$email), $any); }

	protected static function find($user, $any_type=false)
	{
		static $users = array();
		$K   = \Poodle::getKernel();
		$SQL = $K->SQL;
		$where = '';
		if (isset($user['id'])) {
			if (empty($user['id'])) { return \Poodle\Identity::factory(); }
			if (isset($users[$user['id']])) { return $users[$user['id']]; }
			$where = 'identity_id = '.(int)$user['id'];
		}
		else if (!empty($user['email'])) {
			$user['email'] = \Poodle\Input::lcEmail($user['email']);
			foreach ($users as $row) { if ($user['email'] === $row['email']) { return $row; } }
			$where = 'user_email = '.$SQL->quote($user['email']);
		}
		else {
			throw new \Exception('$user unknown: '.implode(', ',$users));
		}

		if (!$any_type && (!isset($K->IDENTITY) || !$K->IDENTITY->isAdmin())) {
			$where .= ' AND user_type>0';
		}

		$query = 'SELECT
			identity_id,
			user_ctime,
			user_nickname,
			user_email,
			user_givenname,
			user_surname,
			user_language,
			user_timezone,
			user_last_visit,
			user_default_status status,
			user_type
		FROM '.$SQL->TBL->users.'
		WHERE '.$where;
		$user = $SQL->uFetchAssoc($query, true);
		if (!$user || !is_array($user)) { return false; }
		$SQL->removePrefix($user, 'identity', 'user');
		return $users[$user['id']] = \Poodle\Identity::factory($user);
	}
}
