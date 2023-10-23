<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\SQL\Manager;

class MySQL implements \Poodle\SQL\Interfaces\Manager
{
	protected $SQL;

	function __construct(\Poodle\SQL $SQL) { $this->SQL = $SQL; }

	public function listDatabases() : iterable
	{
		$result = $this->SQL->query('SHOW DATABASES');
		while (list($name) = $result->fetch_row()) {
			yield $name;
		}
	}

	public function listStorageEngines() : array
	{
		$qr = $this->SQL->query('SELECT
			PLUGIN_NAME
		FROM information_schema.plugins
		WHERE PLUGIN_TYPE = \'STORAGE ENGINE\'
		  AND PLUGIN_MATURITY = \'Stable\'');
		$return = array();
		while ($r = $qr->fetch_row()) {
			$return[strtolower($r[0])] = $r[0];
		}
		return $return;
	}

	public function listTables(bool $detailed=false) : array
	{
		$tables = array();
		if ($detailed) {
			# SELECT TABLE_NAME, TABLE_COMMENT, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='poodle' AND TABLE_TYPE='BASE TABLE'
			$result = $this->SQL->query('SHOW TABLE STATUS'); // v5: WHERE Engine IS NOT NULL
			while ($row = $result->fetch_assoc()) {
				if ($row['Engine']) {
					$tables[] = array(
						'name'    => $row['Name'],
						'comment' => preg_replace('#InnoDB free:.*#','',$row['Comment']),
						'engine'  => $row['Engine'],
					);
				}
			}
		} else {
			$result = $this->SQL->query('SHOW FULL TABLES');
			while ($row = $result->fetch_row()) {
				if (!isset($row[1]) || 'BASE TABLE' === $row[1]) {
					$tables[] = $row[0];
				}
			}
		}
		return $tables;
	}

	public function listColumns(string $table, bool $full=true) : array
	{
		// TODO: issue with: DEFAULT 'CURRENT_TIMESTAMP'
		$return = array();
/*
		$result = $this->SQL->query("SELECT
			column_name,
			UPPER(CASE WHEN 'auto_increment'=extra THEN REPLACE(data_type,'int','serial') ELSE data_type END) AS type,
			character_maximum_length AS length,
			CASE WHEN collation_name LIKE '%_bin' THEN 1 ELSE 0 END AS 'binary',
			CASE WHEN is_nullable='YES' THEN 1 ELSE 0 END AS notnull,
			column_default AS 'default',
			column_comment AS comment,
			numeric_precision, numeric_scale, column_type
		 FROM information_schema.columns
		 WHERE table_schema=DATABASE() AND table_name='{$table}'
		 ORDER BY ordinal_position");
		while ($row = $result->fetch_assoc()) {
			$row['type'] = str_replace('DECIMAL', 'NUMERIC', $row['type']);
			$row['type'] = str_replace('LONGTEXT', 'TEXT', $row['type']);
			$row['type'] = str_replace('LONGBLOB', 'BLOB', $row['type']);
			if ('NUMERIC' === $row['type']) {
				$row['length'] = "{$row['numeric_precision'],$row['numeric_scale']}";
			} else if ('TEXT' === $row['type'] || 'BLOB' === $row['type']) {
				$row['length'] = NULL;
			}
			$return[$row['column_name']] = array(
				'type'    => $row['type'],
				'length'  => $row['length'],
				'binary'  => (bool) $row['binary'],
				'notnull' => (bool) $row['notnull'],
				'default' => $row['default'],
				'comment' => $full ? $row['Comment'] : null,
				'extra' => null // strtoupper($row['Extra'])
			);
		}
*/
		$full = $full?'FULL':'';
		$result = $this->SQL->query("SHOW {$full} COLUMNS FROM {$table}");
		$re_cb = function($m){return strtoupper($m[1]);};
		while ($row = $result->fetch_assoc()) {
			$row['Type'] = preg_replace_callback('#^([a-z\s]+)#', $re_cb, $row['Type']);
			$row['Type'] = str_replace(' unsigned', '', $row['Type']);
			if ($full && strpos($row['Collation'], '_bin')) { $row['Type'] .= ' BINARY'; }
			if (false !== strpos($row['Type'], 'INT(')) {
				$row['Type'] = preg_replace('#INT\(\d+\)#', 'INT', $row['Type']);
			}
			if ('auto_increment' === $row['Extra']) {
				$row['Type'] = (strpos($row['Type'], 'BIGINT') === false) ? 'SERIAL' : 'BIGSERIAL';
				$row['Default'] = null;
			} else if (strpos($row['Default'], '()')) {
				$row['Default'] = strtoupper(substr($row['Default'],0,-2));
			}
			$row['Type'] = str_replace('DECIMAL','NUMERIC',$row['Type']);
			$row['Type'] = str_replace('LONGTEXT','TEXT',$row['Type']);
			$row['Type'] = str_replace('LONGBLOB','BLOB',$row['Type']);
			$length = null;
			if (preg_match('/\\(([0-9,]+)\\)/', $row['Type'], $m)) {
				$length = $m[1];
			}
			$return[$row['Field']] = array(
				'type'  => $row['Type'],
				'length'  => $length,
				'notnull' => $row['Null'] === 'NO',
				'default' => $row['Default'],
				'comment' => $full ? $row['Comment'] : null,
				'extra' => null // strtoupper($row['Extra'])
			);
		}
		return $return;
	}

	public function listIndices(string $table) : array
	{
/*		SELECT constraint_name, column_name, constraint_type
		FROM information_schema.key_column_usage
		LEFT JOIN information_schema.table_constraints USING (constraint_name, table_schema, table_name)
		WHERE table_schema='{$this->SQL->database}' AND table_name='{$table}' ORDER BY ordinal_position*/
		$result = $this->SQL->query('SHOW INDEX FROM '.$table);
		$return = array();
		while ($row = $result->fetch_assoc()) {
			$key = $row['Key_name'];
			if (!isset($return[$key])) {
				$return[$key] = array(
					'columns' => array()
				);
				if ('PRIMARY' === $key) {
					$return[$key]['type'] = 'PRIMARY';
				} else if (empty($row['Non_unique'])) {
					$return[$key]['type'] = 'UNIQUE';
				} else {
					$return[$key]['type'] = ('BTREE'===$row['Index_type']?'':$row['Index_type']); # BTREE or FULLTEXT/SPATIAL
				}
			}
			$col = $row['Column_name']/*(int)$row['Seq_in_index']-1*/;
			$return[$key]['columns'][$col] = $col.($row['Sub_part']?'('.$row['Sub_part'].')':'');
		}
		return $return;
	}

	public function listForeignKeys(string $table) : array
	{
		// bug: SELECT constraint_name, referenced_table_name, delete_rule, update_rule, column_name, referenced_column_name
		$result = $this->SQL->query("SELECT *
		FROM information_schema.referential_constraints
		INNER JOIN information_schema.key_column_usage USING (constraint_name, constraint_schema, referenced_table_name, table_name)
		WHERE table_schema='{$this->SQL->database}' AND table_name='{$table}' ORDER BY ordinal_position");
		$return = array();
		while ($row = $result->fetch_assoc()) {
			$key = $row['CONSTRAINT_NAME'];
			$return[$key]['references'] = $row['REFERENCED_TABLE_NAME'];
			$return[$key]['ondelete']   = $row['DELETE_RULE'];
			$return[$key]['onupdate']   = $row['UPDATE_RULE'];
			$return[$key]['columns'][$row['COLUMN_NAME']] = $row['REFERENCED_COLUMN_NAME'];
		}
		return $return;
	}

	public function listTriggers(string $table) : array
	{
		$result = $this->SQL->query("SELECT trigger_name, action_timing, event_manipulation, action_statement FROM information_schema.triggers
		WHERE event_object_schema='{$this->SQL->database}' AND event_object_table='{$table}'");
		$return = array();
		while ($row = $result->fetch_row()) {
			$return[$row[0]] = array(
				'name'  =>$row[0],
				'timing'=>$row[1],
				'event' =>$row[2],
				'statement'=>$row[3],
			);
		}
		return $return;
	}

	public function listViews() : iterable
	{
		$result = $this->SQL->query("SELECT table_name FROM information_schema.views WHERE table_schema='{$this->SQL->database}'");
		while ($row = $result->fetch_row()) {
			yield $row[0];
		}
	}

	public function listFunctions() : iterable  { return $this->list_definition('FUNCTION'); }
	public function listProcedures() : iterable { return $this->list_definition('PROCEDURE'); }
	private function list_definition(string $type/*FUNCTION|PROCEDURE*/) : iterable
	{
		# SELECT routine_name FROM information_schema.routines WHERE routine_schema='{$this->SQL->database}' AND routine_type='{$type}'
		$result = $this->SQL->query("SHOW {$type} STATUS WHERE Db='{$this->SQL->database}'");
		while ($row = $result->fetch_assoc()) {
			yield $row['Name'];
		}
	}
/*
	public function getView($name)
	{
		if ($result = $this->SQL->query("SELECT VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_SCHEMA='{$this->SQL->database}' AND TABLE_NAME='{$name}'")) {
			if ($row = $result->fetch_row()) {
				return array('definition' => trim(str_replace('`','',$row[0])));
			}
		}
		return false;
	}
*/
	public function getView     (string $name) : ?array { return $this->getMySQLDefinitionFor('VIEW', $name); }
	public function getFunction (string $name) : ?array { return $this->getMySQLDefinitionFor('FUNCTION', $name); }
	public function getProcedure(string $name) : ?array { return $this->getMySQLDefinitionFor('PROCEDURE', $name); }
	private function getMySQLDefinitionFor(string $type/*FUNCTION|PROCEDURE|VIEW*/, string $name) : array
	{
		# CREATE DEFINER=`root`@`localhost` $type `$name`(dtstart DATETIME, dtend DATETIME) RETURNS double(5,1)
		if ($row = $this->SQL->query("SHOW CREATE {$type} {$name}")->fetch_assoc()) {
			$row = str_replace('`','',$row);
			# CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_*` AS select
			if (preg_match('#^CREATE[^\r\n]+?(\([^\r\n]*\)| AS )(?:\s+RETURNS\s+([^\s]+)\s+)?(.*)$#Ds', $row['Create '.ucfirst(strtolower($type))], $match)) {
				$params = array();
				if ($match[1] && preg_match_all('#[\(,]\s*(IN|OUT|INOUT)?\s*([a-zA-Z0-9_]+)\s+([a-zA-Z0-9]+)(?:\(([^\(\)]+)\))?#s', $match[1], $m, PREG_SET_ORDER)) {
					foreach ($m as $p) $params[] = array(
						'dir' =>$p[1],
						'name'=>$p[2],
						'type'=>$p[3],
						'length'=>$p[4],
					);
				}
				return array(
					'parameters' => $params,
					'returns'    => strtoupper($match[2]),
					'definition' => trim(preg_replace('#^BEGIN(.+)END$#Dsi','$1',preg_replace('#(\s|[^\s]+\.)([^\s\.]+)\s+AS\s+\\2#','$1$2',preg_replace('#\s+AS\s+[a-z_]+\(.*?\)#i', '$1', trim($match[3])))))
				);
			}
		}
		return null;
	}

	public function getTableInfo(string $name) : array
	{
		# SELECT TABLE_NAME, TABLE_COMMENT, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA='poodle' AND TABLE_TYPE='BASE TABLE'
		$row = $this->SQL->query("SHOW TABLE STATUS LIKE '{$name}'")->fetch_assoc(); // v5: WHERE Engine IS NOT NULL
		return array(
			'name'    => $row['Name'],
			'comment' => preg_replace('#InnoDB free:.*#', '', $row['Comment']),
			'engine'  => $row['Engine'],
		);
	}

	public function analyze(string $table=null)  : ?\Poodle\SQL\Interfaces\Result { return $this->SQL->query('ANALYZE TABLE ' .($table?:implode(', ', $this->listTables()))); }
	public function check(string $table=null)    : ?\Poodle\SQL\Interfaces\Result { return $this->SQL->query('CHECK TABLE '   .($table?:implode(', ', $this->listTables()))); }
	public function optimize(string $table=null) : ?\Poodle\SQL\Interfaces\Result { return $this->SQL->query('OPTIMIZE TABLE '.($table?:implode(', ', $this->listTables()))); }
	public function repair(string $table=null)   : ?\Poodle\SQL\Interfaces\Result { return $this->SQL->query('REPAIR TABLE '  .($table?:implode(', ', $this->listTables()))); }

	public function tablesStatus()    : iterable { return $this->SQL->query('SHOW TABLE STATUS'); }
	public function serverStatus()    : iterable { return $this->SQL->query('SHOW STATUS'); }
	public function serverProcesses() : iterable { return $this->SQL->query('SHOW PROCESSLIST'); }

	public function setSchemaCharset() : void
	{
		$v = $this->SQL->get_charset();
		$this->SQL->query("ALTER DATABASE CHARACTER SET {$v} COLLATE {$v}_bin");
	}
}
