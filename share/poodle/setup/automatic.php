<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/

namespace Poodle\Setup;

class Automatic
{

	protected static function log($message)
	{
		if (isset($_GET['view_upgrade'])) {
			echo $message . "\n";
		} else {
			\error_log($message);
		}
	}

	public static function upgrade($display_maintenance = true) : bool
	{
		\ignore_user_abort(true);

		if ($display_maintenance && !isset($_GET['view_upgrade'])) {
			$K = \Poodle::getKernel();
			\Poodle::$COMPRESS_OUTPUT = \is_callable('fastcgi_finish_request');
			\ob_start();
			if ($K->OUT->head) {
				$K->OUT->head->title = 'Maintenance';
				$K->OUT->head->addMeta('robots', 'noindex,noarchive');
			}
			if ($K->OUT->crumbs) {
				$K->OUT->crumbs->clear();
			}
			$K->OUT->trace = array();
			$K->OUT->tpl_layout   = 'error';
			$K->OUT->error_msg    = 'We are upgrading our website. Please be patient, we should be back shortly.';
			$K->OUT->error_code   = 503;
			$K->OUT->error_title  = 'Error 503';
			$K->OUT->uri_index    = '';
			$K->OUT->uri_redirect = '';
			$K->OUT->display('error');
			$K->OUT->finish();
			\Poodle\HTTP\Status::set(503);
			\header('Retry-After: 3600');
			if (\is_callable('fastcgi_finish_request')) {
				\fastcgi_finish_request();
			} else {
				$data = \Poodle::ob_get_all();
				\header('Connection: close');
				\header('Content-Length: ' . \strlen($data));
				echo $data;
				\flush();
			}

//			static::log('Start background upgrade');
//			$K->addEventListener('shutdown', function(){\Poodle\Setup\Automatic::database();});
//			exit;
		}

		return static::database();
	}

	public static function database() : bool
	{
		try {
			$K = \Poodle::getKernel();
			$SQL = $K->SQL;
			$XML = $SQL->XML->getImporter();
			$XML->addEventListener('afterquery', function(\Poodle\Events\Event $event){
				Automatic::log("Upgrade {$event->index}: {$event->query}");
			});
			$old_db_version = $K->CFG->poodle->db_version;
			$log = "poodlewcms-{$K->SQL->database}-{$K->SQL->TBL->prefix}-{$old_db_version}";

			\ini_set('error_log', \preg_replace('#([/\\\\])[^/\\\\]+$#D', '$1'.$log.'-'.\Poodle::DB_VERSION.'.log', \ini_get('error_log')));

			static::log("Upgading DB {$old_db_version} to " . \Poodle::DB_VERSION);

			$name = \sys_get_temp_dir() . "/{$log}";

			$sf = $name.'-schema';
			if (\is_file($sf)) {
				static::log("- locked with {$sf}");
				return false;
			}
			$sf_fp = \gzopen($sf, 'w');
			if (!$sf_fp) {
				return false;
			}

			$df = $name.'-data';
			$df_fp = \gzopen($df, 'w');
			if (!$df_fp) {
				\gzclose($sf_fp);
				\unlink($sf);
				return false;
			}

			static::log('- backup schema');
			$SQL->XML->getExporter()->exportSchema($sf_fp);
			\gzclose($sf_fp);

			static::log('- backup data');
			$SQL->XML->getExporter()->exportData($df_fp, array('onduplicate' => 'IGNORE'));
			\gzclose($df_fp);

			if ($old_db_version && $old_db_version < 20180120) {
				try {
					$SQL->exec("UPDATE {$SQL->TBL->l10n_translate} SET msg_id=LOWER(msg_id)");
				} catch (\Throwable $e) {}
			}

			static::log('- sync schema');
			if (!$XML->syncSchemaFromFile(__DIR__ . '/database/schema.xml', $old_db_version)) {
				foreach ($XML->errors as $error) {
					static::log("- schema.xml error: {$error['message']}");
				}
				\unlink($sf);
				\unlink($df);
				return false;
			}

			static::log('- sync data');
			if (!$XML->importDataFromFile(__DIR__ . '/database/data.xml', $old_db_version)) {
				foreach ($XML->errors as $error) {
					static::log("- data.xml error: {$error['message']}");
				}
				\unlink($sf);
				\unlink($df);
				return false;
			}

			$K->CFG->set('poodle', 'db_version', \Poodle::DB_VERSION);
			$K->CACHE->clear();

			\rename($sf, "{$K->cache_dir}/dbbackup-{$old_db_version}-schema.xml.gz");
			\rename($df, "{$K->cache_dir}/dbbackup-{$old_db_version}-data.xml.gz");

			static::log('- done');
			return true;
		} catch (\Exception $e) {
			static::log("- ERROR {$e->getMessage()}\n{$e->getTraceAsString()}");
			return false;
		}
	}

}
