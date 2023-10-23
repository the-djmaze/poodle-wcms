<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class Report
{

	protected static function fix_bin($s)
	{
		return \str_replace('"','\\"', \preg_replace_callback(
			'#([\x00-\x08\x0B\x0C\x0E-\x1F\x7F])#',
			function($m){return '\\x'.\bin2hex($m[1]);},
			$s));
	}

	public static function error($title, $error='', $redirect=false)
	{
		static $executed = false;

		$message = \is_array($error) ? $error['msg'] : ($error ?: $title);

		if ($executed || \Poodle::isShutdown() || \connection_status()) {
			\error_log("ERROR {$message}\n".\print_r(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1));
			return;
		}

		try {
			$K = \Poodle::getKernel();

			if (!\is_object($K)) {
				\error_reporting(0);
				$K = new \stdClass();
				$K->L10N = new \Poodle\L10N();
				$K->OUT = null;
				$K->SESSION = null;
			}

			# Do we have an RFC 2616 Status Code ?
			$code = \is_int($title) ? $title : 0;

			if (($code >= 400 && $code < 600) || ($code >= 800 && $code <= 803)) {
				if (401 === $code && POODLE_BACKEND) {
					# rfc2617 Digest or Basic
					if (2 == $K->CFG->auth_rfc2617->scheme) {
						\header('WWW-Authenticate: Digest realm="'.$K->auth_realm.'",qop="auth",nonce="'.\Poodle\UUID::generate().'",opaque="'.\md5($K->auth_realm).'"');
					} else if (1 == $K->CFG->auth_rfc2617->scheme) {
						\header('WWW-Authenticate: Basic realm="'.$K->auth_realm.'"');
					} else {
						$code = 403;
					}
				} else {
					if ($code >= 800) { $code = 403; }
					if (503 !== $code && !POODLE_BACKEND) {
						\Poodle\LOG::error($title, $message);
					}
				}
				\Poodle\HTTP\Status::set($code);
			}

			if ($code) {
				if (403 === $code && $K->SESSION && !$K->IDENTITY->id) {
					$uri = POODLE_BACKEND ? '/admin/login' : '/login';
					\Poodle\URI::redirect($uri.'?redirect_uri='.\rawurlencode($_SERVER['REQUEST_URI']));
				}
				$K->L10N->load('poodle_report');
				$title = $K->L10N['_SECURITY_STATUS'][$code];
				$message = \sprintf($K->L10N['_SECURITY_MSG'][$code], $_SERVER['REQUEST_URI'])."\n".$message;
			}

			if (POODLE_CLI) {
				echo \str_pad('', 4+\strlen($title), '#')."\n";
				echo "# {$title} #\n";
				echo \str_pad('', 4+\strlen($title), '#')."\n\n";
				echo $message."\n\n";
				exit(1);
			}

			if (\in_array($code, array(204,205,304))) {
				exit;
			}

			// Display 404 image
			if (404 == $code && \preg_match('#\\.(jpe?g|png|gif|svg|webp)$#Di', $_SERVER['REQUEST_PATH'])) {
				$executed = true;
				$file = \realpath('tpl/default/images/404.png');
				\header('Content-Length: ' . \filesize($file));
				\header('Content-Type: image/png');
				\readfile($file);
				exit;
			}

//			&& false === strpos(,'/.well-known')
			if (isset($K->OUT) && $K->OUT && false !== \Poodle\Input\Headers::Accept($K->mlf)) {
				$executed = true;
				if ($K->OUT->head) {
					$K->OUT->head->addMeta('robots', 'noindex,noarchive');
				}
				if ($K->OUT->crumbs) {
					$K->OUT->crumbs->clear();
				}
				$K->OUT->trace = array();
				if (\is_array($error) && !empty($error['trace'])) {
					foreach ($error['trace'] as $trace) {
						if (isset($trace['args'])) {
							foreach ($trace['args'] as $a => $s) {
								switch (\gettype($s))
								{
								case 'integer' :
								case 'double'  : break;
								case 'boolean' : $s = ($s ? 'true' : 'false'); break;
								case 'object'  : $s = '&'.\get_class($s); break;
								case 'resource': $s = 'resource'; break;
								case 'NULL'    : $s = 'null'; break;
								case 'array'   : $s = self::fix_bin(\print_r($s, 1)); break;
								case 'string'  : $s = '"'.self::fix_bin($s).'"'; break;
								}
								$trace['args'][$a] = $s;
							}
						}
						if (!isset($trace['args'])) {
							$trace['args'] = array();
						}
						$K->OUT->trace[] = array(
							'file' => isset($trace['file']) ? \Poodle::shortFilePath($trace['file']) : 'unknown',
							'line' => isset($trace['line']) ? $trace['line'] : 0,
							'func' => (isset($trace['class'])?$trace['class'].$trace['type']:'')
								.$trace['function'].'('.\implode(', ', $trace['args']).')',
						);
					}
				}
/*
				if (403 === $code) {
					$K->OUT->tpl_layout = 'http-status-403';
					if (!$K->IDENTITY->id) {
						$K->OUT->L10N->load('poodle_login');
						$K->OUT->head->addScript('poodle_login');
						$K->OUT->login_error = $error;
						$K->OUT->auth_providers = array();
						foreach (\Poodle\Auth\Provider::getPublicProviders() as $p) {
							$c = new $p['class']($p);
							$f = $c->getAction();
							$p['fields'] = $f->fields;
							$K->OUT->auth_providers[] = $p;
						}
						if (isset($_GET['bys'])) {
							foreach (\Poodle\Auth\Provider::getAdminProviders() as $p)
							{
								$c = new $p['class']($p);
								$f = $c->getAction();
								$p['fields'] = $f->fields;
								$K->OUT->auth_providers[] = $p;
							}
						}
					}
				}
*/
				$K->OUT->tpl_layout   = 'error';
				$K->OUT->error_title  = "Error {$code}";
				$K->OUT->error_msg    = $message ?: $title;
				$K->OUT->error_code   = $code;
				$K->OUT->uri_index    = \Poodle::$URI_BASE;
				$K->OUT->uri_redirect = $redirect;
				$K->OUT->display('error');
				$K->OUT->finish();
				exit;
			}
		} catch (\Throwable $e) {
			if (\Poodle::$DEBUG & \Poodle::DBG_PHP) {
				$message .= "\n\n".$e->getMessage();
			}
		}
		exit(\htmlspecialchars($message));
	}

	public static function confirm($msg, $hidden='', $uri=false)
	{
		$K = \Poodle::getKernel();
		$K->OUT->MESSAGE_TEXT = $msg;
		$K->OUT->S_CONFIRM_ACTION = ($uri ?: $_SERVER['REQUEST_URI']);
		$K->OUT->S_HIDDEN_FIELDS  = $hidden;
		$K->OUT->display('confirm');
		$K->OUT->finish();
	}

}
