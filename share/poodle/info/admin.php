<?php

namespace Poodle\Info;

class Admin extends \Poodle\Resource\Admin
{
	public $title = 'System information';

	public function GET()
	{
		if (isset($_GET['backup'])) {
			if ('zip' === $_GET['backup']) {
				$backup = new \Poodle\Stream\ZIP();
			} else {
				$backup = new \Poodle\Stream\TAR();
			}
			$backup->pushHttpHeaders('backup-'.date('YmdHis'));
//			$backup->addRecursive(getcwd(), '#/(\\.hg(/|$)|\\.hgignore|cache/[^\\.])#');
			$backup->addRecursive(getcwd(), '#/(\\.hg(/|$)|\\.hgignore)#');
			$backup->close();
			exit;
		}

		$OUT = \Poodle::getKernel()->OUT;

		$OUT->info_general = \Poodle\PHP\Info::get(INFO_GENERAL);       // 1
		$OUT->info_config  = \Poodle\PHP\Info::get(INFO_CONFIGURATION); // 4
		$OUT->info_modules = \Poodle\PHP\Info::get(INFO_MODULES);       // 8
//		$OUT->info_envi    = \Poodle\PHP\Info::get(INFO_ENVIRONMENT);   // 16
		$OUT->info_vars    = \Poodle\PHP\Info::get(INFO_VARIABLES);     // 32

		$OUT->disk_space = array(
			'total' => disk_total_space(\Poodle::$DIR_BASE),
			'free' => disk_free_space(\Poodle::$DIR_BASE),
		);
		$OUT->disk_space['used'] = ($OUT->disk_space['total'] - $OUT->disk_space['free']);
//		low="75"
//		high="90"

		$OUT->L10N->load('setup');
		$OUT->head->addCSS('poodle_info');

		$OUT->check = array('dirs' => array(), 'php' => array());
		foreach (System::directories() as $key => $value) {
			$OUT->check['dirs'][] = array(
				'TITLE'  => $key,
				'INFO'   => $OUT->L10N['info_'.preg_replace('#'.POODLE_HOSTS_PATH.'[^/]+#', 'poodle_hosts', $key)],
				'CLASS'  => ($value[2]?'check-ok':'check-fail'),
				'STATUS' => $OUT->L10N['_access'][$value[2]],
			);
		}
		foreach (System::php_extensions() as $key => &$value) {
			if (0 === strpos($value[1], 'pecl-')) {
				$uri = 'https://pecl.php.net/package/' . substr($value[1],5);
			} else {
				$uri = 'https://php.net/'.$value[1];
			}
			$OUT->check['php'][] = array(
				'URI'    => $uri,
				'TITLE'  => $value[2],
				'INFO'   => $OUT->L10N['info_'.$key],
				'CLASS'  => ($value[3]?'check-ok':'check-fail'),
				'STATUS' => $OUT->L10N['_avail'][$value[3]],
			);
		}

		$OUT->head
			->addCSS('poodle_tabs')
			->addScript('poodle_tabs');

		$this->display('poodle/info/admin/info');
	}

}
