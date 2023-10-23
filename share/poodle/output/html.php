<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Output;

class HTML_Element
{
	public
		$dir,     # IMPLIED  -- direction for weak/neutral text --  (ltr|rtl)
		$lang;    # IMPLIED  -- language code -- BCP 47

	function __get($k)
	{
		if (property_exists($this,$k)) return $this->$k;
		trigger_error('Unknown property '.get_class($this).'::'.$k);
	}
	function __set($k, $v)
	{
		trigger_error((property_exists($this,$k)?'Failed to set value for ':'Unknown property ').get_class($this).'::'.$k);
	}
	function __isset($k) { return isset($this->$k); }
}

class HTML_META extends HTML_Element
{
	public
		$content,  # REQUIRED -- associated information -- (ALL,INDEX,FOLLOW,NOFOLLOW,NOINDEX,NONE)
		$name,     # IMPLIED  -- metainformation name -- (author|description|keywords|robots|revised|copyright) must begin with a letter ([A-Za-z]) and may be followed by any number of letters, digits ([0-9]), hyphens ("-"), underscores ("_"), colons (":"), and periods (".")
		$property, # IMPLIED  -- RDFa
		$scheme,   # IMPLIED  -- select form of content --
		$httpEquiv;

	function __construct(array $attributes)
	{
		foreach ($attributes as $k => $v) {
			$this->$k = $v;
		}
	}
}

class HTML_LINK extends HTML_Element
{
	private static
		$relations = 'preload|canonical|alternate|appendix|bookmark|chapter|contents|copyright|glossary|help|home|index|next|prev|section|start|stylesheet|subsection',
		$devices   = 'all|screen|tty|tv|projection|handheld|print|braille|aural';

	public
		$sizes,    # Specifies the size of the linked resource. Only for rel="icon"
		$href,     # Specifies the location of the linked document
		$hreflang, # language_code # Specifies the language of the text in the linked document
		$type,     # MIME_type # Specifies the MIME type of the linked document
		$id,       # Specifies a unique id for an element
		$media,    # Specifies on what device the linked document will be displayed. See self::$devices
		$rel,      # Specifies the relationship between the current document and the linked document. See self::$relations
		$rev,      # Specifies the relationship between the linked document and the current document. See self::$relations
		$title,    # Specifies extra information about an element
		$as;       # Specifies the rel=preload type

/*
	public $class;    # Specifies a classname for an element
	public $ltr;      # Specifies the text direction for the content in an element
	public $style;    # Specifies an inline style for an element

	<link rel="stylesheet" href="../css/default/poodle_debugger.css" type="text/css" media="screen" />
		<link rel="top" href="Forums/" title="Poodle CMS Forum Index" />
		<link rel="search" href="Forums/search/" title="Search" />
	<link rel="help" href="Forums/faq/" title="Forum FAQ" />
	<link rel="copyright" href="credits/" title="Copyrights" />
		<link rel="author" href="Members_List/" title="Members List" />
	<link rel="alternate" type="application/rss+xml" title="RSS" href="rss/news2.php" />
*/

	function __construct($rel, $href, array $properties = array())
	{
		foreach ($properties as $k => $v) {
			if (property_exists($this, $k)) {
				$this->$k = $v;
			}
		}
		$this->rel  = $rel;
		$this->href = $href;
	}
}

class HTML_STYLE extends HTML_Element
{
	public $type = 'text/css';
	private $media = array(
		'all'        => '',
		'screen'     => '',
		'tty'        => '',
		'tv'         => '',
		'projection' => '',
		'handheld'   => '',
		'print'      => '',
		'braille'    => '',
		'aural'      => '',
	);

	function __set($k, $v)
	{
		if (isset($this->media[$k]) && is_string($v)) {
			$this->media[$k] .= trim($v);
			return;
		}
		parent::__set($k, $v);
	}
}

class HTML_HEAD extends HTML_Element
{
	public
		$btf = false; // Use "below the fold" loading
	protected
		$parent,
		$css   = array(), # internal css files
		$link  = array(),
		$meta  = array(), # http://dublincore.org/
		$script= array(
			'cdata'=>'',
			'src'=>array(),
			'poodle'=>array()
		),
		$style,
		$btf_css,
		$title = '{RESOURCE/title}';

	function __construct(HTML $parent)
	{
		$this->parent = $parent;
		$this->style = new HTML_STYLE();
/*		if (!\Poodle::getKernel()->IDENTITY->id) {
			$this->css['poodle_auth'] = 'poodle_auth';
			$this->script['poodle']['poodle_auth'] = 'poodle_auth';
		}*/
		if (\Poodle::$DEBUG || \Poodle\Debugger::displayPHPErrors()) {
			$this->css['poodle_debugger'] = 'poodle_debugger';
			$this->script['poodle']['poodle_debugger'] = 'poodle_debugger';
		}
		if (empty($_COOKIE['PoodleTimezone'])) {
			$this->script['poodle']['poodle_timezone'] = 'poodle_timezone';
		}

		$CFG = \Poodle::getKernel()->CFG;
		if ($CFG) {
			if (!POODLE_BACKEND) {
				if ($CFG->output->google_analytics && empty($_SERVER['HTTP_DNT'])) {
					$this->addScriptData("Poodle.GA_ID='{$CFG->output->google_analytics}';");
				}
				if ($CFG->output->google_verification) {
					$this->addMeta('google-site-verification', $CFG->output->google_verification);
				}
			}

			if ($CFG->output->title_format) {
				$this->title = $CFG->output->title_format;
			}
		}
	}

	public function addMeta(string $name, string $content) : self
	{
		$content = trim($content);
		if (strlen($content)) {
			$this->meta[$name] = new HTML_META(array('name'=>$name, 'content'=>$content));
		}
		return $this;
	}

	public function addMetaRDFa(string $property, string $content) : self
	{
		$content = trim($content);
		if (strlen($content)) {
			$this->meta[] = new HTML_META(array('property'=>$property, 'content'=>$content));
		}
		return $this;
	}

	public function addHttpEquiv(string $name, string $content) : self
	{
		$content = trim($content);
		if (strlen($content)) {
			$this->meta[$name] = new HTML_META(array('httpEquiv'=>$name, 'content'=>$content));
		}
		return $this;
	}

	public function addLink($rel, $href, array $properties = array()) : self
	{
		$this->link[] = new HTML_LINK($rel, $href, $properties);
		return $this;
	}

	public function addCSS(string $src) : self
	{
		$src = strtr($src, '\/', '__');
		if (preg_match('#^([a-z0-9\\-_]+)$#Di',$src)) {
			$this->css[$src] = $src;
		}
		return $this;
	}

	public function addScript(string $src) : self
	{
		if (preg_match('#^([a-z0-9\\-_]+)$#Di',$src)) {
			$this->script['poodle'][$src] = $src;
		} else {
			// Should we append the path with \Poodle::$URI_BASE?
			if ('/' === $src[0]) {
				$src = \Poodle::$URI_BASE.$src;
			}
			$this->script['src'][$src] = $src;
		}
		return $this;
	}

	public function addScriptData(string $v) : self
	{
		$this->script['cdata'] .= $v;
		return $this;
	}

	function __get($k)
	{
		if ('script' === $k) {
			$v = $this->script;
			sort($v['poodle']);
			$v['src'][] = '/'.$this->parent->L10N->lng.'/javascript/'.implode(';',$v['poodle']).'.js';
			return array(
				'cdata'=>$v['cdata'],
				'src'  =>$v['src']
			);
		}
		if ('link' === $k) {
			$v = $this->link;
			sort($v);
			if (!$this->btf && $css = $this->CSSURI()) {
				$v[] = new HTML_LINK('stylesheet', $css);
//				$v[] = new HTML_LINK('preload', $css, array('as'=>'style'));
			}
			return $v;
		}

		if ('btf_css' === $k) {
			if ($this->btf && $css = $this->CSSURI()) {
				return \Poodle::$URI_BASE . $css;
			}
			return null;
		}

		if ('title' === $k) {
			$K = \Poodle::getKernel();
			return strtr($this->title, array(
				'{crumbs}' => $this->parent->crumbs->asString(),
				'{crumbs_reversed}' => $this->parent->crumbs->asString(true),
				'{RESOURCE/title}' => $K->RESOURCE ? $K->RESOURCE->title : '',
				'{site/name}' => $K->CFG ? $K->CFG->site->name : '',
			));
		}

		return parent::__get($k);
	}

	function __set($k, $v)
	{
		if ('link' === $k && $v instanceof HTML_LINK) { $this->link[$v->href] = $v; return; }
		if ('meta' === $k && $v instanceof HTML_META) { $this->meta[$v->name] = $v; return; }
		if ('script' === $k && is_string($v))
		{
			$v = trim($v);
			if (preg_match('#^([a-z0-9\\-_]+)$#Di',$v)) {
				$this->script['poodle'][$v] = $v;
			} else
			if (preg_match('#^([a-z]+:)?/[^\s]+$#Di',$v)) {
				$this->script['src'][$v] = $v;
			} else {
				$this->script['cdata'] .= $v;
			}
			return;
		}
		if ('title' === $k && is_string($v)) { $this->title = $v; return; }
		parent::__set($k, $v);
	}

	protected function CSSURI()
	{
		if ($css = $this->css) {
			sort($css);
			return '/css/'.$this->parent->tpl_name.'/'.implode(';',$css).'.css';
		}
	}
}

class HTML extends \Poodle\Output
{
	public
		$body = '',
		$DTD  = 'html5';

	# en.wikipedia.org/wiki/List_of_HTTP_header_fields#Responses
	protected
		$http = array(
			'Content-Type' => 'text/html',
		),
		$script  = array(
			'cdata'=>'',
			'src'=>array()
		);

	function __construct()
	{
		parent::__construct();
		$this->head = new HTML_HEAD($this);
		$this->head->addCSS('style');
	}

	function __get($k)
	{
		if ('head' === $k) return $this->head;
		if ('script' === $k) return $this->script;
		return parent::__get($k);
	}

	public function addScript(string $src)   : void { $this->script['src'][$src] = $src; }
	public function addScriptData(string $v) : void { $this->script['cdata'] .= $v; }

	# TPL
	public function display(string $filename, $data=null, int $mtime=0, $final=false) : bool
	{
		$this->start();
		if ($this->tpl_header) {
			return parent::display($filename, $data, $mtime, $final ? \Poodle\TPL::OPT_END_PARSER : 0);
		} else {
			$this->body .= $this->toString($filename, $data, $mtime);
		}
		return true;
	}

	public static function parseAttributes(string $name, array $attribs, array $ctx_attribs = array()) : string
	{
		switch ($name)
		{
/*
		case 'a':
			if (empty($attribs['rel']) && !empty($attribs['href']) && \strpos($attribs['href'], '://') {
				$attribs['rel'] = 'noreferrer';
			}
			break;
*/
		case 'time':
			if (!empty($attribs['datetime'])) {
				if ($attribs['datetime'] instanceof \DateTime) {
					$attribs['datetime'] = (new \Poodle\DateTime($attribs['datetime'], 'UTC'))->format('Y-m-d\TH:i:s\Z');
				} else if (is_numeric($attribs['datetime'])) {
					$attribs['datetime'] = \gmdate('Y-m-d\TH:i:s\Z', $attribs['datetime']);
				}
			}
			break;
		case 'option':
			if (isset($attribs['selected'])) $attribs['selected'] = ('' === $attribs['selected'] || !empty($attribs['selected']));
			break;
		case 'input':
			$ctx_attribs = array_merge($ctx_attribs, $attribs);
			if (empty($ctx_attribs['type'])) {
				$attribs['type'] = 'text';
			} else {
				$fn = 'gmdate';
				$f = null;
				switch ($ctx_attribs['type'])
				{
				case 'date':     $f = 'Y-m-d'; break;
				case 'datetime': $f = 'Y-m-d\TH:i:s\Z'; break;
				case 'datetime-local': $f = 'c'; $fn = 'date'; break;
				case 'month':    $f = 'Y-m'; break;
				case 'time' :    $f = 'H:i:s'; break;
				case 'week':     $f = 'Y-\WW'; break;
				}
				if ($f) {
					if (isset($attribs['value'])) {
						$attribs['value'] = static::getInputDate($attribs['value'], $fn, $f);
					}
					if (isset($attribs['min'])) {
						$attribs['min'] = static::getInputDate($attribs['min'], $fn, $f);
					}
					if (isset($attribs['max'])) {
						$attribs['max'] = static::getInputDate($attribs['max'], $fn, $f);
					}
				}
			}
			if (isset($attribs['autofocus'])) $attribs['autofocus'] = ('' === $attribs['autofocus'] || !empty($attribs['autofocus']));
			// no break;
		case 'select':
		case 'textarea':
			// input, select
			if (isset($attribs['multiple'])) $attribs['multiple'] = ('' === $attribs['multiple'] || !empty($attribs['multiple']));
			// input, textarea
			if (isset($attribs['readonly'])) $attribs['readonly'] = ('' === $attribs['readonly'] || !empty($attribs['readonly']));
			// input, select, textarea
			if (isset($attribs['required'])) $attribs['required'] = ('' === $attribs['required'] || !empty($attribs['required']));
			break;
		}
		if (isset($attribs['hidden'])) {
			$attribs['hidden'] = ('' === $attribs['hidden'] || !empty($attribs['hidden']));
		}
		if (!empty($attribs['aria-haspopup'])) {
			$attribs['aria-haspopup'] = 'true';
		}
		// button, menuitem, optgroup, option, select, textarea
		if (isset($attribs['disabled'])) {
			$attribs['disabled'] = ('' === $attribs['disabled'] || !empty($attribs['disabled']));
		}
		// input, menuitem
		if (isset($attribs['checked'])) {
			$attribs['checked']  = ('' === $attribs['checked'] || !empty($attribs['checked']));
		}
		if ('img' === $name) {
			if (!isset($attribs['loading'])) {
				$attribs['loading'] = 'lazy';
			}
/*
			if (isset($attribs['src']) && false === strpos($attribs['src'], '//')) {
				$attribs['src'] = preg_replace('#(/[^/]+\\.(jpe?g|png))$#D', '/webp$1.webp', $attribs['src']);
//				$attribs['loading'] = 'lazy';
			}
*/
		}
		if (('audio' === $name || 'video' === $name) && !isset($attribs['preload'])) {
			$attribs['preload'] = 'none';
		}

		return parent::parseAttributes($name, $attribs, $ctx_attribs);
	}

	protected static function getInputDate($v, $fn, $f)
	{
		if (is_int($v) || ctype_digit($v)) {
			return 0 == $v ? '' : $fn($f,$v);
		}
		if ($v instanceof \DateTime) {
			return $v->format($f);
		}
		return $v;
	}

}
