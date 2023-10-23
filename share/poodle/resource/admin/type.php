<?php
/*  Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Resource\Admin;

abstract class Type
{

	public static function create(array $data) : int
	{
		$id = \Poodle::getKernel()->SQL->TBL->resource_types->insert(array(
			'resource_type_name'     => $data['name'],
			'resource_type_label'    => $data['label'],
			'resource_type_flags'    => isset($data['flags']) ? $data['flags'] : 0,
			'resource_type_class'    => isset($data['class']) ? $data['class'] : null,
			'resource_type_cssclass' => isset($data['cssclass']) ? $data['cssclass'] : null,
			'resource_type_wysiwyg_cfg' => isset($data['wysiwyg_cfg']) ? $data['wysiwyg_cfg'] : null,
			'resource_bodylayout_id' => 0
		),'resource_type_id');
		return $id;
	}

	public static function import(string $file) : bool
	{
/*
		libxml_use_internal_errors(true);
		$DOM = new \DomDocument();
		$DOM->load($file);
		if (!$DOM->schemaValidate(__DIR__.'/type-schema.xsd')) {
			$this->errors = libxml_get_errors();
			return false;
		}
*/
		$K   = \Poodle::getKernel();
		$SQL = $K->SQL;
		libxml_disable_entity_loader(true);
		$XML = simplexml_load_string(file_get_contents($file));
		if (!$XML || !$XML->resourcetypes) {
			throw new \Exception('Import failed');
		}

		foreach ($XML->resourcetypes->resourcetype as $rt) {
			$layout_id = 0;
			$bl = $SQL->uFetchRow("SELECT resource_type_id, resource_bodylayout_id FROM {$SQL->TBL->resource_types} WHERE resource_type_name=".$SQL->quote($rt['name']));
			if ($bl) {
				$id = (int)$bl[0];
				$layout_id = (int)$bl[1];
			} else {
				$id = static::create(array(
					'name'     => $rt['name'],
					'label'    => $rt->label,
					'flags'    => $rt['flags'],
					'class'    => $rt['phpclass'],
					'cssclass' => $rt['cssclass'],
				));
			}
			static::addL10NMsgId($rt->label);

			if ($rt->metadata && $rt->metadata->field) {
				foreach ($rt->metadata->field as $field) {
					$attr = array();
					if ($field->attributes) {
						foreach ($field->attributes->attribute as $a) {
							$n = (string)$a['name'];
							switch ($a['type']) {
								case 'integer': $a = (int)$a; break;
								case 'double' : $a = (float)$a; break;
								case 'boolean': $a = (bool)$a; break;
								case 'NULL'   : $a = NULL; break;
								case 'array':
									if ('options'==$n) {
										$ar = array();
										foreach ($a->option as $i => $v) {
											$ar[] = array(
												'value' => (string)$v['value'],
												'label' => (string)$v
											);
										}
										$a = $ar;
										break;
									}
								case 'string':
								default:
									$a = (string)$a;
									break;
							}
							$attr[$n] = $a;
						}
					}

					try {
						$SQL->TBL->resource_types_fields->insert(array(
							'resource_type_id' => $id,
							'rtf_name'         => (string)$field['name'],
							'rtf_label'        => (string)$field->label,
							'rtf_type'         => constant('Poodle\\FieldTypes::FIELD_TYPE_'.strtoupper($field['type'])),
							'rtf_attributes'   => $attr ? serialize($attr) : '',
							'rtf_sortorder'    => (int)$field['sortorder'],
							'rtf_flags'        => (int)$field['flags']
						));
					} catch (\Throwable $e) {
						// Already exists
					}

					static::addL10NMsgId($field->label);
				}
			}

			if ($rt->attachmenttypes && $rt->attachmenttypes->attachmenttype) {
				foreach ($rt->attachmenttypes->attachmenttype as $type) {
					try {
						$SQL->TBL->resource_attachment_types->insert(array(
							'resource_type_id'                  => $id,
							'resource_attachment_type_name'     => (string)$type['name'],
							'resource_attachment_type_callback' => (string)$type['callback'],
							'media_type_extensions'             => (string)$type['allowed-extensions'],
							'resource_attachment_type_label'    => (string)$type->label,
							'resource_attachment_type_width'    => (string)$type['width'],
							'resource_attachment_type_height'   => (string)$type['height']
						));
					} catch (\Throwable $e) {
						// Already exists
					}
					static::addL10NMsgId($type->label);
				}
			}

			if (!$layout_id && $rt->bodylayouts && $rt->bodylayouts->bodylayout) {
				$layout_id = BodyLayouts::addLayout();
				$SQL->TBL->resource_types->update(array('resource_bodylayout_id'=>$layout_id),"resource_type_id={$id}");
				foreach ($rt->bodylayouts->bodylayout as $bl) {
					BodyLayouts::updateLayoutContent($layout_id, (string)$bl['label'], (string)$bl /*,$bl['l10n']*/);
				}
			}

			if ($rt->resources && $rt->resources->resource) {

				$l10n_ids = array();
				$qr = $SQL->query("SELECT l10n_id, l10n_bcp47 FROM {$SQL->TBL->l10n}");
				while ($r = $qr->fetch_row()) {
					$l10n_ids[$r[0]] = $r[1];
				}

				foreach ($rt->resources->resource as $r) {

					$uri = preg_replace('@[\\s&<>\'"#\\?]@', '-', mb_strtolower(trim($r['uri'])));

					if (!$uri) {
						throw new \Exception('URI cannot be empty');
					}

					if (!preg_match('#^(.*/)([^/]+/?)$#D',$uri,$uri) || 3 != count($uri)) {
						throw new \Exception('Invalid resource URI');
					}

					$resource_id = $parent_id = 0;
					if ($resource = \Poodle\Resource::findByPath($uri[0])) {
						if (trim($uri[0],'/') === trim($resource['uri'],'/')) {
							$resource_id = $resource['id'];
						} elseif (trim($uri[1],'/') === trim($resource['uri'],'/')) {
							$parent_id = $resource['id'];
						}
					}

					if (!$resource_id) {
						if (!$parent_id) {
							throw new \Exception("Resource parent '{$uri[1]}' not found");
						}

						$resource_id = $SQL->TBL->resources->insert(array(
							'resource_uri'       => $uri[0],
							'resource_parent_id' => $parent_id,
							'resource_ctime'     => time(),
							'resource_flags'     => (int)$r['flags'],
							'resource_type_id'   => $id,
							'identity_id'        => $K->IDENTITY->id,
						), 'resource_id');

						if ($r->data) {
							foreach ($r->data as $data) {
								$SQL->TBL->resources_data->insert(array(
									'resource_id'    => $resource_id,
									'l10n_id'        => array_search((string)$data['l10n'], $l10n_ids) ?: 0,
									'identity_id'    => $K->IDENTITY->id,
									'resource_mtime' => time(),
									'resource_title' => (string)$data['title'],
									'resource_body'  => (string)$data,
								));
							}
						}
					}

					if ($r->metadata) {
						foreach ($r->metadata as $data) {
							try {
								$SQL->TBL->resources_metadata->insert(array(
									'resource_id' => $resource_id,
									'l10n_id'     => array_search((string)$data['l10n'], $l10n_ids) ?: 0,
									'resource_meta_name'  => (string)$data['name'],
									'resource_meta_value' => (string)$data
								));
							} catch (\Throwable $e) {
								throw $e;
								// ignore duplicate
							}
						}
					}
				}
			}
		}

		return true;
	}

	protected static function addL10NMsgId(\SimpleXMLElement $element)
	{
		$SQL = \Poodle::getKernel()->SQL;
		$tbl = $SQL->TBL->l10n_translate;
		$r = $SQL->uFetchAssoc("SELECT * FROM {$tbl}");
		unset($r['msg_id']);
		foreach ($r as $k => $v) {
			$r[$k] = (string)$element[substr($k,2)];
		}
		$r['msg_id'] = (string)$element;
		try {
			$tbl->insert($r);
		} catch (\Throwable $e) {
			// Already exists
		}
	}

	protected static function getL10NMsgId(string $id) : string
	{
		$SQL = \Poodle::getKernel()->SQL;
		$r = $SQL->uFetchAssoc("SELECT * FROM {$SQL->TBL->l10n_translate} WHERE msg_id=".$SQL->quote($id));
		if ($r) {
			$label_l10n = array();
			unset($r['msg_id']);
			foreach ($r as $k => $v) { if ($v) $label_l10n[] = substr($k,2).'="'.htmlspecialchars($v).'"'; }
			return ' '.implode(' ',$label_l10n);
		}
		return '';
	}

	public static function export(int $id) : string
	{
		$type = Types::getType($id);

		$SQL = \Poodle::getKernel()->SQL;

		$xml = '<?xml version="1.0"?>
<resource version="1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
	<name>'.htmlspecialchars($type['name']).'</name>
	<version/>
	<resourcetypes>
		<resourcetype name="'.htmlspecialchars($type['name']).'" flags="'.$type['flags'].'" cssclass="'.htmlspecialchars($type['cssclass']).'" phpclass="'.htmlspecialchars($type['class']).'">
			<label'.static::getL10NMsgId($type['label']).'>'.htmlspecialchars($type['label']).'</label>';

		if ($type['fields']) {
			$xml .= '
			<metadata>';
			foreach ($type['fields'] as $field) {
				$label_l10n = '';
				$xml .= '
				<field type="'.strtolower($field['type_name']).'" name="'.htmlspecialchars($field['name']).'" flags="'.$field['flags'].'" sortorder="'.$field['sortorder'].'">
					<label'.static::getL10NMsgId($field['label']).'>'.htmlspecialchars($field['label']).'</label>';
					if ($field['attributes']) {
						$xml .= '
					<attributes>';
						foreach ($field['attributes'] as $k => $v) {
							$xml .= '
						<attribute name="'.$k.'" type="'.gettype($v).'">';
							if ('options' === $k) {
								foreach ($v as $opt) {
									$xml .= '
							<option value="'.htmlspecialchars($opt['value']).'">'.htmlspecialchars($opt['label']).'</option>';
								}
								$xml .= '
						</attribute>';
							} else {
								$xml .= htmlspecialchars($v).'</attribute>';
							}
						}
						$xml .= '
					</attributes>';
					}
				$xml .= '
				</field>';
			}
			$xml .= '
			</metadata>';
		}

		$qr = $SQL->query("SELECT
			l10n_iso639_1,
			resource_title,
			resource_body
		FROM {$SQL->TBL->resources_data}
		LEFT JOIN {$SQL->TBL->l10n} USING (l10n_id)
		wHERE resource_id={$type['bodylayout_id']}
		  /*AND resource_status=2*/
		ORDER BY resource_mtime DESC");
		if ($qr->num_rows) {
			$xml .= '
			<bodylayouts>';
			$layouts = array();
			while ($r = $qr->fetch_row()) {
				if (!isset($layouts[$r[0]])) {
					$layouts[$r[0]] = 1;
				$xml .= '
				<bodylayout l10n="'.$r[0].'" label="'.htmlspecialchars($r[1]).'"><![CDATA['.$r[2].']]></bodylayout>';
				}
			}
			$xml .= '
			</bodylayouts>';
		}

		$qr = $SQL->query("SELECT
			resource_attachment_type_name,
			resource_attachment_type_callback,
			media_type_extensions,
			resource_attachment_type_label,
			resource_attachment_type_width,
			resource_attachment_type_height
		FROM {$SQL->TBL->resource_attachment_types}
		WHERE resource_type_id={$id}");
		if ($qr->num_rows) {
			$xml .= '
			<attachmenttypes>';
			while ($r = $qr->fetch_row()) {
				$xml .= '
				<attachmenttype name="'.htmlspecialchars($r[0]).'" callback="'.htmlspecialchars($r[1]).'" allowed-extensions="'.htmlspecialchars($r[2]).'" width="'.(int)$r[4].'" height="'.(int)$r[5].'">
					<label'.static::getL10NMsgId($r[3]).'>'.htmlspecialchars($r[3]).'</label>
				</attachmenttype>';
			}
			$xml .= '
			</attachmenttypes>';
		}

		$xml .= '
		</resourcetype>
	</resourcetypes>
</resource>';
		return str_replace('    ',"\t",$xml);
	}

}
