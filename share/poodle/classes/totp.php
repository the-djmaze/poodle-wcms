<?php
/*
	Dragonfly™ CMS, Copyright © since 2010
	http://dragonflycms.org

	Dragonfly CMS is released under the terms and conditions
	of the GNU GPL version 2 or any later version

	RFC 6238
	As used by Google Authenticator
*/

namespace Poodle;

abstract class TOTP
{

	public static function createSecret(int $length = 16)
	{
		return Base32::random($length);
	}

	public static function getUri(string $name, string $secret, string $issuer = '')
	{
		$name = rawurlencode($name);
		if ($issuer) {
			$issuer = rawurlencode($issuer);
			return "otpauth://totp/{$issuer}:{$name}?secret={$secret}&issuer={$issuer}";
		}
		return "otpauth://totp/{$name}?secret={$secret}";
	}

	public static function getQRCode(string $name, string $secret, string $issuer = '')
	{
		return QRCode::getMinimumQRCode(
			static::getUri($name, $secret, $issuer),
			QRCode::ERROR_CORRECT_LEVEL_M
		);
	}

	/**
	 * Check if the code is correct. This will accept codes starting
	 * from $discrepancy*30sec ago to $discrepancy*30sec from now
	 */
	public static function verifyCode(string $code, string $secret, int $discrepancy = 1, int $digits = 6) : bool
	{
		if ($key = Base32::decode($secret)) {
			$digits = (8 == $digits) ? 8 : 6;
			$modulo = pow(10, $digits);
			$timeSlice = floor(time() / 30);
			for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
				$value = static::generateCode($key, $modulo, $timeSlice + $i, $digits);
				if (hash_equals($value, $code)) {
					return true;
				}
			}
		}
		return false;
	}

	public static function getCurrentCode(string $secret, int $digits = 6) : string
	{
		return static::generateCode(
			Base32::decode($secret),
			pow(10, 8),
			floor(time() / 30),
			$digits);
	}

	protected static function generateCode(string $key, int $modulo, int $timeSlice, int $digits = 6) : string
	{
		// Pack time into binary string and hash it with users secret key
		$hm = Hash::hmac('SHA1', "\x00\x00\x00\x00".pack('N*', $timeSlice), $key, true);
		// Unpak 4 bytes of the result, use last nipple of result as index/offset
		$value = unpack('N', substr($hm, (ord(substr($hm, -1)) & 0x0F), 4));
		// Only 32 bits
		$value = $value[1] & 0x7FFFFFFF;
		return str_pad($value % $modulo, $digits, '0', STR_PAD_LEFT);
	}

}
