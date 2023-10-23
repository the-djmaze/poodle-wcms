<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class TPL extends \Poodle\TPL\Context
{
	const
		OPT_PUSH_DOCTYPE    = 1,
		OPT_END_PARSER      = 2,
		OPT_XMLREADER       = 4,
		OPT_NO_PATH_METHODS = 8;

	public static
		$USE_EVAL = true,
		$CACHE_DIR = true,
		$ERROR_MODE = 0; # 0 = html, 1 = parsed html

	public
		$L10N,
		$DTD,

		$bodylayout = '';

	protected
		$tpl_path = 'tpl/default/',
		$tpl_type = 'html';

	protected static
		$ALLOW_PATH_METHODS = true;

	private
		$_files = array(),
		$_xml_parser,
		$_total_time = 0;

	function __construct()
	{
		parent::__construct();
		$this->L10N = new \Poodle\L10N();
		if (!self::$USE_EVAL) {
			stream_wrapper_register('tpl', 'Poodle\\TPL\\StreamWrapper');
		}
		if (self::$CACHE_DIR) {
			$dir = \Poodle::getKernel()->cache_dir;
			self::$CACHE_DIR = $dir ? rtrim($dir,'/\\') . '/' : false;
		}
	}

	function __get($key)
	{
		$SQL = \Poodle::getKernel()->SQL;
		switch ($key)
		{
		case 'bugs':         return \Poodle\Debugger::displayPHPErrors() ? \Poodle\Debugger::report() : null;
		case 'bugs_json':    return \Poodle\Debugger::displayPHPErrors() ? json_encode(\Poodle\Debugger::report()) : null;
		case 'memory_usage': return (is_object($this->L10N) ? $this->L10N->filesizeToHuman(memory_get_peak_usage()) : memory_get_peak_usage());
		case 'parse_time':   return microtime(true)-$_SERVER['REQUEST_TIME_FLOAT'];
		case 'tpl_time':     return $this->_total_time;
		case 'debug_json':
			$r = array();
			if (\Poodle\Debugger::displayPHPErrors()) {
				$bugs = array();
				foreach (\Poodle\Debugger::report() as $file => $log) {
					$bugs[] = array('file' => $file, 'log' => $log);
				}
				if ($bugs) { $r['php'] = $bugs; }
				unset($bugs);
			}
			if (\Poodle::$DEBUG) {
				if (\Poodle::$DEBUG & \Poodle::DBG_MEMORY) {
					$r['memory'] = memory_get_peak_usage();
				}
				if (\Poodle::$DEBUG & \Poodle::DBG_TPL_TIME) {
					$r['tpl_time'] = $this->_total_time;
				}
				if (\Poodle::$DEBUG & \Poodle::DBG_TPL_INCLUDED_FILES) {
					$r['tpl_files'] = \Poodle::shortFilePath($this->_files);
					sort($r['tpl_files']);
				}
				if ((\Poodle::$DEBUG & \Poodle::DBG_SQL || \Poodle::$DEBUG & \Poodle::DBG_SQL_QUERIES) && is_object($SQL)) {
					$r['sql'] = array('count' => $SQL->total_queries, 'time' => $SQL->total_time);
					if (\Poodle::$DEBUG & \Poodle::DBG_SQL_QUERIES && $SQL->querylist) {
						$r['sql']['queries'] = array();
						foreach ($SQL->querylist as $f => $q) {
							$r['sql']['queries'][$f] = $q;
						}
					}
				}
				if (\Poodle::$DEBUG & \Poodle::DBG_INCLUDED_FILES) {
					$r['included_files'] = \Poodle::shortFilePath(array_filter(get_included_files(), function($v){return false===strpos($v,'tpl://');}));
					sort($r['included_files']);
				}
				if (\Poodle::$DEBUG & \Poodle::DBG_DECLARED_CLASSES) {
					$r['declared_classes'] = get_declared_classes();
				}
				if (\Poodle::$DEBUG & \Poodle::DBG_DECLARED_INTERFACES) {
					$r['declared_interfaces'] = get_declared_interfaces();
				}
				// get_defined_functions()
				if (\Poodle::$DEBUG & \Poodle::DBG_PARSE_TIME) {
					$r['parse_time'] = microtime(true)-$_SERVER['REQUEST_TIME_FLOAT'];
				}
			}
			return str_replace('\\/','/',json_encode($r));
		}
		return parent::__get($key);
	}

	public function init() {}

	# Gecko supports background-image
	public function uaSupportsSelectOptionBgImage() : bool
	{
		return 'gecko' === \Poodle\HTTP\Client::engine()->name;
	}

	public function toString(string $filename, $data = null, int $mtime = 0, int $options = 0) : ?string
	{
		if ($data && !($data instanceof \Poodle\TPL\Context) && !preg_match('#((tal|i18n|xsl):|(<[^>]+href|src|action|formaction)="/)#',$data)) {
			return $data;
		}
		ob_start();
		if (self::display($filename, $data, $mtime, $options | self::OPT_XMLREADER)) {
			return ob_get_clean();
		}
		echo ob_get_clean();
		return null;
	}

	protected function evalCache(string $key, $ctx) : bool
	{
		if (self::$USE_EVAL) {
			return $this->evalData(\Poodle::getKernel()->CACHE->get($key), $ctx);
		}
		include('tpl://cache/'.$key);
		return true;
	}

	protected function evalData(string $data, $ctx) : bool
	{
		if (self::$USE_EVAL) {
			return ($data && false !== eval('?>'.$data));
		}
		include('tpl://data/'.base64_encode($data));
		return true;
	}

	public function display(string $filename, $data = null, int $mtime = 0, $options = 0) : bool
	{
		$ctx = $this;
		if ($data instanceof \Poodle\TPL\Context) {
			$ctx  = $data;
			$data = null;
		}
		if (!$data && !is_string($filename)) {
			trigger_error('No data to display');
			return false;
		}
		if ($data && !preg_match('#((tal|i18n|xsl):|(<[^>]+href|src|action|formaction)="/)#',$data)) {
			echo $data;
			return true;
		}

		$time = microtime(true);
		$tpl_file = $this->tpl_file;
		$this->tpl_file = $filename.'.xml';

		if (!$this->_xml_parser) {
			$this->_xml_parser = new \Poodle\TPL\Parser($this);
		}

//		static::$ALLOW_PATH_METHODS = !$data;
//		static::$ALLOW_PATH_METHODS = !($options & self::OPT_NO_PATH_METHODS);

		$parsed = false;
		$CACHE = \Poodle::getKernel()->CACHE;
		$error = $file = $cache_file = $cache_key = null;
		if ($filename) {
			if ($data) {
				$cache_key = "tpl/_db/{$this->tpl_type}/".$this->tpl_file;
			} else {
				if (!$mtime && $file = $this->findFile($filename)) {
					$mtime = filemtime($file);
				}
				$cache_key = $this->tpl_path . $this->tpl_type . '/' . $this->tpl_file;
			}
			$cache_key = strtr($cache_key, '\\', '/');
			if (self::$CACHE_DIR) {
				$cache_file = self::$CACHE_DIR . $cache_key . '.php';
				if (is_file($cache_file) && (!$mtime || filemtime($cache_file) > $mtime)) {
					if ($options & self::OPT_PUSH_DOCTYPE) {
						$this->push_doctype();
					}
					include $cache_file;
					$parsed = true;
				}
			} else
			if ($CACHE && $CACHE->exists($cache_key) && (!$mtime || $CACHE->mtime($cache_key) > $mtime)) {
				if ($options & self::OPT_PUSH_DOCTYPE) {
					$this->push_doctype();
				}
				$parsed = $this->evalCache($cache_key, $ctx);
			}
		}

		if (!$parsed) {
			try {
				if ($data) {
					$file = $filename;
				} else {
					if (!$file && $filename) {
						$file = $this->findFile($filename);
					}
					if (!$file) {
						throw new \Exception("TPL file {$filename} not found");
					}
				}
				if ($options & self::OPT_XMLREADER) {
					$parsed = $this->_xml_parser->parse_xml($file, $data);
				} else {
					$parsed = $this->_xml_parser->parse_chunk($file, $data, $options & self::OPT_END_PARSER);
				}
			} catch (\Throwable $e) {
				$error = array(
					'type' => E_USER_WARNING,
					'message' => $e->getMessage(),
					'file' => $filename,
					'line' => $e->getLine(),
				);
			}
			$pdata = $this->_xml_parser->data;
			$this->_xml_parser->data = '';
			if ($parsed) {
				if ($options & self::OPT_PUSH_DOCTYPE) {
					$this->push_doctype();
				}
				if (strlen($pdata)) {
					$err_level = error_reporting(error_reporting() & ~E_PARSE & ~E_USER_WARNING);
					if ($this->evalData($pdata, $ctx)) {
						$data = null;
						if ($cache_file) {
							$dir = dirname($cache_file);
							if (!is_dir($dir)) {
								mkdir($dir, 0777, true);
							}
							file_put_contents($cache_file, $pdata);
						} else if ($cache_key && $CACHE) {
							$CACHE->set($cache_key, $pdata);
						}
						$pdata = null;
					} else {
						$error = error_get_last();
						$error['file'] = $filename;
					}
					error_reporting($err_level);
				} else {
					$error = array(
						'type' => E_USER_WARNING,
						'message' => 'Parsed data resulted in an empty string',
						'file' => $filename,
						'line' => 0,
					);
				}
			} else if ($this->_xml_parser->errors) {
				$error = $this->_xml_parser->errors[0];
			}
			if ($error) {
				$line = (int)$error['line'];
/*
				if ((isset($error['type']) && 4 == $error['type']) || (1 === self::$ERROR_MODE && !isset($error['node']))) {
					$lines = preg_split("#<br[^>]*>#", highlight_string($pdata, true));
				} else {
					$count = 1;
					$lines = preg_replace('#\R#', "\n", $parsed ? $pdata : $data ?: file_get_contents($error['file']));
					$lines = preg_split("#<br[^>]*>#", highlight_string($lines, true));
				}
				$l = max(0, $line-1);
				$lines[$l] = '<b style="background-color:#fcc">'.$lines[$l].'</b>';
				echo "<h1>{$error['message']} in {$filename} on line {$line}</h1>";
				echo "\n".implode("<br/>\n", $lines);
				exit;
*/
				throw new \ParseError("{$error['message']} in {$filename} on line {$line}");
			}
		}
		$this->tpl_file = $tpl_file;
		$this->_total_time += microtime(true)-$time;
		return true;
	}

	# Check for a valid file
	public function findFile(string $filename) : ?string
	{
		$files = array("{$this->tpl_path}{$this->tpl_type}/{$filename}.xml");
		if ('default' !== basename($this->tpl_path)) {
			$files[] = dirname($this->tpl_path)."/default/{$this->tpl_type}/{$filename}.xml";
		}
		$i = explode('/',$filename,3);
		if (isset($i[2])) {
			$files[] = "{$i[0]}/{$i[1]}/tpl/{$this->tpl_type}/{$i[2]}.xml";
		} else if (isset($i[1])) {
			$files[] = "{$i[0]}/tpl/{$this->tpl_type}/{$i[1]}.xml";
		}
		foreach ($files as $file) {
			if ($file = \Poodle::getFile($file)) {
				if (\Poodle::$DEBUG & \Poodle::DBG_TPL_INCLUDED_FILES) {
					$this->_files[] = $file;
				}
				return $file;
			}
		}
		trigger_error("\\Poodle\\TPL::findFile({$this->tpl_path}{$this->tpl_type}/{$filename}.xml): failed to open stream: No such file or directory", E_USER_WARNING);
		return null;
	}

	private function push_doctype() : void
	{
		if ('xml' === $this->tpl_type || $this->_xml_parser->isXML()) {
			echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		}
		echo $this->_xml_parser->doctype();
	}

	public static function parseAttributes(string $name, array $attribs, array $ctx_attribs = array()) : string
	{
		$result = '';
		foreach ($attribs as $name => $value) {
			if (false !== $value && (is_scalar($value) || is_object($value))) {
				$result .= " {$name}=\"".(true === $value ? '' : htmlspecialchars($value))."\"";
			}
		}
		return $result;
	}

	protected static function echo_data(?string $data) : void
	{
		echo htmlspecialchars($data, ENT_NOQUOTES);
	}

	protected static function resolveURI(?string $uri) : ?string
	{
		return is_null($uri) ? null : \Poodle\URI::resolve($uri);
	}

	protected static function get_valid_option(array $options)
	{
		foreach ($options as $value) { if ($value) { return $value; } }
		return null;
	}

	/**
	 * helper method for self::path()
	 */
	private static function pathError($base, string $path, string $current) : void
	{
		$basename = '';
		$file = '';
		$line = 0;
		# self::path gets data in format ($object, "rest/of/the/path"),
		# so name of the object is not really known and something in its place
		# needs to be figured out
		if ($current !== $path) {
			$pathinfo = " (in path '.../{$path}')";
			if (preg_match('#([^/]+)/'.preg_quote($current, '#').'(?:/|$)#', $path, $m)) {
				$basename = "'{$m[1]}'";
			}
		} else $pathinfo = '';

		$bt = debug_backtrace();
		foreach ($bt as $i => $item) {
			if ('eval' === $item['function'] || 'include' === $item['function']) {
				$line = $bt[$i-1]['line'];
			}
			if (isset($item['object'])) {
				$file = $item['object']->tpl_file;
				break;
			}
		}

		if (is_array($base)) {
			$msg = "Array {$basename} doesn't have key named '{$current}'{$pathinfo}";
		} else
		if (is_object($base)) {
			$msg = get_class($base)." object {$basename} doesn't have method/property named '{$current}'{$pathinfo}";
		}
		else {
			$msg = trim("Attempt to read property '{$current}'{$pathinfo} from ".gettype($base)." value {$basename}");
		}
		\Poodle\Debugger::error(E_USER_NOTICE, $msg, $file, $line);
	}

	/**
	 * Resolve TALES path starting from the first path element.
	 * The TALES path : object/method1/10/method2
	 * will call : self::path($ctx->object, 'method1/10/method2')
	 *
	 * @param mixed  $base        first element of the path ($ctx)
	 * @param string $path        rest of the path
	 * @param bool   $check_only  when true, just return true/false if path exists
	 *
	 * @return mixed
	 */
	private static function path($base, string $path, bool $check_only = false)
	{
		if (null === $base) {
			return null;
			self::pathError($base, $path, $path);
		}

		$keys = explode('/', $path);
		$last = count($keys) - 1;
		foreach ($keys as $i => $key) {
			if (is_object($base)) {
				# look for property
				if (property_exists($base, $key)) {
					$base = $base->$key;
					continue;
				}

				if ($base instanceof \ArrayAccess && $base->offsetExists($key)) {
					$base = $base->offsetGet($key);
					continue;
				}

				# look for method. Both method_exists and is_callable are required because of __call() and protected methods
				if (static::$ALLOW_PATH_METHODS && method_exists($base, $key) && is_callable(array($base, $key))) {
					if ($check_only && $i === $last) { return true; }
					$base = $base->$key();
					continue;
				}

				if ($base instanceof \Countable && ('length' === $key || 'size' === $key)) {
					$base = count($base);
					continue;
				}

				# look for isset (priority over __get)
				if (method_exists($base, '__isset')) {
					if (isset($base->$key)) {
						$base = $base->$key;
						continue;
					}
					if ($check_only) {
						return false;
					}
				}
				# ask __get
				if (method_exists($base, '__get')) {
					$base = $base->$key;
					continue;
				}
/* Disabled, disputable if this should be allowed or not
				# magic method call
				if (static::$ALLOW_PATH_METHODS && method_exists($base, '__call')) {
					if ($check_only && $last === $i) { return true; }
					try
					{
						$base = $base->__call($key, array());
						continue;
					}
					catch(\Throwable $e){}
				}
*/
			}

			else if (is_array($base)) {
				# key or index
				if (array_key_exists($key, $base)) {
					$base = $base[$key];
					continue;
				}
			}

			else if (is_string($base)) {
				# access char at index
				if (is_numeric($key)) {
					$base = $base[$key];
					continue;
				}
			}

			# if this point is reached, then the part cannot be resolved
			if ($check_only) {
				return false;
			}
			self::pathError($base, $path, $key);
			return null;
		}

		return $check_only ? !is_null($base) : $base;
	}

}
