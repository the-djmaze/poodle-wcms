<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Identity;

abstract class Validate
{

	public static function passphrase($pass, $nickname, array &$errors, $confirm=null)
	{
		$K = \Poodle::getKernel();
		if (strlen($pass) < $K->CFG->identity->passwd_minlength) {
			$errors[] = sprintf($K->L10N['%s is too short.'], $K->L10N['Passphrase']);
		} else if (is_string($confirm) && $pass !== $confirm) {
			$errors[] = $K->L10N['Passphrase mismatch'];
//		} else if (metaphone($pass) === metaphone($nickname)) {
//		} else if (soundex($pass) === soundex($nickname)) {
		} else if ($pass === $nickname) {
			$errors[] = $K->L10N['Nickname and Passphrase are the same'];
		} else if (levenshtein($pass, $nickname) < 4) {
			$errors[] = $K->L10N['Nickname and Passphrase are almost identical'];
		}
		return empty($errors);
	}

	public static function nickname($nickname, array &$errors)
	{
		$K = \Poodle::getKernel();
		$nickname = trim($nickname);
		$nick_deny = $K->CFG->identity->nick_deny;
		if (!$nickname || mb_strlen($nickname) < $K->CFG->identity->nick_minlength) {
			$errors[] = sprintf($K->L10N['%s is too short.'], $K->L10N['Nickname']);
		} else if (preg_match('#(&|>|<)#', $nickname, $match)) {
			$errors[] = sprintf($K->L10N['%s contains disallowed character %s.'], $K->L10N['Nickname'], $match[1]);
		} else if ($nick_deny && preg_match("#({$nick_deny})#i", $nickname, $match)) {
			$errors[] = sprintf($K->L10N['%s contains disallowed word %s.'], $K->L10N['Nickname'], $match[1]);
		}
		return empty($errors);
	}

	public static function email($email, array &$errors)
	{
		$K = \Poodle::getKernel();
		$email = \Poodle\Input::lcEmail($email);
		if (!$email) {
			$errors[] = sprintf($K->L10N['Provide a valid %s'], $K->L10N['Email address']);
			return false;
		}
		try {
			\Poodle\Security::checkEmail($email);
			$email = $K->SQL->quote($email);
			if ($K->SQL->count('users', "user_email = {$email}") ||
			    $K->SQL->count('users_request', "user_email = {$email}"))
			{
				$errors[] = sprintf($K->L10N['%s is already in use.'], $K->L10N['Email address']);
			}
		} catch (\Throwable $e) {
			$errors[] = $e->getMessage();
		}
		return empty($errors);
	}

}
