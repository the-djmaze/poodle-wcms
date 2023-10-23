<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab;

abstract class CronD
{

	public static function execute($id = 0, $force = false, $log_all = false)
	{
		if (!POODLE_CLI && !POODLE_BACKEND) {
			throw new \Exception('Poodle\\Crontab\\CronD not allowed');
		}
		\Poodle::startStream();
		$stime = time();

		echo "Start crons:\n\n";

		$id = (int) $id;
		if (0 < $id) {
			$where = "cron_id = {$id}";
		} else {
			$where = 'cron_active = 1';
		}

		$timezone = date_default_timezone_get();
		$SQL = \Poodle::getKernel()->SQL;
		$crons = $SQL->query("SELECT
			cron_id id,
			cron_expression expression,
			cron_call callback,
			cron_last_run last_run,
			cron_mail_error mail_error,
			cron_mail_success mail_success
		FROM {$SQL->TBL->crontab}
		WHERE {$where}");
		while ($row = $crons->fetch_assoc())
		{
			try {
				set_time_limit(0);
				if (is_callable($row['callback'])) {
					if (!$force) {
						$cron = new Cron\Expression($row['expression']);
						if (time() < $cron->getNextRunDate('@'.(int)$row['last_run'])->getTimestamp()) {
							if (!POODLE_CLI) {
								echo "Skipped: {$row['callback']}\n\n";
							}
							continue;
						}
					}

/*
					// Make sure only one cronjob is run at a time
					$key = "crond-lock-{$row['id']}";
					$C = \Poodle::getKernel()->CACHE;
					if (!$C->exists($key) || !$C->get($key)) {
						$C->set($key, time());
						register_shutdown_function(array($C,'set'), $key, 0);
						register_shutdown_function(array($C,'delete'), $key);
					} else {
						$time = time() - $C->get($key);
						$msg = "Cron {$row['id']} already running ({$time} seconds)";
						// Notify every 5 minutes
						if (0 == $time % 300) {
							throw new \Exception($msg);
						}
						echo "\t{$msg}\n";
						flush();
						continue;
					}
*/

					echo "Execute: {$row['callback']}\n";
					if (POODLE_CLI) {
						ob_start();
					}
					$result = $row['callback']((int)$row['last_run']);
					echo "\n";
					if (POODLE_CLI) {
						$data = ob_end_clean();
						echo $data;
						if (false !== $result) {
							$addrs = explode(',',$row['mail_success']);
							foreach ($addrs as $addr) {
								$addr = trim($addr);
								if ($addr) { mail($addr,'Cron Success',$data); }
							}
						}
					}
				} else {
					throw new \BadFunctionCallException("{$row['callback']} not callable");
				}
				$SQL->exec("UPDATE {$SQL->TBL->crontab} SET cron_last_run={$stime} WHERE cron_id={$row['id']}");
				if ($log_all) {
					$SQL->TBL->crontab_logs->insert(array(
						'cron_id' => $row['id'],
						'cron_time' => time()
					));
				}
			} catch (\Throwable $e) {
				if (POODLE_CLI) {
					$msg = "Error {$e->getCode()} in {$e->getFile()}#{$e->getLine()}: {$e->getMessage()}\n{$e->getTraceAsString()}\n";
					$addrs = explode(',',$row['mail_error']);
					$data = ob_end_clean();
					foreach ($addrs as $addr) {
						$addr = trim($addr);
						if ($addr) { mail($addr,'Cron Error',$msg.$data); }
					}
					$SQL->TBL->crontab_logs->insert(array(
						'cron_id' => $row['id'],
						'cron_time' => time(),
						'cron_error' => "{$e->getMessage()}\n{$e->getTraceAsString()}"
					));
					fwrite(STDERR, $msg);
				} else {
					$SQL->TBL->crontab_logs->insert(array(
						'cron_id' => $row['id'],
						'cron_time' => time(),
						'cron_error' => $e->getMessage()
					));
					throw $e;
				}
			}
			date_default_timezone_set($timezone);
		}

		echo "Finished crons\n";
	}

}
