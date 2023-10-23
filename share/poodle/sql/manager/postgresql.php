<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

/*
CREATE SEQUENCE seqname
CREATE TABLE products (price numeric CONSTRAINT positive_price CHECK (price > 0));
CREATE TABLE orders (
	order_id integer PRIMARY KEY,
	product_no integer REFERENCES products (product_no) ON DELETE CASCADE,
	quantity integer
);
FOREIGN KEY (b, c) REFERENCES other_table (c1, c2)
ALTER TABLE `test`.`child` DROP FOREIGN KEY `child_parent`;
ALTER TABLE `test`.`child` ADD CONSTRAINT `child_parent` FOREIGN KEY (`parent_id`) REFERENCES `parent` (`id`) ON UPDATE NO ACTION;
NO ACTION, CASCADE delete child as well, RESTRICT, SET NULL and SET DEFAULT.
*/

namespace Poodle\SQL\Manager;

class PostgreSQL implements \Poodle\SQL\Interfaces\Manager
{
	protected $SQL;

	function __construct(\Poodle\SQL $sql) { $this->SQL = $sql; }

	public function listDatabases() : iterable
	{
		$result = $this->SQL->query('SELECT datname FROM pg_database');
		while (list($name) = $result->fetch_row()) {
			yield $name;
		}
	}

	public function listTables(bool $detailed=false) : array
	{
		$result = $this->SQL->query('SELECT tablename FROM pg_catalog.pg_tables WHERE tableowner = current_user');
		$tables = array();
		while (list($tblname) = $result->fetch_row()) {
			$tables[] = $detailed ? array('name'=>$tblname,'comment'=>'','engine'=>'') : $tblname;
		}
		return $tables;
	}

	public function listColumns(string $table, bool $full=true) : array
	{
/*
		$query = "SELECT
			a.attname AS column_name,
			CASE t.typname
				WHEN 'int2' THEN 'smallint'
				WHEN 'int4' THEN 'integer'
				WHEN 'int8' THEN 'bigint'
				WHEN 'bpchar' THEN 'character'
				WHEN 'varchar' THEN 'character varying'
				WHEN 'float4' THEN 'real'
				WHEN 'float8' THEN 'double precision'
				ELSE t.typname
			END AS data_type,
			t.typname AS udt_name,
			CASE a.attlen
				WHEN -1 THEN
					CASE t.typname
						WHEN 'numeric' THEN (a.atttypmod / 65536)
						WHEN 'decimal' THEN (a.atttypmod / 65536)
						WHEN 'money'   THEN (a.atttypmod / 65536)
						ELSE CASE a.atttypmod
							WHEN -1 THEN NULL
							ELSE a.atttypmod - 4
						END
					END
				ELSE a.attlen
			END AS character_maximum_length,
			CASE WHEN a.attnotnull THEN 'NO' ELSE 'YES' END AS is_nullable,
			ad.adsrc AS column_default,
			cd.description AS comment
		FROM pg_class c
		INNER JOIN pg_attribute a ON (a.attnum > 0 AND a.attrelid = c.oid)
		INNER JOIN pg_type t ON (t.oid=a.atttypid)
		LEFT JOIN pg_attrdef ad ON (ad.adrelid = c.oid AND ad.adnum = a.attnum AND a.atthasdef)
		LEFT JOIN pg_description cd ON (cd.objoid = c.oid AND cd.objsubid = a.attnum)
		WHERE c.relname = '{$table}'
		ORDER BY a.attnum";
*/
		$full = $full
			? '(SELECT d.description
			FROM pg_description d, pg_class c, pg_attribute a
			WHERE a.attname=column_name AND a.attnum > 0 AND a.attrelid = c.oid
			  AND c.relname = table_name
			  AND d.objoid = c.oid AND d.objsubid = a.attnum
			)'
			: "''";
		$query = "SELECT column_name, data_type, udt_name, character_maximum_length, is_nullable, column_default,
			{$full} AS comment
		FROM information_schema.columns
		WHERE table_name='{$table}'
		ORDER BY ordinal_position";

		$return = array();
		$result = $this->SQL->query($query);
		$re_cb = function($m){return strtoupper($m[1]);};
		while ($row = $result->fetch_assoc()) {
			$row['data_type'] = preg_replace_callback('#^([a-z\s]+)#', $re_cb, $row['data_type']);
			# do we have an serial ?
			if (strpos($row['column_default'], 'nextval(') !== false) {
				if ($row['data_type'] === 'INTEGER') {
					$row['data_type'] = 'SERIAL';
				} else {
					$row['data_type'] = 'BIGSERIAL';
				}
				$row['column_default'] = null;
			} else if (strpos($row['data_type'], 'CHAR') !== false) {
				$row['data_type'] .= '('.$row['character_maximum_length'].')';
			}
			$row['data_type'] = str_replace(
				array('CHARACTER VARYING', 'CHARACTER', 'INTEGER'),
				array('VARCHAR',           'CHAR',      'INT'),
				$row['data_type']);
			if (preg_match('#^([\d]+|\'(.*)?\')#',$row['column_default'], $match)) {
				$row['column_default'] = isset($match[2])?$match[2]:$match[1];
			} else {
				$row['column_default'] = null;
			}
			$return[$row['column_name']] = array(
				'type'  => $row['data_type'],
				'notnull' => $row['is_nullable'] === 'NO',
				'default' => $row['column_default'],
				'comment' => $row['comment'],
				'extra' => null
			);
		}
		return $return;
	}

	public function listIndices(string $table) : array
	{
		# CREATE TABLE will create implicit sequence "poodle_resources_resource_id_seq" for serial column "poodle_resources.resource_id"
		# CREATE TABLE / PRIMARY KEY will create implicit index "poodle_innodb_tablecount_pkey" for table "poodle_innodb_tablecount"
		$result = $this->SQL->query('SELECT
				CASE WHEN i.indisprimary THEN \'PRIMARY\' ELSE (SELECT relname FROM pg_class WHERE oid = i.indexrelid) END,
				CASE WHEN i.indisprimary THEN \'PRIMARY\' WHEN i.indisunique THEN \'UNIQUE\' ELSE \'\' END,
				ca.attname
			FROM pg_class tc, pg_index i, pg_attribute ca
			WHERE (tc.relname = \''.$table.'\')
				AND (i.indrelid = tc.oid)
				AND (ca.attrelid = tc.oid)
				AND (ca.attnum = ANY (i.indkey))');
		$return = array();
		while ($row = $result->fetch_row()) {
			$key = $row[0]; // str_replace($table.'_', '', $row[0])
			if (!isset($return[$key])) {
				$return[$key] = array(
					'type' => $row[1],
					'columns' => array()
				);
			}
			$return[$key]['columns'][$row[2]] = $row[2];
		}
		return $return;
	}

	public function listForeignKeys(string $table) : array
	{
		$result = $this->SQL->query("SELECT
			rc.constraint_name,
			ref.table_name AS referenced_table_name,
			rc.delete_rule,
			rc.update_rule,
			kcu.column_name,
			ref.column_name AS referenced_column_name
		FROM information_schema.referential_constraints AS rc
		INNER JOIN information_schema.key_column_usage AS kcu ON (kcu.constraint_catalog=rc.constraint_catalog
			AND kcu.constraint_schema=rc.constraint_schema AND kcu.constraint_name=rc.constraint_name)
		INNER JOIN information_schema.key_column_usage AS ref ON (ref.constraint_catalog=rc.constraint_catalog
			AND ref.constraint_schema=rc.constraint_schema AND ref.constraint_name=rc.unique_constraint_name
			AND ref.ordinal_position=kcu.position_in_unique_constraint)
		WHERE rc.constraint_catalog='{$this->SQL->database}' AND kcu.table_name='{$table}'
		ORDER BY kcu.ordinal_position");
		$return = array();
		while ($row = $result->fetch_row()) {
			$key = $row[0];
			$return[$key]['references'] = $row[1];
			$return[$key]['ondelete']   = $row[2];
			$return[$key]['onupdate']   = $row[3];
			$return[$key]['columns'][$row[4]] = $row[5];
		}
		return $return;
	}

	public function listTriggers(string $table) : array
	{
/*
		SELECT tgname, tgargs, proname, prosrc FROM pg_trigger, pg_class, pg_proc WHERE pg_proc.oid=pg_trigger.tgfoid AND pg_trigger.tgrelid = pg_class.oid WHERE relname = \''.strtolower($table)
		CREATE FUNCTION [proname]() RETURNS trigger AS $$ [prosrc] $$ LANGUAGE plpgsql VOLATILE;
		CREATE TRIGGER [tgname] AFTER INSERT ON poodle_resources FOR EACH ROW EXECUTE PROCEDURE [proname]('[tgargs]');
*/
		$result = $this->SQL->query("SELECT trigger_name, condition_timing, event_manipulation, action_statement
			FROM information_schema.triggers
			WHERE event_object_catalog='{$this->SQL->database}' AND event_object_table='{$table}'");
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
		$result = $this->SQL->query("SELECT table_name, view_definition FROM information_schema.views
			WHERE table_catalog='{$this->SQL->database}'");
		while ($row = $result->fetch_row()) {
			yield $row[0];
		}
	}

	public function listFunctions() : iterable
	{
		$result = $this->SQL->query("SELECT routine_name FROM information_schema.routines
			WHERE specific_schema NOT IN ('pg_catalog', 'information_schema')
			  AND type_udt_name != 'trigger'");
		while ($row = $result->fetch_assoc()) {
			yield $row['Name'];
		}
	}

	public function listProcedures() : iterable
	{
		$result = $this->SQL->query('SELECT proname FROM pg_proc');
		while ($row = $result->fetch_assoc()) {
			yield $row['Name'];
		}
	}

	public function getView(string $name) : ?array
	{
		return null;
	}

	public function getFunction(string $name) : ?array
	{
		return null;
	}

	public function getProcedure(string $name) : ?array
	{
/*
		SELECT p.proname AS procedure_name,
			p.pronargs AS num_args,
			t1.typname AS return_type,
			a.rolname AS procedure_owner,
			l.lanname AS language_type,
			p.proargtypes AS argument_types_oids,
			prosrc AS body
		FROM pg_proc p
		LEFT JOIN pg_type t1 ON p.prorettype=t1.oid
		LEFT JOIN pg_authid a ON p.proowner=a.oid
		LEFT JOIN pg_language l ON p.prolang=l.oid
		WHERE proname = '$name'
*/
		return null;
	}

	public function getTableInfo(string $name) : array
	{
		return array('name'=>$name, 'comment'=>'', 'engine'=>'');
	}

	public function analyze(string $table=null)  : ?\Poodle\SQL\Interfaces\Result { return $this->SQL->query('ANALYZE '.$table); }
	public function check(string $table=null)    : ?\Poodle\SQL\Interfaces\Result { return $this->SQL->query('VACUUM ANALYZE '.$table); }
	public function optimize(string $table=null) : ?\Poodle\SQL\Interfaces\Result { return $this->SQL->query('VACUUM FULL '.$table); }
	public function repair(string $table=null)   : ?\Poodle\SQL\Interfaces\Result { return null; }

	public function tablesStatus()    : iterable { return []; }
	public function serverStatus()    : iterable { return []; }
	public function serverProcesses() : iterable { return []; }

	public function setSchemaCharset() : void {}
}
