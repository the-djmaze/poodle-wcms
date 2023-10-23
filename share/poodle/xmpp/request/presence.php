<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#stanzas-semantics-presence
*/

namespace Poodle\XMPP\Request;

class Presence extends \Poodle\XMPP\Request
{

	const
		STANZA = 'presence',

		// https://xmpp.org/rfcs/rfc6121.html#presence-syntax-type
		TYPE_AVAILABLE    = '',
		TYPE_SUBSCRIBE    = 'subscribe',
		TYPE_SUBSCRIBED   = 'subscribed',
		TYPE_UNAVAILABLE  = 'unavailable',
		TYPE_UNSUBSCRIBE  = 'unsubscribe',
		TYPE_UNSUBSCRIBED = 'unsubscribed',

		// https://xmpp.org/rfcs/rfc6121.html#presence-syntax-children-show
		SHOW_ONLINE      = '',
		SHOW_DND         = 'dnd',
		SHOW_AWAY        = 'away',
		SHOW_CHAT        = 'chat',
		SHOW_XA          = 'xa';

	protected
		$show,
		$status,
		$priority,
		$poke;     // XEP-0132

	public function __construct($status = '', $to = '', $type = self::TYPE_AVAILABLE, $show = self::SHOW_ONLINE, $priority = 0)
	{
		$this->status = $status;
		$this->to     = $to;
		if (in_array($show, array(self::TYPE_AVAILABLE, self::TYPE_UNAVAILABLE, self::TYPE_SUBSCRIBE, self::TYPE_SUBSCRIBED, self::TYPE_UNSUBSCRIBE, self::TYPE_UNSUBSCRIBED))) {
			$this->type = $type;
		}
		if (in_array($show, array(self::SHOW_ONLINE, self::SHOW_DND, self::SHOW_AWAY, self::SHOW_CHAT, self::SHOW_XA))) {
			$this->show = $show;
		}
		if ($priority && $priority >= -128 && $priority <= 127) {
			$this->priority = $priority;
		}
	}

	public function getValue()
	{
		$value = '';
		if ($this->poke) {
			$value .= '<poke xmlns="http://jabber.org/protocol/poke"/>';
		}
		if ($this->show) {
			$value .= '<show>'. static::encode($this->show) .'</show>';
		}
		if ($this->show) {
			$value .= '<status>'. static::encode($this->status) .'</status>';
		}
		if ($this->priority) {
			$value .= '<priority>'. static::encode($this->priority) .'</priority>';
		}
		return $value;
	}

	public static function newAway($status = '', $to = '')
	{
		return new \Poodle\XMPP\Request\Presence($status, $to, self::TYPE_AVAILABLE, self::SHOW_AWAY);
	}

	public static function newDoNotDisturb($status = '', $to = '')
	{
		return new \Poodle\XMPP\Request\Presence($status, $to, self::TYPE_AVAILABLE, self::SHOW_DND);
	}

	public static function newExtendedAway($status = '', $to = '')
	{
		return new \Poodle\XMPP\Request\Presence($status, $to, self::TYPE_AVAILABLE, self::SHOW_XA);
	}

	public static function newChat($status = '', $to = '')
	{
		return new \Poodle\XMPP\Request\Presence($status, $to, self::TYPE_AVAILABLE, self::SHOW_CHAT);
	}

	public static function newAvailable($status = '')
	{
		return new \Poodle\XMPP\Request\Presence($status);
	}

	public static function newUnavailable($status = '')
	{
		return new \Poodle\XMPP\Request\Presence($status, '', self::TYPE_UNAVAILABLE);
	}

	public static function newSubscribeResponse($to, $allow = true)
	{
		return new \Poodle\XMPP\Request\Presence('', $to, $allow ? self::TYPE_SUBSCRIBED : self::TYPE_UNSUBSCRIBED);
	}
}
