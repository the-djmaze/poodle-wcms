<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	wiki.zope.org/ZPT/TAL
*/

namespace Poodle\TPL;

interface Reader
{
//	$isEmptyElement
	public function getLineNo() : int;
	public function parse(string $data) : bool;
}

class Parser
{
	public
		$data,
		$errors;

	private
		$parser, // parse_chunk XMLParser
		$level,  // How deep the current rabbit hole goes
		$skip,   // Skip inner nodes
		$nodes,
		$character_data,
		$tal_conditions, // tal:condition level
		$xsl_foreach,    // xsl:for-each level
		$xsl_choose,

		$file = null,

		$DTD = null,
		$DTD_type = null,
		$old_dtd_type = null,
		$HTML = false;

	private static
		$form_elements = array('button','input','select','textarea'),
		$form_methods  = array('reset','submit'),
		$uri_attribs   = array('action','formaction','href','src','srcset','poster'),
		$table_struct  = array('thead','tbody','tfoot');

	function __construct(\Poodle\TPL $TPL)
	{
		$this->TPL = $TPL;
		if ($TPL->DTD) {
			$this->load_dtd($TPL->DTD);
		}
		$this->free();
	}

	function __destruct()
	{
		$this->free();
	}

	public function getCurrentFilename() : ?string
	{
		return $this->file;
	}

	public function free(bool $skip_error=false) : void
	{
		if ($this->parser) {
			if (!$this->parser->free($skip_error)) {
				$this->log_error($this->parser->getErrorString(), $this->parser->getLineNo());
			}
			$this->parser = null;
		}
		$this->level = 0;
		$this->skip  = 0;
		$this->nodes = array();
		$this->tal_conditions = array();
		$this->xsl_foreach = array();
		$this->xsl_choose = array();
		$this->character_data = '';
	}

	public function load_dtd(string $type) : void
	{
		$DTD = array();
		include(__DIR__.'/dtd/'.$type.'.php');
		if ($DTD && is_array($DTD)) { $this->DTD = $DTD; }
		$this->DTD_type = $type;
		$this->HTML = stripos($this->doctype(),'DOCTYPE html');
	}

	public function doctype() : ?string
	{
		return ($this->DTD && isset($this->DTD['DOCTYPE']) ? $this->DTD['DOCTYPE']."\n" : null);
	}

	public function isXML() : bool
	{
		return ($this->DTD && !empty($this->DTD['XML']));
	}

	public function isCaseInsensitive() : bool
	{
		return ($this->DTD && !empty($this->DTD['CASE-INSENSITIVE']));
	}

	public function parse_chunk(string $file, ?string $data, bool $final=true) : bool
	{
		$this->file   = $file;
		$this->data   = '';
		$this->errors = array();
		if (!$this->parser) {
			$this->parser = new XMLParser($this);
		}
		if ($this->parser->parse($data ?: file_get_contents($file))) {
			if ($final) {
				$this->free();
			}
			$this->cleanupData();
		} else {
			$this->log_error($this->parser->getErrorString(), $this->parser->getLineNo());
			$this->free(true);
		}
		return empty($this->errors);
	}

	public function parse_xml(string $file, ?string $data) : bool
	{
		$this->file   = $file;
		$this->data   = '';
		$this->errors = array();
		$parser = new XMLReader($this);
		if (!$parser->parse($data ?: file_get_contents($file))) {
			$this->setXMLErrors();
			return false;
		}
		$this->cleanupData();
		$this->setXMLErrors();
		return empty($this->errors);
	}

	protected function cleanupData() : void
	{
		$this->data = ltrim(preg_replace('#\\R#u', "\n", $this->data));
		$this->data = preg_replace('#>[ \\t]+<#u','> <',$this->data);
		$this->data = preg_replace('#\\s*\\?><\\?php\\s*#u',' ',$this->data);
		$this->data = preg_replace('#\\?>(\\s*)<\\?php else#su','$1 else',$this->data);
	}

	protected function DTD_has_tag_attribute(string $tag, string $attr) : bool
	{
		return (!$this->DTD
			|| isset($this->DTD[$tag][$attr])
			|| isset($this->DTD['STANDARD_ATTRIBUTES'][$attr])
			// HTML5 data-*, WAI-ARIA or 'on*' HTML Event-handler attributes
			|| preg_match('#^(data-|aria-|on[a-z]+$)#D',$attr)
		);
	}

	protected function DTD_is_empty_tag($name) : bool
	{
		return ($this->DTD && isset($this->DTD['EMPTY-TAGS']) && in_array($name, $this->DTD['EMPTY-TAGS']));
	}

	protected function log_error($msg, $line, $column = 0) : void
	{
		$i = array_key_last($this->nodes);
		$this->errors[] = array(
			'message'=> $msg,
			'file'   => $this->file,
			'line'   => $line,
//			'column' => $column,
			'node'   => isset($this->nodes[$i]) ? $this->nodes[$i] : ''
		);
	}

	protected function error(Reader $reader, string $msg, int $type=E_USER_WARNING) : void
	{
		\Poodle\Debugger::error($type, $msg, $this->file, $reader->getLineNo());
	}

	protected function tagBuild(Reader $reader, array &$node, string &$attribs) : string
	{
		$tag = '<'.$node['name'] . $attribs;
		if (($reader->isEmptyElement && !isset($node['value'])) || self::DTD_is_empty_tag($node['name'])) {
			$tag .= '/';
			$node['omit-tag'] = true;
		}
		$tag .= '>';
		if ($this->DTD) {
			if (!isset($this->DTD[$node['name']])) {
				$this->error($reader, "Unknown node '{$node['name']}'");
			} else if ('script' === $node['name'] && isset($this->DTD['CDATA'])) {
				$tag .= $this->DTD['CDATA'][0].' ';
			}
		}
		return $tag;
	}

	protected function tagBuildAttributes(Reader $reader, string $name, array &$attribs, array &$parse_attribs = null) : array
	{
		if (self::DTD_has_tag_attribute('STANDARD_ATTRIBUTES', 'xml:lang')) {
			if (isset($attribs['lang']) && !isset($attribs['xml:lang'])) {
				$attribs['xml:lang'] = $attribs['lang'];
				unset($attribs['lang']);
			}
		} else if (isset($attribs['xml:lang']) && !isset($attribs['lang'])) {
			$attribs['lang'] = $attribs['xml:lang'];
			unset($attribs['xml:lang']);
		}

		// Check for required attributes
		if ($this->DTD && !empty($this->DTD[$name])) {
			foreach ($this->DTD[$name] as $attr => $required) {
				if (0 !== $required && !isset($attribs[$attr])) {
					if (!isset($parse_attribs[$attr])) {
						$this->error($reader, "Adding missing required attribute '{$attr}' to node '{$name}'", E_USER_NOTICE);
					}
					$attribs[$attr] = $required;
				}
			}
		}

		if ($this->HTML) {
			// Only firefox supports td/th colspan="0" and rowspan="0", so set a really big value
			if ('th' === $name || 'td' === $name) {
				if (isset($attribs['colspan']) && '0' === $attribs['colspan']) {
					$attribs['colspan'] = 99;
				}
				if (isset($attribs['rowspan']) && '0' === $attribs['rowspan']) {
					$attribs['rowspan'] = 999;
				}
			} else if (in_array($name, self::$form_elements)) {
				// Set type="submit" because this is default for all browsers, except Internet Explorer
				if ('button' === $name && !isset($attribs['type'])) {
					$attribs['type'] = 'submit';
				}
				// If a form control (such as a submit button) has a name or id value of "submit", it will mask the form's submit method.
				if (isset($attribs['name']) && in_array($attribs['name'], self::$form_methods)) {
					$this->error($reader, "{$name}'s attribute 'name' value '{$attribs['name']}' changed to 'form-{$attribs['name']}' or it will mask the form's method");
					$attribs['name'] = 'form-'.$value;
				}
				if (isset($attribs['id']) && in_array($attribs['id'], self::$form_methods)) {
					$this->error($reader, "{$name}'s attribute 'id' value '{$attribs['id']}' changed to 'form-{$attribs['id']}' or it will mask the form's method");
					$attribs['id'] = 'form-'.$value;
				}
			}
		}

		// Build the attributes lists
		$tag_attribs = $attrs = array();
		foreach ($attribs as $attr => $value) {
			if (0 !== strpos($attr,'tal:') && 0 !== strpos($attr,'i18n:')) {
				if ($this->HTML) {
					if ($value && in_array($attr, self::$uri_attribs) && '/' === $value[0]) {
						$value = \Poodle\URI::resolve($value);
					}
				}
				if (!strpos($attr,':') && !self::DTD_has_tag_attribute($name, $attr)) {
					$this->error($reader, "Unknown attribute '{$attr}' in node '{$name}'", E_USER_NOTICE);
				}
				$attrs[] = var_export($attr, true).'=>'.var_export($value, true);
				if (!isset($parse_attribs[$attr])) {
					$tag_attribs[$attr] = $value;
				}
			}
		}
		return array($tag_attribs, $attrs);
	}

	protected function push_character_data(string $data=null, bool $encode=true) : void
	{
		if (isset($data)) {
			$this->data .= $data;
//		} else if ($encode && false !== strpbrk($this->character_data,'&<>')) { slower ???
		} else if ($encode && (false !== strpos($this->character_data,'&')
		 || false !== strpos($this->character_data,'<')
		 || false !== strpos($this->character_data,'>'))
		) {
			$this->data .= '<?php static::echo_data('.self::data2Str($this->character_data).');?>';
		} else {
			$this->data .= $this->character_data;
		}
		$this->character_data = '';
	}

	// XML Parser: Node value
	public function xml_character_data(Reader $reader, string $data) : void
	{
		$this->character_data .= $data;
	}

	// XML Parser: Node start
	public function xml_node_start(Reader $reader, string $name, array $attribs) : void
	{
		$level = $this->level++;
		if ('tal' === $name) { return; }
		if ($this->skip && $level > $this->skip) { return; }
		if ($this->isCaseInsensitive()) {
			$name = strtolower($name);
		}

		/** XSLT */
		if ($this->xslt_node_start($name, $attribs, $level)) { return; }

		if ($this->HTML) {
			if ('svg' === $name || 'math' === $name) {
				$this->old_dtd_type = $this->DTD_type;
				$this->load_dtd($name);
			}
			else if ('tr' === $name && !in_array($this->nodes[array_key_last($this->nodes)]['name'], self::$table_struct)) {
				$this->error($reader, "Table TR started outside thead, tbody or tfoot");
			}
		}

		$this->push_character_data(/*isset($attribs['tal:omit-tag'])?'':null*/);

		$code = array();
		$node = array(
			'name'      => $name,
			'php'       => array(),
			'value'     => null,
			'omit-tag'  => false,
			'translate' => false
		);

		if (!$attribs) {
			$tag_attribs = $this->tagBuildAttributes($reader, $name, $attribs);
			$echo_attribs = $tag_attribs[0] ? $this->TPL->parseAttributes($name, $tag_attribs[0]) : '';
			$this->data .= $this->tagBuild($reader, $node, $echo_attribs);
			$this->nodes[$level] = $node;
			return;
		}

		/**
		 * TAL
		 * Attribute execution order:
		 *     define
		 *     condition
		 *     repeat
		 *     content or replace or include
		 *     attributes
		 *     omit-tag
		 */

		unset($attribs['xmlns:i18n']);
		unset($attribs['xmlns:metal']);
		unset($attribs['xmlns:tal']);
		unset($attribs['xmlns:xsl']);

		if (!empty($attribs['tal:include'])) {
			$attribs['tal:include'] = str_replace("'", '', $attribs['tal:include']);
			if (isset($attribs['tal:content'])) {
				$this->error($reader, "'tal:include' and 'tal:content' attributes may not appear together.");
				unset($attribs['tal:content']);
			}
			if (isset($attribs['tal:replace'])) {
				$this->error($reader, "'tal:include' and 'tal:replace' attributes may not appear together.");
				unset($attribs['tal:replace']);
			}
		}
		if (isset($attribs['tal:content']) && isset($attribs['tal:replace'])) {
			$this->error($reader, "'tal:content' and 'tal:replace' attributes may not appear together.");
			unset($attribs['tal:replace']);
		}

		if (isset($attribs['tal:define'])
		 && preg_match_all('#(?:(local|global)\\s+)?([a-z][0-9a-z_]+)\\s+([^;]*)(?:$|;)#D', $attribs['tal:define'], $m, PREG_SET_ORDER))
		{
			foreach ($m as $def) {
				$code[] = '$ctx->'.('global'===$def[1]?'getTopContext()->':'')
					.$def[2].' = ('.Tales::translate_expression($def[3]).');';
			}
		}

		if (isset($attribs['tal:condition-else'])) {
			if (isset($this->tal_conditions[$level])) {
				if (strlen(trim($attribs['tal:condition-else']))) {
					$code[] = 'else if ('.Tales::translate_expression($attribs['tal:condition-else']).') {';
				} else {
					$code[] = 'else {';
					unset($this->tal_conditions[$level]);
				}
				$node['php'][] = '}';
			}
		} else
		if (isset($attribs['tal:condition'])) {
//			if (!empty($this->tal_conditions[$level])) { $code[] = ' else'; }
			$code[] = 'if ('.Tales::translate_expression($attribs['tal:condition']).') {';
			$node['php'][] = '}';
			$this->tal_conditions[$level] = true;
		} else {
			unset($this->tal_conditions[$level]);
		}

		if (isset($attribs['tal:repeat'])) {
			// (dest, value) else (dest)
			$code[] = preg_match('#^\\s*([a-z0-9:\\-_]+)\\s+(.*)$#si', $attribs['tal:repeat'], $m)
				? self::create_foreach($m[1], trim($m[2]))
				: self::create_foreach(trim($attribs['tal:repeat']), null);
			$node['php'][] = '} $ctx = $ctx->parent;';
		}

		/**
		 * Replace content including start and end tags
		 * else continue processing
		 */

		$tag = '';
		if (!empty($attribs['tal:include']) && ('style' === $name || 'script' === $name)) {
			$cmd = ('style' === $name) ? 'addCSS' : 'addScript';
			$this->skip = $level;
			$node['omit-tag'] = true;
			$node['value'] = '<?php \Poodle::getKernel()->OUT->head->'.$cmd.'(\''.$attribs['tal:include'].'\'); ?>';
		} else
		if (isset($attribs['tal:replace'])) {
			$this->skip = $level;
			$node['omit-tag'] = true;
			$node['value'] = self::getValue($attribs['tal:replace'], $attribs);
		} else {
			/**
			 * Replace inner content
			 */

			if (isset($attribs['tal:content'])) {
				$node['value'] = self::getValue($attribs['tal:content'], $attribs);
				$this->skip = $level;
				unset($attribs['disable-output-escaping']);
			}
			else if (!empty($attribs['tal:include'])) {
/*				$node['value'] = '<?php echo $this->toString(\''.$attribs['tal:include'].'\', $ctx); ?>';*/
				$node['value'] = '<?php $this->display(\''.$attribs['tal:include'].'\', $ctx); ?>';
				/**
				 * We could parse the file directly when design_mode is false
				 * Then we need to call $this->TPL->findFile($filename) and
				 * then parse_xml($file, $data);
				 */
				$this->skip = $level;
			}

			/**
			 * tal:attributes
			 */
			$parse_attribs = array();
			if (isset($attribs['tal:attributes'])
			 && preg_match_all('#([a-z\\-_]+)\\s+((?:\'[^\']*\'|[^\';]*)*)(?:$|;)#D', $attribs['tal:attributes'], $m, PREG_SET_ORDER))
			{
				foreach ($m as $attr) {
					$k = $attr[1];
					$v = $attr[2];
					$parse_attribs[$k] = Tales::translate_expression($v, isset($attribs[$k])?self::data2Str($attribs[$k]):null);
					if (in_array($k,self::$uri_attribs) && false === stripos($parse_attribs[$k], 'static::resolveURI')) {
						$parse_attribs[$k] = 'static::resolveURI('.$parse_attribs[$k].')';
					}
				}
			}
			unset($attribs['tal:attributes']);

			/**
			 * i18n:attributes
			 */
			if (isset($attribs['i18n:attributes'])
			 && preg_match_all('#([a-z\\-]+)(\\s+[^;]*)?(?:$|;)#D', $attribs['i18n:attributes'], $m, PREG_SET_ORDER))
			{
				foreach ($m as $attr) {
					$k = $attr[1];
					$attr[2] = isset($attr[2]) ? trim($attr[2]) : '';
					if (empty($attr[2])) {
						if (isset($parse_attribs[$k])) {
							$parse_attribs[$k] = '$this->L10N->get('.$parse_attribs[$k].')';
							continue;
						} else if (isset($attribs[$k])) {
							$attr[2] = self::data2Str($attribs[$k]);
						} else {
							$this->error($reader, "Undefined attribute '{$k}' in node '{$name}'", E_USER_NOTICE);
							continue;
						}
					}
					$parse_attribs[$k] = self::l10n($attr[2], true);
				}
			}
			unset($attribs['i18n:attributes']);

			// Not handled
			unset($attribs['tal:on-error']);

			/**
			 * Get final attributes lists
			 */

			$tag_attribs = $this->tagBuildAttributes($reader, $name, $attribs, $parse_attribs);
			$echo_attribs = $tag_attribs[0] ? $this->TPL->parseAttributes($name, $tag_attribs[0]) : '';
			if ($tag_attribs[1] && $parse_attribs) {
				$code[] = '$ctx->attrs = array('.implode(',',$tag_attribs[1]).');';
			}

			/**
			 * http://wiki.zope.org/zope3/ZPTInternationalizationSupport
			 */
			if (isset($attribs['i18n:domain'])) { trigger_error('i18n:domain not supported'); }
			if (isset($attribs['i18n:source'])) { trigger_error('i18n:source not supported'); }
			if (isset($attribs['i18n:target'])) { trigger_error('i18n:target not supported'); }
			if (isset($attribs['i18n:name']))   { trigger_error('i18n:name not supported'); }
			if (isset($attribs['i18n:data']))   { trigger_error('i18n:data not supported'); }
			if (isset($attribs['i18n:translate'])) {
				$attribs['i18n:translate'] = trim($attribs['i18n:translate']);
				if (strlen($attribs['i18n:translate'])) {
					$node['value'] = '<?php static::echo_data($this->L10N->dbget('.self::data2Str($attribs['i18n:translate']).')); ?>';
				} else {
					$node['translate'] = true;
				}
			}

			if ($parse_attribs) {
				foreach ($parse_attribs as $attr => $value) {
					if (!strpos($attr,':') && !self::DTD_has_tag_attribute($name, $attr)) {
						$this->error($reader, "Unknown attribute '{$attr}' in node '{$name}'", E_USER_NOTICE);
					}
					$parse_attribs[$attr] = "'{$attr}'=>{$value}";
				}
				$echo_attribs .= '<?php echo static::parseAttributes(\''.$name.'\', array('.implode(',', $parse_attribs).')';
				if ($tag_attribs[1]) {
					$echo_attribs .= ', $ctx->attrs';
				}
				$echo_attribs .= ');?>';
			}
			unset($parse_attribs);
			unset($tag_attribs);

			/**
			 * Recreate a proper tag
			 */

			$tag = $this->tagBuild($reader, $node, $echo_attribs);

			/**
			 * omit-tag option?
			 */

			if (isset($attribs['tal:omit-tag'])) {
				$exp = trim($attribs['tal:omit-tag']);
				if (strlen($exp)) {
					$exp = Tales::translate_expression($exp);
					$code[] = 'if (!('.$exp.')) { ?>'.$tag.'<?php }';
					$node['php'][] = 'if (!('.$exp.')) { ?></'.$name.'><?php }';
				}
				$tag = '';
				$node['omit-tag'] = true;
			}
		}

		if ($code) {
			$this->data .= '<?php '.implode(' ', $code).' ?>';
		}
		$this->data .= $tag;
		$this->nodes[$level] = $node;
	}

	// XML Parser: Node end
	public function xml_node_end(Reader $reader, string $name) : void
	{
		$level = --$this->level;
		if ('tal' === $name) {
			$this->push_character_data();
			return;
		}
		if ($this->skip) {
			if ($level > $this->skip) { return; }
			$this->skip = 0;
		}
		if ($this->isCaseInsensitive()) {
			$name = strtolower($name);
		}

		/** XSLT */
		if ($this->xslt_node_end($name, $level)) { return; }

		/**
		 * Default
		 */

		if ($this->old_dtd_type && ('svg' === $name || 'math' === $name)) {
			$this->load_dtd($this->old_dtd_type);
		}

		$node = array_pop($this->nodes);

		if ($node['translate']) {
			$node['value'] = '<?php static::echo_data('.self::l10n($this->character_data).'); ?>';
		}
		$this->push_character_data($node['value'], 'script' !== $name);

		if (!$node['omit-tag']) {
			if ('script' === $name && $this->DTD && isset($this->DTD['CDATA'])) {
				$this->data .= ' '.$this->DTD['CDATA'][1];
			}
			$this->data .= "</{$name}>";
		}
		if ($node['php']) {
			$this->data .= '<?php '.implode(' ', array_reverse($node['php'])).' ?>';
		}
	}

	protected function xslt_node_start(string $name, array $attribs, int $level) : bool
	{
		if (0 !== strpos($name, 'xsl:')) { return false; }
		if ('choose' !== substr($name,4)) {
			$this->push_character_data();
		}
		switch (substr($name,4))
		{
		case 'choose':
			$this->xsl_choose[$this->level] = true;
			break;

		case 'for-each':
			$var = isset($attribs['as']) ? $attribs['as'] : preg_replace('#^.*/([^/]+)$#D', '$1', $attribs['select']);
			$this->push_character_data();
			$this->data .= '<?php ' . self::create_foreach($var, $attribs['select']) . ' ?>';
			$this->xsl_foreach[] = $var;
			break;

		case 'when': if (empty($this->xsl_choose[$level])) { break; }
		case 'if':
			$exp = $attribs['test'];
			$var = ($i = count($this->xsl_foreach)) ? $this->xsl_foreach[$i-1].'->' : '';
			$exp = preg_replace('#([^!=><])=#', '$1==', $exp);
			$exp = preg_replace('#(^|[^a-z>$])([a-z][a-z0-9_]+)(?=[^a-z0-9_]|$)#Di', '$1$ctx->'.$var.'$2', html_entity_decode($exp));
			$exp = str_replace($var.'position()', 'repeat->'.$var.'index', $exp);
			$exp = str_replace($var.'last()', 'repeat->'.$var.'last', $exp);
			$this->data .= '<?php if ('.$exp.') { ?>';
			break;
		case 'otherwise':
			if (!empty($this->xsl_choose[$level])) {
				$this->data .= '<?php else { ?>';
			}
			break;

		case 'value-of':
			$i = count($this->xsl_foreach);
			$this->data .= self::getValue(
				($i ? $this->xsl_foreach[$i-1] . '/' : '') . $attribs['select'],
				$attribs);
			break;
		}
		return true;
	}

	protected function xslt_node_end(string $name, int $level) : bool
	{
		if (0 !== strpos($name, 'xsl:')) { return false; }
		switch (substr($name,4))
		{
		case 'for-each':
			array_pop($this->xsl_foreach);
			$this->push_character_data();
			$this->data .= '<?php } $ctx = $ctx->parent; ?>';
			break;

		case 'choose': unset($this->xsl_choose[$level+1]); break;
		case 'otherwise':
		case 'when': if (empty($this->xsl_choose[$level])) { break; }
		case 'if':
			$this->push_character_data();
			$this->data .= '<?php } ?>';
			break;

		case 'text': $this->push_character_data(); break;
		}
		return true;
	}

	protected static function create_foreach(string $var, string $exp) : string
	{
		return '$ctx = $ctx->new_context_repeat(\''.$var.'\', '.Tales::translate_expression($exp).'); foreach ($ctx->repeat->'.$var.' as $ctx->'.$var.') {';
	}

	protected static function getValue(string $expr, array &$attribs) : string
	{
		if (!strlen($expr)) return '';

		preg_match('/^(?:(text|structure)\\s+)?(.+)/', $expr, $match);

		if (isset($attribs['i18n:translate'])) {
			unset($attribs['i18n:translate']);
			$expr = self::l10n($match[2], true, true);
		} else {
			$expr = Tales::translate_expression($match[2]);
		}
		if ('structure' === $match[1] || (isset($attribs['disable-output-escaping']) && 'yes' === $attribs['disable-output-escaping'])) {
			return '<?php echo '.$expr.'; ?>';
		}
		return '<?php static::echo_data('.$expr.');?>';
	}

	protected static function l10n(string $str, bool $parse=false, bool $dbget=false) : string
	{
		$str = trim($str);
		# Look for tales modifier (not:, path:, string:, php:, etc...)
		if ($parse) {
			$str = Tales::translate_expression($str);
		} else {
			$str = Tales::string($str);
		}
		return '$this->L10N->'.($dbget?'dbget':'get')."({$str})";
	}

	protected static function data2Str(string $data) : string
	{
		return '\''.addcslashes($data,"'\\").'\'';
	}

	protected function setXMLErrors() : void
	{
		foreach (libxml_get_errors() as $error) {
			$this->log_error($error->message, $error->line, $error->column);
		}
		libxml_clear_errors();
	}

}

// James Clark's expat
class XMLParser implements Reader
{
	private
		$owner,
		$parser,     // parse_chunk XML Parser
		$input,      // parse_chunk XML Parser current data
		$offset = 5, // The index of the start of the current buffer within the stream
		$line   = 0,
		$final  = false;

	function __construct(Parser $owner)
	{
		$this->owner = $owner;

		// Create and initialize parser
		$this->parser = xml_parser_create('UTF-8');
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, false);
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');

		xml_set_object($this->parser, $this);
		xml_set_character_data_handler($this->parser, 'character_data');
		xml_set_element_handler($this->parser, 'node_start', 'node_end');
/*
		xml_set_default_handler($this->parser, 'xml_default_handler');
		xml_set_end_namespace_decl_handler($this->parser, 'xml_end_namespace_decl');
		xml_set_processing_instruction_handler($this->parser, 'xml_processing_instruction');
		xml_set_start_namespace_decl_handler($this->parser, 'xml_start_namespace_decl');
		xml_set_unparsed_entity_decl_handler($this->parser, 'xml_unparsed_entity_decl');
		# xml_set_external_entity_ref_handler — Set up external entity reference handler
		# xml_set_notation_decl_handler — Set up notation declaration handler
*/
		xml_parse($this->parser, '<tal>', false);
	}

	protected function node_start($parser, string $name, array $attribs) : void
	{
		$this->owner->xml_node_start($this, $name, $attribs);
	}

	protected function node_end($parser, string $name) : void
	{
		$this->owner->xml_node_end($this, $name);
	}

	protected function character_data($parser, string $data) : void
	{
		$this->owner->xml_character_data($this, $data);
	}

	function __destruct()
	{
		if (!$this->free()) {
			throw new \Exception($this->getErrorString(), $this->getLineNo());
		}
	}

	public function free(bool $skip_error = false) : bool
	{
		if ($this->parser) {
			if (!$this->final) {
				$this->final = true;
				if (!xml_parse($this->parser, '</tal>', true) && !$skip_error) {
					return false;
				}
			}
			xml_parser_free($this->parser);
			$this->parser = null;
		}
		return true;
	}

	public function parse(string $data) : bool
	{
		$data = preg_replace('#<(\\?xml|\\!DOCTYPE)[^>]*>\\r?\\n?#s', '', $data);
		$this->input = $data;
		if (xml_parse($this->parser, $data, false)) {
			$this->offset += strlen($data);
			$this->line  = xml_get_current_line_number($this->parser);
			$this->input = null;
			return true;
		}
		return false;
	}

	public function getLineNo() : int
	{
		return xml_get_current_line_number($this->parser) - $this->line;
	}

	public function getErrorString() : string
	{
		return xml_error_string(xml_get_error_code($this->parser));
//		,xml_get_current_column_number($this->parser)
	}

	function __get($k)
	{
		if ('isEmptyElement' === $k) {
			$p = xml_get_current_byte_index($this->parser) - $this->offset;
			if (isset($this->input[$p])) {
				return ('/' === $this->input[$p]);
			}
			return false;
		}
	}

}

class XMLReader extends \XMLReader implements Reader
{
	const
		XMLNS = 'xmlns:tal="http://xml.zope.org/namespaces/tal"
	xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
	xmlns:i18n="http://xml.zope.org/namespaces/i18n"';

	private
		$owner;

	function __construct(Parser $owner)
	{
		$this->owner = $owner;
	}

	public function getLineNo() : int
	{
		return (($node = $this->expand()) ? $node->getLineNo() : 0);
	}

	public function parse(string $data) : bool
	{
		try {
			libxml_clear_errors();
			libxml_use_internal_errors(true);
			libxml_disable_entity_loader(true);
			$data = preg_replace('#<(\\?xml|\\!DOCTYPE)[^>]*>#s', '', $data);
			if (!parent::xml('<tal '.static::XMLNS.'>'.$data.'</tal>', null, LIBXML_COMPACT)) {
				return false;
			}
			while ($this->read()) {
				switch ($this->nodeType)
				{
				case \XMLReader::ELEMENT: // Start element
					$attributes = array();
					if ($this->hasAttributes) {
						while ($this->moveToNextAttribute()) {
							$attributes[$this->name] = $this->value;
						}
						$this->moveToElement();
					}
					$this->owner->xml_node_start($this, $this->name, $attributes);
					if ($this->isEmptyElement) {
						$this->owner->xml_node_end($this, $this->name);
					}
					break;

				case \XMLReader::TEXT:
				case \XMLReader::CDATA:
				case \XMLReader::WHITESPACE:
				case \XMLReader::SIGNIFICANT_WHITESPACE:
					if ($this->hasValue) {
						$this->owner->xml_character_data($this, $this->value);
					}
					break;

				case \XMLReader::END_ELEMENT:
					$this->owner->xml_node_end($this, $this->name);
					break;
				}
			}
		} finally {
			$this->close();
		}
		return true;
	}
}
