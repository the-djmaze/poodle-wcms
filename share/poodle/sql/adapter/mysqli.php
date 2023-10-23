<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\SQL\Adapter;

class MySQLi extends \MySQLi implements \Poodle\SQL\Interfaces\Adapter
{
	const
		ENGINE    = 'MySQL',
		TBL_QUOTE = '`';

	protected
		$debug = false;

	protected
		$cfg = array(
			'host' => null,
			'port' => null, # 3306
			'username' => null,
			'password' => null,
			'database' => null, # 1046 = no database selected / 1049 = unknown database
			'charset'  => 'utf8mb4', # dev.mysql.com/doc/refman/5.0/en/charset-connection.html
			# MySQLi advanced options
			'socket'  => null,
			'flags'   => null,
			'options' => array(), # php.net/mysqli_options
			'ssl'     => array(   # php.net/mysqli_ssl_set
				'key'  => null,
				'cert' => null,
				'ca'   => null,
				'capath' => null,
				'cipher' => null,
			),
			'storage_engine' => null,
		);

	function __construct($config)
	{
		mysqli_report(MYSQLI_REPORT_ERROR);

		if (empty($config['charset'])) {
			$config['charset'] = $this->cfg['charset'];
		}
		$this->cfg = array_merge($this->cfg, $config);
		if (preg_match('#^(.*)?:(\d+)?$#D', $this->cfg['host'], $match)) {
			$this->cfg['host'] = (empty($match[1]) ? null : $match[1]);
			$this->cfg['port'] = (empty($match[2]) ? null : $match[2]);
		}
		if (!$this->cfg['socket']) { $this->cfg['socket'] = null; }
		if (!is_int($this->cfg['flags'])) { $this->cfg['flags'] = null; }
		if (!is_array($this->cfg['options'])) { $this->cfg['options'] = array(); }

		parent::init();
		foreach ($this->cfg['options'] as $option => $value) {
			if (!parent::options($option, $value)) {
				throw new \Exception($this->error, $this->errno);
			}
		}

		$ssl = &$this->cfg['ssl'];
		if ($ssl['key'] || $ssl['cert'] || $ssl['ca'] || $ssl['capath'] || $ssl['cipher']) {
			parent::ssl_set($ssl['key'], $ssl['cert'], $ssl['ca'], $ssl['capath'], $ssl['cipher']);
		}

		$this->connect();
	}

	function __destruct()
	{
//		mysqli_report(MYSQLI_REPORT_OFF);
		parent::close();
	}

	public function get_charset()
	{
//		return parent::get_charset();
		return $this->cfg['charset'];
	}

	public function getServerInfo()
	{
		if (preg_match('/([0-9\\.]+)-(MariaDB)/i', $this->server_info, $m)) {
			return array(
				'engine' => $m[2],
				'version' => $m[1]
			);
		}
		return array(
			'engine' => $this->engine,
			'version' => $this->server_info
		);
	}

	public function connect($host='localhost', $username='', $passwd='', $dbname='', $port=3306, $socket='')
	{
		if (!parent::real_connect($this->cfg['host'], $this->cfg['username'], $this->cfg['password'], null, $this->cfg['port'], $this->cfg['socket'], $this->cfg['flags'])) {
			throw new \Poodle\SQL\Error("{$this->connect_errno}: {$this->connect_error}", \Poodle\SQL\Error::NO_CONNECTION);
		}
		$this->select_db();
		if ($this->server_version < 50503) {
			throw new \Exception("Unsupported MySQL version {$this->server_version}");
		}
		if ('utf8' === $this->cfg['charset']) {
			$this->cfg['charset'] = 'utf8mb4';
		}
		$this->set_charset();
		# dev.mysql.com/doc/refman/5.1/en/time-zone-support.html
		parent::query('SET time_zone = \'+0:00\'');
		parent::query('SET SESSION sql_mode = \'TRADITIONAL,ONLY_FULL_GROUP_BY,NO_AUTO_VALUE_ON_ZERO,PIPES_AS_CONCAT\'');
		if ($this->cfg['storage_engine']) {
			parent::query("SET storage_engine={$this->cfg['storage_engine']}");
		}
	}

	public function ping() { if (!parent::ping()) { $this->connect(); } }

	public function real_connect($h='',$u='',$pass='', $db='', $p=3306, $s='', $f=0) { $this->connect(); }

	public function select_db($dbname='')
	{
		if (!parent::select_db($this->cfg['database'])) {
			throw new \Poodle\SQL\Error("{$this->errno}: {$this->error}", \Poodle\SQL\Error::NO_DATABASE);
		}
	}
	public function dbname() { return $this->cfg['database']; }

	public function set_charset($charset='utf8mb4')
	{
		return (parent::set_charset($this->cfg['charset']) && parent::query("SET collation_connection = {$this->cfg['charset']}_bin"));
	}

	public function setDebug(bool $active)
	{
		$this->debug = $active;
		if (!$active) {
			mysqli_report(MYSQLI_REPORT_ERROR);
		}
	}

	public function query($query, $unbuffered=0)
	{
		try {
			if ($this->debug) {
				if ('S' === $query[0] && preg_match('#\\s+LIMIT\\s+(\\d+)\\s*,\\s*(\\d+)#si', $query, $m)) {
					throw new \Poodle\SQL\Exception("MySQL LIMIT syntax not allowed, use ISO syntax 'LIMIT {$m[2]} OFFSET {$m[1]}'");
				}
				// only match backtick in column names, not inside data
				if (preg_match('#`[a-z0-9_]+`#i', preg_replace('#\'[^\n\'\\\\]*(?:\\\\.[^\n\'\\\\]*)*\'#s','',$query))) {
					throw new \Poodle\SQL\Exception('MySQL backtick syntax not allowed, use the ISO syntax');
				}
				mysqli_report(preg_match('#\\s+WHERE\\s+#', $query) ? MYSQLI_REPORT_ALL : MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
			}
/*
			if ('S' === $query[0]) {
				# PgSQL
				$query = preg_replace('#([^\\s,]+)\\s*=\\s*ANY\\s*\\(\\s*STRING_TO_ARRAY\\s*\\((.+),\\s*\',\'\\)\\)#i', 'FIND_IN_SET($1, $2)', $query);
				# PgSQL POSIX regular expression
				$query = preg_replace('#\\s+~\\s+\'#', ' REGEXP \'', $query);
				$query = preg_replace('#\\s+!~\\s+\'#', ' NOT REGEXP \'', $query);
				# SQL99 regular expression
				$query = preg_replace('#\\s+SIMILAR\\s+TO\\s+\'#', ' REGEXP \'', $query);
			}
*/
			$result = $this->real_query($query, $unbuffered);
		} catch (\mysqli_sql_exception $e) {
			if (strpos($e->getMessage(), 'index used') === false) {
				throw new \Poodle\SQL\Exception($e->getMessage(), $e->getCode(), $query);
			}
			\Poodle\Debugger::trigger($e->getMessage(), dirname(__DIR__));
			if ('S' === $query[0]) {
				mysqli_report(MYSQLI_REPORT_OFF);
				$result = $this->real_query($query, $unbuffered);
				mysqli_report(MYSQLI_REPORT_ERROR);
			} else {
				$result = true;
			}
		}
		if ($this->field_count) {
			# SELECT, SHOW, DESCRIBE
			if ($unbuffered) {
				return new MySQLi_UnbufferedResult($this);
			}
			return new MySQLi_Result($this);
		}
		# INSERT, UPDATE, DELETE
		return $result;
	}

	public function real_query($query)
	{
		try {
			if (!parent::real_query($query)) {
				throw new \Poodle\SQL\Exception($this->error, $this->errno, $query);
			}
			return true;
		} catch (\Throwable $e) {
			// 'Incorrect string value' error can be when database is not utf8mb4,
			// just convert them to HTML Entities and try again
			if (strpos($e->getMessage(), 'Incorrect string value') !== 0) {
				throw $e;
			}
			return parent::real_query(preg_replace_callback(
				'#(\\xF0[\\x90-\\xBF][\\x80-\\xBF]{2}|[\\xF1-\\xF3][\\x80-\\xBF]{3}|\\xF4[\\x80-\\x8F][\\x80-\\xBF]{2})+#',
				function($m){return mb_convert_encoding($m[0],'HTML-ENTITIES','UTF-8');},
				$query
			));
		}
	}

	public function showTables($prefix)
	{
		$q = 'SHOW TABLES';
		if ($prefix) $q .= " LIKE '{$prefix}%'";
		return $this->query($q);
	}

	public function quoteBinary($data)    { return (isset($data[0]) && is_string($data)) ? '0x'.bin2hex($data) : "''"; }
	public function escapeBinary($data)   { return (isset($data[0]) && is_string($data)) ? '0x'.bin2hex($data) : ''; }
	public function unescapeBinary($data) { return $data; }

	public function quoteString($data)    { return "'".parent::real_escape_string($data)."'"; }
	public function escape_string($data)  { return parent::real_escape_string($data); }

	public function insert_id($idfield)   { return $this->insert_id; }

	public function begin() { return $this->real_query('BEGIN'); } # START TRANSACTION

	public function search(array $fields, &$text)
	{
		static
			$ft_max_word_len = 0,
			$ft_min_word_len = 0,
			$innodb_ft_max_token_size = 0,
			$innodb_ft_min_token_size = 0;
		$text = preg_replace('/\\s+&\\s+!/', ' -', $text); // NOT
		$text = preg_replace('/\\s+&\\s+/', ' +', $text);  // AND
		$text = preg_replace('/\\s+|\\s+/', ' ', $text);   // OR
		/*
		 * Short words are ignored, the default minimum length is 4 characters. You can change the min and max word length with the variables ft_min_word_len and ft_max_word_len
		 * Words called stopwords are ignored, you can specify your own stopwords, but default words include the, have, some - see default stopwords list.
		 * You can disable stopwords by setting the variable ft_stopword_file to an empty string.
		 * http://dev.mysql.com/doc/refman/5.1/en/fulltext-stopwords.html
		 */
		if (!$ft_min_word_len) {
			mysqli_report(MYSQLI_REPORT_OFF);
			if ($res = parent::query('SHOW VARIABLES WHERE Variable_name LIKE \'%_word_len\' OR Variable_name LIKE \'%_token_size\'')) {
				while ($r = $res->fetch_row()) {
					${$r[0]} = (int)$r[1];
				}
				$res->free();
			}
			mysqli_report(MYSQLI_REPORT_ERROR);
			$ft_min_word_len = max($ft_min_word_len, 3);
		}
		$text = \Poodle\Unicode::as_search_txt($text);
		if (preg_match_all('#[^\s]{'.$ft_min_word_len.',}#', $text, $match)) {
			$text = $match[0];
		}
		return 'MATCH ('.implode(',', $fields).') AGAINST (\''.parent::real_escape_string(implode(' ', $text)).'\' IN BOOLEAN MODE)';
	}

	public function createLock(string $name, int $timeout = 0)
	{
		return $this->real_query("SELECT GET_LOCK('" . parent::real_escape_string($name) . "', {$timeout})");
	}

	public function releaseLock(string $name)
	{
		return $this->real_query("DO RELEASE_LOCK('" . parent::real_escape_string($name) . "')");
	}

	public function upsert($table, array $insert_data, array $update_data, array $update_where)
	{
		$insert_data = array_merge($update_data, $insert_data);
		foreach ($update_data as $k => &$v) {
			if ($v instanceof \Poodle\SQL\ValueRaw) {
				$v = "{$k}={$v}";
			} else {
				$v = "{$k}=VALUES({$k})";
			}
		}
		return $this->query("INSERT INTO {$table} (".implode(',',array_keys($insert_data)).")
		VALUES (".implode(',',$insert_data).")
		ON DUPLICATE KEY UPDATE ".implode(', ', $update_data));
	}
}

class MySQLi_UnbufferedResult extends \MySQLi_Result implements \Poodle\SQL\Interfaces\Result
{
	function __construct(MySQLi $obj) { parent::__construct($obj); }

	private static
		$ints   = array(1=>1,2=>2,3=>3,8=>8,9=>9),
		$floats = array(4=>4,5=>5,246=>246);
//		$dates  = array(7=>7,10=>10,11=>11,12=>12,14=>14);
/**		http://php.net/manual/en/mysqli.constants.php
		246 = MYSQLI_TYPE_NEWDECIMAL
		  1 = MYSQLI_TYPE_TINY        TINYINT
		  2 = MYSQLI_TYPE_SHORT       SMALLINT
		  3 = MYSQLI_TYPE_LONG        INT
		  4 = MYSQLI_TYPE_FLOAT
		  5 = MYSQLI_TYPE_DOUBLE
		  6 = MYSQLI_TYPE_NULL
		  7 = MYSQLI_TYPE_TIMESTAMP
		  8 = MYSQLI_TYPE_LONGLONG    BIGINT
		  9 = MYSQLI_TYPE_INT24       MEDIUMINT
		 10 = MYSQLI_TYPE_DATE
		 11 = MYSQLI_TYPE_TIME
		 12 = MYSQLI_TYPE_DATETIME
		 13 = MYSQLI_TYPE_YEAR
		 14 = MYSQLI_TYPE_NEWDATE
		 16 = MYSQLI_TYPE_BIT
		MYSQLI_TYPE_INTERVAL
		MYSQLI_TYPE_ENUM
		MYSQLI_TYPE_SET
		MYSQLI_TYPE_TINY_BLOB
		MYSQLI_TYPE_MEDIUM_BLOB
		MYSQLI_TYPE_LONG_BLOB
		MYSQLI_TYPE_BLOB
		253 = MYSQLI_TYPE_VAR_STRING  VARCHAR
		254 = MYSQLI_TYPE_STRING      CHAR or BINARY
		MYSQLI_TYPE_CHAR
		MYSQLI_TYPE_GEOMETRY
*/

	protected
		$object_name = null,
		$object_params = null;

	private function type_cast($row, $resulttype)
	{
		if ($row) {
			$fields = $this->fetch_fields();
			foreach ($fields as $i => $field) {
				if (isset(self::$floats[$field->type])) {
					if ($resulttype & 1) { $row[$field->name] = (float)$row[$field->name]; }
					if ($resulttype & 2) { $row[$i] = (float)$row[$i]; }
				}
				else if (isset(self::$ints[$field->type])) {
					if ($resulttype & 1) { $row[$field->name] = (int)$row[$field->name]; }
					if ($resulttype & 2) { $row[$i] = (int)$row[$i]; }
				}
//				else if (isset($dates[$field->type])) { $row[$field->name] = new \DateTime($row[$field->name], new \DateTimeZone('UTC')); }
			}
		}
		return $row;
	}

	public function fetch_array($type_cast=false)
	{
		return $type_cast ? $this->type_cast(parent::fetch_array(MYSQLI_BOTH), 3) : parent::fetch_array(MYSQLI_BOTH);
	}

	public function fetch_assoc($type_cast=false)
	{
		return $type_cast ? $this->type_cast(parent::fetch_assoc(), 1) : parent::fetch_assoc();
	}

	/**
	 * $class_name methods are called in this order:
	 *     __set() ONLY when property does not exist
	 *     __construct()
	 */
	public function fetch_object($class_name=null, array $params=null)
	{
		$class_name = $class_name ?: $this->object_name ?: 'stdClass';
		$params = $params ?: $this->object_params;
		return $params
			? parent::fetch_object($class_name, $params)
			: parent::fetch_object($class_name);
	}

	public function fetch_row($type_cast=false)
	{
		return $type_cast ? $this->type_cast(parent::fetch_row(), 2) : parent::fetch_row();
	}

	public function fetch_all($resulttype=\Poodle\SQL::ASSOC, $type_cast=false)
	{
//		if (!$type_cast && method_exists('MySQLi_Result', 'fetch_all')) { return parent::fetch_all($resulttype); } # MYSQLI_NUM | MYSQLI_ASSOC | MYSQLI_BOTH
		$rows = array();
		if ($resulttype === \Poodle\SQL::BOTH) { while ($row = $this->fetch_array($type_cast)) $rows[] = $row; }
		else if ($resulttype === \Poodle\SQL::NUM) { while ($row = $this->fetch_row($type_cast)) $rows[] = $row; }
		else { while ($row = $this->fetch_assoc($type_cast)) $rows[] = $row; }
		return $rows;
	}

	public function setFetchObjectParams($class_name=null, array $params=array())
	{
		$this->object_name = $class_name;
		$this->object_params = $params;
	}

	public function jsonSerialize()
	{
		$data = array();
		while ($row = $this->fetch_assoc(true)) {
			$data[] = $row;
		}
		return $data;
	}

	# ArrayAccess can only be used with buffered results
	public function offsetExists($k)  { return false; }
	public function offsetGet($k)     { return null; }
	public function offsetSet($k, $v) { return; }
	public function offsetUnset($k)   { return; }
	# Countable
	public function count()   { return $this->num_rows; }
}

class MySQLi_Result extends MySQLi_UnbufferedResult implements \Poodle\SQL\Interfaces\Result
{
	# ArrayAccess
	public function offsetExists($k)  { return (ctype_digit((string)$k) && $k >= 0 && $k < $this->num_rows); }
	public function offsetGet($k)     { return ($this->data_seek($k) ? $this->fetch_assoc() : null); }
}
