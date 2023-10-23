<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Crontab;

use Poodle\Crontab\Cron\Expression;

class Admin
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
		$title      = 'Crontab Administration',
		$body       = '',
		$searchable = false,
		$modifier_identity_id = 0,

		$allowed_methods = array('GET','POST');

	public function GET()
	{
		$K = \Poodle::getKernel();
		$K->OUT->crumbs->append($K->OUT->L10N['Crontab'], '/admin/poodle_crontab/');

		if (ctype_digit(\Poodle::$PATH[1])) {
			$id = (int) \Poodle::$PATH[1];
			if (isset($_GET['run'])) {
//				$K->OUT->start();
				header('Content-Type: text/plain');
				CronD::execute($id, isset($_GET['force']));
				return true;
			}
			if (0 < $id) {
				if (isset(\Poodle::$PATH[2]) && 'history' == \Poodle::$PATH[2]) {
					if (isset($_GET['clear'])) {
						$K->SQL->TBL->crontab_logs->delete("cron_id={$id}");
						$msg = $K->L10N->get('History removed');
						\Poodle::closeRequest($msg, 201, \Poodle\URI::admin('/poodle_crontab/'), $msg);
					}
					return $this->displayHistory($id);
				}
				$SQL = $K->SQL;
				$K->OUT->crumbs->append($K->OUT->L10N['Edit task']);
				$K->OUT->crontab_entry = $SQL->uFetchAssoc("SELECT
					cron_id         id,
					cron_expression expression,
					cron_call       callback,
					cron_last_run   last_run,
					cron_active     active,
					cron_mail_error mail_error,
					cron_mail_success mail_success
				FROM {$SQL->TBL->crontab}
				WHERE cron_id={$id}");
			} else {
				$K->OUT->crumbs->append($K->OUT->L10N['Add task']);
				$K->OUT->crontab_entry = array(
					'id'         => 0,
					'expression' => '@hourly',
					'callback'   => '',
					'last_run'   => '',
					'active'     => false,
					'mail_error' => '',
					'mail_success' => ''
				);
			}
			try {
				$expr = new Expression($K->OUT->crontab_entry['expression']);
			} catch (\Throwable $e) {
				$expr = new Expression('@hourly');
			}
			try {
				$K->OUT->crontab_entry['next_run'] = $expr->getNextRunDate();
			} catch (\Throwable $e) {
				$K->OUT->crontab_entry['next_run'] = null;
			}
			$K->OUT->crontab_entry['expression'] = array(
				'minute'  => $expr->getField(Expression::MINUTE),
				'hour'    => $expr->getField(Expression::HOUR),
				'day'     => $expr->getField(Expression::DAY),
				'month'   => $expr->getField(Expression::MONTH),
				'weekday' => $expr->getField(Expression::WEEKDAY)
			);
			$this->title = $K->OUT->L10N->get($this->title);
			return $K->OUT->display('poodle/crontab/entry');
		}
		else
		{
			$SQL = $K->SQL;
			$K->OUT->crontab_cmd = \Poodle::$DIR_BASE.'index.php '.\Poodle\URI::abs('/crontab/')
				. ($_SERVER['SERVER_MOD_REWRITE'] ? ' --sef' : '')
				. ' >/dev/null 2>&1';
			$K->OUT->crontab_entries = $SQL->query("SELECT
				cron_id         id,
				cron_call       callback,
				cron_last_run   last_run,
				cron_active     active
			FROM {$SQL->TBL->crontab}
			ORDER BY cron_active DESC, cron_call");
			$this->title = $K->OUT->L10N->get($this->title);

			$K->OUT->where_php = 'php';
			$K->OUT->where_php_cli = 'php_cli';
			if (function_exists('shell_exec')) {
				$K->OUT->where_php = shell_exec('which php') ?: $K->OUT->where_php;
				$K->OUT->where_php_cli = shell_exec('which php_cli') ?: $K->OUT->where_php_cli;
			}
//			$K->OUT->where_php = system('which php');

			return $K->OUT->display('poodle/crontab/admin');
		}
	}

	public function POST()
	{
		if (ctype_digit(\Poodle::$PATH[1])) {
			$id = (int) \Poodle::$PATH[1];
			$cron = array(
				'cron_call' => $_POST['callback'],
				'cron_expression' => implode(' ',$_POST['expression']),
				'cron_active' => $_POST->bool('active'),
				'cron_mail_error' => $_POST->text('mail_error'),
				'cron_mail_success' => $_POST->text('mail_success'),
			);
			if (!is_callable($cron['cron_call'])) {
				throw new \BadFunctionCallException("{$cron['cron_call']} not callable");
			}
			new Expression($cron['cron_expression']);
			$K = \Poodle::getKernel();
			$tbl = $K->SQL->TBL->crontab;
			if (0 < $id) {
				$tbl->update($cron,'cron_id='.$id);
				$msg = $K->L10N->get('The changes have been saved');
			} else {
				$cron['cron_last_run'] = 0;
				$tbl->insert($cron);
				$msg = $K->L10N->get('Added');
			}
			\Poodle::closeRequest($msg, 201, \Poodle\URI::admin('/poodle_crontab/'), $msg);
		}
	}

	protected function displayHistory($id)
	{
		$K = \Poodle::getKernel();
		$page   = max(1, $_GET->uint('page'));
		$limit  = 50;
		$offset = max(0, $page-1) * $limit;
		$count  = $K->SQL->count('crontab_logs', "cron_id={$id}");
		$K->OUT->crumbs->append($K->OUT->L10N['History']);
		$K->OUT->crontab_pagination = new \Poodle\Pagination(
			$_SERVER['REQUEST_PATH'].'?page=${page}',
			$count, $offset, $limit);
		$K->OUT->crontab_history = $K->SQL->query("SELECT
			cron_time  time,
			cron_error error
		FROM {$K->SQL->TBL->crontab_logs}
		WHERE cron_id={$id}
		ORDER BY cron_time DESC
		LIMIT {$limit} OFFSET {$offset}");
		return $K->OUT->display('poodle/crontab/history');
	}

	public static function test($last_run)
	{
		echo "\tPoodle\\Crontab\\Admin::test() executed\n";
	}

}
