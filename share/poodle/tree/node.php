<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://docs.embarcadero.com/products/rad_studio/delphiAndcpp2009/HelpUpdate2/EN/html/delphivclwin32/!!MEMBERTYPE_Properties_ComCtrls_TTreeNode.html
*/

namespace Poodle\Tree;

class Node extends \ArrayIterator
{
	public
		$text,
		$data;

	protected
		$level    = 0,     // Indicates the level of indentation of a node within the tree view control.
		$expanded = false, // Specifies whether the tree node is expanded.
		$selected = false; // Determines whether the node is selected.

	public function __construct($text)
	{
		$item->text = $text;
	}

	public function append($item=null)
	{
		if (is_string($item)) {
			$item = new Node($item);
		}
		self::offsetSet($item->text, $item);
		if ($item instanceof Node) {
//			parent::append($item);
			return $item;
		}
	}

	public function offsetSet($i, $v)
	{
		if ($v instanceof Node) {
			parent::offsetSet($i, $v);
		} else {
			trigger_error('Value is not a \Poodle\Tree\Node');
		}
	}

	public function hasChildren() { return 0<$this->count(); }

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
