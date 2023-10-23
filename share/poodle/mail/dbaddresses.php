<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Mail;

class DBAddresses implements \IteratorAggregate, \ArrayAccess, \Countable
{
	protected $email_rel_name;
	protected $email_rel_id;
	protected $email_addresses = array();

	protected $errno = 0;
	protected $error = null;

	function __construct($rel_name, $rel_id)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$this->email_rel_name = strtolower($rel_name);
		$this->email_rel_id   = (int)$rel_id;
		$result = $SQL->query('SELECT
			email_address_id id,
			email_address_value value,
			email_address_primary is_primary,
			email_address_reply_to is_reply_to,
			email_address_invalid invalid,
			email_address_opt_out opt_out
		FROM '.$SQL->TBL->email_addresses_rel.' ear
		INNER JOIN '.$SQL->TBL->email_addresses.' ea USING (email_address_id)
		WHERE deleted=0
		  AND ear_name='.$SQL->quote($this->email_rel_name).'
		  AND ear_id='.$this->email_rel_id.'
		ORDER BY email_address_primary DESC, email_address_invalid ASC, email_address_value ASC');
		while ($row = $result->fetch_assoc())
		{
			$this->email_addresses[strtolower($row['value'])] = new DBAddress($this->email_rel_name, $this->email_rel_id, $row);
		}
	}
	function __destruct() {}

	function __get($key)
	{
		switch ($key)
		{
		case 'errno': return $this->errno;
		case 'error': return $this->error;
		case 'primary':
		case 'reply_to':
			$key = 'is_'.$key;
			foreach ($this->email_addresses as $address) { if ($address[$key]) { return $address['value']; } }
		}
		return null;
	}
	protected function set_error($no, $msg = null)
	{
		$this->errno = $no;
		$this->error = $msg;
		return false;
	}

	public function add_address($addr, $primary=false, $replyTo=false, $invalid=false, $optOut=false)
	{
		$addr = DBAddress::trim($addr);
		if (!$addr || !preg_match('#^\w+([\'\.\-\+]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,4})+$#D', $addr))
		{
			return $this->set_error(1, 'Email address "'.$addr.'" did not validate');
		}
		$addr_lc = strtolower($addr);
		$add_address = null;
		foreach ($this->email_addresses as $k => $address)
		{
			if ($addr_lc === $k) { $add_address = $address; }
			// We should only have one primary. If we are adding a primary but
			// if we find an existing primary, reset this one's primary flag.
			else if ($primary && $address->primary) { $address->primary = false; }
		}
		if (!$add_address)
		{
			$add_address = new DBAddress($this->email_rel_name, $this->email_rel_id);
			$this->email_addresses[$addr_lc] = $add_address;
		}
		$add_address->value    = $addr;
		$add_address->primary  = $primary;
		$add_address->reply_to = $replyTo;
		$add_address->invalid  = $invalid;
		$add_address->opt_out  = $optOut;
		return true;
	}

	public function delete_address($addr)
	{
		$addr = strtolower(DBAddress::trim($addr));
		if (isset($this->email_addresses[$addr]))
		{
			$this->email_addresses[$addr]->delete();
			unset($this->email_addresses[$addr]);
			return true;
		}
		return false;
	}

	public function save()
	{
		# build fresh list?
		//$SQL = \Poodle::getKernel()->SQL;
		//$SQL->exec('DELETE FROM {$SQL->TBL->email_addresses_rel} WHERE ear_name='.$SQL->quote($this->email_rel_name).' AND ear_id='.$this->email_rel_id);
		# now store each address
		foreach ($this->email_addresses as $address) { $address->save(); }
	}

	public static function get_primary($rel_name, $rel_id)  { return self::get_address($rel_name, $rel_id, 'email_address_primary'); }
	public static function get_reply_to($rel_name, $rel_id) { return self::get_address($rel_name, $rel_id, 'email_address_reply_to'); }
	private static function get_address($rel_name, $rel_id, $order_by)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$address = $SQL->uFetchRow('SELECT
			email_address
		FROM '.$SQL->TBL->email_addresses_rel.' AS ear
		INNER JOIN '.$SQL->TBL->email_addresses.' AS ea USING (email_address_id)
		WHERE deleted=0
		  AND ear_name='.$SQL->quote($rel_name).'
		  AND ear_id='.(int)$rel_id.'
		ORDER BY '.$order_by.' DESC');
		return empty($address[0]) ? null : $address[0];
	}
/*
	public static function cleanup()
	{
		$SQL = \Poodle::getKernel()->SQL;
		$result = $SQL->query('SELECT COUNT(*) AS hits, ea.email_address_id
		FROM {$SQL->TBL->email_addresses} AS ea
		LEFT JOIN {$SQL->TBL->email_addresses_rel} AS ear USING (email_address_id)
		GROUP BY email_address_id
		HAVING hits=0
		ORDER BY 1 ASC');
		while ($row = $result->fetch_row())
		{
			if ($row[0]) { break; }
			$SQL->exec('DELETE FROM {$SQL->TBL->email_addresses} WHERE email_address_id='.$row[0]);
		}
	}
*/
/*
	# Iterator
	public function key()     { return key($this->email_addresses); }
	public function current() { return current($this->email_addresses); }
	public function next()    { return next($this->email_addresses); }
	public function rewind()  { return reset($this->email_addresses); }
	public function valid()   { return (null !== key($this->email_addresses)); }
*/
	# IteratorAggregate
	public function getIterator() { return new \ArrayIterator($this->email_addresses); }
	# ArrayAccess
	public function offsetExists($key) { return array_key_exists($key, $this->email_addresses); }
	public function offsetGet($key)    { return isset($this->email_addresses[$key]) ? $this->email_addresses[$key] : null; }
	public function offsetSet($key, $val)
	{
		if (!is_array($val)) { throw new \Exception('Poodle\\Mail\\DBAddresses: [ '.$key.' ] value must be of type array, or use the add_address() method instead'); }
		$this->add_address($key, !empty($val['primary']), !empty($val['reply_to']), !empty($val['invalid']), !empty($val['opt_out']));
	}
	public function offsetUnset($key)  { $this->delete_address($key); }
	# Countable
	public function count()   { return count($this->email_addresses); }
}

class DBAddress implements \ArrayAccess
{
	protected $id;
	protected $rel_id;
	protected $rel_name;
	protected $data = array(
		'value'=>null,
		'primary'=>null,
		'reply_to'=>null,
		'invalid'=>null,
		'opt_out'=>null
	);
	function __construct($rel_name, $rel_id, array $address=null)
	{
		$this->rel_id = (int)$rel_id;
		$this->rel_name = $rel_name;
		if ($address)
		{
			foreach ($address as $k => &$v)
			{
				if ('id' === $k) $this->id = (int)$v;
				else self::__set($k, $v);
			}
		}
	}
	function __get($key)  { return isset($this->data[$key]) ? $this->data[$key] : null; }
	function __set($key, $val)
	{
		if (array_key_exists($key, $this->data))
		{
			switch ($key)
			{
			case 'value':
				$val = self::trim($val);
				break;
			case 'invalid':
			case 'opt_out':
			case 'primary':
			case 'reply_to':
				$val = (bool)$val;
			}
			$this->data[$key] = $val;
		}
	}
	function __isset($key) { return isset($this->data[$key]); }
	function __toString() { return $this->data['value']; }

	public function save()
	{
		$SQL = \Poodle::getKernel()->SQL;
		# The local-part of a mailbox MUST BE treated as case sensitive.
		# We ignore this at search, due to most servers ignoring
		# case-sensitivity and just rely on valid input of the visitor
		$id = $SQL->uFetchRow('SELECT email_address_id FROM '.$SQL->TBL->email_addresses.' WHERE LOWER(email_address_value)=LOWER('.$SQL->quote($this->data['value']).')');
		$id = empty($id[0]) ? null : (int)$id[0];
		if (!$id)
		{
			$id = $SQL->insert('email_addresses', array(
				'email_address_value'   => $this->data['value'],
				'email_address_invalid' => $this->data['invalid'],
				'email_address_opt_out' => $this->data['opt_out']
			), 'email_address_id');
		}
		else
		{
			$SQL->update('email_addresses', array(
				'email_address_value'   => $this->data['value'],
				'email_address_invalid' => $this->data['invalid'],
				'email_address_opt_out' => $this->data['opt_out'],
				'deleted' => 0
			), 'email_address_id='.$id);
		}
		$this->delete(); # cleanup
		$SQL->insert('email_addresses_rel', array(
			'ear_name' => $this->rel_name,
			'ear_id'   => $this->rel_id,
			'email_address_id'       => $id,
			'email_address_primary'  => $this->data['primary'],
			'email_address_reply_to' => $this->data['reply_to'],
		));
		$this->id = $id;
	}
	public function delete()
	{
		if ($this->id)
		{
			$SQL = \Poodle::getKernel()->SQL;
			$SQL->exec('DELETE FROM '.$SQL->TBL->email_addresses_rel.' WHERE ear_name='.$SQL->quote($this->rel_name).' AND ear_id='.$this->rel_id.' AND email_address_id='.$this->id);
		}
	}

	# http://tools.ietf.org/html/rfc2821#section-2.4
	# The local-part of a mailbox MUST BE treated as case sensitive.
	public static function trim($addr)
	{
		if (preg_match('#^(.*)@([^@]*)$#Ds', trim(preg_replace('#^.*<([^>]+)>.*#', '$1', $addr)), $addr))
		{
			return $addr[1].'@'.strtolower($addr[2]);
		}
	}

	# ArrayAccess
	public function offsetExists($key) { return $this->__isset($key); }
	public function offsetGet($key)    { return $this->__get($key); }
	public function offsetSet($key, $val) { $this->__set($key, $val); }
	public function offsetUnset($key)     { $this->__set($key, null); }
}
