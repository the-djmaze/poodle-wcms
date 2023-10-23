<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Search;

class Resource extends \Poodle\Resource
{
	public $allowed_methods = array('GET');

	function __construct($data=array())
	{
		$data = self::detectData($data);
		parent::__construct($data);
	}

	public function GET()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;
//		$K->OUT->Title($PAGE->L10N['Search']);
//		$K->OUT->crumbs->append($PAGE->L10N['Search']);

		if (isset($_GET['q'])) {
			$options = array();
			if (isset($_GET['limit'])) {
				$options['limit'] = $_GET->uint('limit');
			}
			if (isset($_GET['offset'])) {
				$options['offset'] = $_GET->uint('offset');
			}
//			$K->OUT->crumbs->append($PAGE->specialchars($_GET['q']));
			$K->OUT->searchresult = \Poodle\Search::query($_GET['q'], $options);
		}

		$head = $K->OUT->head;
		if ($head) {
			$head
				->addMeta('description', $this['meta-description'])
				->addMeta('keywords', $this['meta-keywords']);
		}

		if ($this->body) {
			$K->OUT->display('resources/'.$this->id.'-body-'.$this->l10n_id, $this->body, $this->mtime);
		} else {
			$K->OUT->display('poodle/search/results');
		}
	}
}
