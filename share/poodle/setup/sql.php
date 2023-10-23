<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/

namespace Poodle\Setup;

class SQL
{
	protected static
		$id = 0;

	public static function version()
	{
		static $version;
		if (!is_int($version)) {
			$version = 0;
			$SQL = \Poodle::getKernel()->SQL;
			if (isset($SQL->TBL->config)
			 && $v = $SQL->uFetchRow("SELECT cfg_value FROM {$SQL->TBL->config} WHERE cfg_section='poodle' AND cfg_key='db_version'")
			){
				$version = (int) $v[0];
			}
		}
		return $version;
	}

	public function exec($fname, $title)
	{
		$this->error = false;
		$K = \Poodle::getKernel();
		$old_version = static::version();
		$K->OUT->PROGRESS_ID = ++self::$id;
		$K->OUT->PROGRESS_TITLE = $title . " ({$old_version})";
		$K->OUT->display('poodle/setup/progressbar');
		\Poodle::ob_flush_all();

		$XML = $K->SQL->XML->getImporter();
		$XML->addEventListener('afterquery', function(\Poodle\Events\Event $event){
			$perc_t = round(100.0 * $event->index / $event->count);
			$this->echoProgress($perc_t, preg_match('#^(ALTER|CREATE|DROP|INSERT).*?(FUNCTION|INDEX|INTO|PROCEDURE|TABLE|TRIGGER|VIEW)\s+([^\s]+)#',$event->query,$m) ? "{$m[1]} {$m[2]} {$m[3]}" : '');
		});
		if (!$XML->syncSchemaFromFile(__DIR__ . "/database/{$fname}.xml", $old_version)) {
			echo '<p class="error">'.print_r($XML->errors, true).'</p>';
		} else {
			$this->echoProgress(100, '');
			return true;
		}
		return false;
	}

	protected function echoProgress($perc_t, $message)
	{
		echo '<script>progress('.self::$id.','.$perc_t.','.json_encode(htmlspecialchars($message)).');</script>';
		\Poodle::ob_flush_all();
	}

}
