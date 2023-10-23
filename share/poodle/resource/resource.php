<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle;

abstract class Resource implements \ArrayAccess
{
	const
		// TBL->resources.resource_flags
		FLAG_FIXED_URI   =  1, // can't modify uri
		FLAG_FIXED_TYPE  =  2, // can't modify type
		FLAG_FIXED_DATE  =  4, // can't modify publish and expiry date
		FLAG_SUB_LOCKED  =  8, // can't add sub-resources

		STATUS_DELETED   = -1, // cancelled
		STATUS_DRAFT     =  0,
		STATUS_PENDING   =  1,
		STATUS_PUBLISHED =  2;

	public
		$uri       = '', # The Route
		$parent_id = 0,
		$type_id   = 0,
		$ctime     = 0, # creation date
		$ptime     = 0, # publish date
		$etime     = 0, # expiry date
		$flags     = 0,
		$creator_identity_id = 0,
		$bodylayout_id = 0,
//		$etag      = '',
		// resource type
		$type_name  = '',
		$type_label = '',
//		$type_class = '',
		$type_flags = 0,
		// resource data
		$l10n_id     = 0,
		$mtime       = 0, # modified date
		$status      = 0,
		$rollback_of = 0,
		$title       = '',
		$body        = '',
		$modifier_identity_id = 0,

		// http://tools.ietf.org/html/rfc4918#section-9
		$allowed_methods = array(
/*			'PROPFIND',
			'PROPPATCH',
			'MKCOL',
			'GET',
			'HEAD',
			'POST',
			'DELETE',
			'PUT',
			'COPY',
			'MOVE',
			'LOCK',
			'UNLOCK',
			'OPTIONS',
			'REPORT',
			'MKCALENDAR',
			'TESTRRULE'
*/		);

	protected
		$id = 0,
		$author = null,      # \Poodle\Identity(creator_identity_id)
		$metadata = null,    # resource metadata
		$attachments = null; # \Poodle\Resource\Attachments

	function __construct(array $data = array())
	{
		$this->ctime = $this->mtime = time();
		if ($data) {
			$data = array_intersect_key($data, get_object_vars($this));
			foreach ($data as $k => $v) {
				switch (gettype($this->$k)) {
					case 'boolean': $v = !!$v; break;
					case 'integer': $v = (int)$v; break;
					case 'double':  $v = (float)$v; break;
					case 'array':   $v = explode(',',$v); break;
					case 'object':  $v = new $v(); break;
				}
				$this->$k = $v;
			}
		}

		// Set the default language when the resource has none
		// Important for properly working of metadata and others
		if (!$this->hasL10N()) {
			$this->l10n_id = 0;
		} else if (!$this->l10n_id) {
			$this->l10n_id = \Poodle::getKernel()->L10N->id;
		}
	}

	function __get($k)
	{
		if ('basename'===$k) {
			return preg_replace('#^.*/([^/]+/?)$#D','$1',$this->uri);
		}
		if ('comments' === $k) {
			return \Poodle\Comments::getFor($this->id);
		}
		if ('full_uri' === $k) {
			return \Poodle\URI::abs(\Poodle\URI::index($this->uri));
		}
		if ('lng' === $k) {
			return \Poodle\L10N::getBCP47ByID($this->l10n_id);
		}
		if (property_exists($this,$k)) {
			if ('attachments' === $k && !$this->attachments) {
				$this->getAttachments();
			}
			if ('author' === $k && !$this->author) {
				$this->author = \Poodle\Identity\Search::byID($this->creator_identity_id);
			}
			if ('metadata' === $k) {
				return $this->getMetadata();
			}
			return $this->$k;
		}
		if ($this->getMetadata()->offsetExists($k)) {
			return $this->getMetadata($k);
		}
		trigger_error("Undefined property ".get_class($this)."->{$k}");
	}

	function __set($k, $v)
	{
		if (!property_exists($this, $k)) {
			\Poodle\Debugger::trigger("Setting undefined property ".get_class($this)."->{$k}", __FILE__);
			$this->$k = $v;
		} else {
			\Poodle\Debugger::trigger("Not allowed to set property ".get_class($this)."->{$k}", __FILE__, E_USER_ERROR);
		}
	}

	protected static function detectData($data)
	{
		if (!is_array($data)) {
			if (is_int($data) || is_string($data)) {
				$data = ctype_digit((string)$data) ? self::findById($data) : self::findByPath($data);
			}
		}

		if ($data && isset($data['type_id'])) {
			// Get resource data?
			if (!isset($data['body']) && $data['type_id'] >= 0
			 && !($data['type_flags'] & \Poodle\Resource\Type::FLAG_NO_DATA))
			{
				$data = array_merge($data, static::getData($data['id']));
			}
			if (!$data['type_class']) {
				$data['type_class'] = 'Poodle\\Resource\\Basic'; // static::class
			}
		}

		return $data;
	}

	public function toString($OUT = null)
	{
		if (!$OUT) { $OUT = \Poodle::getKernel()->OUT; }
		return $OUT->toString('resources/'.$this->id.'-body-'.$this->l10n_id, $this->body, $this->mtime);
	}

	protected $head_processed = false;
	public function display($file = null, $OUT = null)
	{
		$K = \Poodle::getKernel();
		$OUT = $OUT ?: $K->OUT;
		$head = $OUT->head;
		$imgs = null;
		$duplicates = array();
		if ($head && !$this->head_processed) {
			$OUT->opengraph_image = null;
			$this->head_processed = true;
			$head
				->addMeta('description', $this['meta-description'] ?: $K->CFG->site->description)
				->addMeta('keywords', $this['meta-keywords']);
			if ($this['link-canonical']) {
				$head->addLink('canonical', $this['link-canonical']);
			}

			// Link alternate languages
			$l10n_links = $this->getL10NLinks();
			if (1 < count($l10n_links)) {
				// Each language page must identify all language versions, including itself.
				// For example, if your site provides content in English and Dutch,
				// the Dutch version must include a hreflang="x-default" link for itself
				// in addition to link to English version.
				foreach ($this->getL10NLinks() as $lng) {
//					if ($OUT->L10N->lng != $OUT->L10N->default_lng) {
//					if ($lng['bcp47'] == $OUT->L10N->lng) {
//					if ($lng['bcp47'] == $OUT->L10N->ua_lng) {
					$head->addLink('alternate', $lng['href'], array('hreflang' => $lng['bcp47']));
				}
				$head->addLink('alternate', \Poodle\URI::index($this->uri), array('hreflang' => 'x-default'));
			}

			// Facebook Open Graph
			// It's best to use a square image, as Facebook displays them in that matter.
			// That image should be at least 50x50 in any of the usually supported image forms (JPG, PNG, etc.)
			// $head->addMetaRDFa('og:image', $this['og-image']);
			// $head->addMetaRDFa('og:site_name', $K->CFG->site->name);
			// $head->addMetaRDFa('og:url', $this['link-canonical']);
			if (!POODLE_BACKEND) {
				// Get and set OpenGraph images
				$imgs = $K->SQL->query("SELECT
					media_file
				FROM {$K->SQL->TBL->media}
				INNER JOIN {$K->SQL->TBL->resources_attachments} USING (media_id)
				INNER JOIN {$K->SQL->TBL->resource_attachment_types} USING (resource_attachment_type_id)
				WHERE resource_attachment_type_name='opengraph_image'
				  AND resource_id={$this->id}");
				if ($imgs->num_rows) {
					while ($img = $imgs->fetch_row()) {
						$OUT->opengraph_image = \Poodle::$URI_MEDIA.'/'.$img[0];
						$img = \Poodle\URI::abs($OUT->opengraph_image);
						if (!isset($duplicates[$img])) {
							$duplicates[$img] = 1;
							$OUT->head->addMetaRDFa('og:image',$img);
						}
					}
				}
			}
		}

		$robots = $this['meta-robots'];
		if ($this['meta-description']) {
			$robots .= ($robots ? ', ' : '') . 'noodp, noydir';
		}
		if ($robots) {
			header("X-Robots-Tag: {$robots}", false);
		}
		if ($this->etime){
			header('X-Robots-Tag: unavailable_after: '.date('d-M-y H:i:s T', $this->etime), false);
		}

		if ($file) {
			$OUT->display($file);
		} else if (!$this->body) {
			// 415 Unsupported Media Type
			trigger_error("The resource '{$this->uri}' has an empty body, and therefore it is not shown", E_USER_WARNING);
			return \Poodle\Report::error(404);
			return \Poodle\HTTP\Status::set(204);
		} else {
			$OUT->display('resources/'.$this->id.'-body-'.$this->l10n_id, $this->body, $this->mtime);
			if ($imgs) {
				if (!$imgs->num_rows && preg_match_all('#<img[^>]+src="([^"]+)"#', $OUT->body, $imgs)) {
					foreach ($imgs[1] as $img) {
						$img = \Poodle\URI::abs($img);
						if (!isset($duplicates[$img])) {
							$duplicates[$img] = 1;
							$OUT->head->addMetaRDFa('og:image',$img);
						}
					}
				}
				unset($imgs, $duplicates);
				if (!empty($K->CFG->output->og_image)) {
					$OUT->head->addMetaRDFa('og:image',\Poodle\URI::abs($K->CFG->output->og_image));
				}
			}
		}

		if ($this->bodylayout_id) {
			$layout = \Poodle\Resource\BodyLayouts::getLayout($this->bodylayout_id);
			if ($layout && !empty($layout['body'])) {
				$OUT->body = $OUT->toString("bodylayouts/{$layout['id']}", $layout['body'], $layout['mtime']);
			}
		}
	}

	public static function findBy(array $data)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$where = '';
		if (isset($data['id'])) {
			$id = (int)$data['id'];
			if (!$id) { throw new \Exception('Invalid resource ID'); }
			$where = "WHERE resource_id={$id}";
		} else
		if (isset($data['uri'])) {
			if (!is_string($data['uri']) || !strlen($data['uri']) || false !== strpos($data['uri'],'//')) {
				throw new \Exception('Invalid resource path');
			}
			$parts = explode('/', trim($data['uri'],'/'));
			if ($parts[0]) {
				$uri  = '/';
				$uris = array($SQL->quote($uri));
				foreach ($parts as $part) {
					$uris[] = $SQL->quote($uri .= $part);
					$uris[] = $SQL->quote($uri .= '/');
				}
				$where = "WHERE resource_ptime<=UNIX_TIMESTAMP()
				  AND resource_uri IN (".implode(',',array_reverse($uris)).")
				ORDER BY LENGTH(resource_uri) DESC";
/*
				return $SQL->uFetchAssoc("SELECT
					*
				FROM {$SQL->TBL->view_public_resources}
				WHERE uri IN (".implode(',',array_reverse($uris)).")
				ORDER BY LENGTH(uri) DESC");
*/
			} else {
				$where = "WHERE resource_id=1";
			}
		}
		return $SQL->uFetchAssoc("SELECT
			resource_id id,
			resource_uri uri,
			resource_parent_id parent_id,
			resource_type_id type_id,
			resource_ctime ctime,
			resource_ptime ptime,
			resource_etime etime,
			resource_flags flags,
			identity_id creator_identity_id,
			r.resource_bodylayout_id bodylayout_id,
			resource_type_name type_name,
			resource_type_label type_label,
			resource_type_class type_class,
			resource_type_flags type_flags
		FROM {$SQL->TBL->resources} r
		LEFT JOIN {$SQL->TBL->resource_types} USING (resource_type_id)
		{$where}");
	}

	public static function findByPath($path)
	{
		return self::findBy(array('uri'=>$path));
	}

	public static function findById($id)
	{
		return self::findBy(array('id'=>$id));
	}

	# $path = $_SERVER['PATH_INFO']
	public static function factory($path)
	{
		if ($data = static::detectData($path)) {
			$class = $data['type_class'];
			return new $class($data);
		}
	}

	public function getAttachments()
	{
		if (!$this->attachments) {
			$this->attachments = new \Poodle\Resource\Attachments($this);
		}
		return $this->attachments;
	}

	public static function getData($id)
	{
		$id = (int)$id;
		$SQL = \Poodle::getKernel()->SQL;
		$L10N = \Poodle::getKernel()->L10N;
		$data = $SQL->uFetchAssoc("SELECT
			l10n_id,
			resource_status status,
			resource_mtime mtime,
			resource_title title,
			resource_body body,
			rollback_of,
			identity_id modifier_identity_id
		FROM {$SQL->TBL->resources_data}
		WHERE resource_id = {$id}
		  AND resource_status = ".self::STATUS_PUBLISHED."
		  AND l10n_id IN (0, 1, {$L10N->default_id}, {$L10N->id})
		ORDER BY l10n_id = {$L10N->id} DESC, l10n_id DESC, resource_mtime DESC");
		return $data ?: array();
	}
/*
	public function PROPFIND(){}
	public function PROPPATCH(){}
	public function MKCOL(){}
	public function GET(){}
	public function HEAD(){}
	public function POST(){}
	public function DELETE(){}
	public function PUT(){}
	public function COPY(){}
	public function MOVE(){}
	public function LOCK(){}
	public function UNLOCK(){}
*/

	public function getIntroText()
	{
		$intro = explode('<!-- pagebreak', $this->body, 2);
		if (isset($intro[1])) {
			$intro = $intro[0];
		} else if (!($intro = $this->getMetadata('description'))) {
			$intro = mb_substr(strip_tags($this->body), 0, 180);
		}
		return $intro;
	}

	public function getMetadata($key=null)
	{
		if (!($this->metadata instanceof \Poodle\Resource\Metadata)) {
			$this->metadata = new \Poodle\Resource\Metadata($this);
		}
		if ($key && !$this->metadata->offsetExists($key)) {
			\Poodle\Debugger::trigger("Undefined metadata {$key}", __FILE__, E_USER_NOTICE);
			return;
		}
		return $key ? $this->metadata[$key] : $this->metadata;
	}

	public function getChildrenOfType($type=null, $limit=0, $offset=0)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$where = '';
		if (is_int($type) || ctype_digit($type)) {
			$where = "AND type_id = {$type}";
		} else if (is_string($type)) {
			$where = "AND type_name = {$SQL->quote($type)}";
		}
		return $SQL->query("SELECT
			id,
			uri,
			parent_id,
			type_id,
			flags,
			creator_identity_id,
			mtime,
			status,
			modifier_identity_id,
			l10n_id,
			title,
			body,
			type_name,
			type_class,
			type_flags
		FROM {$SQL->TBL->view_latest_resources_data}
		WHERE parent_id = {$this->id}
		  AND ptime <= UNIX_TIMESTAMP()
		  AND (etime = 0 OR etime > UNIX_TIMESTAMP())
/*		  AND uri LIKE '".$SQL->escape_string(rtrim($this->uri, '/'))."/%'*/
		  {$where}
		ORDER BY title");
	}

	private $l10n_links;
	public function getL10NLinks()
	{
		if (!is_array($this->l10n_links)) {
			$this->l10n_links = array();
			$languages = \Poodle\L10N::active();
			if (1 < count($languages)) {
				$K = \Poodle::getKernel();
				$qr = $K->SQL->query("SELECT
					l10n_id
				FROM {$K->SQL->TBL->resources_data}
				WHERE resource_id = {$this->id} AND resource_status = 2 AND l10n_id IN (".implode(',', $languages).")
				GROUP BY 1");
				$languages = array_flip($languages);
				while ($lng = $qr->fetch_row()) {
					$this->l10n_links[] = array(
						'id' => $lng[0],
						'bcp47' => $languages[$lng[0]],
						'href' => \Poodle\URI::index('/'.$languages[$lng[0]].$this->uri),
					);
				}
			}
		}
		return $this->l10n_links;
	}

	public function hasL10N()
	{
		return !($this->type_flags & \Poodle\Resource\Type::FLAG_NO_L10N);
	}

	# ArrayAccess
	public function offsetExists($k)  { return $this->getMetadata()->offsetExists($k); }
	public function offsetGet($k)     { return $this->getMetadata($k); }
	public function offsetSet($k, $v) {}
	public function offsetUnset($k)   {}

}
