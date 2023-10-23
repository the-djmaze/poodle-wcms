<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\SQL\XML;

class Exporter
{
	public
		$errors;

	protected
		$SQL,
		$prefix;

	protected static
		$onDuplicateActions = array('ERROR','IGNORE','UPDATE'),
		$dataModes = array('ON-EMPTY','ON-UPDATE','IDENTICAL','UNIQUE');

	function __construct(\Poodle\SQL $SQL)
	{
		$this->SQL = $SQL;
		$this->prefix = $this->SQL->TBL->prefix;
	}

	protected static function isStream($stream)
	{
		if (!is_resource($stream)) {
			throw new \InvalidArgumentException("\$stream is not a resource, but a " . gettype($stream));
		}
		$type = get_resource_type($stream);
		if ('stream' !== $type) {
			throw new \InvalidArgumentException("\$stream is not a resource of type 'stream', but '{$type}'");
		}
	}

	protected function getDocFoot() { return "\n\n</database>"; }
	protected function getDocHead()
	{
		return '<?xml version="1.0"?>'."\n"
		.'<database version="1.0" name="'.$this->SQL->database.'" charset="'.$this->SQL->get_charset().'" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
	}

	protected function writeTableDataXML($stream, $name, $table, array $config=array())
	{
		static::isStream($stream);

		// Sort on max first 6 columns, else might have error: Out of sort memory; increase server sort buffer size
//		$result = $this->SQL->query("SELECT * FROM {$table} ORDER BY 1");
		$result = $this->SQL->query("SELECT * FROM {$table}");
		if ($result->num_rows) {
			$data = "\n\n\t".'<table name="'.$name.'"';
			if (isset($config['onduplicate']) && in_array($config['onduplicate'], self::$onDuplicateActions)) {
				$data .= ' onduplicate="'.$config['onduplicate'].'"';
			}
			if (isset($config['datamode']) && in_array($config['datamode'], self::$dataModes)) {
				$data .= ' datamode="'.$config['datamode'].'"';
			}
			$data .= '>';

			$r = $result->fetch_assoc();
			# columns
			$data .= "\n\t\t<col name=\"".implode("\"/>\n\t\t<col name=\"",array_keys($r))."\"/>";
			//$data .= "\n\t\t<col name=\"{$name}\"/>";

			fwrite($stream, $data);
			$data = '';

			do {
				foreach ($r as $k => $v) {
					if (is_null($v)) {
						$r[$k] = '<td xsi:nil="true"/>';
					} else if ($v && preg_match('#[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]#', $v)) {
						$r[$k] = '<td encoding="hex">'.bin2hex($v).'</td>';
					} else {
//						if (false !== strpbrk($v,'&<>')) { slower ???
						if (false !== strpos($v,'&') || false !== strpos($v,'<') || false !== strpos($v,'>')) {
							if (false !== strpos($v,']]>')) {
								$v = htmlspecialchars($v, ENT_NOQUOTES);
							} else {
								$v = '<![CDATA['.$v.']]>';
							}
						}
						$r[$k] = "<td>{$v}</td>";
					}
				}
				// Push stream intermediate to reduce memory usage
				fwrite($stream, "\n\t\t<tr>" . implode('',$r) . "</tr>");
			} while ($r = $result->fetch_row());

			return false !== fwrite($stream, "\n\t</table>");
		}
		return true;
	}

	public function exportData($stream, array $config=array())
	{
		static::isStream($stream);

		$re = '#^'.$this->prefix.'(.+)$#D';
		fwrite($stream, $this->getDocHead());

		if (!isset($config['skip_tables'])) {
			$config['skip_tables'] = array(
				'auth_providers_assoc',
				'auth_providers_endpoints',
				'auth_providers_nonce',
				'log',
				'sessions',
			);
		}

		foreach ($this->SQL->listTables() as $table) {
			if (!in_array(substr($table,strlen($this->prefix)), $config['skip_tables'])
			 && preg_match($re, $table, $t)
			) {
				$this->writeTableDataXML($stream, $t[1], $table, $config);
			}
		}

		return false !== fwrite($stream, $this->getDocFoot());
	}

	public function getFunctionXML($name, $func)
	{
		$func = $this->SQL->getFunction($func);
		$data = "\t".'<function name="'.$name.'" returns="'.$func['returns'].'">';
		foreach ($func['parameters'] as $param) {
			$data .= "\n\t\t".'<param name="'.$param['name'].'"'
				.($param['dir']?' direction="'.$param['dir'].'"':'')
				.($param['type']?' type="'.$param['type'].'"':'')
				.($param['length']?' length="'.$param['length'].'"':'').'/>';
		}
		return $data
			."\n\t\t".'<body><![CDATA['.$func['definition'].']]></body>'
			."\n\t".'</function>';
	}

	public function getProcedureXML($name, $proc)
	{
		$proc = $this->SQL->getProcedure($proc);
		$data = "\t".'<procedure name="'.$name.'">';
		foreach ($proc['parameters'] as $param) {
			$data .= "\n\t\t".'<param name="'.$param['name'].'"'
				.($param['dir']?' direction="'.$param['dir'].'"':'')
				.($param['type']?' type="'.$param['type'].'"':'')
				.($param['length']?' length="'.$param['length'].'"':'').'/>';
		}
		return $data
			."\n\t\t".'<body><![CDATA['.$proc['definition'].']]></body>'
			."\n\t".'</procedure>';
	}

	public function getTableXML($name, $table, array $info=array())
	{
		if (!$info) $info = $this->SQL->getTableInfo($table);

		$data = "\t".'<table name="'.$name.'"';
		if ($info['comment']) { $data .= ' comment="'.htmlspecialchars($info['comment']).'"'; }
		if ($info['engine'] ) { $data .= ' engine="'.htmlspecialchars($info['engine']).'"'; }
		$data .= '>';

		# columns
		foreach ($this->SQL->listColumns($table) as $name => $col) {
			preg_match('#([A-Z]+)(?:\s*\(([^\(\)]+)\))?(\s+BINARY)?#i',$col['type'], $m);
			$attr = array('type="'.$m[1].'"');
			if (!empty($m[2])) $attr[] = 'length="'.$m[2].'"';
			if (!empty($m[3])) $attr[] = 'binary="true"';
			if (!$col['notnull']) $attr[] = 'nullable="true"';
			if (isset($col['default'])) $attr[] = 'default="'.$col['default'].'"';
			if ($col['comment']) $attr[] = 'comment="'.htmlspecialchars($col['comment']).'"';
			$data .= "\n\t\t".'<col name="'.$name.'" '.implode(' ',$attr).'/>';
		}

		# indices
		$indices = $this->SQL->listIndices($table);
		ksort($indices);
		foreach ($indices as $name => $key) {
			$data .= "\n\t\t".'<key name="'.$name.'"'.($key['type']?" type=\"{$key['type']}\"":'').'>';
			foreach ($key['columns'] as $name => $v)
				$data .= "\n\t\t\t".'<col name="'.$name.'"'.(strlen($name)!=strlen($v)?' length="'.substr($v,strlen($name)+1,-1).'"':'').'/>';
			$data .= "\n\t\t".'</key>';
		}

		# foreign keys
		$re = '#^'.$this->prefix.'(.+)$#D';
		foreach ($this->SQL->listForeignKeys($table) as $name => $key) {
			$name = preg_replace($re, '$1', $name);
			$key['references'] = preg_replace($re, '$1', $key['references']);
			$data .= "\n\t\t<key name=\"{$name}\" type=\"FOREIGN\" references=\"{$key['references']}\" ondelete=\"{$key['ondelete']}\" onupdate=\"{$key['onupdate']}\">";
			foreach ($key['columns'] as $name => $v) {
				$data .= "\n\t\t\t<col name=\"{$name}\"";
				if ($name !== $v) { $data .= " refcolumn=\"{$v}\""; }
				$data .= "/>";
			}
			$data .= "\n\t\t</key>";
		}

		# triggers
		foreach ($this->SQL->listTriggers($table) as $trigger) {
			$data .= "\n\t\t".'<trigger name="'.$trigger['name'].'" timing="'.$trigger['timing'].'" event="'.$trigger['event'].'"><![CDATA['.$trigger['statement'].']]></trigger>';
		}

		return $data."\n\t</table>";
	}

	public function getViewXML($name, $view)
	{
		$view = $this->SQL->getView($view);
		if ($this->SQL->TBL->prefix) {
			$view['definition'] = preg_replace("#([^a-z_]){$this->SQL->TBL->prefix}([a-z0-9_]+)#si", '$1{$2}', $view['definition']);
		}
		$view['definition'] = preg_replace(array(
				'# (from|(left|right|inner|outer) join|where|group by|order by) #',
				'#(^select |,)#',
				'# +\r?\n#s'
			), array(
				"\n\$1 ",
				"\$1\n\t",
				"\n"
			), $view['definition']
		);
		return "\t".'<view name="'.$name.'"><![CDATA['.$view['definition'].']]></view>';
	}

	public function exportSchema($stream)
	{
		static::isStream($stream);

		$re = '#^'.$this->prefix.'(.+)$#D';
		fwrite($stream, $this->getDocHead());

		foreach ($this->SQL->listFunctions() as $name) {
			if (preg_match($re, $name, $t)) {
				fwrite($stream, "\n\n".$this->getFunctionXML($t[1], $name));
			}
		}

		foreach ($this->SQL->listProcedures() as $name) {
			if (preg_match($re, $name, $t)) {
				fwrite($stream, "\n\n".$this->getProcedureXML($t[1], $name));
			}
		}

		foreach ($this->SQL->listTables(true) as $info) {
			if (preg_match($re, $info['name'], $t)) {
				fwrite($stream, "\n\n".$this->getTableXML($t[1], $info['name'], $info));
			}
		}

		foreach ($this->SQL->listViews() as $view) {
			if (preg_match($re, $view, $t)) {
				fwrite($stream, "\n\n".$this->getViewXML($t[1], $view));
			}
		}

		return false !== fwrite($stream, $this->getDocFoot());
	}

	public function exportTableData($stream, $table, array $config=array())
	{
		static::isStream($stream);
		$name = preg_match('#^'.$this->prefix.'(.+)$#D', $table, $t) ? $t[1] : $table;
		return false !== fwrite($stream, $this->getDocHead())
			&& false !== $this->writeTableDataXML($stream, $name, $table, $config)
			&& false !== fwrite($stream, $this->getDocFoot());
	}
}
