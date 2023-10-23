<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Sortorder path items are split with a '|' (pipe) character.
	This character is used on purpose as it is the last of the ANSI charset
	and therefore the "ORDER BY" and sorting goes perfectly correct.
	Example:
		1
		1|0
		1|1|0
		1|1|1
*/

namespace Poodle;

/**
 * http://docs.embarcadero.com/products/rad_studio/delphiAndcpp2009/HelpUpdate2/EN/html/delphivclwin32/ComCtrls_TCustomTreeView.html
 */
class Menu extends \Poodle\SQL\Record
{
	public
		$name     = '',
		$cssclass = '';

	protected
		$id       = 0,
		$items    = null; // Menu_Items

	protected
		$sql_table = 'menus',
		$sql_id_field = 'menu_id',
		$sql_field_map = array(
			'menu_id' => 'id',
			'menu_name' => 'name',
			'menu_cssclass' => 'cssclass',
		);

	function __construct($id=0)
	{
		$this->items = new Menu_Items();

		if ($id && (is_int($id) || ctype_digit($id))) {
			if (!$this->sqlInitRecord($id))
			{
				throw new \Exception("No menu found with ID: {$id}");
			}
			else
			{
				$SQL = \Poodle::getKernel()->SQL;
				$qr = $SQL->query("SELECT
					mitem_id id,
					/*mitem_parent_id parent_id,*/
					mitem_flags flags,
					mitem_label label,
					mitem_uri uri,
					mitem_cssclass cssclass,
					mitem_image image,
					mitem_sortpath sortpath
				FROM {$SQL->TBL->menus_items}
				WHERE menu_id={$this->id} AND mitem_flags>0
				ORDER BY mitem_sortpath");
				$parents = array();
				while ($r = $qr->fetch_assoc())
				{
					$level = substr_count($r['sortpath'],'|');
					$item  = new Menu_Item();
					$item->data = $r;
					if (!$level || $parents[$level-1]) {
						$parents[$level] = $item;
						if ($level) {
							$parents[$level-1]->append($item);
						} else {
							$this->append($item);
						}
					}
				}
			}
		}
	}

	function __get($k)
	{
		if (property_exists($this, $k)) { return $this->$k; }
		trigger_error("Property {$k} does not exist");
	}

	public static function factory($id)
	{
		return new Menu($id);
	}

	public function append($item=null)
	{
		return $this->items->append($item);
	}

	public function findById($id)
	{
		return $id ? $this->items->findById($id) : null;
	}

	public function save()
	{
		if ($this->sqlSaveRecord()) {
			$this->saveItems($this->items);
		}
	}

	protected function saveItems($items, $sortpath='')
	{
		$i = 1;
		foreach ($items as $item) {
			if ($item) {
				$path = $sortpath.str_pad($i++,3,'0',STR_PAD_LEFT);
				$tbl  = \Poodle::getKernel()->SQL->TBL->menus_items;
				$data = array(
					'menu_id'         => $this->id,
					'mitem_parent_id' => ($item->parent instanceof Menu_Item) ? (int)$item->parent->id : 0,
					'mitem_flags'     => $item->flags,
					'mitem_label'     => $item->label,
					'mitem_uri'       => $item->uri,
					'mitem_cssclass'  => $item->cssclass,
					'mitem_image'     => $item->image,
					'mitem_sortpath'  => $path,
				);
				if ($item->id) {
					$tbl->update($data, "mitem_id={$item->id}");
				} else {
					$item->id = $tbl->insert($data, 'mitem_id');
				}
				// Save children
				$this->saveItems($item->children, $path.'|');
			}
		}
	}

}

class Menu_Items extends \ArrayIterator
{
	public function append($item=null)
	{
		if (!$item) {
			$item = new Menu_Item();
		}
		if ($item instanceof Menu_Item) {
			$item->parent = $this;
			parent::append($item);
			return $item;
		}
	}

	public function offsetSet($i, $v)
	{
		if ($v instanceof Menu_Item) {
			$v->parent = $this;
			parent::offsetSet($i, $v);
		} else {
			trigger_error('Value is not a Menu_Item');
		}
	}

	public function hasChildren() { return 0<$this->count(); }

	public function findById($id)
	{
		$found = null;
		if ($id) {
			foreach ($this as $item) {
				$found = $item->findById($id);
				if ($found) break;
			}
		}
		return $found;
	}

	public function asort()       { parent::ksort(); }
	public function natcasesort() { parent::uksort('strnatcasecmp'); }
	public function natsort()     { parent::uksort('strcasecmp'); }
	public function sortrecursive($function='strnatcasecmp')
	{
		parent::uksort($function);
		foreach ($this as $item) {
			$item->sortrecursive($function);
		}
	}
}

class Menu_Item extends Menu_Items
{
	const
		FLAG_DISABLED = 0,
		FLAG_ENABLED  = 1;

	protected
		$parent    = null,  // Identifies the parent Menu_Item of the tree node
		$expanded  = false, // Specifies whether the tree node is expanded.
		$selected  = false, // Determines whether the node is selected.

		// Dummy props to get property_exists() working
		$active,
		$children,
		$href,
		$l10n_label,
		$level,
		$text,
		$css_class,

		$data = array(
			'id'       => 0,
			'flags'    => 1,
			'label'    => '',
			'uri'      => '',
			'cssclass' => '',
			'image'    => '',
			'sortpath' => '',
		);

	function __construct(Menu_Item $parent=null)
	{
		$this->parent = $parent;
	}

	function __get($k)
	{
		switch ($k)
		{
			// Indicates the level of indentation of a node within the tree view control.
			case 'level':
				return $this->parent ? 1+$this->parent->level : 0;

			case 'href':
				return strlen($this->data['uri']) ? $this->data['uri'] : null;

			case 'active':
				if ($_SERVER['PATH_INFO'] === $this->data['uri']) {
					return true;
				}
				foreach ($this as $item) {
					if ($item->__get('active')) { return true; }
				}
				return false;

			case 'allowed':
				if (strlen($this->data['uri']) && false === strpos($this->data['uri'], '//')) {
					return \Poodle\ACL::view($this->data['uri']);
				}
				return true;

			case 'children':
				return iterator_to_array($this);

			case 'text':
				$k = 'label';

			case 'l10n_label':
				return \Poodle::getKernel()->L10N->dbget($this->label);

			case 'css_class':
				$c = $this->data['cssclass'];
				if ($this->__get('active')) { $c = trim($c.' active'); }
				return $c ?: null;
		}
		if (property_exists($this, $k)) { return $this->$k; }
		if (array_key_exists($k,$this->data)) { return $this->data[$k]; }
		trigger_error("Property {$k} does not exist");
	}

	protected static function fixType($ov, $v)
	{
		if (is_int($ov)) return (int)$v;
		else if (is_bool($ov)) return !!$v;
		else if (is_string($ov)) return (string)$v;
		return $v;
	}

	function __set($k, $v)
	{
		if ('parent' === $k && !(null == $v || $v instanceof Menu_Item)) {
			throw new \Exception("{$k} not an instance of Menu_Item");
		}
		if ('data' === $k) {
			foreach ($v as $k => $v) { self::__set($k, $v); }
		} else
		if (array_key_exists($k, $this->data)) {
			$this->data[$k] = self::fixType($this->data[$k], $v);
			if ('flags' === $k && static::FLAG_DISABLED == $v) {
				foreach ($this as $subitem) {
					$subitem->flags = \Poodle\Menu_Item::FLAG_DISABLED;
				}
			}
		} else
		if (property_exists($this, $k)) {
			$this->$k = self::fixType($this->$k, $v);
		} else
			trigger_error("Property {$k} does not exist");
	}

	public function findById($id)
	{
		if ($id == $this->data['id']) return $this;
		return parent::findById($id);
	}
}
