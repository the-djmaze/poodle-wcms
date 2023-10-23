<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\XMPP;

abstract class Stream
{
	const
		LOG_DEBUG     = 7,
		LOG_INFO      = 6,
		LOG_NOTICE    = 5,
		LOG_WARNING   = 4,
		LOG_ERROR     = 3,
		LOG_CRITICAL  = 2,
		LOG_ALERT     = 1,
		LOG_EMERGENCY = 0;

//	use \Poodle\Events;

	protected
		$id = '',
		$xmlparser,
		$socket,
		$log_level = 3,
		$receivedNodes = array(), // List of XMLNode instances
		$eventsCache = array(),   // List of array(XMLNode, $extension_object)
		$extensions = array();    // All loaded extensions as: namespace => Object()

	private
		$config,
		$event_blocking = 0,
		$is_ready = false;

	function __construct($config)
	{
		if (is_string($config)) {
			$config = parse_url($config) ?: array();
			if (isset($config['user'])) $config['user'] = rawurldecode($config['user']);
			if (isset($config['pass'])) $config['pass'] = rawurldecode($config['pass']);
			if (isset($config['query'])) {
				$options = array();
				parse_str($config['query'], $options);
				unset($config['query']);
				$config = array_merge($options, $config);
			}
		}
		$config = array_merge(array(
			'scheme'     => 'tcp',
			'host'       => null,
			'port'       => null,
			'user'       => null,
			'pass'       => null,
			'path'       => null,
			// options
			'to'         => null,
			'timeout'    => 15,
			'persistent' => false,
		), $config);
		if (preg_match('/^([^@]+)@([^@]+)$/D', $config['user'], $m)) {
			$config['user'] = $m[1];
			$config['to']   = $m[2];
		}

		// https://xmpp.org/rfcs/rfc6120.html#tcp-resolution-prefer
		// https://xmpp.org/extensions/xep-0156.html not implemented
		if ($config['to'] && !$config['host'] && $srv = dns_get_record("_xmpp-client._tcp.{$config['to']}", DNS_SRV)) {
			 $config['host'] = $srv[0]['target'];
			 $config['port'] = $srv[0]['port'];
		}

		if (!$config['port']) {
			$config['port'] = 5222;
		}
		if (!$config['host']) {
			$config['host'] = $config['to'];
		}
		if (!$config['to']) {
			$config['to'] = $config['host'];
		}
		$this->config = $config;
	}

	function __destruct()
	{
		$this->disconnect();
	}

	public function connect()
	{
		if ($this->socket) {
			return $this;
		}
		$attempts = 3;
		$errno = $errstr = null;
		$flags = STREAM_CLIENT_CONNECT;
		if ($this->config['persistent']) {
			$flags |= STREAM_CLIENT_PERSISTENT;
		}
		$address = $this->getAddress();
		$options = array();
		$context = stream_context_create($options);
		$this->log("Connecting to {$address}", static::LOG_NOTICE);
		while (!$this->socket && $attempts--) {
			$this->socket = stream_socket_client($address, $errno, $errstr, $this->config['timeout'], $flags, $context);
			if (!$this->socket) {
				$this->log("Failed to connect. {$errno}: {$errstr}", static::LOG_ERROR);
				sleep(1);
			}
		}
		if (!$this->socket) {
			throw new \Exception("Failed to connect {$errno}: {$errstr}");
		}
		$this->id = (string) $this->socket;
		$this->id = substr($this->id, strrpos($this->id,'#'));
		return $this->setBlocking()->setTimeout($this->config['timeout'])->start();
	}

	public function start()
	{
		$this->is_ready = false;
		$this->log("Start stream", static::LOG_INFO);
		libxml_use_internal_errors(true);
		libxml_disable_entity_loader(true);
		$parser = xml_parser_create_ns('UTF-8');
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, true);
		xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
		xml_set_object($parser, $this);
		xml_set_element_handler($parser, 'xml_node_start', 'xml_node_end');
		xml_set_character_data_handler($parser, 'xml_character_data');
		$this->xmlparser = $parser;
		$this->receivedNodes = array();
		$this->eventsCache = array();
		$this->send('<stream:stream to="'.$this->config['to'].'" xmlns:stream="'.Extensions\Stream::NS.'" xmlns="'.static::NS.'" version="1.0">', true);
		return $this;
	}

	public function reconnect()
	{
		return $this->disconnect()->connect();
	}

	public function disconnect($notify_server = true)
	{
		if ($this->socket && $notify_server) {
			$this->send('</stream:stream>', true);
		}
		if ($this->socket) {
//			stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
			fclose($this->socket);
			$this->log("Disconnected", static::LOG_NOTICE);
		}
		$this->socket = null;
		$this->is_ready = false;
		return $this;
	}

	public function setBlocking($mode = true)
	{
		if (!stream_set_blocking($this->socket, $mode)) {
			throw new \Exception('setBlocking failed');
		}
		return $this;
	}

	// STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT ?
	public function setCrypto($enable = true, $type = STREAM_CRYPTO_METHOD_ANY_CLIENT)
	{
		if (!stream_socket_enable_crypto($this->socket, $enable, $enable ? $type : 0)) {
			throw new \Exception('setCrypto failed');
		}
		return $this->start();
	}

	public function setTimeout($timeout, $microseconds = 0)
	{
		if (!stream_set_timeout($this->socket, $timeout, $microseconds)) {
			throw new \Exception('setTimeout failed');
		}
		return $this;
	}

	public function receive($priority = false)
	{
		// Wait half a second maximum
		$read = array($this->socket);
		$dummy = array();
		$buffer = '';
		$length = 0;
		while (stream_select($read, $dummy, $dummy, 0, 500000)) {
			$buffer .= fread($this->socket, 1024);
			$l = strlen($buffer);
			if ($length === $l || $l % 1024) {
				// We didn't receive more data
				break;
			}
			$length = $l;
		}
		if ($buffer) {
			$this->log("<= {$buffer}", static::LOG_DEBUG);
			if ($priority) {
				++$this->event_blocking;
			}
			if (!xml_parse($this->xmlparser, $buffer, false)) {
				$code = xml_get_error_code($this->xmlparser);
				$this->log("XML error {$code}: ".xml_error_string($code), static::LOG_ERROR);
			}
			if ($priority) {
				--$this->event_blocking;
			}
			while ($event = array_shift($this->eventsCache)) {
				$this->triggerEvent($event);
/*
				$event = new \Poodle\Events\Event("{{$node->ns}}{$node->name}");
				$event->node = $node;
				$this->dispatchEvent($event);
*/
			}
			if (!$this->is_ready && $this->receivedNodes) {
				$this->log("Stream ready", static::LOG_INFO);
				$this->is_ready = true;
			}
			return $buffer;
		}
/*
		try {
			$this->checkTimeout($buffer);
		} catch (TimeoutException $exception) {
			$this->reconnectTls($exception);
		}
*/
	}

	public function send($string, $priority = false)
	{
		if ($this->log_level == static::LOG_INFO) {
			$node = preg_replace('#^<(/?[a-z]+).+$#Dsi', '<$1>', $string);
			$this->log("=> {$node}", static::LOG_INFO);
		} else if ($this->log_level == static::LOG_DEBUG) {
			$this->log("=> {$string}", static::LOG_DEBUG);
		}
		if (!$this->socket) {
			throw new \Exception("Send error: not connected");
		}
		$length = strlen($string);
		$bytes = fwrite($this->socket, $string, $length);
		if ($bytes != $length) {
			throw new \Exception('Send failed');
		}
		$this->receive($priority);
		return $this;
	}

	public function getAddress()
	{
		return "{$this->config['scheme']}://{$this->config['host']}:{$this->config['port']}";
	}

	public function getUsername()
	{
		return $this->config['user'];
	}

	public function getUserAddress()
	{
		return "{$this->config['user']}@{$this->config['to']}";
	}

	public function getPassphrase()
	{
		return $this->config['pass'];
	}

	public function getResource()
	{
		$username = $this->getUsername();
		$username = explode('/', $username);
		return isset($username[1]) ? $username[1] : '';
	}

	public function setLogLevel($level)
	{
		$this->log_level = max(0, $level);
		return $this;
	}

	public function log($msg, $level = 0)
	{
		if ($this->log_level >= $level) {
			$msg = "XMPP{$this->id} {$level} {$msg}";
			switch ($this->log_level)
			{
			case static::LOG_DEBUG:
			case static::LOG_INFO:
				error_log($msg);
				break;
			case static::LOG_NOTICE:
				trigger_error($msg);
				break;
			case static::LOG_WARNING:
				trigger_error($msg, E_USER_WARNING);
				break;
			default:
			case static::LOG_ERROR:
			case static::LOG_CRITICAL:
			case static::LOG_ALERT:
			case static::LOG_EMERGENCY:
				error_log($msg);
			}
		}
		return $this;
	}

	public function cacheEventNode(XMLNode $node, $prepend = false)
	{
		if ($obj = $this->getNodeExtension($node)) {
			if (!method_exists($obj, strtr($node->name, '-', '_'))) {
				$this->log("Extension method not found for {$node->ns} {$node->name}", static::LOG_NOTICE);
				return;
			}
			if ($prepend || $this->event_blocking) {
				$this->log("Prepend event: {{$node->ns}}{$node->name}", static::LOG_DEBUG);
				array_unshift($this->eventsCache, array($node, $obj));
			} else {
				$this->log("Append  event: {{$node->ns}}{$node->name}", static::LOG_DEBUG);
				$this->eventsCache[] = array($node, $obj);
			}
		}
	}

	protected function triggerEvent($event)
	{
		$node = $event[0];
		$obj  = $event[1];
		$this->log("Trigger event: {{$node->ns}}{$node->name}", static::LOG_DEBUG);
		$fn = strtr($node->name, '-', '_');
		$obj->$fn($node);
	}

	protected function getNodeExtension(XMLNode $node)
	{
		$obj = null;
		$ns = $node->ns;
		if (!isset($this->extensions[$ns])) {
			$ns = substr($ns, 0, strrpos($ns,':')+1) . '*';
		}
		if (isset($this->extensions[$ns])) {
			$obj = $this->extensions[$ns];
		}
		if ($obj) {
			return $obj;
		}
		$this->log("Extension not found for {$node->ns}", static::LOG_NOTICE);
	}

	/**
	 * xml_parser start_element_handler
	 */
	protected function xml_node_start($parser, $name, $attr)
	{
		$p = strrpos($name, ':');
		$node = new XMLNode();
		$node->name       = substr($name, $p+1);
		$node->ns         = substr($name, 0, $p);
		$node->attributes = $attr;

		// Set child node
		$c = count($this->receivedNodes);
		if (1 < $c) {
			$node->parent = $this->receivedNodes[$c-1];
			$node->parent->children[] = $node;
		}

		$this->receivedNodes[] = $node;
	}

	/**
	 * xml_parser end_element_handler
	 */
	protected function xml_node_end($parser, $name)
	{
		if (!$this->receivedNodes) {
			throw new \Exception('XML node end missing start node');
		}
		$p = strrpos($name, ':');
		$node = array_pop($this->receivedNodes);
		if ($node->name !== substr($name, $p+1) || $node->ns !== substr($name, 0, $p)) {
			throw new \Exception('Invalid XML node end');
		}

		// End of stream ?
		if (!$this->receivedNodes) {
			$this->log("Stream closed", static::LOG_INFO);
			$this->disconnect(false);
			return;
		}
		if (1 === count($this->receivedNodes)) {
			$this->cacheEventNode($node);
		}
	}

	/**
	 * xml_parser character_data_handler
	 */
	protected function xml_character_data($parser, $data)
	{
		$node = $this->receivedNodes[count($this->receivedNodes) - 1];
		$node->value .= $data;
	}

}
