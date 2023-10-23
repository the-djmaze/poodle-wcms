<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/
if (!class_exists('Poodle',0)) { exit; }
$K = Poodle::getKernel();
if (!($K instanceof \Poodle\Kernels\Setup)) { exit; }

$K->OUT->REQUEST_URI    = $_SERVER['REQUEST_URI'];
$K->OUT->SAVE_CFG_ERROR = null;

if (is_file($K->cfg_file)) {
	$K->OUT->display('poodle/setup/configuration_status');
	return;
}

if (isset($_GET['download'])) {
	if (!empty($_SESSION['CONFIG'])) {
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.strlen($_SESSION['CONFIG']));
		\Poodle\HTTP\Headers::setContentDisposition('attachment', array('filename'=>'config.php'));
		\Poodle\HTTP\Headers::setContentType('text/plain', array('name'=>'config.php'));
		echo $_SESSION['CONFIG'];
	}
	exit;
}

$config = $K->def_config = array(
	'dbms' => array(
		'adapter' => '',
		'tbl_prefix' => 'cms_',
		'master' => array(
			'host' => 'localhost',
			'username' => '',
			'password' => '',
			'database' => '',
			'charset' => 'utf8',
		),
	)
);

$K->OUT->DBMS_ERROR = null;

# database extensions
$ext_db = array(
	'interbase' => 'Firebird/Interbase',
	'mysqli'    => 'MySQL/MariaDB/Percona',
	'pgsql'     => 'PostgreSQL',
);
foreach ($ext_db as $type => $name) {
	if (!extension_loaded($type) || !\Poodle::getFile('poodle/sql/adapter/'.$type.'.php')) {
		unset($ext_db[$type]);
	}
}

if ($_POST) {
	# process input
	foreach ($config as $k => &$v) {
		if (is_array($v)) {
			foreach ($v as $k2 => &$v2) {
				if (is_array($v2)) {
					foreach ($v2 as $k3 => &$v3) {
						if (isset($_POST['config'][$k][$k2][$k3])) {
							$v3 = $_POST['config'][$k][$k2][$k3];
						}
					}
				}
				else if (isset($_POST['config'][$k][$k2])) {
					$v2 = $_POST['config'][$k][$k2];
				}
			}
		} else if (isset($_POST['config'][$k])) {
			$v = $_POST['config'][$k];
		}
	}
	if (!isset($ext_db[$config['dbms']['adapter']])) {
		$config['dbms']['adapter'] = '';
	}

	# try to connect to SQL Server
	try {
		$K->OUT->start();
		new \Poodle\SQL($config['dbms']['adapter'], $config['dbms']['master'], $config['dbms']['tbl_prefix']);
		$K->config = $config;

		$config_file = strtr(\Poodle\Config\File::get(null, true), array(
			"'adapter' => '',"    => "'adapter' => '{$config['dbms']['adapter']}',",
			"'tbl_prefix' => ''," => "'tbl_prefix' => '{$config['dbms']['tbl_prefix']}',",
			"'host' => '',"       => "'host' => '{$config['dbms']['master']['host']}',",
			"'username' => '',"   => "'username' => '{$config['dbms']['master']['username']}',",
			"'password' => '',"   => "'password' => '{$config['dbms']['master']['password']}',",
			"'database' => '',"   => "'database' => '{$config['dbms']['master']['database']}',",
		));
		if (!is_writable(dirname($K->cfg_file)) || !file_put_contents($K->cfg_file, $config_file)) {
			$K->OUT->SAVE_CFG_ERROR = sprintf($K->OUT->L10N['Download_config_php'], str_replace(\Poodle::$DIR_BASE,'/',dirname($K->cfg_file).'/'));
			$_SESSION['CONFIG'] = $config_file;
		}

		$K->OUT->display('poodle/setup/configuration_status');
		return;
	} catch (\Throwable $e) {
		$K->OUT->DBMS_ERROR = $e->getMessage();
	}
}

function generate_options(&$array, $def)
{
	$list = array();
	foreach ($array as $k => $v) {
		$list[] = array('value'=>$k, 'selected'=> ($def === $k), 'text'=>$v);
	}
	return $list;
}

# show form
$K->OUT->dbms_types  = generate_options($ext_db, $config['dbms']['adapter']);
$K->OUT->base_config = $config;
$K->OUT->display('poodle/setup/configuration');
