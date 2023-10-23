<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource;

class Admin extends \Poodle\Resource\Edit
{
	public
		// resource type
		$type_class,
		$title      = 'Administration',

		// http://tools.ietf.org/html/rfc4918#section-9
		$allowed_methods = array('GET','HEAD'
/*			'PROPFIND',
			'PROPPATCH',
			'MKCOL',
			'GET',
			'HEAD',
			'POST',
			'DELETE',
			'PUT',
			'COPY',
			'MOVE',
			'LOCK',
			'UNLOCK',
			'OPTIONS',
			'REPORT',
			'MKCALENDAR',
			'TESTRRULE'
*/		),

		// Change scope from protected to public
		$metadata = null;

	function __construct(array $data = array())
	{
		if (!POODLE_BACKEND) { \Poodle\Report::error(403); }
		if (\Poodle::$PATH[0] && !isset(\Poodle::$PATH[1])) {
			\Poodle\URI::redirect('/admin/'.\Poodle::$PATH[0].'/');
		}

		$K = \Poodle::getKernel();
		// Do admin security check
		if (!$K->IDENTITY->isAdmin()) {
			if ($K->IDENTITY->id) { \Poodle\Report::error(403); } # unauthorized member
			// Use Authorization login
			\Poodle\URI::login(true);
			\Poodle\Report::error(401);
		}
		if (isset($K->OUT->L10N[$this->title])) {
			$this->title = $K->OUT->L10N[$this->title];
		}
		parent::__construct($data);

		$K->OUT->L10N->load('poodle_resource');
		if (\Poodle::$PATH[0] && 1 == count($K->OUT->crumbs)) {
			$K->OUT->crumbs->append($this->title, '/admin/'.\Poodle::$PATH[0].'/');
		}
	}

	protected function closeRequest($msg = null, $uri = null)
	{
		$msg = \Poodle::getKernel()->L10N->get($msg ?: 'The changes have been saved');
		$q = isset($_GET['l10n_id']) ? '?l10n_id='.$_GET['l10n_id'] : '';
		\Poodle::closeRequest($msg, 201, $uri ?: \Poodle\URI::admin("/resources/{$this->id}{$q}"), $msg);
	}

	public static function factory($path = null)
	{
		$class = 'Poodle\\Resource\\Admin';
		if (!empty(\Poodle::$PATH[0]))
		{
			if (!preg_match('#^[a-zA-Z0-9_]+$#D',\Poodle::$PATH[0])) {
				throw new \Exception('Invalid resource path');
			}
			if ('login' === \Poodle::$PATH[0]) {
				$class = 'Poodle\\Login';
			} else if ('resources' === \Poodle::$PATH[0]) {
				$class = 'Poodle\\Resource\\Admin\\Resources';
			} else {
				$class = preg_replace('#^([^_]+_[^_]+)#','$1_Admin',\Poodle::$PATH[0]);
			}
		}
		\Poodle::getKernel()->L10N->load(preg_replace('#^([^_]+_[^_]+).*$#D','$1',$class),true);
		$ns_class = strtr($class, '_', '\\');
		if (class_exists($ns_class)) { return new $ns_class; }
		if (class_exists($class)) { return new $class; }
		\Poodle\Report::error("Class '{$class}' not found");
	}

	public function GET()
	{
		$this->HEAD();
		$resource = \Poodle\Resource::factory('/admin/');
		if ($resource->body) {
			$resource->display();
		} else {
			\Poodle::getKernel()->OUT->display('admin/index');
		}
	}

	public function HEAD()
	{
		\Poodle\HTTP\Headers::setLastModified($this->mtime);
		\Poodle::getKernel()->OUT->send_headers();
	}

}
