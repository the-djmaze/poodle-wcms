<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Kernels;

class Setup extends \Poodle
{
	public
		$step,
		$site;

	public
		$CFG      = null,
		$L10N     = null,
		$RESOURCE = null;

	protected
		$FTP = null;

	function __construct()
	{
		if (!POODLE_BACKEND) {
			throw new \Exception('Setup currently not allowed.');
		}
		$this->CACHE = \Poodle\Cache::factory();
		\Poodle::$DEBUG = \Poodle::DBG_ALL;
		\Poodle::$COMPRESS_OUTPUT = false;
		\Poodle\Debugger::start();
		session_start();
	}

	function __get($key)
	{
		if (array_key_exists($key, $this->_readonly_data)) {
			return $this->_readonly_data[$key];
		}
		return parent::__get($key);
	}

	protected function init()
	{
		$this->OUT = new \Poodle\Output\HTML();
		$this->OUT->tpl_header = 'poodle/setup/header';
		$this->OUT->tpl_footer = 'poodle/setup/footer';
		$this->OUT->head
			->addCSS('poodle_debugger')
			->addCSS('poodle_setup')
			->addCSS('poodle_tabs')
			->addScript('poodle_setup')
			->addScript('poodle_tabs');

		$this->L10N = new \Poodle\L10N();
		$this->L10N->load('poodle_setup');

		$this->step = $_GET->uint('step');
		if ($this->step>5 || $this->step<0 || ($this->step>1 && !isset($_SESSION['SETUP_MODE'])))
		{
			\Poodle\HTTP\Headers::setLocation($_SERVER['SCRIPT_NAME'], 301);
			exit;
		}
		if ($this->step>2 && !$this->isVerifiedAdmin())
		{
			\Poodle\HTTP\Headers::setLocation($_SERVER['SCRIPT_NAME'].'?step=2', 301);
			exit;
		}

		$this->step = max(1,$this->step);

		if (isset($_GET['host']) && !preg_match('#(\\.\\.|/|\)#',$_GET['host']))
		{
			$_SESSION['SETUP_MODE'] = null;
			$_SESSION['SETUP_SITE'] = mb_strtolower($_GET['host']);
			\Poodle\HTTP\Headers::setLocation($_SERVER['SCRIPT_NAME'], 301);
			exit;
		}
		$mode       = isset($_SESSION['SETUP_MODE']) ? $_SESSION['SETUP_MODE'] : null;
		$this->site = isset($_SESSION['SETUP_SITE']) ? $_SESSION['SETUP_SITE'] : mb_strtolower($_SERVER['HTTP_HOST']);

		$this->cfg_file = POODLE_HOSTS_PATH.$this->site.'/config.php';

		$cfg_dir = null;
		if (is_file($this->cfg_file)) {
			$cfg_dir = $this->site;
			if (!$mode) $mode = 'upgrade';
			unset($_SESSION['CONFIG']);
		}
		$cfg = parent::getConfig($cfg_dir);
		$cfg = isset($cfg['general']) ? $cfg['general'] : array();
		if (!isset($cfg['dbms']['slave'])) { $cfg['dbms']['slave'] = array(); }
		$this->_readonly_data = array_merge($this->_readonly_data, $cfg);

		if ('upgrade' == $mode && $SQL = $this->loadDatabase()) {
			if (!isset($SQL->TBL->users) || !$SQL->TBL->users->count()) {
				$mode = 'install';
			} else {
				$this->CACHE = \Poodle\Cache::factory($cfg['cache_uri']);
			}
		}

		$_SESSION['SETUP_MODE'] = $mode ? $mode : 'install';
		$_SESSION['SETUP_SITE'] = $this->site;

		if ($this->step > 4 && 'upgrade' == $_SESSION['SETUP_MODE'])
		{
			\Poodle\HTTP\Headers::setLocation('', 301);
			exit;
		}
	}

	public function run()
	{
		$this->OUT->head->title = $this->L10N['_steps'][$this->step];
		$this->OUT->site     = $this->site;
		$this->OUT->uri_next = '?step='.(1+$this->step);
		$this->OUT->menu     = array();
		foreach ($this->L10N['_steps'] as $i => $title) {
			if ($i > 4 && 'upgrade' == $_SESSION['SETUP_MODE']) break;
			$this->OUT->menu[] = array(
				'class' => ($i<$this->step ? 'done' : ($i==$this->step ? 'current' : null)),
				'title' => $title);
		}
		require('poodle/setup/steps/'.$this->step.'.inc');
		$this->OUT->finish();
	}

	public function display_error($msg)
	{
		$this->OUT->start();
		echo '<p class="error">'.$msg.'</p>';
		$this->OUT->finish();
		exit;
	}

	public function isVerifiedAdmin()
	{
		return is_file($this->verificationFilename());
	}

	public function verificationFilename()
	{
		return getcwd().'/setup-'.session_id().'.verify';
	}

	public function setFTPSettings($host, $directory, $username, $passphrase)
	{
		$directory = rtrim($directory,'/');
		$_SESSION['FTP'] = null;
		try {
			$FTP = new \Poodle\FTP();
			if (!$FTP->connect($host, $username, $passphrase)
			 || !$FTP->chdir($directory)
			 || !$FTP->uploadData(basename($this->verificationFilename()), '')
			 || !$this->isVerifiedAdmin())
			{
				return false;
			}
		} catch (\Throwable $e) {
			return false;
		}

		$this->FTP = $FTP;

		$_SESSION['FTP'] = \Poodle::dataToJSON(array(
			'host' => $host,
			'path' => $directory,
			'user' => $username,
			'pass' => $passphrase,
		));
		return true;
	}

	public function getFTPConnection()
	{
		if (null === $this->FTP) {
			$this->FTP = false;
			$cfg = empty($_SESSION['FTP']) ? false : json_decode($_SESSION['FTP'], true);
			if ($cfg) {
				try {
					$FTP = new \Poodle\FTP();
					if ($FTP->connect($cfg['host'], $cfg['user'], $cfg['pass'])
					 && $FTP->chdir($cfg['path']))
					{
						$this->FTP = $FTP;
					}
				} catch (\Throwable $e) {}
			}
		}
		return $this->FTP;
	}

	public function loadDatabase() : ?\Poodle\SQL
	{
		$dbms = $this->_readonly_data['dbms'];
		if ($dbms['adapter'] && $dbms['master']) {
			$this->SQL = new \Poodle\SQL($dbms['adapter'], $dbms['master'], $dbms['tbl_prefix']);
		} else {
			$this->SQL = null;
		}
		return $this->SQL;
	}

}
