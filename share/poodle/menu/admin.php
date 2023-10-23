<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Menu;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Menus',
		$allowed_methods = array('GET','HEAD','POST');

	public function GET()
	{
		if (ctype_digit(\Poodle::$PATH[1])) {
			$this->editMenu(\Poodle::$PATH[1]);
		} else {
			$this->viewList();
		}
	}

	public function POST()
	{
		$K = \Poodle::getKernel();
		if (ctype_digit(\Poodle::$PATH[1])) {
			$menu = new \Poodle\Menu(\Poodle::$PATH[1]);

			if (isset($_POST['move_item']))
			{
				$item  = $menu->findById($_POST->uint('move_item'));
				if (!$item) {
					throw new \Exception('Menu item not found');
				}
				$aitem = $menu->findById($_POST->uint('after_id'));
				if ($_POST['parent_id']) {
					$pitem = $menu->findById($_POST->uint('parent_id'));
					if (!$pitem) {
						throw new \Exception('Menu item parent not found');
					}
				} else {
					$pitem = $menu->items;
				}
				// Remove from old parent
				$items = $item->parent ? $item->parent : $menu->items;
				$f = 0;
				foreach ($items as $i => $v) {
					if ($f) { $items[$i-$f] = $v; }
					if ($v->id == $item->id) { $f = 1; }
				}
				if ($f) { unset($items[$i]); }
				$item->parent = null;

				// Add to new parent
				for ($i = count($pitem); $i > 0; --$i) {
					$pitem[$i] = $pitem[$i-1];
					if ($aitem && $pitem[$i]->id == $aitem->id) { break; }
				}
				$pitem[$i] = $item;

				$menu->save();
				header('Content-Type: application/json');
				echo json_encode(array('moved'=>true));
			}
			else if (isset($_POST['delete_item']))
			{
				$item = $menu->findById($_POST->uint('delete_item'));
				if ($item) {
					$item->flags = \Poodle\Menu_Item::FLAG_DISABLED;
					$menu->save();

					header('Content-Type: application/json');
					echo json_encode(array('deleted'=>true));
				}
			}
			else
			{
				$parent = $_POST['parent_id'] ? $menu->findById($_POST->uint('parent_id')) : $menu->items;
				$item   = $menu->findById($_POST->uint('mitem_id'));
				if (!$item) {
					$item = $parent->append();
				}
				if ($parent !== $item->parent) {
					echo 'Error: moved';
				} else {
					$item->label = $_POST['mitem_label'];
					$item->uri   = $_POST['mitem_uri'];
					$menu->save();

					header('Content-Type: application/json');
					echo json_encode(array(
						'mitem_id'    => $item->id,
						'mitem_label' => $item->label,
						'mitem_uri'   => $item->uri,
					));
				}
			}
		}
	}

	protected function viewList()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;
		$OUT->menus = $SQL->query("SELECT menu_id id, menu_name name FROM {$SQL->TBL->menus}");
		$OUT->display('poodle/menu/admin/list');
	}

	protected function editMenu($id)
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
		$OUT = $K->OUT;
		$OUT->edit_menu = new \Poodle\Menu($id);
		$OUT->crumbs->append($OUT->edit_menu->name);

		$OUT->resource_uris = $SQL->query("SELECT
			resource_uri uri
		FROM {$SQL->TBL->resources}
		WHERE NOT resource_flags & ".self::FLAG_SUB_LOCKED."
		ORDER BY resource_uri");

		$OUT->head
			->addCSS('poodle_menu_admin')
			->addScript('poodle_menu_admin');
		$OUT->menus = $SQL->query("SELECT menu_id id, menu_name name FROM {$SQL->TBL->menus}");
		$OUT->display('poodle/menu/admin/edit');
	}

}
