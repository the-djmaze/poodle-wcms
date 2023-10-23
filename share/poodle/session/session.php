<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class Session implements \ArrayAccess
{
	use \Poodle\Events;

	protected
		$config,
		$handler,
		$timeout,
		$is_new = null;

	function __construct(object $config)
	{
		if (empty($config->name)) {
			$config->name = \Poodle\PHP\INI::get('session.name') ?: 'POODLE_SID';
		}
		if (empty($config->serializer)) {
			$config->serializer = 'php_serialize';
		}
		if (empty($config->samesite)) {
			$config->samesite = 'Strict'; // Lax
		}

		$this->config = $config;

		$handler = $config->handler ?: 'Poodle\\Session\\Handler\\Builtin';
		$this->setHandler(new $handler($this));

		$this->start(/*true*/);
		header_register_callback(array($this, 'startNoRead'));
//		register_shutdown_function(array($this, 'write_close'));
	}

	public static function getSaveHandlers()
	{
		// php.ini session.save_handler
		return preg_split('/\s+/', trim(\Poodle\PHP\Info::get(INFO_MODULES)['module_session']['items']['Registered save handlers']));
	}

	public static function getSerializeHandlers()
	{
		// php.ini session.serialize_handler
		return preg_split('/\s+/', trim(\Poodle\PHP\Info::get(INFO_MODULES)['module_session']['items']['Registered serializer handlers']));
	}

	/**
	 * finishes session without saving data
	 */
	public function abort()
	{
		return session_abort();
	}

	public function close()
	{
		return session_write_close();
	}

	public function destroy()
	{
		$_SESSION = array();
		if (isset($_COOKIE[$this->config->name])) {
			\Poodle\HTTP\Cookie::remove($this->config->name, session_get_cookie_params());
			unset($_COOKIE[$this->config->name]);
		}
		if (session_status() === PHP_SESSION_ACTIVE) {
			return session_destroy();
		}
		return true;
	}

	public function offsetExists($key)
	{
		return array_key_exists($key, $_SESSION);
	}
	public function offsetGet($key)
	{
		return $_SESSION[$key];
	}
	public function offsetSet($key, $value)
	{
		$this->start();
		$_SESSION[$key] = $value;
	}
	public function offsetUnset($key)
	{
		$this->start();
		unset($_SESSION[$key]);
	}

	/**
	 * reinitializes a session with original values stored in session storage
	 */
	public function reset()
	{
		return session_reset();
	}

	public function setHandler(\SessionHandlerInterface $handler = null)
	{
		$this->handler = $handler;
	}

	public function start($read_and_close = false)
	{
		if (!$this->canStart()) {
			return false;
			throw new \Exception('Session already started!');
			throw new \Exception('Cannot start session when headers already sent!');
		}

		if (\Poodle\UserAgent::getInfo()->bot) {
			return false;
		}

		if (!session_set_save_handler($this->handler, false)) {
			throw new \Exception('Failed to set session handler');
		}

		$options = array(
			'cookie_httponly'   => 1,
			'cookie_secure'     => !empty($_SERVER['HTTPS']),
			'name'              => $this->config->name,
//			'gc_probability'    => 0, // for manual session_gc()
			'serialize_handler' => $this->config->serializer,
			'use_cookies'       => 1,
			'use_only_cookies'  => 1, # SID in url
			'use_strict_mode'   => 1,
			'use_trans_sid'     => 0,    # SID ob
/*
			'gc_divisor'     => 100,
			'gc_maxlifetime' => 1440,
			cookie_lifetime
			cookie_path
			cookie_domain
			referer_check
			cache_limiter
			cache_expire
			lazy_write
*/
		);
		if ($read_and_close) {
			$options['read_and_close'] = true;
		}

		if (isset(\Poodle::$URI_BASE[1])) {
			$options['cookie_path'] = \Poodle::$URI_BASE . '/';
		}
		$options['cookie_samesite'] = $this->config->samesite;
		if (!empty($this->config->save_path)) {
			$options['save_path'] = $this->config->save_path;
			session_save_path($this->config->save_path);
		}
		\Poodle\PHP\INI::set('url_rewriter.tags', '');       # SID in tags

//		session_id(sha1($this->config->name.microtime()));
//		session_name($this->config->name);

		if (!empty($this->config->timeout)) {
			$this->setTimeout($this->config->timeout);
		} else {
			$this->timeout = \Poodle\PHP\INI::get('session.gc_maxlifetime');
		}

		if (!session_start($options)) {
			throw new \Error('Failed to start session');
		}

/*
		if (1 === random_int(1, (int)\Poodle\PHP\INI::get('session.gc_divisor')/\Poodle\PHP\INI::get('session.gc_probability')))
		if (1 === random_int(1, 100)) { session_gc(); }

		# Session hijack attempt?
		if (!empty($_SESSION['_PID']) && $this->pid() !== $_SESSION['_PID']) {
			$pid = $_SESSION['_PID'];
			$this->destroy();
			$L10N = \Poodle::getKernel()->L10N;
			$L10N->load('poodle_report');
			\Poodle\LOG::error('Session', "PID Incorrect,\nwas {$pid}\nnow:".$this->pid());
			\Poodle\Report::error('Session issue', sprintf($L10N->get('_SECURITY_MSG','_EXPIRED'), 'session'), \Poodle\URI::abs());
		}

		# Session expired?
		if (!$_SESSION && isset($_COOKIE[$name]) && session_id() === $_COOKIE[$name]) {
//			\Poodle\LOG::warning('Session', session_id().' is empty');
			$this->start();
		}
*/

		if (is_null($this->is_new)) {
			$this->is_new = !$_SESSION;
		}

		return true;
	}

	public function getTimeout()
	{
		return $this->timeout;
	}

	public function setTimeout($time)
	{
		// if $time > 300 then $time is in seconds else in minutes
		$time = (int) $time;
		$this->timeout = min(32767, max(300, 300 > $time ? $time * 60 : $time));
		if ($this->canStart()) {
			\Poodle\PHP\INI::set('session.gc_maxlifetime', $this->timeout);
		}
	}

	public function status()
	{
		return session_status();
	}

	public function canStart()
	{
		return (session_status() !== PHP_SESSION_ACTIVE && !headers_sent());
	}

	public function hasCookie()
	{
		return isset($_COOKIE[$this->config->name]);
	}

	public function is_new()
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			$this->start();
		}
		return $this->is_new;
	}

	public function startNoRead()
	{
		$data = $_SESSION;
		$this->start();
		$_SESSION = $data;
	}

	public function write_close()
	{
		$result = false;
		if (session_status() === PHP_SESSION_ACTIVE) {
			if (\Poodle\UserAgent::getInfo()->bot) {
				$this->destroy();
			} else try {
				if (!isset($_SESSION['_PID'])) {
					$_SESSION['_PID'] = $this->pid();
				}
				$this->triggerEvent('beforeWrite');
				$result = session_write_close();
				$this->is_new = false;
			} catch (\Throwable $e) {
//				error_log(__CLASS__ . ' ' . $e->getMessage()."\n".$e->getTraceAsString());
				trigger_error(__CLASS__ . ' ' . $e->getMessage()."\n".$e->getTraceAsString(), E_USER_WARNING);
			}
		}
		return $result;
	}

	# Create Protection ID
	protected static function pid()
	{
		static $pid;
		if (!$pid) {
			// Can't use all $_SERVER['HTTP_*'] vars because MSIE changes them randomly
			// Firefox changes when FirePHP/0.6 is active or not
			// So we only use a partial string and the stable ua detect object
			$pid = md5(
				substr($_SERVER['HTTP_USER_AGENT'],0,strpos($_SERVER['HTTP_USER_AGENT'],')'))
				.json_encode(\Poodle\UserAgent::getInfo())
			);
		}
		return $pid;
	}

}
