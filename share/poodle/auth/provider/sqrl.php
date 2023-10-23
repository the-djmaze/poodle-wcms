<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://www.grc.com/sqrl/sqrl.htm
	https://play.google.com/store/apps/details?id=org.ea.sqrl
	https://www.grc.com/files/sqrl.exe
*/

namespace Poodle\Auth\Provider;

class SQRL extends \Poodle\Auth\Provider
{
	protected
		$has_form_fields = false;

	const
		CURRENT_ID_MATCH       = 1,
		PREVIOUS_ID_MATCH      = 2,
		IP_MATCHED             = 4,
		ACCOUNT_DISABLED       = 8,
		FUNCTION_NOT_SUPPORTED = 16,
		TRANSIENT_ERROR        = 32,
		COMMAND_FAILED         = 64,
		CLIENT_FAILURE         = 128,
		BAD_ID_ASSOCIATION     = 256,

		SESSION_TIMEOUT = 15 * 60;

	public function getAction($credentials=array())
	{
		\Poodle::getKernel()->OUT->head->addScript('poodle_auth_sqrl');
		$response = new SQRLResponseBody($this);
		$this->setTransient($response['nut']);
		return new \Poodle\Auth\Result\Form(
			array(
				array('name'=>'sqrl_uri', 'type'=>'hidden', 'label'=>'Scan QR code with SQRL', 'value'=>$response->getSqrlURI()),
			),
			'?auth='.$this->id,
			'auth-sqrl'
		);
	}

	public function authenticate($credentials)
	{
		if (!empty($credentials['sqrl_uri'])) {
			header("Location: {$credentials['sqrl_uri']}");
			exit;
		}

		/**
		 * This is the login page websocket
		 */
		if (XMLHTTPRequest && isset($_GET['sqrl_check'])) {
			try {
				header("Access-Control-Allow-Origin: {$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}" . \Poodle::$URI_BASE);
				header('Access-Control-Allow-Credentials: true');
				header('Access-Control-Max-Age: 1'); # cache for 1 day
				header('Access-Control-Allow-Methods: GET, OPTIONS');
//				header('Content-Type: application/json');
				\Poodle::startStream();
				while (!$_SESSION['Poodle']['IDENTITY']->isMember()) {
					\Poodle::getKernel()->SESSION->reset();
					echo "false\n";
					# Wait 1.5 seconds
					usleep(1500000);
				}
				echo "true\n";
			} catch (\Exception $e) {
			}
			\Poodle::getKernel()->SESSION->abort();
			exit;
		}

		if (!empty($credentials['login']) && $credentials['login'] instanceof \Poodle\Auth\Result\Success) {
			return $credentials['login'];
		}

		/**
		 * Now handle the API callback from the client to query for
		 * server status, login user, register new users, disable the account,
		 * enable the account and remove the account.
		 */
		\Poodle::getKernel()->SESSION->abort();

		$transient = null;

		try {
			# Validate Client data
			# If the string is not Base64URL encoded, die here and don't process code below.
			$this->onlyAllowBase64URL($credentials['client']);

			# Validate Server data
			# If the string is not Base64URL encoded, die here and don't process code below.
			$this->onlyAllowBase64URL($credentials['server']);

			/**
			 * Split the client variables into an array so we can use them later.
			 */
			$client = new SQRLClientBody($credentials['client']);

			/**
			 * Check the user call that we have a valid signature for the current authentication.
			 */
			if (!$this->verifyMessage($credentials['ids'], $client['idk'])) {
				throw new \Exception('Incorrect signature', self::CLIENT_FAILURE);
			}

			/**
			 * Check the user call that we have a valid previous (if available)
			 * signature for the current authentication.
			 */
			if (!empty($client['pidk'])) {
				# Validate Previous Identity Signature
				if (!$this->verifyMessage($credentials['pids'], $client['pidk'])) {
					throw new \Exception('Incorrect previous signature', self::CLIENT_FAILURE);
				}
			}

			/**
			 * Prepare the admin post array that we will use multiple
			 * times to refeer the client back to the server.
			 */
			$server = new SQRLServerBody($credentials['server']);

			/**
			 * Prepare response.
			 *
			 * Set version number for the call, new nut for the session and
			 * a path with query that the next client call should use in order
			 * to contact the server.
			 */
			$response = new SQRLResponseBody($this);

			/**
			 * Fetch the current transient session where we keep all session information.
			 */
			$transient = $this->getTransient($server['nut']);

			/**
			 * Check if the users IP have changed since last time we logged in. Only required when CPS is used.
			 */
			if (empty($transient)) {
				$transient = $server;
				throw new \Exception('Missing transient session', self::TRANSIENT_ERROR);
			}

			if (!$client->hasOption('noiptest')
			 && !empty($transient['ip_address'])
			 && $transient['ip_address'] == $_SERVER['REMOTE_ADDR']) {
				$response['tif'] += self::IP_MATCHED;
			}

			switch ($client['cmd'])
			{
			case 'query':
			case 'ident':
//			case 'disable':
//			case 'enable':
//			case 'remove':
				$cmd = "apiCmd{$client['cmd']}";
				$response['tif'] += $this->$cmd($response, $client, $transient);
				break;
			default:
				# Unknown or not yet implemented  command
				throw new \Exception("Unknown SQRL command {$client['cmd']}", self::FUNCTION_NOT_SUPPORTED);
			}

			/**
			 * Set the extra options for users preferences.
			 */
//			$client->hasOption('sqrlonly');
//			$client->hasOption('hardlock');

			/*
			 * Prepare the return values and set the transient
			 * session where we keep all the session information.
			 */
			$this->setTransient($response['nut'], $transient);
			$response->setQry();

			/**
			 * Display the result as an base64url encoded string.
			 */
			$response->output();

		} catch (\Throwable $e) {
			error_log($e->getMessage());
			$this->exitWithErrorCode($e->getCode(), $transient);
		}
	}

	protected function apiCmdQuery(SQRLResponseBody $response, $client)
	{
		/**
		 * Query the system for the current user status.
		 */
		$identity = $this->getIdentityByClaimedId($client['idk']);
		if ($identity) {
			$response['tif'] += self::CURRENT_ID_MATCH;
			# If the client requests a Server Unlock Key then add that to the response.
			if ($client->hasOption('suk')) {
				$response['suk'] = json_decode($identity['info'])->suk;
			}
		}

		if ($this->getIdentityByClaimedId($client['pidk'])) {
			$response['tif'] += self::PREVIOUS_ID_MATCH;
		}
	}

	protected function apiCmdIdent(SQRLResponseBody $response, $client, $transient)
	{
		$associatedExistingUser = false;

		/**
		 * Identify with the system either creating a new user or
		 * authorizing login with a user already in the system.
		 */
		$claimed_id = $client['idk'];
		$identity = $this->getIdentityByClaimedId($claimed_id);
		if (!$identity) {
			/**
			 * Fetch the current user from the transient session store
			 * and remove it as we only keep it for the current session.
			 */
			if ($identity_id = $transient['identity_id']) {
				$associatedExistingUser = true;
			} else if ($identity_id = $this->getIdentityByClaimedId($client['pidk'])) {
				/**
				 * Check if we have a hit on a previous account so we need to
				 * update the current identity to our new identity identifier.
				 */
				$identity_id = $user['id'];
				$claimed_id = $client['pidk'];
			}

			/**
			 * Check if we should associate an old user or create a new one.
			 * Checking if registring users are allowed on the current installation.
			 */
			if (!$identity_id) {
				error_log(print_r($client,1));
				throw new \Exception("User can't register with SQRL code {$claimed_id}", self::COMMAND_FAILED);
			}

			$cred = new \Poodle\Auth\Credentials($identity_id, $claimed_id);
			$cred->hash_claimed_id = false;
			$cred->info = json_encode(array('suk' => $client['suk'], 'vuk' => $client['vuk']));
			$this->updateAuthentication($cred);
		} else if (!empty($transient['session'])) {
			/**
			 * Add session data signaling to the sqrl.js script
			 * that a login has been successfully transacted.
			 */
			$SES = \Poodle::getKernel()->SESSION;
			$_COOKIE[session_name()] = $transient['session'];
			$SES->start($transient['session']);
			# Lookup user in the database
			$user = \Poodle\Identity\Search::byID($identity['id']);
			if (!$user) {
				throw new \Exception("A database record for the supplied identity_id ({$identity['id']}) could not be found.", self::COMMAND_FAILED);
			}
			$_SESSION['Poodle']['IDENTITY']->authenticate(
				array('login' => new \Poodle\Auth\Result\Success($user)),
				$this
			);
			$SES->write_close();
		}

		$response['tif'] += self::CURRENT_ID_MATCH;

		/**
		 * If Client Provided Session is enabled we need to respond with links
		 * for the client to follow in order to securely login.
		 */
		if ($client->hasOption('cps')) {
			$response->setLoginUrl($associatedExistingUser);
		}
	}

	protected function onlyAllowBase64URL($s)
	{
		if (!\Poodle\Base64::urlVerify($s)) {
			throw new \Exception("Incorrect input {$s}", self::CLIENT_FAILURE);
		}
	}

	/**
	 * Return with information to the server about the error that occured.
	 */
	private function exitWithErrorCode($code, $transient = null)
	{
		$response = new SQRLResponseBody($this);
		$response['tif'] = $code;

		if ($transient) {
			$this->setTransient($response['nut'], $transient);
			$response->setQry();
		} else {
			$response['nut'] = null;
		}

		$response->output();
	}

	# ed25519
	protected function verifyMessage($signature, $public_key)
	{
		# Signature, if the string is not Base64URL encoded, exit here.
		$this->onlyAllowBase64URL($signature);
		return \Poodle\Crypt\Sodium::sign_verify_detached(
			\Poodle\Base64::urlDecode($signature),
			$_POST['client'] . $_POST['server'],
			\Poodle\Base64::urlDecode($public_key)
		);
	}

	protected function getTransient($id)
	{
		$C = \Poodle::getKernel()->CACHE;
		$key = "transients/auth_sqrl/{$id}";
		$data = $C->get($key);
		$C->delete($key);
		return $data;
	}

	public function setTransient($id, array $data = null)
	{
		if (!$data) {
			$data = array(
				'identity_id'  => \Poodle::getKernel()->IDENTITY->id,
				'ip_address'   => $_SERVER['REMOTE_ADDR'],
				'redirect_uri' => isset($_GET['redirect_uri']) ? $_GET['redirect_uri'] : null,
				'session'      => session_id()
			);
		}
		return \Poodle::getKernel()->CACHE->set("transients/auth_sqrl/{$id}", $data, SQRL::SESSION_TIMEOUT);
	}

}

abstract class SQRLBody implements \ArrayAccess
{
	protected $data = array();

	public function __construct(string $body = null)
	{
		if ($body) {
			/**
			 * Split the variables into an array.
			 */
			$body = explode("\r\n", \Poodle\Base64::urlDecode($body));
			if (!isset($body[1]) && $this instanceof SQRLServerBody) {
				/**
				 * If the previous value from the client is only a single value
				 * that means the client only have seen the URL from the server
				 * and we should fetch the query values from the call.
				 *
				 * Else we handle the server string with properties that are line separated.
				 */
				if ($p = strpos($body[0], '?')) {
					$body[0] = substr($body[0], $p+1);
				}
				$body = explode('&', $body[0]);
			}
			foreach ($body as $k => $v) {
				$p = explode('=', $v, 2);
				if (isset($p[1])) {
					$this->data[$p[0]] = $p[1];
				}
			}
		}
	}

	public function offsetExists($key)
	{
		return array_key_exists($key, $this->data);
	}

	public function offsetGet($key)
	{
		return $this->data[$key];
	}

	public function offsetSet($key, $v)
	{
		$this->data[$key] = $v;
	}

	public function offsetUnset($key)
	{
		unset($this->data[$key]);
	}

	public function __toString()
	{
		$body = array();
		foreach ($this->data as $k => $v) {
			if (!is_null($v)) {
				if ('tif' === $k) {
					$v = dechex($v);
				}
				$body[] = "{$k}={$v}";
			}
		}
		return implode("\r\n", $body);
	}
}

class SQRLClientBody extends SQRLBody
{
	protected $data = array(
		'opt'  => null,
		'cmd'  => null,
		/**
		 * Identity key, used to check the validity of the current user
		 * and also associate the current login with the account.
		 */
		'idk'  => null, # Base64
		'pidk' => null, # Base64
		/**
		 * Server unlock key is returned on request from the client
		 * when the client requires it for more advanced features.
		 */
		'suk'  => null, # Base64
		/**
		 * Verify Unlock Key, used to verify the unlock request signature sent
		 * from the client when a disabled account should be enabled again.
		 */
		'vuk'  => null, # Base64

		'ver'  => null, # 1
		'ins'  => null, # Base64
	);

	public function hasOption($key)
	{
		/**
		 * Explode the option array with all the SQRL options.
		 * Valid values are:
		 *   suk = Request for Server unlock key
		 *   cps = Client Provided Session is available
		 *   noiptest = Server don't need to check the IP address of the client (remote device login)
		 *   sqrlonly = Client requests the server to only allow SQRL logins, all other authentication should be disabled.
		 *   hardlock = Client request all "out of band" changes to the account. Like security questions to retrieve the account when passphrase is lost.
		 */
		return in_array($key, explode('~', $this->data['opt']));
	}
}

class SQRLServerBody extends SQRLBody
{
	protected $data = array(
		'nut' => null,
//		'redirect_uri' => null, # Poodle param
//		'auth'         => null, # Poodle param
//		'x'            => null,
	);
}

class SQRLResponseBody extends SQRLBody
{
	public $provider_id;

	protected $data = array(
		'ver' => 1,
		'tif' => null, # dechex('tif')
		'sin' => 0,
		'nut' => null,
		'qry' => null,
		'url' => null,
		'can' => null,
		'suk' => null,
	);

	public function __construct(SQRL $provider)
	{
		$this->provider_id = $provider->id;
		# Generate a random nut
		$this->data['nut'] = \Poodle\Random::string(32, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
	}

	public function getAuthURI()
	{
		return $_SERVER['REQUEST_PATH']
			. "?auth={$this->provider_id}"
			. "&nut={$this->data['nut']}"
			. '&x=' . max(1, strlen(\Poodle::$URI_BASE));
	}

	public function getSqrlURI()
	{
		return 'sqrl://' . $_SERVER['HTTP_HOST'] . $this->getAuthURI();
	}

	protected function setActionUrl($action)
	{
		$url = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}";
		if (!in_array($_SERVER['SERVER_PORT'], array(80,443))) {
			$url .= ":{$_SERVER['SERVER_PORT']}";
		}
		$url .= parse_url(admin_url('admin-post.php'), PHP_URL_PATH);
		$this->data['url'] = $url . '?action=' . $action;
		$this->data['can'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . \Poodle::$URI_BASE . '?q=canceled';
	}

	public function setLoginUrl($associatedExistingUser = false)
	{
		$this->setActionUrl('sqrl_login&nut=' . $this->data['nut'] . ($associatedExistingUser ? '&existingUser=1' : ''));
	}

	public function setQry()
	{
		$this->data['qry'] = $this->getAuthURI();
	}

	public function output()
	{
		$body = \Poodle\Base64::urlEncode($this . "\r\n");
		header('Content-Type: application/x-www-form-urlencoded');
		header('Content-Length: ' . strlen($body));
		echo $body;
		exit;
	}
}
