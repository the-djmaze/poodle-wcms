<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Session;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Sessions',
		$allowed_methods = array('GET','POST');

	public function POST()
	{
		if (!empty($_POST['delete'])) {
			$d = $_POST['delete'];
			$SQL = \Poodle::getKernel()->SQL;
			foreach ($d as $id => $v) {
				$SQL->TBL->sessions->delete("sess_id={$SQL->quote($id)}");
			}
		}
		if (isset($_POST['save'])) {
			$CFG = \Poodle::getKernel()->CFG;
			$CFG->set('session', 'name', trim($_POST['session']['name']));
			$CFG->set('session', 'timeout', $_POST->uint('session','timeout'));
			$CFG->set('session', 'samesite', $_POST->text('session','samesite'));

			$handler = trim($_POST['session']['handler']);
//			in_array('SessionHandlerInterface', class_implements($handler))
			if (class_exists($handler) && is_subclass_of($handler, 'Poodle\Session')) {
				$CFG->set('session', 'handler', $handler);
			}
			$CFG->set('session', 'serializer', trim($_POST['session']['serializer']));
			$CFG->set('session', 'save_path', trim($_POST['session']['save_path']));
		}
		\Poodle\URI::redirect($_SERVER['REQUEST_URI']);
	}

	public function GET()
	{
		$K = \Poodle::getKernel();
		$K->OUT->session_save_path   = session_save_path();
		$K->OUT->session_serializers = array(array('name'=>'', 'current'=>false));
//		phpinfo(INFO_MODULES);
		$info = \Poodle\PHP\Info::get(INFO_MODULES);
		$serializers = preg_split('/\s+/', trim($info['module_session']['items']['Registered serializer handlers']));
		unset($info);
		$current_serializer = $K->CFG->session->serializer;
		foreach ($serializers as $serializer) {
			// msgpack is broken
			if ('msgpack' == $serializer && !version_compare(phpversion('msgpack'), '2.0.2', '>')) {
				continue;
			}
			// https://github.com/igbinary/igbinary7/issues/20
			// PHP7 fix is pushed later then 1.3.0a1 release
			// Keep track of https://github.com/TysonAndre/igbinary/blob/master/package.xml
			if ('igbinary' == $serializer && !version_compare(phpversion('igbinary'), '1.3.0a1', '>')) {
				continue;
			}
			$K->OUT->session_serializers[] = array('name'=>$serializer, 'current'=>$serializer==$current_serializer);
		}
		$K->OUT->sessions_list = $K->SQL->query("SELECT
				sess_id,
				identity_id,
				sess_timeout,
				sess_expiry,
				sess_ip,
				sess_uri,
				sess_user_agent,
				user_nickname
			FROM {$K->SQL->TBL->sessions}
			LEFT JOIN {$K->SQL->TBL->users} USING (identity_id)
			ORDER BY sess_expiry ASC");
		$K->OUT->display('poodle/session/admin');
	}

}
