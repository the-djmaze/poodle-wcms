<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.
*/

namespace Poodle\Mail\Send;

class None extends \Poodle\Mail\Send
{
	# Fake mail sender for servers without mail support.
	public function send() { return true; }
	public function close() {}
}
