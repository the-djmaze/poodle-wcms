<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Crypt
{
	protected
		$compression = null;

	protected static
		$compressors = array(
			'zlib_rfc1950' => array(
				'encode' => array('zlib_encode','args' => array(ZLIB_ENCODING_DEFLATE, 9)),
				'decode' => array('zlib_decode'),
				'desc'   => 'ZLib, RFC 1950'
			),
			'zlib_rfc1951' => array(
				'encode' => array('zlib_encode','args' => array(ZLIB_ENCODING_RAW, 9)),
				'decode' => array('zlib_decode'),
				'desc'   => 'ZLib deflate, RFC 1951'
			),
			'zlib_rfc1952' => array(
				'encode' => array('zlib_encode','args' => array(ZLIB_ENCODING_GZIP, 9)),
				'decode' => array('zlib_decode'),
				'desc'   => 'Gzip, RFC 1952'
			),
			'lzf' => array(
				'encode' => array('lzf_compress'),
				'decode' => array('lzf_decompress'),
				'desc'   => 'LZF'
			),
			'bz' => array(
				'encode' => array('bzcompress', 'args' => array(9, 30)),
				'decode' => array('bzdecompress'),
				'desc'   => 'BZip2, using work factor 30'
			),
		);

	function __construct(array $options = array())
	{
		foreach ($options as $k => $v) {
			if (property_exists($this, $k)) {
				$this->$k = $options[$k];
			}
		}
	}

	abstract public function encrypt(string &$data) : string;
	abstract public function decrypt(string &$encrypted) : string;

	public static function listCompressors() : array
	{
		$list = array(''=>'none');
		foreach (self::$compressors as $k => $v) {
			if (function_exists($v['encode'][0]) && function_exists($v['decode'][0])) {
				$list[$k] = $v['desc'];
			}
		}
		if (isset($list['lzf'])) {
			$list['lzf'] .= (lzf_optimized_for() ? ', optimized for speed' : ', optimized for compression');
		}
		return $list;
	}

	protected function compressor(string &$data, bool $decode=false) : string
	{
		if (!$this->compression) {
			return $data;
		}
		if (!isset(self::$compressors[$this->compression])) {
			throw new \Exception("Unknown compression: {$this->compression}");
		}
		$compressor = self::$compressors[$this->compression][$decode?'decode':'encode'];
		if (!function_exists($compressor[0])) {
			throw new \Exception("Unsupported compression: {$this->compression}");
		}
		if (isset($compressor['args'])) {
			$args = $compressor['args'];
			if (2 === count($args)) {
				return $compressor[0]($data, $args[0], $args[1]);
			}
			return $compressor[0]($data, $args[0]);
		}
		return $compressor[0]($data);
	}

}
