<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\TPL;

class Repair
{
	public
		$errors;

	private
		$data,
		$nodes,
		$parser,
		$DTD = null;

	private static
		$REGEX_TAG  = '#</?([^\s0-9\p{P}][\p{L}\p{N}\p{P}]*)(?:\s[^>]*)?>|<\!\[CDATA\[.*\]\]>|<\!--.*-->#su',
		$XSLT_TAGS  = array(
			'xsl:choose'   =>array(),
			'xsl:for-each' =>array('select'=>''),
			'xsl:if'       =>array('test'=>''),
			'xsl:otherwise'=>array(),
			'xsl:text'     =>array(),
			'xsl:value-of' =>array('select'=>''),
			'xsl:when'     =>array()
		),
		$TAL_ATTRIBS = array(
			'tal:content'=>0,
			'tal:replace'=>0,
			'tal:repeat'=>0,
			'tal:condition'=>0,
			'tal:condition-else'=>0,
			'tal:attributes'=>0,
			'tal:omit-tag'=>0,
			'i18n:translate'=>0,
			'i18n:attributes'=>0
		);

	function __construct() { $this->free(); }
	function __destruct()  { $this->free(); }

	public function free()
	{
		if ($this->parser) {
			xml_parser_free($this->parser);
			$this->parser = null;
		}
		$this->nodes = array();
	}

	public function load_dtd($type)
	{
		include(__DIR__.'/dtd/'.$type.'.php');
		if (isset($DTD)) {
			$this->DTD = array_merge($DTD, self::$XSLT_TAGS);
			$this->DTD['STANDARD_ATTRIBUTES'] = isset($this->DTD['STANDARD_ATTRIBUTES']) ? array_merge($this->DTD['STANDARD_ATTRIBUTES'], self::$TAL_ATTRIBS) : self::$TAL_ATTRIBS;
		}
	}

	public static function specialchars($v) { return htmlspecialchars(is_array($v)?$v[0]:$v, ENT_NOQUOTES); }
	public static function specialchars_decode($v) { return htmlspecialchars_decode(is_array($v)?$v[0]:$v, ENT_NOQUOTES); }
	public function body($data)
	{
		$this->errors = array();

		$data = preg_replace('#<\?.*\?>|\?>|<\?#s','',$data);
		$data = preg_replace('#<acronym#i','<abbr',$data);
		# NOTE: Tidy doesn't preserve white space
		if (class_exists('Tidy', false))
		{
			# http://tidy.sourceforge.net/docs/quickref.html
			$tidy_config = array(
				'bare'=>1,
//				'input-xml'=>1,
				'join-classes'=>1,
				# new tags is ignored in XML mode
				'new-blocklevel-tags'=>implode(',', array_keys(self::$XSLT_TAGS)),
				'new-empty-tags'     =>implode(',', $this->DTD_get_empty_tags()),
//				'new-inline-tags'    =>implode(',', $this->DTD_get_inline_tags()),
//				'new-pre-tags'       =>implode(',', array_keys(self::$XSLT_TAGS)),
				'newline'=>'LF',
				'numeric-entities'=>1,
				'output-xhtml'=>1,
//				'output-xml'=>1,
				'show-body-only'=>1,
				'quote-nbsp'=>0,
				'wrap'=>0,
				'word-2000'=>1,
			);
			$tidy = new \tidy();
			$data = $tidy->repairString($data, $tidy_config, 'utf8');
		} else {
			$data = preg_replace('#<(\?xml|\!DOCTYPE)[^>]*>\n?#s','',preg_replace('#\r\n|\r|\n#', "\n", $data));
			$data = preg_replace('#<head[^>]*>.*?</head>#si','',$data);
			$data = trim(preg_replace('#</?(html|body|link|meta|base)[^>]*>#si','',$data));
			$data = preg_replace('#<((?:area|br|col|hr|img|input|param)[^>]*[^/>])>#si','<$1 />',$data);
			$data = preg_replace_callback('#="[^"]*[<>][^"]*"#','self::specialchars',$data);
		}

		preg_match_all(self::$REGEX_TAG,'<repairhtml>'.$data.'</repairhtml>',$tags);
		$tags = preg_replace('#\s+([^\s0-9\p{P}][\p{L}\p{N}\p{P}]+)\s*=\s*#u',' $1=', $tags[0]);
		$tags = preg_replace('#=([^"\'\s]+)#','="$1"', $tags);
		$tags = preg_replace('#(</?)font#','$1span', $tags);
		$data = array_map('self::specialchars',preg_split(self::$REGEX_TAG,$data));

		$last = count($tags)-1;
		$i = $li = 0;
		$continue = true;
		$data[$last]='';
		while ($continue && $i<=$last)
		{
			# Create and initialize parser
			$this->parser = xml_parser_create('UTF-8');
			xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
			xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, false);
			xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');

			xml_set_object($this->parser, $this);
			xml_set_character_data_handler($this->parser, 'xml_character_data');
			xml_set_element_handler($this->parser, 'xml_node_start', 'xml_node_end');
/*
			xml_set_default_handler($this->parser, 'xml_default_handler');
			xml_set_end_namespace_decl_handler($this->parser, 'xml_end_namespace_decl');
			xml_set_processing_instruction_handler($this->parser, 'xml_processing_instruction');
			xml_set_start_namespace_decl_handler($this->parser, 'xml_start_namespace_decl');
			xml_set_unparsed_entity_decl_handler($this->parser, 'xml_unparsed_entity_decl');
			# xml_set_external_entity_ref_handler — Set up external entity reference handler
			# xml_set_notation_decl_handler — Set up notation declaration handler
*/
			$this->data = '';
			for ($i = 0; $i<=$last; ++$i)
			{
				# CDATA & comments shouldn't be encoded
				if ('!'===$tags[$i][1]) {
					$this->data .= $tags[$i].$data[$i];
					continue;
				}
				$continue = xml_parse($this->parser, $tags[$i].$data[$i], $i==$last);
				if (!$continue) {
					# http://www.xmlsoft.org/html/libxml-xmlerror.html#xmlParserErrors
					$ec = xml_get_error_code($this->parser);
					$this->errors[] = array(
						'error' => xml_error_string($ec),
						'errno' => $ec,
						'line'  => xml_get_current_line_number($this->parser),
						'node'  => $this->nodes[count($this->nodes)-1]
					);
					# try to fix
					switch (xml_get_error_code($this->parser))
					{
					case 4: # Not well-formed (invalid token)
						break;

					case 41: # Attribute without value
						$tags[$i] = preg_replace('#\s([^\s0-9\p{P}][\p{L}\p{N}\p{P}]+)(>|\s)#Du',' $1="$1"$2',$tags[$i]);
						$continue = true;
						break;

					case 42: # Attribute redefined
						$tags[$i] = preg_replace('#([^=\s]+=)(.*)\s\\1"[^"]*"#','$1$2',$tags[$i]);
						$continue = true;
						break;

					case 65: # Space required
					case 72: # < required
					case 73: # > required
					case 74: # </ required
						$c=0;
						$tags[$i-1] = preg_replace('#="([^"\s]*)"([^"\s]*)"#','="$1$2"',$tags[$i-1],-1,$c);
						if (!$c) {
							if ($li == $i) {
								$tags[$i-1] = htmlspecialchars_decode($tags[$i-1], ENT_NOQUOTES);
								$tags[$i]   = htmlspecialchars($tags[$i], ENT_NOQUOTES);
							} else {
								$tags[$i-1] = htmlspecialchars($tags[$i-1], ENT_NOQUOTES);
							}
						}
						$continue = true;
						break;

					case 76: # Mismatched tag
						preg_match(self::$REGEX_TAG,$tags[$i],$m);
						$m=strtolower($m[1]);
						if (in_array($m, $this->nodes)) {
							$n=count($this->nodes);
							$t='';
							while (0<=--$n && $m!=$this->nodes[$n]) $t.="</{$this->nodes[$n]}>";
							$tags[$i] = $t.$tags[$i];
							$continue = $n>=0;
						} else {
							$tags[$i] = htmlspecialchars($tags[$i], ENT_NOQUOTES);
							$continue = true;
						}
						break;
					}
					$li = $i;
					break;
				}
			}
			$this->free();
		}
		$data = $continue ? trim($this->data) : false;
		$this->data = '';
		return $data;
	}

	protected function DTD_has_tag_attribute($tag, $attr)
	{
		return (!$this->DTD || isset($this->DTD[$tag][$attr]) || isset($this->DTD['STANDARD_ATTRIBUTES'][$attr]));
	}
	protected function DTD_is_empty_tag($name) { return ($this->DTD && isset($this->DTD['EMPTY-TAGS']) && in_array($name, $this->DTD['EMPTY-TAGS'])); }
	protected function DTD_get_empty_tags()  { return ($this->DTD && isset($this->DTD['EMPTY-TAGS']))  ? $this->DTD['EMPTY-TAGS']  : array(); }
	protected function DTD_get_inline_tags() { return ($this->DTD && isset($this->DTD['INLINE-TAGS'])) ? $this->DTD['INLINE-TAGS'] : array(); }

	protected function error($msg, $type=E_USER_WARNING)
	{
		$this->errors[] = array(
			'error' => $msg,
			'errno' => $type,
			'line'  => xml_get_current_line_number($this->parser),
		);
//		\Poodle\Debugger::error($type, $msg, __FILE__, xml_get_current_line_number($this->parser));
	}

	# XML Parser: Node value
	protected function xml_character_data($parser, $data) { $this->data .= htmlspecialchars($data, ENT_NOQUOTES); }

	# XML Parser: Node start
	protected function xml_node_start($parser, $name, $attribs)
	{
		$lname = strtolower($name);
		$this->nodes[] = $lname;
		if ('repairhtml' === $lname) { return; }

		$this->data .= '<'.$name;
		foreach ($attribs as $attr => $value) {
//			if (self::DTD_has_tag_attribute($lname, $attr)) {
				$this->data .= " {$attr}=\"".htmlspecialchars(trim($value))."\"";
//			}
		}
		if (self::DTD_is_empty_tag($lname)) $this->data .= ' /';
		$this->data .= '>';
	}

	# XML Parser: Node end
	protected function xml_node_end($parser, $name)
	{
		$lname = strtolower($name);
		$node  = array_pop($this->nodes);
		if ('repairhtml' === $lname || self::DTD_is_empty_tag($lname)) return;
		$this->data .= "</{$name}>";
	}

}
