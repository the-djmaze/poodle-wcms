<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	All crons will be executed using CLI:
		php index.php https://[domain]/crontab/
	Suppress output emails:
		php index.php https://[domain]/crontab/ > /dev/null
	Suppress error emails:
		php index.php https://[domain]/crontab/ > /dev/null 2>&1
*/

namespace Poodle\Kernels;

use Poodle\Crontab\Cron\Expression;

class Crontab extends \Poodle\Kernels\General
{
	public
		$mlf = 'txt';

	public function run()
	{
		if (!POODLE_CLI) {
			\Poodle\Report::error(404);
			throw new \Exception('Invalid Crontab request');
		}

		# Load configuration
		$this->loadConfig();

		\Poodle\Crontab\CronD::execute();

		# Something may started the output so lets properly close it
		if (isset($this->OUT) && !POODLE_CLI) {
			$this->OUT->finish();
		}
	}
}
