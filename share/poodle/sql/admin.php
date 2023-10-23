<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\SQL;

class Admin /*extends \Poodle\Resource*/
{
	public
		// resource
		$id        = 0,
		$uri       = '',
		$parent_id = 0,
		$type_id   = 0,
		$ctime     = 0, # creation date
		$ptime     = 0, # publish date
		$etime     = 0, # expiry date
		$flags     = 0,
		$creator_identity_id = 0,
		$bodylayout_id = 0,
		// resource type
		$type_name,
		$type_label,
		$type_class,
		$type_flags,
		// resource data
		$l10n_id    = 0,
		$mtime      = 0, # modified date
		$status     = 0,
		$title      = 'SQL Administration',
		$body       = '',
		$searchable = false,
		$modifier_identity_id = 0,

		$allowed_methods = array('GET','HEAD','POST');

	public function GET()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		if (isset($_GET['export'])) {
			if (isset($_GET['table']) && !preg_match('#^[a-z0-9_]+$#D', $_GET['table'])) {
				\Poodle\Report::error(412);
			}

			if ('xlsx' === $_GET['export']) {
				$result = $SQL->query("SELECT * FROM {$_GET['table']}");
				$xlsx = new \Poodle\XLSX\Writer();
				$style1 = array('color'=>'#FFFFFF','fill'=>'#666','font-style'=>'bold');
				$style2 = array('fill'=>'#EEE');
				$i = 0;
				$r = $result->fetch_assoc();
				$xlsx->writeSheetHeader('result', array_fill_keys(array_keys($r), 'string'), false, $style1);
				do {
					$xlsx->writeSheetRow('result', $r, (0 == $i++ % 2 ? array() : $style2));
				} while ($r = $result->fetch_row());
				$xlsx->writeToStdOut($_GET['table'].'.xlsx');
				return;
			}

			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Pragma: no-cache');
			header('Content-Transfer-Encoding: binary');
			$type = 'xml';
			$filename = $_GET['export'].'.xml';
			if ('csv' === $_GET['export']) {
				$type = 'octet-stream';
				$filename = $_GET['table'].'.csv';
			} else if ('xml' === $_GET['export']) {
				$filename = $_GET['table'].'.xml';
			}
			\Poodle\HTTP\Headers::setContentDisposition('attachment', array('filename'=>$filename));
			\Poodle\HTTP\Headers::setContentType('application/'.$type, array('name'=>$filename));

			$fp  = fopen('php://output','w');
			if ('csv' === $_GET['export'])
			{
				$SQL->resultToCSV($SQL->query("SELECT * FROM {$_GET['table']}"), $fp);
			}
			else if ('xml' === $_GET['export'])
			{
				$SQL->XML->getExporter()->exportTableData($fp, $_GET['table']);
			}
			else if ('data' === $_GET['export'])
			{
				$SQL->XML->getExporter()->exportData($fp, array('onduplicate' => 'IGNORE'));
			}
			else if ('schema' === $_GET['export'])
			{
				$SQL->XML->getExporter()->exportSchema($fp);
			}
			fclose($fp);
			return;
		}

		$this->HEAD();

		$K->OUT->crumbs->append($K->OUT->L10N['Database'], '/admin/poodle_sql/');
		$K->OUT->sql_query = null;
		$K->OUT->sql_tables = null;
		$K->OUT->query_result = null;
		if (isset($_GET['tables'])) {
			if ('list' === $_GET['tables']) {
				$K->OUT->sql_tables = $SQL->listTables(true);
			} else {
				$K->OUT->query_result = $SQL->{$_GET['tables']}();
			}
		} else if (isset($_GET['info'])) {
			$K->OUT->db_lists = array(
				array(
					'name' => 'tables',
					'items' => $SQL->listTables()
				),
				array(
					'name' => 'views',
					'items' => $SQL->listViews()
				),
				array(
					'name' => 'functions',
					'items' => $SQL->listFunctions()
				),
				array(
					'name' => 'procedures',
					'items' => $SQL->listProcedures()
				)
			);
/*
			$SQL->tablesStatus();
			$SQL->serverStatus();
			$SQL->serverProcesses();
*/
			return $K->OUT->display('poodle/sql/info');
		} else {
			$K->OUT->head->addScript('poodle_sql_admin');
		}
		$K->OUT->display('poodle/sql/admin');
	}

	public function HEAD()
	{
		\Poodle\HTTP\Headers::setLastModified($this->mtime);
		\Poodle::getKernel()->OUT->send_headers();
	}

	public function POST()
	{
		$K = \Poodle::getKernel();

		if (isset($_GET['import'])) {
			if (!empty($_FILES['import_xml'])) {
				$SQL = $K->SQL;
				$XML = $SQL->XML->getImporter();
				$file = $_FILES->getAsFileObject('import_xml');
				if (XMLHTTPRequest) {
					\Poodle::startStream();
					//header('Content-Type: application/octet-stream');
					$errors = array();
					if ($file->errno) {
						$errors[] = array('message' => $file->error);
					} else {
						$XML->addEventListener('afterquery', function(\Poodle\Events\Event $event){
							Admin::pushJSON(array('progress' => array('max'=>$event->count, 'value'=>$event->index, 'message'=>$event->query)));
						});
						if (!$XML->syncSchemaFromFile($file->tmp_name)) {
							$errors[] = $XML->errors[0];
						}
						$K->CACHE->clear();
					}
					if ($errors) {
						static::pushJSON(array('errors' => $errors));
					} else {
						static::pushJSON(array('complete' => true));
					}
				} else {
					if ($file->errno) {
						\Poodle\Report::error($file->error);
					}
					$XML->addEventListener('afterquery', function(){echo '. ';});
					if (!$XML->syncSchemaFromFile($file->tmp_name)) {
						echo '<p class="error">'.print_r($XML->errors, true).'</p>';
					} else {
						echo 'Finished';
					}
					$K->CACHE->clear();
				}
			}
			return;
		}

		$K->OUT->sql_tables = null;
		$K->OUT->sql_query = null;
		$K->OUT->query_result = null;

		if (isset($_POST['sql_query']) && isset($_POST['execute'])) {
			$K->OUT->sql_query = $q = $_POST->raw('sql_query');
			$r = $K->SQL->query($q);
			if (is_bool($r)) {
				\Poodle\Notify::success('Query executed');
			} else {
				$K->OUT->query_result = $r;
			}
			\Poodle\LOG::info(__CLASS__ . ' Query', $q);
		}

		$this->HEAD();
		$K->OUT->display('poodle/sql/admin');
	}

	protected static function pushJSON(array $data)
	{
		echo \Poodle::dataToJSON($data) . "\n";
	}

}
