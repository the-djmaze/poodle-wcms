<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	You can obtain a copy of the license at poodle/LICENSE.txt
	or http://www.opensource.org/licenses/cddl1.php
	See the License for the specific language governing permissions
	and limitations under the License.

	When distributing Covered Code, include this CDDL HEADER in each
	file and include the License file at poodle/LICENSE.txt.
	If applicable, add the following below this CDDL HEADER,
	with the fields enclosed by brackets "[]" replaced with your
	own identifying information:
		Portions Copyright [yyyy] [name of copyright owner]
*/

if (PHP_VERSION_ID < 70300) {
	header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error');
	exit('This software needs atleast PHP 7.3, currently: '.PHP_VERSION);
}

if (function_exists('sys_getloadavg')) {
	$load = sys_getloadavg();
	if ($load[0] > 80) {
		header('HTTP/1.1 503 Service Unavailable');
		header('Retry-After: 120');
		exit('Server too busy. Please try again later.');
	}
	unset($load);
}

if (!defined('POODLE_BACKEND'))   { define('POODLE_BACKEND', false); }
if (!defined('POODLE_HOSTS_PATH')){ define('POODLE_HOSTS_PATH', 'poodle_hosts/'); }
// When php-cgi is executed from cli, we ignore it. It has incorrect behavior and a very bad config!
define('POODLE_CLI', false !== stripos(php_sapi_name(), 'cli'));
define('XMLHTTPRequest', (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' === $_SERVER['HTTP_X_REQUESTED_WITH']));
define('WINDOWS_OS', '\\' === DIRECTORY_SEPARATOR);

if (WINDOWS_OS) {
	$_SERVER = str_replace(DIRECTORY_SEPARATOR,'/',$_SERVER);
	function uri_dirname($path) : string {
		return rtrim(strtr(dirname($path),DIRECTORY_SEPARATOR,'/'),'/');
	}
	// Workaround issue with IIS Helicontech APE which defines vars lowercased
	foreach ($_SERVER as $k => $v) {
		$_SERVER[strtoupper($k)] = $v;
	}
	// When using ISAPI with IIS, the value will be off if the request was not made through the HTTPS protocol.
	if (isset($_SERVER['HTTPS']) && 'off' === $_SERVER['HTTPS']) {
		unset($_SERVER['HTTPS']);
	}
} else {
	function uri_dirname($path) : string {
		return rtrim(dirname($path),'/');
	}
}

// Default HTTP Strict Transport Security
if (isset($_SERVER['HTTPS'])) {
	header('Strict-Transport-Security: max-age=31536000');
}

# Custom function to detect if array is associative
if (!function_exists('is_assoc')) {
	function is_assoc($a) : bool {
		return is_array($a) && array_keys($a) !== range(0, count($a) - 1);
	}
}

if (!function_exists('get_class_basename')) {
	function get_class_basename($c) : string {
		$c = get_class($c);
		return substr($c, strrpos($c, '\\') + 1);
	}
}

if (!function_exists('public_method_exists')) {
	function public_method_exists($object, $method_name) : bool {
		return is_callable(array($object, $method_name));
	}
}

if (!function_exists('get_object_public_vars')) {
	function get_object_public_vars($object) : ?array {
		return get_object_vars($object);
	}
}

abstract class Poodle
{
	const
		VERSION    = '2.7.0.1125',
		DB_VERSION = 20200527,
		CHARSET    = 'UTF-8',

		DBG_MEMORY              = 1,
		DBG_PARSE_TIME          = 2,
		DBG_TPL_TIME            = 4,
		DBG_SQL                 = 8,
		DBG_SQL_QUERIES         = 16,
		DBG_JAVASCRIPT          = 32,
		DBG_PHP                 = 64,
		DBG_EXEC_TIME           = 128,
		DBG_TPL_INCLUDED_FILES  = 256,
		DBG_INCLUDED_FILES      = 268435456,
		DBG_DECLARED_CLASSES    = 536870912,
		DBG_DECLARED_INTERFACES = 1073741824,
		DBG_ALL                 = 2147483647; # 64bit: 9223372036854775807

	public static
		$DEBUG = 0,

		$UMASK = null, # octdec()?

		$COMPRESS_OUTPUT = false,

		$EXT  = null,
		$PATH = array(),

		$DIR_BASE  = '',
		$DIR_MEDIA = 'media/',

		$URI_ADMIN,
		$URI_BASE,
		$URI_INDEX,
		$URI_MEDIA,
		$UA_LANGUAGES;

	protected
		$_readonly_data = array(
			'auth_realm'  => 'My Website',
			'cache_dir'   => null,
			'cache_uri'   => null,
			'main_crypt'  => null,
			'design_mode' => false,
			'dbms' => array('adapter'=>'', 'tbl_prefix'=>'', 'master'=>array(), 'slave'=>array()),
		),
//		$CFG,
//		$SQL,
		$CACHE,
		$IDENTITY;

	function __construct(array $cfg)
	{
		if (!$cfg) {
			\Poodle\HTTP\Status::set(503);
			exit('The URI that you requested, is temporarily unavailable due to maintenance on the server.');
		}
		if (!isset($cfg['dbms']['slave'])) {
			$cfg['dbms']['slave'] = array();
		}
		$this->_readonly_data = array_merge($this->_readonly_data, $cfg);

		register_shutdown_function(array($this, 'onShutdown'));
	}

	function __get($key)
	{
		if ('SQL' === $key) { return $this->loadDatabase(); }
		if ('CFG' === $key) { return $this->loadConfig(); }
		if (property_exists($this,$key)) { return $this->$key; }
		if (array_key_exists($key, $this->_readonly_data)) {
			return $this->_readonly_data[$key];
		}
		$bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
		trigger_error("Undefined property: {$key} by: {$bt[1]['file']}#{$bt[1]['line']}");
		return null;
	}

	abstract public function run();
/*
	private static
		$PROCESS_UID = 0,
		$PROCESS_OWNER = 'nobody';
*/
	public static function chmod($file, $mask=0666) : bool
	{
		return chmod($file, self::$UMASK ^ $mask);
	}

	public static function closeRequest($msg, $status_code=200, $uri=null, $box_msg=null) : void
	{
		if (POODLE_CLI) { echo $status_code.' '.$msg; exit; }
		if (XMLHTTPRequest) {
			header('Pragma: no-cache');
			header('Cache-Control: no-cache');
			\Poodle\HTTP\Status::set($status_code);
			switch ($status_code) {
			case 201: if ($uri) { header('Location: '.$uri); } break;
			case 204: exit;
			}
			if ($box_msg) { \Poodle\Notify::success($box_msg); }
			exit($msg);
		}
		if ($status_code >= 400) { \Poodle\Report::error($status_code, $msg); }
		if ($msg) { \Poodle\Notify::success($msg); }
		\Poodle\URI::redirect($uri);
	}

	public static function getFile($name, array $dirs = array())
	{
		if (!$dirs) {
			return stream_resolve_include_path($name);
		}
		foreach ($dirs as $dir) {
			// Plesk issue when using '.' and open_basedir
			// Warning: spl_autoload(): open_basedir restriction in effect.
			// Warning: is_file(): open_basedir restriction in effect.
			if ('.' === $dir[0]) { $dir = getcwd().substr($dir,1); }

			$file = $dir.DIRECTORY_SEPARATOR.$name;
			if (is_file($file)) {
				return $file;
			}
		}
	}

	public static function shortFilePath($file)
	{
		static $paths;
		if (!$paths) { $paths = array_merge(array(static::$DIR_BASE), explode(PATH_SEPARATOR, preg_replace('#\\.+'.PATH_SEPARATOR.'#', '', get_include_path()))); }
		return str_replace($paths, '', $file);
//		if (!$paths) { $paths = '#^('.strtr(get_include_path(),PATH_SEPARATOR,'|').')#'; }
//		if (!$paths) { $paths = '#^('.implode('|',array_merge(array(static::$DIR_BASE), explode(PATH_SEPARATOR, preg_replace('#\.+'.PATH_SEPARATOR.'#', '', get_include_path())))).')#'; }
//		return preg_replace(self::$re_paths, '', $file);
	}

	public static function getConfig($cfg_dir=null) : array
	{
		$dir = mb_strtolower($cfg_dir ? $cfg_dir : $_SERVER['HTTP_HOST']);
		$config = array();

		if (!is_file(POODLE_HOSTS_PATH."{$dir}/config.php")) {
			if ($cfg_dir) {
				trigger_error('Poodle config not found');
				return $config;
			}
			// Redirect to domain when config with(out) 'www' does exists
			$dir = (0===strpos($dir,'www.')) ? substr($dir,4) : "www.{$dir}";
			if (is_file(POODLE_HOSTS_PATH."{$dir}/config.php")) {
				\Poodle\URI::redirect("{$_SERVER['REQUEST_SCHEME']}://{$dir}{$_SERVER['REQUEST_URI']}");
			}
			// Detect any domain config
			$dir = 'default';
		}

		if (is_file(POODLE_HOSTS_PATH."{$dir}/config.php")) {
			include(POODLE_HOSTS_PATH."{$dir}/config.php");
		}

		if ($config) {
			if (!isset($config['general']['cache_dir'])) {
				$config['general']['cache_dir'] = strtr(realpath(POODLE_HOSTS_PATH.$dir),DIRECTORY_SEPARATOR,'/').'/cache';
			}
			if (!isset($config['general']['cache_uri'])) {
				$config['general']['cache_uri'] = 'file://'.$config['general']['cache_dir'];
			}
		}

		if (is_null(self::$UMASK)) {
			self::$UMASK = strpos(PHP_SAPI, 'fcgi') ? 0022 : 0;
/*
			# Get the process information
			if (!WINDOWS_OS && function_exists('posix_getpwuid')) {
				# w32 get_current_user() returns process
				$pwuid = posix_getpwuid(posix_geteuid());
				self::$PROCESS_UID = posix_geteuid();
				self::$PROCESS_OWNER = array_shift($pwuid);
			}
			self::$UMASK = (preg_match('#(www-data|nobody|apache)#', self::$PROCESS_OWNER) || getmyuid() !== self::$PROCESS_UID) ? 0 : 0022;
*/
		}
//		register_shutdown_function('umask', umask(self::$UMASK)); # Reset at shutdown
		umask(self::$UMASK);

		return $config;
	}

	protected static $KERNEL = null;
	public static function getKernel() { return self::$KERNEL ?: self::loadKernel(); }
	public static function loadKernel($kernel_name=null, array $cfg=array())
	{
		if (self::$KERNEL) { throw new \Exception('Poodle Kernel already loaded'); }
		$name = $kernel_name;
		if (!$name && isset(self::$PATH[0])) {
			$name = self::$PATH[0];
		}
		$name = $name ? strtolower($name) : 'general';

		self::$COMPRESS_OUTPUT = true;

		$config = self::getConfig(isset($cfg['cfg_dir'])?$cfg['cfg_dir']:null);
		unset($cfg['cfg_dir']);
		$config = array_merge($config, $cfg);
		if (!isset($config[$name])) {
			if ($kernel_name) {
				// Kernel name was requested and therefore required. So we kill the process
				trigger_error("Poodle config[{$kernel_name}] not found", E_USER_ERROR);
			}
			$config[$name] = array();
			if (isset($config['general'])) {
				$name = 'general';
			}
		}
		if (isset($config['general'])) {
			$config[$name] = array_merge($config['general'], $config[$name]);
		}

		$class = 'Poodle\\Kernels\\'.$name;
		self::$KERNEL = new $class($config[$name]);
		self::$KERNEL->init();

		return self::$KERNEL;
	}

	protected function init(){}
	public function addEventListener(string $type, callable $function){}
	public function triggerEvent(string $type){}

	private static $shutdown = false;
	final public static function isShutdown() : bool
	{
		return self::$shutdown;
	}
	final public function onShutdown() : void
	{
		if (!self::$shutdown) {
			self::$shutdown = true;
			ini_set('display_errors', 0);
			ignore_user_abort(true);
			self::ob_flush_all();

			try {
				if (isset($this->SESSION)) {
					$this->SESSION->write_close();
					unset($this->SESSION);
				}
			} catch (\Throwable $e) { } # skip

			if (is_callable('fastcgi_finish_request')) {
				// Special FPM/FastCGI (fpm-fcgi) function to finish request and
				// flush all data while continuing to do something time-consuming.
				fastcgi_finish_request();
			}

			try {
				$this->triggerEvent('shutdown');
			} catch (\Throwable $e) { } # skip

			foreach (array_keys(get_object_vars($this)) as $val) {
				$this->$val = null;
			}
		}
	}

	public static function startStream() : void
	{
		ob_implicit_flush();
		ini_set('implicit_flush',1);
		ini_set('output_buffering', 0);
		static::ob_clean();
		// Emulate the header BigPipe sends so we can test through Varnish.
		header('Surrogate-Control: BigPipe/1.0');
		// Explicitly disable caching so Varnish and other upstreams won't cache.
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');
		// Setting this header instructs Nginx to disable fastcgi_buffering and disable gzip for this request.
		header('X-Accel-Buffering: no');
	}

	# destroy output buffering
	public static function ob_clean() : void
	{
		if ($i = ob_get_level()) {
			# Clear buffers:
			while ($i-- && ob_end_clean());
			if (!ob_get_level()) header('Content-Encoding: ');
		}
	}

	# Flush all output buffers
	public static function ob_flush_all() : void
	{
		if ($i = ob_get_level()) {
			while ($i-- && ob_end_flush());
		}
		flush();
	}

	# Get content of all output buffers
	public static function ob_get_all() : ?string
	{
		if ($i = ob_get_level()) {
			while ($i--) {
				if (!$i) {
					return ob_get_clean();
				}
				ob_end_flush();
			}
		}
		return null;
	}

	# autoload() ads a few milliseconds on each call
	public static function autoload($name) : void
	{
		$name = ltrim($name,'\\');
		//trigger_error('Autoload: '.$name);
/*
		if (!preg_match('#^([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)+$#', $name)) {
			# PEAR bug and such
			return;
		}
*/
		# split class_name into segments where the
		# first segment is the library or component
		$path = explode(strpos($name, '\\') ? '\\' : '_', $name);
		if (empty($path[1])) { return; }

		/** Default spl_autoload also lowercases the filename */
		$path = array_map('strtolower', $path);

		/** When the class name is the directory itself add itself as filename  */
		if (!isset($path[2])) { $path[2] = $path[1]; }

		if (!static::includeFile(implode('/',$path) . '.php')) {
			/** Attempt to find class/interface/trait in global container directory */
			$lib = array_shift($path);
			if ($path[0] === $path[1]) { array_shift($path); }
			static::includeFile($lib.'/classes/'.implode('_',$path) . '.php');
		}
	}

	/** case-sensitive autoload for Zend Framework and such */
	public static function autoloadCS($name) : void
	{
		static::includeFile(strtr($name, '\\', DIRECTORY_SEPARATOR) . '.php');
	}

	public static function includeFile($file) : bool
	{
		if ($file = stream_resolve_include_path($file)) {
			include_once $file;
			return true;
		}
		return false;
	}

	public function loadDatabase() : ?\Poodle\SQL
	{
		if (!isset($this->SQL) && !empty($this->_readonly_data['dbms'])) {
			$dbms = $this->_readonly_data['dbms'];
			$this->SQL = new \Poodle\SQL($dbms['adapter'], $dbms['master'], $dbms['tbl_prefix'], $dbms['slave']);
			$this->SQL->debug = \Poodle::$DEBUG;
		}
		return $this->SQL;
	}

	public function loadConfig() : \Poodle\Config
	{
		if (!isset($this->CFG)) {
			$this->loadDatabase();
			if (!isset($this->SQL->TBL->config)) {
				$this->CFG = false;
				header('Retry-After: 3600');
				\Poodle\Report::error(503);
			} else {
				$this->CFG = \Poodle\Config::load();
				# Check CMS DB version to CMS Version
				if ($this->CFG->poodle->db_version < self::DB_VERSION) {
					\Poodle\Setup\Automatic::upgrade();
					exit;
				}
//				$this->SQL->debug = $this->CFG->debug->poodle_level;
			}
		}
		return $this->CFG;
	}

	protected function crypt(string $data, bool $encrypt) : string
	{
		$options = json_decode($this->_readonly_data['main_crypt'], true);
		if ($data && $options) {
			$c = new \Poodle\Crypt\Symmetric($options);
			return $encrypt
				? $c->Encrypt($data)
				: $c->Decrypt($data);
		}
		return $data;
	}
	public function encrypt(string $data) : string { return crypt($data, true); }
	public function decrypt(string $data) : string { return crypt($data, false); }

	public static function dataToJSON($data, int $options = 0)
	{
		return json_encode($data, $options | JSON_THROW_ON_ERROR | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
	}

	public static function isCallable($name) : bool
	{
		static $disabled = null;
		if (!$name || !\is_callable($name)) {
			return false;
		}
		if (null === $disabled) {
			$disabled = \ini_get('disable_functions') . ','
				. (\extension_loaded('suhosin') ? \ini_get('suhosin.executor.func.blacklist') : '');
			$disabled = \array_filter(\array_unique(\explode(',', strtolower($disabled))));
		}
		return !\in_array(strtolower($name), $disabled, true);
	}
}
\Poodle::$DIR_BASE = getcwd().DIRECTORY_SEPARATOR;

/** Use default spl_autoload first (lowercased) */
spl_autoload_extensions('.php');
spl_autoload_register();
/** Else use our extended autoload functions */
spl_autoload_register('Poodle::autoload');
spl_autoload_register('Poodle::autoloadCS');

if (POODLE_CLI) { include('bootstrap_cli.php'); }

#
# User-Agent
#

$_SERVER['HTTP_USER_AGENT'] = isset($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : '';

if (empty($_SERVER['HTTP_USER_AGENT'])
 || 5 > strlen($_SERVER['HTTP_USER_AGENT'])
 || !preg_match('#^[a-zA-Z]#', $_SERVER['HTTP_USER_AGENT']))
{
	error_log("Invalid User-Agent [{$_SERVER['REMOTE_ADDR']}]: {$_SERVER['HTTP_USER_AGENT']}");
	\Poodle\HTTP\Status::set(412);
	exit('You must send a correct User-Agent header so we can identify your browser');
}

# Nagios check_http, hyperspin.com, paessler.com/support/kb/questions/12 and StatusCake
if (preg_match('#(check_http/|hyperspin\.com|paessler.com|StatusCake|Test Certificate Info)#i', $_SERVER['HTTP_USER_AGENT'])) {
	exit('OK');
}

# http://support.microsoft.com/kb/293792
if ('contype' === $_SERVER['HTTP_USER_AGENT']) {
	\Poodle\HTTP\Headers::setContentType('application/'.(strpos($_SERVER['REQUEST_URI'], 'pdf')?'pdf':'octet-stream'));
	exit;
}

#
# Load default server behavior
#

class_exists('Poodle\\DateTime');

setlocale(LC_ALL, 'C');

header('X-Content-Type-Options: nosniff'); # IE8 google.com/search?q=X-Content-Type-Options
header('X-UA-Compatible: IE=edge');
header('imagetoolbar: no'); # IE
header('X-Powered-By: Poodle WCMS using PHP');
session_cache_limiter('');

putenv('HOME'); # cannot open /root/*: Permission denied
//if (!getenv('MAGIC')) { putenv('MAGIC='.__DIR__.(WINDOWS_OS?'/win32':'').'/magic.mime'); } # /usr/share/misc/magic

\Poodle\PHP\INI::init();

if (!isset($_SERVER['REQUEST_SCHEME'])) { $_SERVER['REQUEST_SCHEME'] = isset($_SERVER['HTTPS']) ? 'https' : 'http'; }
if (!isset($_SERVER['HTTP_ACCEPT'])) { $_SERVER['HTTP_ACCEPT'] = '*/*'; }
if (empty($_SERVER['SERVER_PROTOCOL'])) { $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0'; }
if (empty($_SERVER['PATH_INFO'])) {
	if (!empty($_SERVER['REDIRECT_PATH_INFO'])) {
		$_SERVER['PATH_INFO'] = $_SERVER['REDIRECT_PATH_INFO'];
	}
	else if (!empty($_SERVER['HTTP_X_PATH_INFO'])) {
		// IIS
		$_SERVER['PATH_INFO'] = $_SERVER['HTTP_X_PATH_INFO'];
	}
	else if (!empty($_SERVER['ORIG_PATH_INFO'])) {
		// cgi.fix_pathinfo=1
		$_SERVER['PATH_INFO'] = str_replace($_SERVER['PHP_SELF'],'',$_SERVER['ORIG_PATH_INFO']);
	}
	//if (empty($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO'])) { $_SERVER['PATH_INFO'] = str_replace($_SERVER['SCRIPT_NAME'],'',$_SERVER['ORIG_PATH_INFO']); } // cgi.fix_pathinfo=1
	if (empty($_SERVER['PATH_INFO'])) {
		$_SERVER['PATH_INFO'] = preg_replace('#\\?.*#', '', substr($_SERVER['REQUEST_URI'], strrpos($_SERVER['SCRIPT_NAME'], '/')));
	}
}
if (empty($_SERVER['HTTP_HOST'])) {
	$_SERVER['HTTP_HOST'] = (empty($_SERVER['SERVER_NAME']) ? '127.0.0.1' : $_SERVER['SERVER_NAME']);
}
unset($_SERVER['REDIRECT_PATH_INFO']);
unset($_SERVER['PATH_TRANSLATED']); # it's incorrect
unset($_GET['PATH_INFO']);

# Poodle entries
$_SERVER['HTTP_SEARCH_QUERY'] = '';
if (!empty($_GET['q'])) {
	$_SERVER['HTTP_SEARCH_QUERY'] = urldecode($_GET['q']);
} else if (!empty($_SERVER['HTTP_REFERER']) && preg_match('#[\?&](p|q|query|text)=([^&]+)#', $_SERVER['HTTP_REFERER'], $path)) {
	$_SERVER['HTTP_SEARCH_QUERY'] = urldecode($path[2]);
	$_SERVER['HTTP_REFERER'] = preg_replace('#^([^\?;]+).*$#D', '$1', $_SERVER['HTTP_REFERER']).'?'.$path[1].'='.$path[2];
}
$_SERVER['SERVER_MOD_REWRITE'] = !empty($_SERVER['SERVER_MOD_REWRITE']) || !empty($_SERVER['REDIRECT_SERVER_MOD_REWRITE']) || !empty($_SERVER['HTTP_X_SERVER_MOD_REWRITE']);
unset($_SERVER['REDIRECT_SERVER_MOD_REWRITE']);

# Harden PHP
unset($HTTP_RAW_POST_DATA);
$_REQUEST = array();
$_GET = new \Poodle\Input\GET($_GET);
if ($_POST || 'POST' === $_SERVER['REQUEST_METHOD']) {
/*	// Issue with raw post data like JSON
	if (!$_POST && !$_FILES) {
		$fp = fopen('php://input','r');
		if ($fp && fread($fp,1024)) {
			\Poodle\HTTP\Status::set(400); // Bad Request
			\Poodle\HTTP\Status::set(413); // Request Entity Too Large
			exit('POST data exceeds post_max_size: '.\Poodle\PHP\INI::get('post_max_size'));
		}
	}
*/
	$_POST = new \Poodle\Input\POST($_POST);
	if ($_FILES) { $_FILES = new \Poodle\Input\FILES($_FILES); }
}

#
# Let's configure the system
#

// IPv4 | IPv6 loopback | IPv6 link-local | IPv6 ULA
if (preg_match('#^(10|127.0.0|172.(1[6-9]|2\d|3[0-1])|192\.168|::1[:$]|fe80:|fc00:)#', $_SERVER['SERVER_ADDR'])
 || preg_match('#^(10|127.0.0|172.(1[6-9]|2\d|3[0-1])|192\.168|::1[:$]|fe80:|fc00:)#', $_SERVER['REMOTE_ADDR']))
{
	\Poodle::$DEBUG = \Poodle::DBG_ALL;
}

\Poodle::$URI_INDEX = \Poodle::$URI_ADMIN = $_SERVER['SCRIPT_NAME'];
if (POODLE_BACKEND) {
	\Poodle::$URI_INDEX = preg_replace('#/[^/]+(/[^/]+)$#D', '/index.php', \Poodle::$URI_INDEX);
} else {
	\Poodle::$URI_ADMIN = uri_dirname(\Poodle::$URI_ADMIN).'/admin/index.php';
}
\Poodle::$URI_BASE  = uri_dirname(\Poodle::$URI_INDEX);
\Poodle::$URI_MEDIA = \Poodle::$URI_BASE.'/media';
if ($_SERVER['SERVER_MOD_REWRITE']) {
	\Poodle::$URI_ADMIN = uri_dirname(\Poodle::$URI_ADMIN);
	\Poodle::$URI_INDEX = \Poodle::$URI_BASE;
	$_SERVER['PHP_SELF'] = preg_replace("#^{$_SERVER['SCRIPT_NAME']}/*#",uri_dirname($_SERVER['SCRIPT_NAME']).'/',$_SERVER['PHP_SELF']);
}

$path = strpos($_SERVER['REQUEST_URI'], '?');
$_SERVER['REQUEST_PATH'] = $path ? substr($_SERVER['REQUEST_URI'], 0, $path) : $_SERVER['REQUEST_URI'];

# tools.ietf.org/html/rfc2616#section-3.9
\Poodle::$UA_LANGUAGES = (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])
	? ''
	: preg_replace('#;q=(?!0\\.)([0-9]*)\\.?#', ';q=0.$1',strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'])));

if ('/' !== $_SERVER['PATH_INFO']) {
	if (preg_match('#\\.(jpe?g|png|webp|gif|svg)$#Di', $_SERVER['PATH_INFO'])) {
		\Poodle\Output\Image::display(ltrim($_SERVER['PATH_INFO'], '/'), false);
	}

	# Detect and strip ISO 639-1 language name + optional ISO-3166-1 country code (RFC 1766)
//	if (preg_match('#^/([a-z]{1,8}(?:-[a-z]{1,8})?)(/.*)?$#D', $_SERVER['PATH_INFO'], $path)) {
	if (preg_match('#^/([a-z]{1,3}(?:-[a-z]{1,8})?)(/.*)?$#D', $_SERVER['PATH_INFO'], $path)) {
		if (\Poodle\L10N::getIniFile($path[1])) {
			if (empty($path[2])) {
				\Poodle\HTTP\Headers::setLocation($path[1].'/'.(empty($_SERVER['QUERY_STRING'])?'':'?'.$_SERVER['QUERY_STRING']), 301);
				exit;
			}
			\Poodle::$UA_LANGUAGES = $path[1].';q=9,'.\Poodle::$UA_LANGUAGES;
			$_SERVER['PATH_INFO'] = $path[2];
		}
	}

	# Detect output extension and split directories
	if ('/' !== $_SERVER['PATH_INFO']) {
		if (preg_match('#^(/.+)(?:/|\.([a-z0-9]+))$#D', $_SERVER['PATH_INFO'], $path)) {
			$_SERVER['PATH_INFO'] = $path[1];
			if (empty($path[2])) {
				$_SERVER['PATH_INFO'] .= '/';
			} else if ('css' === $path[2] || 'js' === $path[2] || empty($_SERVER['REDIRECT_STATUS']) || $_SERVER['REDIRECT_STATUS'] < 400) {
				\Poodle::$EXT = $path[2];
			}
		}
	}
}
\Poodle::$PATH = new \Poodle\HTTP\PathInfo($_SERVER['PATH_INFO']);

unset($path);
