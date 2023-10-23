<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	SMTP is rfc 821 compliant and implements some rfc 2821 commands.
	http://networksorcery.com/enp/protocol/smtp.htm
	https://tools.ietf.org/html/rfc5321 Simple Mail Transfer Protocol
	https://tools.ietf.org/html/rfc6409 Message Submission for Mail

	Mail has changed many times, and yet again:
	https://tools.ietf.org/html/rfc8314#section-3

	SSL must be abandoned, so there's only:
	- Unencrypted (port 25)
	- Implicit TLS (port 465)
	- STARTTLS (port 587)
*/

namespace Poodle\Mail;

class SMTP extends \Poodle\Stream\Socket
{
	public
		$debug = array(),
		$dsn_ret    = '', // FULL | HDRS
		$dsn_notify = '', // NEVER | SUCCESS,FAILURE,DELAY
		$dsn_orcpt  = ''; // rfc822;support@example.com

	protected
		$server_auth_methods = array(),
		$extensions;

	protected static
		$AUTH_METHODS = array('LOGIN', 'PLAIN', 'SCRAM-SHA-1', 'CRAM-MD5');

	public function __construct($config_uri)
	{
		parent::__construct($config_uri);
		$cfg = $this->config;
		if (!$cfg['host']) {
			$cfg['host'] = '127.0.0.1';
		}
		if (!$cfg['port']) {
			$cfg['port'] = 25;
			if ('tls' === $cfg['scheme'] || 'ssl' === $cfg['scheme']) {
				$cfg['port'] = 465;
			} else if ('starttls' === $cfg['scheme']) {
				$cfg['port'] = 587;
			}
		} else if (!$cfg['scheme']) {
			if (587 === $cfg['port']) {
				$cfg['scheme'] = 'starttls';
			} else if (465 === $cfg['port']) {
				$cfg['scheme'] = 'tls';
			}
		}
		if (!$cfg['timeout']) {
			$cfg['timeout'] = 15;
		}
		if (!$cfg['ehlo']) {
			# if a hostname for the EHLO wasn't specified we force a default
			if (!empty($_SERVER['HTTP_HOST'])) {
				$cfg['ehlo'] = $_SERVER['HTTP_HOST'];
			} else if (!empty($_SERVER['SERVER_NAME'])) {
				$cfg['ehlo'] = $_SERVER['SERVER_NAME'];
			} else {
				$cfg['ehlo'] = gethostname() ?: php_uname('n') ?: 'localhost.localdomain';
			}
		}

		$this->config = $cfg;
	}

	public function __destruct()
	{
		$this->quit();
	}

	public static function detectCrypto($host, $port, $starttls = null) : ?string
	{
		return parent::detectCrypto($host, $port, $starttls ? 'smtp' : null);
	}

	public static function parseConfigUri($uri) : array
	{
		return array_merge(array(
			'ehlo'    => '',
			'auth'    => '', // see $AUTH_METHODS
			'legacy'  => 1,
		), parent::parseConfigUri($uri));
	}

	public static function getAuthMethods()
	{
		return static::$AUTH_METHODS;
	}

	public function getServerAuthMethods()
	{
		if (!$this->isConnected()) {
			$cfg = $this->config;
			try {
				$this->config['pass'] = null;
				$this->connect();
				$this->quit();
			} finally {
				$this->config['pass'] = $cfg['pass'];
			}
		}
		return $this->server_auth_methods;
	}

	public function hasExtension($name)
	{
		return in_array($name, $this->extensions);
	}

	protected function startEncryption()
	{
/*
		$context = stream_context_get_options($this->socket);
		if (!empty($context['ssl']['crypto_method'])) {
			$method = $context['ssl']['crypto_method'];
		}
*/
		$cryptos = static::getCryptoMethods();
		if (!$this->config['legacy']) {
			unset($cryptos['tlsv1.1'], $cryptos['tlsv1.0']);
		}
		if ('ssl' !== $this->config['scheme']) {
			unset($cryptos['sslv3']);
		}

//		return stream_socket_enable_crypto($this->socket, true, array_sum($cryptos));
		try {
			return !!$this->setCrypto(true, array_sum($cryptos));
		} catch (\Throwable $e) {
			error_log($e->getMessage());
		}
		return false;
	}

	public function connect() : void
	{
		# Try to make an SMTP connection when there's none
		if ($this->isConnected()) {
			return;
		}

		$address = $this->getTarget();
		$this->debug[] = "stream_socket_client('{$address}')";
		parent::connect();

		if ('ssl' === $this->config['scheme'] || 'tls' === $this->config['scheme'] || 465 === $this->config['port']) {
			$code = 0;
		} else {
			$code = $this->getResponseCode();
		}
		$encrypted = false;
		if ($code || 'starttls' === $this->config['scheme']) {
			// try starttls
			$this->ehlo();
			if ('starttls' === $this->config['scheme']) {
				if (!in_array('STARTTLS', $this->extensions)) {
					throw new \Exception("SMTP Server {$address} STARTTLS not possible");
				}
				$this->sendCommand('STARTTLS', 'STARTTLS', 220);
				// Begin encrypted connection
				$encrypted = $this->startEncryption();
				if (!$encrypted) {
					throw new \Exception("SMTP Server {$address} STARTTLS failed: ".\Poodle\Debugger::getLastError()['message']);
				}
			}
		} else {
			$encrypted = $this->startEncryption();
			if (!$encrypted) {
				throw new \Exception("SMTP Server {$address} TLS failed");
			}
			if (!$this->getResponseCode()) {
				throw new \Exception("Could not connect to {$address}", E_USER_ERROR);
			}
		}
		if ($encrypted) {
			$this->debug[] = "SMTP Server {$address} uses encryption";
			// We must send EHLO after TLS negotiation
			$this->ehlo();
		} else {
			trigger_error("SMTP Server {$address} does not use encryption");
		}

		if ($this->config['user'] && $this->config['pass']) {
			if ($this->server_auth_methods) {
				if (empty($this->config['auth'])) {
					foreach (static::$AUTH_METHODS as $method) {
						if (in_array($method, $this->server_auth_methods) && \Poodle\Auth\SASL::isSupported($method)) {
							$this->config['auth'] = $method;
							break;
						}
					}
				} else if (!in_array($this->config['auth'], $this->server_auth_methods)) {
					throw new \Exception("SMTP Server {$address} does not support AUTH method {$this->config['auth']}, use: ".implode(' or ', $this->server_auth_methods));
				}
				if (empty($this->config['auth'])) {
					throw new \Exception("SMTP Server {$address} uses unsupported AUTH method(s): ".implode(', ', $this->server_auth_methods));
				}
				$this->auth();
			} else {
				trigger_error("SMTP AUTH not allowed on {$address}");
			}
		}
	}

	### CONNECTION FUNCTIONS ###

	protected function sendCommand($cmd, $error_msg=null, ...$ok_codes)
	{
		$this->debug[] = "=> {$cmd}";
		# See mail/l10n/en.php for all error codes
		$this->write("{$cmd}\r\n");
		list($code, $msg) = $this->getResponse();
		if (!in_array($code, $ok_codes ?: [250])) {
			$this->errno = $code;
			$this->errstr = $msg;
			if (in_array(234, $ok_codes) || in_array(235, $ok_codes) || in_array(334, $ok_codes)) {
				\Poodle\Log::error('SMTP '.$code, "{$error_msg} {$msg}");
			} else {
				\Poodle\Log::error('SMTP '.$code, "{$msg}\n\nCommand: {$cmd}");
			}
			if ($error_msg) {
				if (502 === $code) {
					trigger_error("{$error_msg} command is not implemented: {$msg}", $code);
				} else {
					if (preg_match('/^[0-9.]+ /', $msg)) {
						$msg = preg_replace('/\\r?\\n[0-9.]+ +/s', ' ', $msg);
					}
					throw new \Poodle\Mail\Exception("{$error_msg} not accepted by host {$this->getTarget()}", $code, $msg);
				}
			}
			return false;
		}
		return $msg;
	}

	protected function socket_write_line($line)
	{
		if (strlen($line) && '.' === $line[0]) {
			$line = '.'.$line;
		}
		$this->write($line."\r\n");
	}

	### SMTP COMMANDS ###

	public function from($from)
	{
		if (!$this->isConnected()) {
			throw new \Exception("from() called without being connected to {$this->getTarget()}");
		}
		$dsn = '';
		if ($this->dsn_ret && in_array('DSN', $this->extensions)) {
			$dsn = " RET={$this->dsn_ret}";
		}
		$this->sendCommand("MAIL FROM:<{$from}>{$dsn}", 'MAIL');
	}

	public function to($to)
	{
		if (!$this->isConnected()) {
			throw new \Exception("to() called without being connected to {$this->getTarget()}");
		}
		$dsn = '';
		if ($this->dsn_notify && in_array('DSN', $this->extensions)) {
			$dsn = " NOTIFY={$this->dsn_notify}";
		}
		return false !== $this->sendCommand("RCPT TO:<{$to}>{$dsn}", null, 250, 251);
	}

	public function data($data)
	{
/*
		$this->sendCommand("SIZE=$numberofbytes");
*/
		if (!$this->isConnected()) {
			throw new \Exception("data() called without being connected to {$this->getTarget()}");
		}
		$this->sendCommand('DATA', 'DATA', 354);
		# Cool, the server is ready to accept data!
		# Now normalize the line breaks so we know the explode works
		$data = str_replace("\r\n","\n",$data);
		$data = str_replace("\r","\n",$data);
		$data = explode("\n",$data);

		# according to rfc 821 we should not send more than 1000 characters
		# on a single line (including the CRLF), so we will break the data up
		# into lines by \r and/or \n then if needed we will break
		# each of those into smaller lines to fit within the limit.

		# we need to find a good way to determine if headers are
		# in the msg_data or if it is a straight msg body
		# currently I'm assuming rfc 822 definitions of msg headers
		# and if the first field of the first line (':' seperated)
		# does not contain a space then it _should_ be a header
		# and we can process all lines before a blank "" line as
		# headers.
		$field = substr($data[0], 0, strpos($data[0],':'));
		$in_headers = (!empty($field) && !strstr($field,' '));
		foreach ($data as $line) {
			if ($in_headers && '' === $line) {
				$in_headers = false;
			}
			# Check to break this line up into smaller lines
			while (strlen($line) > 998) {
				$pos = strrpos(substr($line, 0, 998), ' ') ?: 997;
				$this->socket_write_line(substr($line, 0, $pos));
				$line = substr($line, $pos+1);
				# if we are processing headers we need to add a LWSP-char to
				# the front of the new line rfc 822 on long msg headers
				if ($in_headers) {
					$line = "\t{$line}";
				}
			}
			$this->socket_write_line($line);
		}

		/**
		 * All the message data has been sent so lets end it
		 * Data end responses could be:
		 *
		 *     250 Ok: queued as 7523C1BEFA
		 *     250 ok 1215687857 qp 1544
		 *     250 2.0.0 n54CEgvI018278 Message accepted for delivery
		 */
		$msg = $this->sendCommand("\r\n.");
		if (false !== $msg) {
			$msg = preg_replace('#\s+qp\s+#', '-', $msg);
			// postfix
			$msg = preg_replace('#^.+queued as\s#i', '', $msg);
			// exim
			$msg = preg_replace('#^.+id=#i', '', $msg);
			// sendmail
			$msg = preg_replace('#^.*\s([a-zA-Z0-9]+)\s+Message accepted.*$#Dis', '$1', $msg);

			$this->msg_id = trim($msg);
			return true;
		}
	}

	# Close the active SMTP session if one exists.
	public function quit()
	{
		if ($this->isConnected()) {
//			$this->reset();
			$this->sendCommand('QUIT', null, 250, 221);
			$this->disconnect();
		}
	}

	# aborts current mail to start new mail
	protected function reset()
	{
		if (!$this->isConnected()) {
			throw new \Exception("reset() called without being connected to {$this->getTarget()}");
		}
		$this->sendCommand('RSET', 'RSET', 250);
	}

	protected function auth()
	{
		$type = $this->config['auth'];
		$username = $this->config['user'];
		$passphrase = $this->config['pass'];

		$SASL = \Poodle\Auth\SASL::factory($type);
		$SASL->base64 = true;
		// Start authentication
		$cmd = "AUTH {$type}";
		$result = $this->sendCommand($cmd, $cmd, 334);
		switch ($type)
		{
		// RFC 4616
		case 'PLAIN':
			$this->sendCommand($SASL->authenticate($username, $passphrase), $cmd.' Username/Passphrase', 235);
			break;

		case 'LOGIN':
			$result = $this->sendCommand($SASL->authenticate($username, $passphrase, $result), $cmd.' Username', 334);
			$this->sendCommand($SASL->challenge($result), $cmd.' Passphrase', 235);
			break;

		// RFC 2195
		case 'CRAM-MD5':
			$this->sendCommand($SASL->authenticate($username, $passphrase, $result), $cmd, 235);
			break;

		// RFC 5802
		case 'SCRAM-SHA-1':
			$result = $this->sendCommand($SASL->authenticate($username, $passphrase), $cmd, 234);
			$result = $this->sendCommand($SASL->challenge($result), $cmd.' Challenge', 235);
			$SASL->verify($result);
			break;
		// PLAIN-CLIENTTOKEN OAUTHBEARER XOAUTH
/*
		// https://developers.google.com/gmail/imap/xoauth2-protocol
		case 'XOAUTH2':
			throw new \Exception('Please use app passphrases: https://support.google.com/mail/answer/185833');
			$this->sendCommand($SASL->authenticate($username, $passphrase), $cmd, 235);
			break;
*/
		}
	}

	protected function ehlo()
	{
		if (!$this->isConnected()) {
			throw new \Exception("ehlo() called without being connected to {$this->getTarget()}");
		}
		# The SMTP command EHLO supersedes the earlier HELO
		$result = $this->sendCommand("EHLO {$this->config['ehlo']}", null, 250, 220);
		if (false === $result) {
			$result = $this->sendCommand("HELO {$this->config['ehlo']}", null, 250, 220);
		}
		if (false === $result) {
			throw new \Poodle\Mail\Exception("ehlo() failed on host {$this->getTarget()}", $this->errno, $this->errstr);
		}
		$this->extensions = explode("\n", $result);
		if (preg_match('/AUTH([^\\n]+)/', $result, $m)) {
			$this->server_auth_methods = preg_split('/[\\s=]+/', trim($m[1]));
		} else {
			$this->server_auth_methods = array();
		}
	}

	protected function getResponse()
	{
		$lines = array();
		while ($data = trim(fgets($this->socket, 1024))) {
			$this->debug[] = "<= {$data}";
			$lines[] = substr($data, 4);
			if (!isset($data[3]) || $data[3] === ' ') { break; }
		}
		return $lines
			? array((int)substr($data,0,3), implode("\n",$lines))
			: array(0, 'No response from server');
	}

	protected function getResponseCode()
	{
		$buffer = $this->read();
		return $buffer ? (int) substr($buffer, 0, 3) : 0;
	}

}
