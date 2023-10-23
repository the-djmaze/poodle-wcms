<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/
if (!class_exists('Poodle',0)) { exit; }
$K = \Poodle::getKernel();
if (!($K instanceof \Poodle\Kernels\Setup)) { exit; }

if (empty($_SESSION)) { exit('Error: No session active'); }

$K->OUT->FATAL_ERROR = 0;

// Show FTP login form
$ftp_path = '';
if (!$K->isVerifiedAdmin()) {
	// httpdocs = Plesk
	// public_html = cPanel
	$sid_file = preg_replace('#^.*?/(htdocs|https?docs|domains|public_html|private_html|www)(/|$)#D', '/$1$2', $K->verificationFilename());

	$K->OUT->sid_file = $sid_file;

	if ('POST' === $_SERVER['REQUEST_METHOD']) {
		$ftp_path = rtrim($_POST['ftp']['path'], '/');
		if (!$K->setFTPSettings($_POST['ftp']['host'], $ftp_path, $_POST['ftp']['user'], $_POST['ftp']['pass'])) {
			$K->OUT->FATAL_ERROR = 1;
			$K->OUT->ftp = array(
				'host' => $_POST['ftp']['host'],
				'path' => $ftp_path,
				'user' => $_POST['ftp']['user']
			);
			$K->OUT->display('poodle/setup/ftp_form');
			return;
		}
	} else {
		$K->OUT->ftp = array(
			'host' => $K->site,
			'path' => dirname($sid_file),
			'user' => ''
		);
		$K->OUT->display('poodle/setup/ftp_form');
		return;
	}
}

$K->OUT->privileges = array();
$FTP = $K->getFTPConnection();
foreach (\Poodle\Info\System::directories($K->site, $FTP) as $key => $value) {
	$K->OUT->FATAL_ERROR |= ($value[0] && !$value[2]);
	$K->OUT->privileges[] = array(
		'TITLE'  => $key,
		'INFO'   => $K->OUT->L10N['info_'.preg_replace('#'.POODLE_HOSTS_PATH.'[^/]+#', 'poodle_hosts', $key)],
		'CLASS'  => ($value[2]?'ok':'fail'),
		'STATUS' => $K->OUT->L10N['_access'][$value[2]],
	);
}



$httpd = array(
	# PHP is an Apache module?
	'Server' => array(
		intval(function_exists('apache_get_modules') || stripos(PHP_SAPI, 'apache') !== false || stripos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false),
		$_SERVER['SERVER_SOFTWARE'],
	),
	'OS' => array(
		true,
		php_uname('s').', '.php_uname('r').' '.php_uname('v').', '.php_uname('m'),
	),
	'SEO' => array(
		(int)$_SERVER['SERVER_MOD_REWRITE'],
		''
	),
);
$K->OUT->httpd = array();
foreach ($httpd as $key => &$value) {
	$K->OUT->httpd[] = array(
		'TITLE'  => $key,
		'INFO'   => sprintf($K->OUT->L10N['info_'.$key], $value[1]),
		'CLASS'  => ($value[0]?'ok':'fail'),
		'STATUS' => $K->OUT->L10N['_avail'][$value[0]],
	);
}



/* Database extensions */
$has_db = 0;
$ext_db = array(
	'interbase' => array(0, 'firebirdsql.org', 'Firebird/Interbase'),
	'mysql'     => array(0, 'mysql.com', 'MySQL'),
	'mysqli'    => array(0, 'mysql.com', 'MySQL Improved Extension'), # \Poodle\SQL\Adapter\MySQLi
	'pgsql'     => array(0, 'postgresql.org', 'PostgreSQL'),          # \Poodle\SQL\Adapter\PgSQL
//	'SQLite' => array(0, 'sqlite.org', 'SQLite')
);
$K->OUT->dbms = array();
foreach ($ext_db as $key => &$value) {
	if (!\Poodle::getFile('poodle/sql/adapter/'.$key.'.php')) { continue; }
	$value[3] = extension_loaded($key);
	$has_db |= $value[3];
	$K->OUT->dbms[] = array(
		'URI'    => 'http://'.$value[1],
		'TITLE'  => $value[2],
		'INFO'   => $K->OUT->L10N['info_'.$key],
		'CLASS'  => ($value[3]?'ok':'fail'),
		'STATUS' => $K->OUT->L10N['_avail'][$value[3]],
	);
}
if (!$has_db) {
	$K->OUT->FATAL_ERROR |= 1;
	$K->OUT->dbms[] = array(
		'URI'    => 'http://php.net/manual/en/refs.database.php',
		'TITLE'  => 'No database extensions installed!',
		'INFO'   => $K->OUT->L10N['info_no_dbms'],
		'CLASS'  => 'fail',
		'STATUS' => $K->OUT->L10N['_avail'][0],
	);
}



# Check for PHP extensions
$K->OUT->php_ext = array();
foreach (\Poodle\Info\System::php_extensions() as $key => &$value) {
	$K->OUT->FATAL_ERROR |= ($value[0] && !$value[3]);
	if (0 === strpos($value[1], 'pecl-')) {
		$uri = 'https://pecl.php.net/package/' . substr($value[1],5);
	} else {
		$uri = 'https://php.net/'.$value[1];
	}
	$K->OUT->php_ext[] = array(
		'URI'    => $uri,
		'TITLE'  => $value[2],
		'INFO'   => $K->OUT->L10N['info_'.$key],
		'CLASS'  => ($value[3]?'ok':'fail'),
		'STATUS' => $K->OUT->L10N['_avail'][$value[3]],
	);
}



/* Check for PHP configuration */
$configuration = array(
	'file_uploads'      => array(1, 'ini.core#ini.sect.file-uploads'),
	'allow_url_fopen'   => array(1, 'filesystem.configuration#ini.allow-url-fopen'),
	'allow_url_include' => array(0, 'filesystem.configuration#ini.allow-url-include'),
	'mbstring.func_overload' => array(0, 'mbstring.func_overload'),
);
# upload_max_filesize < post_max_size < memory_limit
$K->OUT->php_cfg = array();
foreach ($configuration as $key => &$def) {
	$on = \Poodle\PHP\INI::get($key);
	$K->OUT->php_cfg[] = array(
		'URI'    => 'http://php.net/'.$def[1],
		'TITLE'  => $key,
		'INFO'   => $K->OUT->L10N['info_'.$key],
		'CLASS'  => (($def[0]==$on)?'ok':'fail'),
		'STATUS' => $K->OUT->L10N['_access'][$def[0]==$on],
		'VALUE'  => ($on?'on':'off'),
	);
}
$on = ob_get_level();
$K->OUT->php_cfg[] = array(
	'URI'    => 'http://php.net/zlib.output_compression',
	'TITLE'  => 'Output buffering',
	'INFO'   => $K->OUT->L10N['info_output_buffering'],
	'CLASS'  => ((0==$on)?'ok':'fail'),
	'STATUS' => $K->OUT->L10N['_access'][0==$on],
	'VALUE'  => ($on?'on':'off'),
);

$on = \Poodle\PHP\INI::get('max_input_vars');
$K->OUT->php_cfg[] = array(
	'URI'    => 'http://php.net/info.configuration#ini.max-input-vars',
	'TITLE'  => 'max_input_vars',
	'INFO'   => $K->OUT->L10N['info_max_input_vars'],
	'CLASS'  => ((1000<=$on)?'ok':'fail'),
	'STATUS' => $K->OUT->L10N['_access'][0==$on],
	'VALUE'  => $on,
);

$K->OUT->display('poodle/setup/server_check');
