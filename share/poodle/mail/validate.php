<?php

namespace Poodle\Mail;

class Validate
{

	const
		ERR_ADDR_SHORT   = 1,
		ERR_ADDR_LONG    = 2,
		ERR_ADDR_INVALID = 3,
		ERR_NO_MX        = 4,
		ERR_NO_MAILBOX   = 5;

	protected static $mxdomains = array();

	public static function MX($address)
	{
		return !!static::address($address);
	}

	/**
	 * Check DNS if MX/A exists for $address domain
	 */
	public static function address($address)
	{
		if (strlen($address) < 6) {
			throw new Exception('Email address is too short', 1);
		}
		if (strlen($address) > 254) {
			throw new Exception('Email address is too long', 2);
		}
		if (!preg_match('/^(.+)@([^@]+)$/D', $address, $m)) {
			throw new Exception('Email address is not valid', 3);
		}
		if (!isset(static::$mxdomains[$m[2]])) {
			$mx = \Poodle\DNS::getMX($m[2]);
			if (!$mx) {
				$mx = array(\Poodle\DNS::getIP($m[2]));
			}
			static::$mxdomains[$m[2]] = empty($mx[0]) ? false : $mx;
		}
		if (empty(static::$mxdomains[$m[2]])) {
			throw new Exception("The mail domain does not exist", 4);
		}
		return array(
			'local'  => $m[1],
			'domain' => $m[2],
			'mx'     => static::$mxdomains[$m[2]]
		);
	}

	/**
	 * Check MSA/MTA if mailbox exists for $address
	 * Fails when firewall/ISP blocks port 25
	 */
	public static function Mailbox($address)
	{
		$server = null;
		try {
			$result = static::address($address);
			foreach ($result['mx'] as $mx) {
				try {
					$server = new \Poodle\Mail\SMTP($mx.':25/?timeout=3');
					$server->connect();
					break;
				} catch (\Throwable $e) {
					$server = null;
				}
			}
			if ($server) {
				$server->from(\Poodle::getKernel()->CFG->mail->from);
				$server->to($address);
				$server->quit();
			} else {
				\Poodle\LOG::error('email address', $address.': connection failed (firewall?)');
			}
		} catch (\Throwable $e) {
//			$server->errno;
//			$server->error;
			\Poodle\LOG::error('email address', $address.': '.$e->getMessage());
			throw new Exception("The mailbox is not accessible", 5);
		}
		return true;
	}

}
