<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Stream;

if (!defined('STREAM_CRYPTO_METHOD_TLS_SECURE_CLIENT')) {
	// STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT requires OpenSSL v1.1.1
	if (OPENSSL_VERSION_NUMBER < 0x10101000 || !defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
		define('STREAM_CRYPTO_METHOD_TLS_SECURE_CLIENT', STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
	} else {
		define('STREAM_CRYPTO_METHOD_TLS_SECURE_CLIENT', STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
	}
}

class Socket
{
	protected
		$config = array(),
		$errno = 0,
		$errstr = null,
		$select_tv_sec = 0,
		$select_tv_usec = 500000,
		$socket; # the socket to the server

	function __construct($uri)
	{
		$this->config = static::parseConfigUri($uri);
	}

	function __destruct()
	{
		$this->disconnect();
	}

	public static function parseConfigUri($uri) : array
	{
		$cfg = parse_url($uri) ?: array();
		$options = array();
		if (isset($cfg['query'])) {
			parse_str($cfg['query'], $options);
			unset($cfg['query']);
		}
		$cfg = array_merge(array(
			'scheme'     => null,
			'host'       => null,
			'port'       => null,
			'user'       => null,
			'pass'       => null,
			'timeout'    => (float) ini_get('default_socket_timeout'),
			'ssl'        => null,
			'persistent' => null,
		), $options, $cfg);
		if ($cfg['user']) {
			$cfg['user'] = rawurldecode($cfg['user']);
		}
		if ($cfg['pass']) {
			$cfg['pass'] = rawurldecode($cfg['pass']);
		}
/*
		if (!$cfg['ssl']) {
			// https://www.php.net/manual/en/context.ssl.php
			$cfg['ssl'] = array(
				'crypto_method' => STREAM_CRYPTO_METHOD_TLS_SECURE_CLIENT,
			);
		}
*/
		return $cfg;
	}

	public function connect() : void
	{
		$address = $this->getTarget();

		$flags = STREAM_CLIENT_CONNECT;
		if ($this->config['persistent']) {
			$flags |= STREAM_CLIENT_PERSISTENT;
		}

		$context = array();
		if ($this->config['ssl']) {
			$context['ssl'] = $this->config['ssl'];
		}
		$context = stream_context_create($context); // stream_context_get_default($context);

		$this->socket = stream_socket_client("tcp://{$address}", $this->errno, $this->errstr, $this->config['timeout'], $flags, $context);
		if (!$this->socket) {
			throw new \Exception("Connection to {$address} failed: " . ($this->errstr ?: error_get_last()['message']), $this->errno);
		}

		if ($this->config['timeout']) {
			$s = intval($this->config['timeout']);
			$ms = ($this->config['timeout'] - $s) * 1000000;
			$this->setTimeout($s, $ms);
		}

/*
		if (0 !== stream_set_read_buffer($this->socket, 0)) {
			throw new \Exception("stream_set_read_buffer() failed on {$address}");
		}
		if (0 !== stream_set_write_buffer($this->socket, 0)) {
			throw new \Exception("stream_set_write_buffer() failed on {$address}");
		}
*/
	}

	public function disconnect() : void
	{
		if ($this->socket) {
//			stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
			fclose($this->socket);
			$this->socket = null;
		}
	}

	public function copyToStream(resource $dest, int $maxlength = -1, int $offset = 0) // : int|false
	{
		return stream_copy_to_stream($this->socket, $dest, $maxlength, $offset);
	}

	public function setNotificationCallback(callable $method) : void
	{
		$params = stream_context_get_params($this->socket);
		$params['notification'] = $method;
		if (!stream_context_set_params($this->socket, $params)) {
			throw new \Exception('stream_context_set_params failed');
		}
	}

	public function disableCrypto() : self
	{
		return $this->setCrypto(false, 0);
	}

	public function enableCrypto(int $type = STREAM_CRYPTO_METHOD_TLS_SECURE_CLIENT) : self
	{
		return $this->setCrypto(true, $type);
	}

	public function getCrypto()
	{
		$data = stream_get_meta_data($this->socket);
		return empty($data['crypto']) ? false : $data['crypto'];
	}

	protected function setCrypto(bool $enable, int $type) : self
	{
		// stream_set_blocking($this->socket, true);
		if (!stream_socket_enable_crypto($this->socket, $enable, $type)) {
			throw new \Exception("setCrypto() with {$this->getTarget()} failed: ".\Poodle\Debugger::getLastError()['message']);
		}
		// stream_set_blocking($this->socket, false);
		return $this;
	}

	public function getContextOptions() : array
	{
		return stream_context_get_options($this->socket);
	}

	public function getMetaData() : array
	{
		return stream_get_meta_data($this->socket);
	}

	public function getTarget() : string
	{
		return "{$this->config['host']}:{$this->config['port']}";
	}

	protected function isConnected() : bool
	{
		if ($this->socket) {
			$status = socket_get_status($this->socket);
			if (!$status['eof']) { return true; }
			# hmm this is an odd situation... the socket is
			# valid but we aren't connected anymore
			$this->disconnect();
		}
		return false;
	}

	public function isBlocking() : bool
	{
		return stream_get_meta_data($this->socket)['blocked'];
	}

	public function setBlocking(bool $mode) : self
	{
		if (!stream_set_blocking($this->socket, $mode)) {
			throw new \Exception("setBlocking() with {$this->getTarget()} failed");
		}
		return $this;
	}

	public function setContextOption(string $wrapper, string $option, $value) : self
	{
		if (!stream_context_set_option($this->socket, $wrapper, $option, $value)) {
			throw new \Exception("stream_context_set_option with {$this->getTarget()} failed");
		}
		return $this;
	}

	public function setTimeout(int $timeout, int $microseconds = 0) : self
	{
		if (!stream_set_timeout($this->socket, $timeout, $microseconds)) {
			throw new \Exception("stream_set_timeout with {$this->getTarget()} failed");
		}
		return $this;
	}

	public function read(int $count = 0) : ?string
	{
		return static::readData($this->socket, $count);
	}

	public function readLine(int $count = 0) : ?string
	{
		return static::readData($this->socket, $count, true);
	}

	public function write($string) // : int|false
	{
		return fwrite($this->socket, $string);
	}

	protected static function readData($socket, int $count = 0, bool $line = false) : ?string
	{
		$data = null;
		$read = array($socket);
		$dummy = array();
		if (stream_select($read, $dummy, $dummy, 0, 500000)) {
			$count = max(0, $count);
			if ($line) {
				$data = $count ? fgets($socket, $count) : fgets($socket);
				if (false === $data) {
					throw new \Exception('Failed to read stream');
				}
			} else if ($count > 8192) {
				$data = '';
				$length = 0;
				while (0 < $count) {
					if (false === ($tmp = fread($socket, min($count, 8192)))) {
						throw new \Exception('Failed to read stream');
					}
					$data .= $tmp;
					$count -= strlen($tmp);
					$l = strlen($data);
					if ($length === $l) {
						// We didn't receive more data
						break;
					}
					$length = $l;
				}
			} else {
				$data = '';
				$length = 0;
				do {
					if (false === ($tmp = fread($socket, $count ?: 1024))) {
						throw new \Exception('Failed to read stream');
					}
					$data .= $tmp;
					$l = strlen($data);
					if ($count && $count <= $l) {
						break;
					}
					if ($length === $l || $l % 1024) {
						// We didn't receive more data
						break;
					}
					$length = $l;
				} while (stream_select($read, $dummy, $dummy, 0, 500000));
			}
		} else {
			throw new \Exception('Stream read timeout');
		}
		return $data;
	}

	public static function getCryptoMethods() : array
	{
		// stream_get_transports()
		$cryptos = array(
			'tlsv1.2' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
			'tlsv1.1' => STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
			'tlsv1.0' => STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT,
			'sslv3'   => STREAM_CRYPTO_METHOD_SSLv3_CLIENT,
		);
		// STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT requires OpenSSL v1.1.1
		if (OPENSSL_VERSION_NUMBER >= 0x10101000 && defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
			return array_merge(array(
					'tlsv1.3' => STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
				),
				$cryptos
			);
		}
		return $cryptos;
	}

	public static function detectCrypto($host, $port, $starttls = null) : ?string
	{
		$context = stream_context_create(array('ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true,
//			'crypto_method' => STREAM_CRYPTO_METHOD_TLS_SECURE_CLIENT,
		)));

		foreach (static::getCryptoMethods() as $crypto => $type) {
			$socket = stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
			if (!$socket) {
				return null;
			}
			try {
				switch ($starttls)
				{
				case 'smtp':
					static::readData($socket);
					fwrite($socket, "EHLO {$host}\r\n");
				case 'sieve':
					if (!strpos(static::readData($socket), 'STARTTLS')) {
						return null;
					}
					fwrite($socket, "STARTTLS\r\n");
					static::readData($socket);
					break;

				case 'xmpp':
					if (!stream_set_blocking($socket, true)) {
						return null;
					}

					fwrite($socket, '<stream:stream to="'.$host.'" xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client" version="1.0">');
					$data = static::readData($socket);
					if (!strpos($data, '<starttls')) {
						return null;
					}

					fwrite($socket, '<starttls xmlns="urn:ietf:params:xml:ns:xmpp-tls"><required /></starttls>');
					$data = static::readData($socket);
					if (false === strpos($data, '<proceed')) {
						return null;
					}
					break;
				}

				if (stream_socket_enable_crypto($socket, true, $type)) {
					return $crypto;
				}
			} finally {
				fclose($socket);
			}
		}
		return null;
	}

}
