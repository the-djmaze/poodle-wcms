<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\SQL\XML;

class MySQL extends Importer
{
	private $engines;

	function __construct(\Poodle\SQL $SQL)
	{
		parent::__construct($SQL);
		$this->engines = $SQL->listStorageEngines();
	}

	private function getEngine(array $table) : string
	{
		if (!empty($table['engine'])) {
			$name = strtolower($table['engine']);
			if (isset($this->engines[$name])) {
				return $table['engine'];
			}
			switch ($name)
			{
			case 'aria':
				return 'MyISAM';
			case 'myrocks':
			case 'tokudb':
				return 'InnoDB';
/*
			archive
			binlog
			blackhole
			csv
			federated
			memory
			mrg_myisam
			partition
			performance_schema
			sequence
			wsrep
*/
			}
		}
		return '';
	}

	protected function syncTable(array $table)
	{
		$name = $this->tbl_prefix . $table['name'];
		$charset = $this->SQL->get_charset();

		// Alter table
		if (in_array($name, $this->db_tables)) {
			/**
			 * Convert character set and collation
 			 * SELECT table_name, column_name, character_set_name, collation_name FROM information_schema.COLUMNS WHERE table_schema = "poodle"
			 */
			$collation = $this->SQL->uFetchRow("SELECT table_collation FROM information_schema.TABLES
			WHERE table_schema = '{$this->SQL->database}'
			  AND table_name = '{$name}'");
			if ($collation) {
				$collation = $collation[0];
				$cs = explode('_', $collation)[0];
				if ($charset !== $cs) {
					trigger_error("table {$name} has charset {$cs}, and is converted to {$charset}");
					$this->doQuery("ALTER TABLE {$name} CONVERT TO CHARACTER SET {$charset} COLLATE " . \str_replace("{$cs}_", "{$charset}_", $collation), 0);
					$this->doQuery("ALTER TABLE {$name} CHARACTER SET {$charset} COLLATE {$charset}_bin", 0);
				}
			}

			$q = array();

			# columns
//			$cols = isset($this->SQL->TBL->$name) ? $this->SQL->listColumns($name) : array();
			$cols = $this->SQL->listColumns($name);
			foreach ($table['columns'] as $field) {
				if (empty($field['type'])) {
					continue;
				}
				$m = null;
				$n = $field['name'];
				$t = $field['type'];
				if (!empty($field['length'])) {
					$t .= "({$field['length']})";
				}
				if ($field['binary']) {
					$t .= ' BINARY';
				}
				if (isset($cols[$n])) {
					$col = $cols[$n];
					if ($col['type'] !== $t
					 || $col['notnull'] != $field['notnull']
					 || $col['comment'] != $field['comment']
					 || $col['default'] !== $field['default']
					){
						$m = 'MODIFY COLUMN';
						if ($field['notnull'] && !$col['notnull'] && isset($field['default'])) {
							$default = $field['default'];
							if ('CURRENT_TIMESTAMP' !== $default) {
								$default = "'{$default}'";
							}
							$this->SQL->query("UPDATE {$name} SET {$n} = {$default} WHERE {$n} IS NULL");
						}
					}
				} else
				if (!empty($field['oldname']) && isset($cols[$field['oldname']])) {
					$m = 'CHANGE COLUMN '.$field['oldname'];
				} else {
					$m = 'ADD COLUMN';
				}
				if ($m) {
					$q[] = $m.' '.$this->get_field_specification($field);
				}
			}

			# indices and foreign keys
			$keys = $this->SQL->listIndices($name);
			$fkeys = null;
			foreach ($table['keys'] as $key) {
				if (!$this->validPlatform($key)) {
					continue;
				}
				$n = $key['name'];
				if ('FOREIGN' === $key['type']) {
					$n = $this->tbl_prefix . $n;
					$fkeys = is_null($fkeys) ? $this->SQL->listForeignKeys($name) : $fkeys;
					if (!isset($fkeys[$n])
					 || $fkeys[$n]['references'] !== $this->tbl_prefix.$key['references']
					 || $fkeys[$n]['ondelete'] !== $key['ondelete']
					 || $fkeys[$n]['onupdate'] !== $key['onupdate']
					) {
						if (isset($fkeys[$n])) {
							$q[] = "DROP FOREIGN KEY {$n}";
						}
						$q[] = "ADD ".$this->get_foreign_key($key);
					}
				} else {
					$fields = array();
					foreach ($key['columns'] as $field) {
						$fn = $field['name'];
						$fields[$fn] = $fn . (empty($field['length']) ? '' : "({$field['length']})");
					}
					$primary = 'PRIMARY' === $n;
					$ADD = false;
					if (isset($keys[$n])) {
						$ADD = !$primary && $keys[$n]['type'] !== $key['type'];
						if (!$ADD) {
							foreach ($fields as $k => $v) {
								if (!isset($keys[$n]['columns'][$k]) || $keys[$n]['columns'][$k] !== $v) {
									$ADD = true;
									break;
								}
							}
						}
						if ($ADD) {
							$q[] = ($primary ? "DROP PRIMARY KEY" : "DROP INDEX {$n}");
						}
					}
					if (!isset($keys[$n]) || $ADD) {
						$q[] = ($primary ? "ADD PRIMARY KEY" : "ADD {$key['type']} INDEX {$n}").' ('.implode(', ',$fields).')';
					}
				}
			}
			$c = 1 + \count($table['keys']);
			if ($q) {
				$this->doQuery("ALTER TABLE {$name} ".\implode(', ',$q), $c);
			} else {
				$this->triggerAfterQuery("TABLE {$name} up to date", $c);
			}
		}

		// Create table
		else {
			$fields = $keys = array();

			foreach ($table['columns'] as $field) {
				if (empty($field['type'])) {
					$this->aq_event->index += (1 + count($table['keys']));
					return;
				}
				$fields[] = $this->get_field_specification($field);
			}

			foreach ($table['keys'] as $key) {
				if (!$this->validPlatform($key)) {
					continue;
				}
				$key_fields = array();
				foreach ($key['columns'] as $field) {
					$n = $field['name'];
					$key_fields[$n] = $n . (empty($field['length']) ? '' : "({$field['length']})");
				}
				$key_fields = implode(',',$key_fields);
				if ('PRIMARY' === $key['name']) {
					$fields[] = "PRIMARY KEY ({$key_fields})";
				} else
				if ('FOREIGN' === $key['type']) {
					$fields[] = $this->get_foreign_key($key);
				} else {
					$keys[] = "CREATE {$key['type']} INDEX {$key['name']} ON {$name} ({$key_fields})";
				}
			}

			if (!empty($table['engine']) && !isset($this->engines[strtolower($table['engine'])])) {
				$table['engine'] = 'MyISAM';
			}

			$this->doQuery("CREATE TABLE {$name} (".\implode(',',$fields).")"
				.(empty($table['engine'])?'':" ENGINE = {$table['engine']}")
				.(empty($table['comment'])?'':" COMMENT = ".$this->SQL->quote($table['comment']))
				." DEFAULT CHARACTER SET = {$charset} COLLATE = {$charset}_bin",
				1 + (\count($table['keys']) - \count($keys))
			);

			foreach ($keys as $query) {
				$this->doQuery($query);
			}

			$this->db_tables[$name] = $name;
		}
	}

	protected function syncTableTrigger(array $table, array $trigger)
	{
		static $triggers = array();
		if ($this->validPlatform($trigger)) {
			$table = "{$this->tbl_prefix}{$table['name']}";
			if (!isset($triggers[$table])) {
				$triggers[$table] = $this->SQL->listTriggers($table);
			}
			if (!isset($triggers[$table][$trigger['name']]) || $triggers[$table][$trigger['name']]['statement'] != $trigger['statement']) {
				$this->SQL->query("DROP TRIGGER IF EXISTS {$trigger['name']}");
				$this->doQuery("CREATE TRIGGER {$trigger['name']} {$trigger['timing']} {$trigger['event']} ON {$table} FOR EACH ROW {$trigger['statement']}");
			} else {
				$this->triggerAfterQuery("TRIGGER {$trigger['name']} ON {$table} up to date");
			}
		}
	}

	private function get_foreign_key($key)
	{
		if ('FOREIGN' === $key['type']) {
			$ref_fields = array();
			foreach ($key['columns'] as $field) {
				$n = $field['name'];
				$ref_fields[$n] = empty($field['refcolumn']) ? $n : $field['refcolumn'];
			}
			return "CONSTRAINT {$this->tbl_prefix}{$key['name']} FOREIGN KEY (".implode(',',array_keys($ref_fields)).") REFERENCES {$this->tbl_prefix}{$key['references']} (".implode(',',$ref_fields).")"
				.($key['ondelete']?" ON DELETE {$key['ondelete']}":'')
				.($key['onupdate']?" ON UPDATE {$key['onupdate']}":'');
		}
	}

	private function get_field_specification($field)
	{
		if (!isset($field['type'])) {
			return false;
		}
		$t = $field['type'];
		if ('BLOB' === $t) { $t = 'LONGBLOB'; }
		else if ('TEXT' === $t || 'SEARCH' === $t) { $t = 'LONGTEXT'; }
		else { $t = str_replace('SERIAL', 'INT', $t); }

		$v = $field['name'].' '.$t
			. (empty($field['length']) ? '' : "({$field['length']})")
			. (empty($field['binary']) ? '' : ' BINARY')
			. (empty($field['notnull']) ? '' : ' NOT NULL')
			. (false === strpos($field['type'], 'SERIAL') ? '' : ' AUTO_INCREMENT');
		if (isset($field['default'])) {
			if ('CURRENT_TIMESTAMP' === $field['default']) {
				$v .= " DEFAULT {$field['default']}";
			} else {
				$v .= " DEFAULT '{$field['default']}'";
			}
		}
		if (!empty($field['comment'])) {
			$v .= " COMMENT ".$this->SQL->quote($field['comment']);
		}
		return $v;
	}

}
