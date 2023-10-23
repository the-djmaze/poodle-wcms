<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Kernels;

#
# The kernel class
#

class General extends \Poodle
{
	use \Poodle\Events;

	public
		$mlf      = 'html',

//		$L10N     = null,
//		$OUT      = null,
		$RESOURCE = null,
		$SESSION  = null;

	function __construct(array $cfg)
	{
		if (!$cfg) {
			\Poodle\HTTP\Status::set(503);
			exit('The URI that you requested, is temporarily unavailable due to maintenance on the server.');
		}

		\Poodle\Debugger::start();

		if (\Poodle::$EXT) {
			$this->mlf = \Poodle::$EXT;
		}

		if (!isset($cfg['max_resource_revisions'])) {
			$cfg['max_resource_revisions'] = 0;
		}
		parent::__construct($cfg);

		\Poodle\PHP\INI::set('user_agent', 'Poodle/'.self::VERSION.' ('.PHP_OS.'; '.PHP_SAPI.'; +http://'.$_SERVER['HTTP_HOST'].'/)');
/*		if (\Poodle::$DEBUG & \Poodle::DBG_PHP) {
			\Poodle\PHP\INI::set('docref_root', 'http://php.net/');
			\Poodle\PHP\INI::set('html_errors', 1);
		}*/

		if (!\Poodle\PHP\INI::enabled('allow_url_fopen', false) || \Poodle\PHP\INI::enabled('allow_url_include')) {
			# Force allow_url_fopen=on and allow_url_include=off
			stream_wrapper_unregister('ftp');
			stream_wrapper_unregister('ftps');
			stream_wrapper_unregister('http');
			stream_wrapper_unregister('https');
			stream_wrapper_register('http',  'Poodle\\Stream\\Wrapper\\HTTP');
			stream_wrapper_register('https', 'Poodle\\Stream\\Wrapper\\HTTP');
		}
	}

	function __destruct()
	{
		if (property_exists($this, '_readonly_data')) {
			self::onShutdown();
		}
	}

	function __get($key)
	{
		if ('IDENTITY' === $key) {
			if (!isset($this->IDENTITY)) {
				$this->IDENTITY = \Poodle\Identity::getCurrent();
			}
			return $this->IDENTITY;
		}

		if ('CACHE' === $key) {
			if (!isset($this->CACHE)) {
				$this->CACHE = \Poodle\Cache::factory($this->_readonly_data['cache_uri']);
			}
			return $this->CACHE;
		}

		if ('L10N' === $key) {
			return $this->L10N = new \Poodle\L10N();
		}

		if ('OUT' === $key) {
			$class = 'Poodle\\Output\\'.$this->mlf;
			if (!\class_exists($class)) {
//				\Poodle\LOG::debug('output', "Unknown class {$class}");
				$this->mlf = 'html';
				$class = 'Poodle\\Output\\HTML';
//				\Poodle\Report::error(404);
			}
			return $this->OUT = new $class();
		}

		return parent::__get($key);
	}

	function __set($key, $val)
	{
		if (array_key_exists($key, $this->_readonly_data)) {
			throw new \Exception('Disallowed to set property: '.$key);
		}
		$this->$key = $val;
	}

	function __isset($key)
	{
		if ('OUT' === $key) { return true; }
		return (property_exists($this, $key) && isset($this->$key)) || isset($this->_readonly_data[$key]);
	}

	public function run()
	{
		# Load configuration
		$this->loadConfig();

		# Show error page if the http server sends an error
		if (isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS'] >= 400 && $_SERVER['REDIRECT_STATUS'] < 600) {
			\Poodle\Report::error((int)$_SERVER['REDIRECT_STATUS']);
			//$message = ucfirst(preg_replace('/:.*/', '', $_SERVER['REDIRECT_ERROR_NOTES']));
		}

		# Start the session
		$this->SESSION = new \Poodle\Session($this->CFG->session);

		$this->__get('IDENTITY');
		if (isset($_GET['logout'])) {
			$this->IDENTITY->logout();
			\Poodle\URI::redirect(preg_replace('#[?&]logout#','',$_SERVER['REQUEST_URI']));
		}

		if (!$this->SESSION->hasCookie() && '/upgrade-browser' !== $_SERVER['PATH_INFO'] && \Poodle\UserAgent::isOld()) {
			\Poodle\URI::redirect('/upgrade-browser');
		}

		// Check security
		if (POODLE_BACKEND) {
			if (\Poodle\UserAgent::isOld()) {
				\Poodle\Report::error('You must upgrade your browser');
			}
			if ('/login' !== $_SERVER['PATH_INFO'] && !($this->IDENTITY->isAdmin() && \Poodle\ACL::admin($_SERVER['PATH_INFO']))) {
				\Poodle\Report::error(403);
			}
		} else {
			# Check if maintenance is turned on
			if ($this->CFG->site->maintenance && !$this->IDENTITY->isAdmin()) {
				\Poodle\HTTP\Status::set(503);
				header('Retry-After: '.max(120, $this->CFG->site->maintenance_till - time()));
				\Poodle\Report::error($this->L10N['Maintenance'], $this->CFG->site->maintenance_text);
			}
			if ('/login' !== $_SERVER['PATH_INFO'] && '/upgrade-browser' !== $_SERVER['PATH_INFO'] && !\Poodle\ACL::view($_SERVER['PATH_INFO'])) {
				\Poodle\Report::error(403);
			}
		}

		# Finally show the requested resource
		$method = &$_SERVER['REQUEST_METHOD'];
		$this->RESOURCE = POODLE_BACKEND ? \Poodle\Resource\Admin::factory() : \Poodle\Resource::factory($_SERVER['PATH_INFO']);
		$uri = '/'.ltrim($this->RESOURCE->uri,'/');

		if (!POODLE_BACKEND) {
			if (0 !== strpos($_SERVER['PATH_INFO'],$uri)) {
				\Poodle\URI::redirect($uri, 302);
			}
			if ('Poodle\\Resource\\Basic' === get_class($this->RESOURCE) && $_SERVER['PATH_INFO'] !== $uri) {
				\Poodle\Report::error(404);
			}
			if ($this->RESOURCE->etime && $this->RESOURCE->etime < time()) {
				header('X-Robots-Tag: none, unavailable_after: '.date('d-M-y H:i:s T', $this->RESOURCE->etime));
				\Poodle\Report::error(410);
			}
		}

		if (('*' === $this->RESOURCE->allowed_methods || in_array($method, $this->RESOURCE->allowed_methods))
		 && (method_exists($this->RESOURCE, $method) || method_exists($this->RESOURCE, '__call')))
		{
			$this->RESOURCE->$method();
		} else {
			if ('OPTIONS' === $method) {
				header('Allow: '.implode(', ', $this->RESOURCE->allowed_methods));
				exit;
			}
			\Poodle\HTTP\Status::set(405);
			header('Allow: '.implode(', ', $this->RESOURCE->allowed_methods));
			echo $method.' method not allowed';
			exit(2);
		}

		# Something may started the output so lets properly close it
		if (isset($this->OUT)) { $this->OUT->finish(); }
	}

}
