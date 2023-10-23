<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Debugger;

abstract class Exception
{
	protected static function fix_bin($s)
	{
		return str_replace('"','\\"', preg_replace_callback(
			'#([\x00-\x08\x0B\x0C\x0E-\x1F\x7F])#',
			function($m){return '\\x'.bin2hex($m[1]);},
			$s));
	}

	public static function process($e)
	{
		try {
			\Poodle\HTTP\Status::set(500);

			$K = \Poodle::getKernel();
			$class_name = get_class($e);
			if (!(\Poodle::$DEBUG & \Poodle::DBG_PHP) && $class_name === 'Poodle\\SQL\\Exception') {
				switch ($e->getCode())
				{
				case \Poodle\SQL\Error::NO_EXTENSION:
					exit($e->getMessage().' extension not loaded in PHP. Recompile PHP, edit php.ini or choose a different SQL layer.');
				case \Poodle\SQL\Error::NO_CONNECTION:
					exit('The connection to the database server failed.');
				case \Poodle\SQL\Error::NO_DATABASE:
					exit('It seems that the database doesn\'t exist.');
				}
			}

			$title = strtr($class_name, '_', ' ');
			$trace = $e->getTrace();
			array_unshift($trace, array(
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'class' => null,
				'type'  => null,
				'function' => null,
			));
			foreach ($trace as $i => $d) {
				if (isset($d['args'])) {
					foreach ($d['args'] as $a => $s) {
						switch (gettype($s))
						{
						case 'integer' :
						case 'double'  : break;
						case 'boolean' : $s = ($s ? 'true' : 'false'); break;
						case 'object'  : $s = '&'.get_class($s); break;
						case 'resource': $s = 'resource'; break;
						case 'NULL'    : $s = 'null'; break;
						case 'array'   : $s = self::fix_bin(print_r($s, 1)); break;
						case 'string'  : $s = '"'.self::fix_bin($s).'"'; break;
						}
						$trace[$i]['args'][$a] = $s;
					}
				}
			}
			if (\Poodle::$DEBUG & \Poodle::DBG_PHP || \Poodle::getKernel()->IDENTITY->isAdmin()) {
//				echo $e->getTraceAsString();
				$code = $e->getCode();
				$msg = $e->getMessage();
				\Poodle\Report::error($title, array('msg' => htmlspecialchars($msg), 'trace' => $trace));
			} else {
				$trace = $trace[0]['file'].' @ '.$trace[0]['line']."\n".$trace[0]['class'].$trace[0]['type'].$trace[0]['function'].'(';
				if (!empty($trace[0]['args'])) { $trace .= '"'.implode('", "', $trace[0]['args']).'\"'; }
				$trace .= ")\n";
				try {
					\Poodle\LOG::error($e->getCode(), $trace.$e->getMessage());
				}
				catch (\Throwable $e) {}
				\Poodle\Report::error($title, 'An exception occured while processing this page. We have logged this error and will fix it when needed.');
			}
		}
		catch (\Throwable $e) { exit($e->getMessage()); }
	}
}
