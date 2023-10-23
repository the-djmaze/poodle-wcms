<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/
if (!class_exists('Poodle',0)) { exit; }
$K = \Poodle::getKernel();
if (!($K instanceof \Poodle\Kernels\Setup)) { exit; }

if (!is_file($K->cfg_file) || !is_readable($K->cfg_file)) {
	\Poodle\HTTP\Headers::setLocation('?step='.($K->step-1), 303);
	$K->display_error('Configuration not finished');
}

$K->loadDatabase();

$fatal_error = 0;

if (!count($K->SQL->TBL)
 || 'upgrade' == $_SESSION['SETUP_MODE']
 || empty($_SESSION['DB_ERROR'])
 || (!empty($_SESSION['DB_ERROR']) && isset($_GET['retry']))
 || isset($_GET['verify']))
{
	$query_file = new \Poodle\Setup\SQL();
	$K->OUT->start();

	$K->SQL->setSchemaCharset();
/*
	$files = array();
	foreach (glob(dirname(dirname(__DIR__)).'/*',GLOB_ONLYDIR) as $dir) if (is_file($dir.'/db.xml')) $files[] = $dir.'/db.xml';
	natcasesort($files);
*/
	// Create/Sync schema
	$fatal_error = !$query_file->exec('schema', $K->OUT->L10N['Creating schema']);
	if (!$fatal_error) {
		if (!isset($_GET['verify'])) {
			// Create/Sync data
			$fatal_error = !$query_file->exec('data', $K->OUT->L10N['Inserting data']);
			if (!$fatal_error) {
				$K->SQL->exec("UPDATE {config} SET cfg_value='".\Poodle::DB_VERSION."' WHERE cfg_section='poodle' AND cfg_key='db_version'", true);
				if (!\Poodle\Setup\SQL::version()) {
					$K->SQL->exec("UPDATE {config} SET cfg_value=".time()." WHERE cfg_section='site' AND cfg_key='startdate'", true);
				}
				$K->CACHE->clear();
			}
		}
	}
}
$_SESSION['DB_ERROR'] = $fatal_error;

$K->OUT->FATAL_ERROR = $fatal_error;
$K->OUT->display('poodle/setup/database');
