<?php

namespace Poodle\Info;

abstract class System
{

	public static function directories($site = null, $FTP = null)
	{
		$site = $site ?: $_SERVER['HTTP_HOST'];
		$dirs = array(
		//	'media'           => array(0, \Poodle::$DIR_MEDIA),
			'media/archives'  => array(0, \Poodle::$DIR_MEDIA.'archives'),
			'media/audio'     => array(0, \Poodle::$DIR_MEDIA.'audio'),
			'media/documents' => array(0, \Poodle::$DIR_MEDIA.'documents'),
			'media/images'    => array(0, \Poodle::$DIR_MEDIA.'images'),
			'media/videos'    => array(0, \Poodle::$DIR_MEDIA.'videos'),
			POODLE_HOSTS_PATH.$site          => array(0, POODLE_HOSTS_PATH.$site),
			POODLE_HOSTS_PATH.$site.'/cache' => array(0, POODLE_HOSTS_PATH.$site.'/cache'),
		);
//		foreach (glob(\Poodle::$DIR_MEDIA.'*', GLOB_ONLYDIR) as $dir) { $dirs[str_replace(\Poodle::$DIR_BASE,'',$dir)] = array(0, $dir); }
//		if (HAS_CONFIG) { unset($dirs[$core_dir]); }
		foreach ($dirs as $key => &$value) {
			$dir = $value[1];
			while (!is_dir($dir)) {
				$up = dirname($dir);
				if (2 > strlen($up)) { break; }
				$dir = $up;
			}
			if ($FTP && !is_writable($dir)) {
				$FTP->chmod($ftp_path.'/'.$dir, 0777);
			}
			if ($dir != $value[1] && is_writable($dir)) {
				$m = umask(0);
				mkdir($value[1], 0777, true);
				umask($m);
			}
			$value[2] = is_writable($value[1]);
		}
		return $dirs;
/*
		# Check writable directories
		$dirs = array(
			array(0, 'tpl/'),
			array(0, 'tpl/default/images/'),
		);
		$dir = dirname(dirname(dirname(__DIR__)));
		$value = is_writable($dir);
		$K->OUT->comp_dirs = array(
			array(
				'TITLE' => $dir,
				'INFO'  => $K->OUT->L10N['info_libs_dir'],
				'CLASS' => ($value?'ok':'fail'),
				'STATUS' => $K->OUT->L10N['_access'][$value],
			)
		);
		foreach ($dirs as $key => &$value) {
			$value[2] = is_writable(\Poodle::$DIR_BASE.$value[1]);
			$K->OUT->FATAL_ERROR |= ($value[0] && !$value[2]);
			$key = substr($value[1], 0, -1);
			$K->OUT->comp_dirs[] = array(
				'TITLE' => $key,
				'INFO'  => $K->OUT->L10N['info_'.$key],
				'CLASS' => ($value[2]?'ok':'fail'),
				'STATUS' => $K->OUT->L10N['_access'][$value[2]],
			);
		}
*/
	}

	public static function php_extensions()
	{
		/*
		Fixed  : date, filter, Reflection, SPL, standard
		Default: session, SimpleXML
		expat  : wddx, xml
		libxml : xmlreader, xmlwriter
		others : bcmath, calendar, com_dotnet, ftp, hash, iconv, json, odbc, tokenizer
		*/
		$ext = array(
			'bcompiler' => array(0, 'bcompiler', 'bytecode Compiler'),
			'bz2'       => array(0, 'bzip2', 'Bzip2'),
			'ctype'     => array(0, 'ctype', 'ctype'),                                  // enabled by default
			'dom'       => array(1, 'dom', 'DOM'),                                      // enabled by default
			'fileinfo'  => array(0, 'fileinfo', 'Fileinfo'),                            // 5.3 enabled by default
			'gd'        => array(0, 'image', 'GD2'),
			'gmagick'   => array(0, 'gmagick', 'Gmagick'),
			'imagick'   => array(0, 'imagick', 'Imagick'),
			'imap'      => array(0, 'imap', 'IMAP'),
			'libxml'    => array(0, 'libxml', 'libxml'),                                // enabled by default
			'lzf'       => array(0, 'lzf', 'LZF'),
			'mbstring'  => array(0, 'mbstring', 'Multibyte Strings'),
			'memcached' => array(0, 'memcached', 'Memcached'),
			'openssl'   => array(0, 'openssl', 'OpenSSL'),
		//	'pcre'      => array(1, 'pcre', 'Perl Compatible Regular Expressions'),
			'posix'     => array(0, 'posix', 'POSIX Functions'),                        // enabled by default
			'rar'       => array(0, 'rar', 'Rar'),
			'tidy'      => array(0, 'tidy', 'Tidy'),
			'xml'       => array(1, 'xml', 'XML'),                                      // enabled by default
			'xsl'       => array(0, 'xsl', 'XSL'),                                      // enabled by default
			'xxtea'     => array(0, 'pecl-xxtea', 'xxtea'),
			'zip'       => array(0, 'zip', 'Zip'),
			'zlib'      => array(0, 'zlib', 'Zlib'),
			'uuid'      => array(0, 'pecl-uuid', 'UUID'),
		);
		# Extensions not available on Windows platforms.
		if ('\\' === DIRECTORY_SEPARATOR) { unset($ext['posix']); }
		foreach ($ext as $key => &$value) {
			$value[3] = extension_loaded($key);
		}
		$ext['gd'][2] .= ' with' . (is_callable('imagewebp') ? '' : 'out') . ' WebP';
		return $ext;
	}

}
