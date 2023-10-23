<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	https://xmpp.org/rfcs/rfc6120.html#stanzas-semantics-message
*/

namespace Poodle\XMPP\Request;

class Message extends \Poodle\XMPP\Request
{

	const
		STANZA = 'message',

		// https://xmpp.org/rfcs/rfc6121.html#message-syntax-type
		TYPE_CHAT      = 'chat',
		TYPE_GROUPCHAT = 'groupchat',
		TYPE_HEADLINE  = 'headline',
		TYPE_NORMAL    = 'normal',    // default

		XHTML_NONE     = false,
		XHTML_DEFAULT  = '<a><img><blockquote><br><cite><em><p><span><strong><ol><ul><li>',
		XHTML_EXTENDED = '<a><img><abbr><acronym><address><blockquote><br><cite><code><dfn><div><em><h1><h2><h3><h4><h5><h6><kbd><p><pre><q><samp><span><strong><var><dl><dt><dd><ol><ul><li>';

	protected
		$subject,
		$message,
		$xhtml = false, // XEP-0071
		$attention,     // XEP-0224
		$thread;

	public function __construct($message, $to, $type = self::TYPE_CHAT)
	{
		$this->message = $message;
		$this->to      = $to;
		$this->type    = $type;
	}

	public function getValue()
	{
		$value = '';
		if ($this->subject) {
			$value .= '<subject>'. static::encode($this->subject) .'</subject>';
		}
		if ($this->attention) {
			$value .= '<attention xmlns="urn:xmpp:attention:0"/>';
		}
		if ($this->message) {
			if ($this->xhtml) {
				$value .= '<body>'. strip_tags($this->message) .'</body>';
				$value .= '<html xmlns="http://jabber.org/protocol/xhtml-im">'
					. '<body xmlns="http://www.w3.org/1999/xhtml">'
					. strip_tags(
						$this->message,
						self::XHTML_EXTENDED === $this->xhtml ? self::XHTML_EXTENDED : self::XHTML_DEFAULT
					)
					. '</body></html>';
			} else {
				$value .= '<body>'. static::encode($this->message) .'</body>';
			}
		}
		if ($this->thread) {
			$value .= '<thread>'. static::encode($this->thread) .'</thread>';
		}
		return $value;
	}

}
