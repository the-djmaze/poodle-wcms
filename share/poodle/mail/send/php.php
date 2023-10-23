<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	NOTE: It is worth noting that the mail() function is not suitable for
	      larger volumes of email in a loop. This function opens and closes
	      a SMTP socket for each email, which is not very efficient.
*/

namespace Poodle\Mail\Send;

class PHP extends \Poodle\Mail\Send
{

	# Sends mail using the PHP mail() function.
	public function send()
	{
		$this->prepare($header, $body, self::HEADER_ADD_BCC | self::HEADER_NO_SUBJECT);
		if (empty($body)) {
			return false;
		}

		$params = '';
		if (isset($this->sender)) {
			$old_from = \Poodle\PHP\INI::set('sendmail_from', $this->sender->address);
			$params = '-oi -f '.escapeshellarg($this->sender->address);
		}

		$rt = mail(
			$this->recipients['To']->asEncodedString(),
			$this->encodeHeader('', $this->subject),
			str_replace("\r\n", "\n", $body),
			$header,
			$params
		);

		if (!empty($old_from)) {
			\Poodle\PHP\INI::set('sendmail_from', $old_from);
		}
		if (!$rt) {
			throw new \Exception($this->l10n('PHP mail() function failed'), E_USER_ERROR);
		}
		return true;
	}

	public function close() {}

}
