<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/

namespace Poodle\SQL\Interfaces;

interface Manager
{
	function __construct(\Poodle\SQL $SQL);
	public function listDatabases() : iterable;
	public function listTables(bool $detailed=false) : array;
	public function listColumns(string $table, bool $full=true) : array;
	public function listIndices(string $table) : array;
	public function listForeignKeys(string $table) : array;
	public function listTriggers(string $table) : array;
	public function listViews() : iterable;
	public function listFunctions() : iterable;
	public function listProcedures() : iterable;
	public function getView     (string $name) : ?array;
	public function getFunction (string $name) : ?array;
	public function getProcedure(string $name) : ?array;
	public function getTableInfo(string $name) : array;
	public function analyze(string $table=null) : ?Result;
	public function check(string $table=null) : ?Result;
	public function optimize(string $table=null) : ?Result;
	public function repair(string $table=null) : ?Result;
	public function tablesStatus() : iterable;
	public function serverStatus() : iterable;
	public function serverProcesses() : iterable;
	public function setSchemaCharset() : void;
}
