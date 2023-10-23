<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class Breadcrumbs extends \ArrayIterator
{
	function __construct(Output $OUT)
	{
		parent::__construct();

		if (POODLE_BACKEND) {
			$CFG = \Poodle::getKernel()->CFG;
			$label = $OUT->L10N['Administration'];
/*			if ($CFG && $CFG->site->name) {
				$label = sprintf($OUT->L10N['%s management'], $CFG->site->name);
			}*/
			$this->append($label, '/admin/');
		} else {
			$SQL = \Poodle::getKernel()->SQL;
			if ($SQL && isset($SQL->TBL->resources, $SQL->TBL->resources_data)) {
				$uri  = '/';
				$uris = array($SQL->quote($uri));
				$parts = explode('/', trim($_SERVER['PATH_INFO'],'/'));
				if ($parts[0]) {
					foreach ($parts as $part) {
						$uris[] = $SQL->quote($uri .= $part);
						$uris[] = $SQL->quote($uri .= '/');
					}
				}
				$qr = $SQL->query("SELECT
					resource_uri AS uri,
					(SELECT resource_title
						FROM {$SQL->TBL->resources_data} AS rd
						WHERE rd.resource_id = r.resource_id
						  AND l10n_id IN (0,1,{$OUT->L10N->id})
						  AND resource_status = 2
						ORDER BY l10n_id DESC, resource_mtime DESC
						LIMIT 1
					)
				FROM {$SQL->TBL->resources} AS r
				WHERE resource_uri IN (".implode(',',array_reverse($uris)).")
				ORDER BY LENGTH(resource_uri) ASC");
				while ($r = $qr->fetch_row()) {
					$this->append($r[1]?:basename($r[0]), $r[0]);
				}
			}
		}
	}

	public function __toString()
	{
		return $this->asString();
	}

	public function asString(bool $reversed=false, int $offset=0, ?int $length=null)
	{
		$crumbs = array();
		foreach ($this as $crumb) {
			$crumbs[] = $crumb['label'];
		}
		if ($offset || $length) {
			$crumbs = array_slice($crumbs, $offset, $length);
		}
		if ($reversed) { $crumbs = array_reverse($crumbs); }
		$CFG = \Poodle::getKernel()->CFG;
		$c = $CFG ? $CFG->output->crumb : '';
		if (!$c) $c = '▸';
		return implode(" {$c} ", $crumbs);
	}

	public function clear()
	{
		parent::__construct(array());
	}

	public function append($label, ?string $uri=null) : self
	{
		parent::append(array(
			'label' => $label,
			'uri'   => strlen($uri) ? \Poodle\URI::resolve($uri) : $uri
		));
		return $this;
	}
/*
	public void append ( mixed $value )
	public void asort ( void )
	__construct ( mixed $array )
	public int count ( void )
	mixed current ( void )
	public array getArrayCopy ( void )
	public void getFlags ( void )
	mixed key ( void )
	public void ksort ( void )
	public void natcasesort ( void )
	public void natsort ( void )
	void next ( void )
	public void offsetExists ( string $index )
	public mixed offsetGet ( string $index )
	public void offsetSet ( string $index , string $newval )
	public void offsetUnset ( string $index )
	void rewind ( void )
	void seek ( int $position )
	public string serialize ( void )
	public void setFlags ( string $flags )
	public void uasort ( string $cmp_function )
	public void uksort ( string $cmp_function )
	public string unserialize ( string $serialized )
	bool valid ( void )
*/
}

class Breadcrumb
{
	public
		$label,
		$uri;
}

class OutputHeadDummy
{
	public function addMeta($name, $content) { return $this; }
	public function addMetaRDFa($property, $content) { return $this; }
	public function addHttpEquiv($name, $content) { return $this; }
	public function addLink($rel, $href, array $properties = array()) { return $this; }
	public function addCSS($src) { return $this; }
	public function addScript($src) { return $this; }
	public function addScriptData($v) { return $this; }
}

abstract class Output extends \Poodle\TPL
{
	use \Poodle\Events;

	public
		$title = null,
		$tpl_header = null,
		$tpl_footer = null,
		$tpl_layout = 'default';

	# en.wikipedia.org/wiki/List_of_HTTP_header_fields#Responses
	protected
		$head, // OutputHead
		$http = array(
			'Content-Type' => 'application/xml',
		),
		$tpl_name = 'default';

	private
		$crumbs,
		$started = false;

	function __construct()
	{
		parent::__construct();
		$this->head = new OutputHeadDummy();
		// Only Internet Explorer needs the useless P3P header to accept cookies
		$this->http['P3P'] = 'CP="CAO DSP COR CURa ADMa DEVa OUR IND PHY ONL UNI COM NAV INT DEM PRE"';
		$K = \Poodle::getKernel();
		if ($K->CFG && !POODLE_BACKEND) {
			$this->setTPLName($K->CFG->output->template);
		}
//		if (!empty($_GET['tpl'])) $this->setTPLName($_GET['tpl']);
		if (POODLE_BACKEND) {
			$this->tpl_layout = 'admin';
		}
		$this->http['Content-Type'] .= '; charset='.\Poodle::CHARSET;
	}

	function __get($k)
	{
		if ('crumbs' === $k) {
			if (!$this->crumbs) {
				$this->crumbs = new Breadcrumbs($this);
			}
			return $this->crumbs;
		}
		return parent::__get($k);
	}

	public function setTPLName(string $name) : void
	{
		if (preg_match('#^[a-z0-9_]+$#', $name) && is_dir('tpl/'.$name)) {
			$this->tpl_name = $name;
			$this->tpl_path = 'tpl/'.$name.'/';
		}
	}

	public function http(string $field, string $value) : void { $this->http[$field] = $value; }

	public function addScript(string $src) : void {}

	public function addScriptData(string $v) : void {}

	final public function started() : bool { return $this->started; }

	final public function send_headers() : void
	{
		if ($this->http && !POODLE_CLI) {
			if (!headers_sent($file, $line)) {
				$http = &$this->http;
				if (!isset($http['Cache-Control'])) {
					$K = \Poodle::getKernel();
					if (XMLHTTPRequest || POODLE_BACKEND || !empty($K->RESOURCE['cache-control'])) {
						$http['Pragma'] = 'no-cache';
						$http['Cache-Control'] = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0';
//						$http['Cache-Control'] = 'no-cache';
//						session_cache_limiter('nocache');
					} else if ($K->IDENTITY && $K->IDENTITY->id) {
						$http['Cache-Control'] = 'private, max-age='.session_cache_expire().', post-check=0, pre-check='.session_cache_expire();
//						$http['Cache-Control'] = 'private'.(POODLE_BACKEND?', no-store':'').', max-age=0, pre-check=0, post-check=0';
//						session_cache_limiter('private');
					} else {
						$http['Cache-Control'] = 'public, max-age='.session_cache_expire();
//						session_cache_limiter('public');
					}
				}
				if (!isset($http['Content-Language'])) $http['Content-Language'] = $this->L10N->lng;
				if (!isset($http['Date'])) $http['Date'] = gmdate('D, d M Y H:i:s \G\M\T'); # RFC 1123
//				'Expires' => gmdate('D, d M Y H:i:s \G\M\T'), # RFC 1123
//				'Last-Modified' => gmdate('D, d M Y H:i:s \G\M\T'),
				foreach ($http as $name => $value) { header("$name: $value"); }
			} else {
				\Poodle\Debugger::error(E_RECOVERABLE_ERROR, 'Headers already sent', $file, $line);
			}
		}
	}

	# $mode = bitwise: PHP_OUTPUT_HANDLER_START, PHP_OUTPUT_HANDLER_CONT & PHP_OUTPUT_HANDLER_END
	public static function ob_handler(string $buffer, int $mode) : string
	{
		static $html = false;
		if ($mode & PHP_OUTPUT_HANDLER_START) { $html = preg_match('#Content-Type:\s+[a-z]+/[htx]+ml#i',implode(' ',headers_list())); }
		if ($html) {
			//$buffer = preg_replace('#\n\s+#',"\n", preg_replace('#\s+\n#',"\n", preg_replace('#\s+</#','</', $buffer)));
			if ($mode & PHP_OUTPUT_HANDLER_END && \Poodle::$DEBUG & \Poodle::DBG_EXEC_TIME && !XMLHTTPRequest) {
				$buffer .= '<!-- Page generated: '.round((microtime(true)-$_SERVER['REQUEST_TIME_FLOAT']),4).' seconds -->';
			}
		}
		# Compress output if server/php config allows
		return (\Poodle::$COMPRESS_OUTPUT ? ob_gzhandler($buffer, $mode) : $buffer);
	}

	public function start() : bool
	{
		if ($this->started) { return false; }
		$this->started = true;
		$file = $this->tpl_path.'tpl.php';

		// Also load language files for the root template (e.g. /tpl/[project_name]/l10n/en.php) if present
		if (\Poodle::getFile("tpl/{$this->tpl_name}/l10n/{$this->L10N->lng}.php")) {
			// Load language files
			$this->L10N->load('tpl_'.$this->tpl_name);
		}

		if (is_file($file)) {
			include_once $file;
			$class = 'Poodle_Output_TPL_'.$this->tpl_name;
			if (class_exists($class, false)) {
				$callable = "{$class}::start";
				if (is_callable($callable)) {
					call_user_func($callable, $this);
				}
			} else {
				trigger_error("Class {$class} not found");
			}
		} else {
			trigger_error("{$file} not found");
		}

		if (!headers_sent()) {
			# Start output buffer handler
			ob_start(get_class($this) . '::ob_handler');

			$this->send_headers();
		}

		$this->triggerEvent('afterStart');

		if ($this->tpl_header) {
			parent::display($this->tpl_header, null, 0, self::OPT_PUSH_DOCTYPE);
		}
		return true;
	}

	# TPL
	public function finish() : void
	{
		if ($this->started()) {
			if ($this->tpl_footer) {
				parent::display($this->tpl_footer, null, 0, self::OPT_END_PARSER);
//			} else if (XMLHTTPRequest) {
//				echo $this->body;
			} else {
				parent::display('layouts/'.$this->tpl_layout, null, 0, self::OPT_PUSH_DOCTYPE | self::OPT_XMLREADER);
			}
		}
	}

	public static function minifyXML(string $str) : string
	{
		$str = preg_replace('#\\s*(<[^>]+>)\\s*#', '$1', $str);
		return preg_replace('#\\R#', "\n", $str);
	}

}
