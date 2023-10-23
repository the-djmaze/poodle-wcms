<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://docs.embarcadero.com/products/rad_studio/delphiAndcpp2009/HelpUpdate2/EN/html/delphivclwin32/!!MEMBERTYPE_Properties_ComCtrls_TTreeNode.html
*/

namespace Poodle;

class Tree extends \Poodle\Tree\Node
{
	const
		LIGHT_VERTICAL = '│', // "\xE2\x94\x20";
		LIGHT_V_RIGHT  = '├', // "\xE2\x94\x9C";
		LIGHT_UP_RIGHT = '└'; // "\xE2\x94\x82";

	protected
		$id,
		$cssclass;

	function __construct($id='menu')
	{
		$this->id = $id;
	}

	public static function convertURI($uri)
	{
		# Gecko supports background-image
		if (\Poodle::getKernel()->OUT->uaSupportsSelectOptionBgImage()) {
			return basename($uri);
		}
		$uri = preg_replace('#[^/]+/([^/]+)/?$#', self::LIGHT_V_RIGHT.' $1', substr($uri,1));
		return preg_replace('#[^/]+/#', self::LIGHT_VERTICAL, $uri);
	}

}
