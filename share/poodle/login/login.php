<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

class Login extends \Poodle\Resource
{
	public
		$allowed_methods = array('GET','HEAD','POST');

	private static
		$auth_providers = array();

	public static function getProviders() : array
	{
		if (!self::$auth_providers) {
			$is_2fa = !empty($_SESSION['POODLE_LOGIN_2FA']);
			$providers = POODLE_BACKEND
				? \Poodle\Auth\Provider::getAdminProviders($is_2fa)
				: \Poodle\Auth\Provider::getPublicProviders($is_2fa);
			foreach ($providers as $p) {
				$c = new $p['class']($p);
				$f = $c->getAction(
					$is_2fa ? array('identity_id' => $_SESSION['POODLE_LOGIN_2FA']) : array()
				);
				$p['fields'] = $f->fields;
				self::$auth_providers[] = $p;
			}
		}
		return self::$auth_providers;
	}

	public function GET()
	{
		$K = \Poodle::getKernel();
		if ($K->IDENTITY->id) {
			$options = static::getOptions();
			\Poodle\URI::redirect($options['redirect_uri']);
		}

		$K->L10N->load('poodle_login');
		if (isset($_GET['forgot'])) {
			if (!empty($_GET['forgot'])) {
				$this->viewNewPassphraseForm();
			} else {
				$this->viewForgotForm();
			}
		} else
		if (isset($_GET['auth'])) {
			$this->doLogin($_GET->int('auth'));
		} else {
			$this->viewForm();
		}
	}

	public function POST()
	{
		$K = \Poodle::getKernel();
		$K->L10N->load('poodle_login');
		if (!$K->IDENTITY->id) {
			if (isset($_GET['forgot'])) {
				if (isset($_POST['new_passphrase'])) {
					$this->processNewPassphrase();
				}
				if (isset($_POST['forgot'])) {
					$this->processForgotForm();
				}
			}
			// Login using provider_id
			else if (isset($_POST['provider'])) {
				$this->doLogin($_POST->int('provider'));
			}
			else if (!empty($_GET['auth'])) {
				$this->doLogin($_GET->int('auth'));
			}
			// Login using detection
			else if (isset($_POST['openid_identifier'])) {
				self::checkAttempt(1, 'login');
				$auth_provider = \Poodle\Auth\Detect::provider($_POST['openid_identifier']);
				if ($auth_provider) {
					$this->processAuthProviderResult($auth_provider->authenticate($_POST), $auth_provider);
				} else {
					$this->viewForm('Provider not detected');
				}
			}
		}
	}

	protected static function checkAttempt(int $auth_provider_id, string $action) : void
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$c = $SQL->uFetchRow("SELECT
			auth_attempt_count,
			auth_attempt_last_time
		FROM {$SQL->TBL->auth_attempts}
		WHERE auth_provider_id=".((int)$auth_provider_id)."
		  AND auth_attempt_ip=".$SQL->quote($_SERVER['REMOTE_ADDR'])."
		  AND auth_attempt_action=".$SQL->quote($action));
		if ($c) {
			$timeout = $K->CFG->auth->attempts_timeout;
			if ($c[0] >= $K->CFG->auth->attempts && $c[1] > (time() - $timeout)) {
				$time = $c[1] + $timeout;
				if ('newpassphrase' === $action || 'forgot' === $action) {
					$error_msg = 'Passphrase recovery blocked until %s';
				} else if ('login' === $action) {
					$error_msg = 'Login blocked until %s';
				} else {
					$error_msg = 'Login blocked until %s';
				}
				header('Retry-After: '.max(0, $time - time()));
				\Poodle\Report::error(429, sprintf($K->L10N->get($error_msg), $K->L10N->date('DATE_F', $time)));
			}
		}
	}

	protected static function incAttempt(int $auth_provider_id, string $action) : void
	{
		$K = \Poodle::getKernel();
		$tbl = $K->SQL->TBL->auth_attempts;
		$tbl->delete('auth_attempt_last_time<'.(time() - ($K->CFG->auth->attempts_timeout)));
		$tbl->upsert(array(
				'auth_provider_id' => $auth_provider_id,
				'auth_attempt_ip' => $_SERVER['REMOTE_ADDR'],
				'auth_attempt_action' => $action,
				'auth_attempt_count' => 1
			),
			array(
				'auth_attempt_count' => new \Poodle\SQL\ValueRaw('auth_attempt_count+1'),
				'auth_attempt_last_time' => time()
			)
		);
	}

	protected function processForgotForm()
	{
		self::checkAttempt(1, 'forgot');
		if (\Poodle\AntiSpam\Captcha::validate($_POST) < 3) {
			return $this->viewForgotForm('Form validation failed');
		}

		\Poodle\LOG::notice('Forgot Passphrase', print_r($_POST, true));

		$identity = null;
		try {
			if ($email = $_POST->email('forgot', 'email')) {
				$identity = \Poodle\Identity\Search::byEmail($email);
			}
		} catch (\Throwable $e) {}
		if (!$identity) {
			$claimed_id = $_POST->text('forgot', 'auth_claimed_id');
			if ($claimed_id && $id = \Poodle\Auth\Detect::identityId(1, $claimed_id)) {
				$identity = \Poodle\Identity\Search::byID($id);
			}
		}
		if (!$identity) {
			return $this->viewForgotForm('The given username or email address could not be found');
		}

		$hash_key = \Poodle\Identity\Request::newPassphrase($identity);
		$mail_resource = \Poodle\Resource::factory(39);
		if (!$mail_resource) {
			$this->viewForgotForm('Failed to send email');
			return;
		}
		// Fill the email template with needed data
		$K = \Poodle::getKernel();
		$MAIL = \Poodle\Mail::sender();
		$MAIL->addTo($identity->email, $identity->surname);
		$MAIL->activate_uri = \Poodle\URI::abs($_SERVER['REQUEST_PATH'].'?forgot='.$hash_key);
		$MAIL->identity = $identity;
		$MAIL->subject = str_replace('{sitename}', $K->CFG->site->name, $mail_resource->title);
		$MAIL->body = $mail_resource->toString($MAIL);
/*
		$MAIL = \Poodle\Mail::sender();
		// Fill the email template with needed data
		$MAIL->identity = $identity;
		$MAIL->activate_uri = \Poodle\URI::abs($_SERVER['REQUEST_PATH'].'?forgot='.$hash_key);
		$MAIL->addTo($identity->email, $identity->surname);
		$MAIL->subject = $MAIL->L10N['Reset your passphrase'];
		$MAIL->body    = $MAIL->L10N['Follow the following link to reset your passphrase']
			.' '.$MAIL->activate_uri;
		// Wrap body inside layout
		$MAIL->body = $MAIL->toString('layouts/email');
*/
		if ($MAIL->send()) {
			$this->viewForm(false, 'Check your email to reset your passphrase');
		} else {
			$this->viewForgotForm($MAIL->error);
		}
	}

	protected function processNewPassphrase()
	{
		self::checkAttempt(1, 'newpassphrase');
		if (\Poodle\AntiSpam\Captcha::validate($_POST) > 2) {
			$request = \Poodle\Identity\Request::getPassphrase($_GET['forgot']);
			if ($request && $request['identity_id']) {
				$identity = \Poodle\Identity\Search::byID($request['identity_id']);
				if ($identity) {
					$errors = array();
					if (empty($_POST['new_passphrase']) || !\Poodle\Identity\Validate::Passphrase($_POST['new_passphrase'], $identity->nickname, $errors)) {
						return $this->viewNewPassphraseForm('The provided passphrase is incomplete or not according to the given guidelines');
					}
					$identity->updateAuth(1, null, $_POST['new_passphrase']);
					\Poodle\Identity\Request::remove($_GET['forgot'], 1);
					$msg = \Poodle::getKernel()->L10N->get('Your passphrase has been reset. Please login with your credentials');
					\Poodle::closeRequest($msg, 303, preg_replace('#login\\?.*#D', '', $_SERVER['REQUEST_URI']), $msg);
				}
			}
		}
		$this->viewNewPassphraseForm('The provided information was incorrect');
	}

	protected function viewNewPassphraseForm($error=false)
	{
		if ($error) {
			self::incAttempt(1, 'newpassphrase');
		}
		self::checkAttempt(1, 'newpassphrase');

		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		if ($error) {
			\Poodle\Notify::error($OUT->L10N->get($error));
		}
		$OUT->head->addCSS('poodle_login');
		$OUT->tpl_layout = 'login';
		$OUT->new_passphrase_info = sprintf($OUT->L10N['The passphrase must be at least %d characters'], $K->CFG->identity->passwd_minlength);
		$OUT->display('poodle/login/new-passphrase');
	}

	protected function viewForgotForm($error=false)
	{
		if ($error) {
			self::incAttempt(1, 'forgot');
		}
		self::checkAttempt(1, 'forgot');

		$OUT = \Poodle::getKernel()->OUT;
		if ($error) {
			\Poodle\Notify::error($OUT->L10N->get($error));
		}
		$this->title = $OUT->L10N->get('Forgot your passphrase?').' | Poodle';
		$OUT->head->addCSS('poodle_login');
		$OUT->tpl_layout = 'login';
		$OUT->display('poodle/login/forgot');
	}

	protected function viewForm($error=null, $found=null)
	{
		if ($error) {
			self::incAttempt(0, 'login');
		}
		self::checkAttempt(0, 'login');

		$options = static::getOptions();

		$OUT = \Poodle::getKernel()->OUT;
		if ($error) {
//			\Poodle\HTTP\Status::set(400);
			\Poodle\Notify::error($OUT->L10N->get($error));
		}
		if ($found) {
			\Poodle\Notify::success($OUT->L10N->get($found));
		}
		$OUT->head
			->addMeta('robots', 'none')
			->addCSS('poodle_login')
			->addScript('poodle_login');
		$OUT->tpl_layout = 'login';
		$OUT->auth_providers = self::getProviders();
		$OUT->login_redirect_uri = $options['redirect_uri'];
		$OUT->display('poodle/login/form');
	}

	protected function doLogin($provider_id)
	{
		if ($provider_id) {
			$provider = \Poodle\Auth\Provider::getActiveById($provider_id);
			if ($provider instanceof \Poodle\Auth\Provider) {
				if ($provider->has_form_fields && \Poodle\AntiSpam\Captcha::validate($_POST) < 2) {
					return $this->viewForm('Form validation failed');
				}
				$credentials = isset($_POST['auth'][$provider_id]) ? $_POST['auth'][$provider_id] : $_POST;
				if ($provider->is_2fa && !empty($_SESSION['POODLE_LOGIN_2FA'])) {
					$credentials['identity_id'] = $_SESSION['POODLE_LOGIN_2FA'];
				}
				$result = \Poodle::getKernel()->IDENTITY->authenticate($credentials, $provider);
				return $this->processAuthProviderResult($result, $provider);
			}
		}
		$this->viewForm('Unknown login method');
	}

	protected function processAuthProviderResult($result, \Poodle\Auth\Provider $provider)
	{
		$options = static::getOptions();
		$K = \Poodle::getKernel();
		if ($result instanceof \Poodle\Auth\Result\Success) {
			// Require 2FA?
			if (!$provider->is_2fa && $result->user->has2FA()) {
				$_SESSION['POODLE_LOGIN_2FA'] = $result->user->id;
				return $this->viewForm();
			}
			if ($provider->is_2fa && !$result->user) {
				return $this->viewForm();
			}
			unset($_SESSION['POODLE_LOGIN_2FA']);

			// Autocreate identity
			if (!$K->IDENTITY->id && $result->claimed_id) {
				$K->IDENTITY->save();
				$K->IDENTITY->updateAuth($provider, $result->claimed_id);
				$K->IDENTITY->addToGroup(1);
			}

			if (!$K->IDENTITY->id) {
				return $this->viewForm('Unknown login');
			}

			$K->IDENTITY->updateLastVisit();
			\Poodle\L10N::setGlobalLanguage($K->IDENTITY->language);
			\Poodle\LOG::info(\Poodle\LOG::LOGIN, get_class($provider));

			if (!empty($options['cookie'])) {
				$class = isset($K->CFG->auth_cookie->class) ? $K->CFG->auth_cookie->class : 'Poodle\\Auth\\Provider\\Cookie';
				$class::set();
			}

			$uri = $options['redirect_uri'];

			if (XMLHTTPRequest) {
				\Poodle\HTTP\Status::set(202);
//				header('Content-Type: application/json');
//				echo json_encode(array('status' => '302','location' => $uri));
			} else {
//				\Poodle\Notify::success($K->L10N['You successfully logged in']);
				\Poodle\URI::redirect($uri);
			}
		}
		else if ($result instanceof \Poodle\Auth\Result\Redirect) {
			$_SESSION['POODLE_LOGIN'] = $options;
			if (!XMLHTTPRequest) {
				\Poodle\URI::redirect($result->uri);
			}
			\Poodle\HTTP\Status::set(202);
			header('Content-Type: application/json');
			echo json_encode(array(
				'status' => '302',
				'location' => $result->uri
			));
		}
		else if ($result instanceof \Poodle\Auth\Result\Form) {
			$_SESSION['POODLE_LOGIN'] = $options;
			if (XMLHTTPRequest) {
				\Poodle\HTTP\Status::set(202);
				header('Content-Type: application/json');
				echo json_encode(array(
					'form' => array(
						'action' => $result->action,
						'submit' => $result->submit,
						'fields' => $result->fields
					),
					'provider_id' => $provider->id
				));
			} else {
				$K->OUT->tpl_layout = 'login';
				$this->body = $this->result2html($result, $provider);
				$this->display();
			}
		}
		else if ($result instanceof \Poodle\Auth\Result\Error) {

			\Poodle\LOG::error(\Poodle\LOG::LOGIN, get_class($provider).'#'.$result->getCode().': '.$result->getMessage());

			return $this->viewForm('The provided information is unknown or incorrect. Please try again');

			if (XMLHTTPRequest) {
				header('Content-Type: application/json');
				echo json_encode(array('error'=>array(
					'code'=>$result->getCode(),
					'message'=>$result->getMessage()
				)));
			} else {
				echo $result->getCode().': '.$result->getMessage();
//				print_r(get_class($result));
//				print_r($_POST['auth'][$provider->id]);
			}
		}
		else {
			print_r($result);
		}
	}

	protected function result2html(\Poodle\Auth\Result\Form $result, $provider) : string
	{
		$OUT = \Poodle::getKernel()->OUT;
		if (!$result->submit) {
			foreach (self::getProviders() as $aprovider) {
				if ($aprovider['id'] == $provider->id) {
					$OUT->head
						->addMeta('robots', 'none')
						->addCSS('poodle_login')
						->addScript('poodle_login');
					$aprovider['fields'] = $result->fields;
					$OUT->auth_providers = array($aprovider);
					$options = static::getOptions();
					$OUT->login_redirect_uri = $options['redirect_uri'];
					return $OUT->toString('poodle/login/form');
				}
			}
		}
		$OUT->auth_result = $result;
		$OUT->auth_provider = $provider;
		return $OUT->toString('poodle/login/redirect-form');
	}

	protected static function getOptions() : array
	{
		static $options;
		if (!$options) {
			$options = empty($_SESSION['POODLE_LOGIN']) ? array() : $_SESSION['POODLE_LOGIN'];
			unset($_SESSION['POODLE_LOGIN']);
			if (isset($_POST['auth_cookie'])) {
				$options['cookie'] = !empty($_POST['auth_cookie']);
			} else if (isset($_GET['auth_cookie'])) {
				$options['cookie'] = !empty($_GET['auth_cookie']);
			}
			if (!empty($_POST['redirect_uri'])) {
				$options['redirect_uri'] = $_POST['redirect_uri'];
			} else if (!empty($_GET['redirect_uri'])) {
				$options['redirect_uri'] = $_GET['redirect_uri'];
			}
			if (empty($options['redirect_uri']) || '/' !== $options['redirect_uri'][0]) {
				$options['redirect_uri'] = (POODLE_BACKEND ? '/admin/' : '/');
			}
		}
		return $options;
	}

}
