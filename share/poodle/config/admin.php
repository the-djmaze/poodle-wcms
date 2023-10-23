<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Config;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Configuration',
		$allowed_methods = array('GET','HEAD','POST');

	private static
		$cfg = array(
			'site' => array(
				'name' => 'text',
				'description' => 'text',
				'timezone' => 'text',
//				'domain_name' => 'text',
//				'base_path' => 'text',
				'maintenance' => 'bool',
				'maintenance_text' => 'text',
				'maintenance_till' => 'datetime',
				// Owner
				'company' => 'text',
				'address' => 'text',
				'postcode' => 'text',
				'locality' => 'text',
				'country_code' => 'uint',
				'phonenumber' => 'tel',
			),
			'output' => array(
/*				'no_frames' => 'bool',
				'wysiwyg' => 'bool',
*/				'template' => 'text',
				'crumb' => 'text',
				'title_format' => 'text',
				'google_analytics' => 'text',
				'google_verification' => 'text',
				'footer' => 'html',
			),
			'privacy' => array(
				// privacy_has
				'comments' => 'bool',
				'newsletters' => 'bool',
				'newsletters_track' => 'bool',
				'registration' => 'bool',
				// privacy_applications
				'addthis' => 'bool',
				'doubleclick' => 'bool',
				'facebook' => 'bool',
				'google_adsense' => 'bool',
				'google_adwords' => 'bool',
				'google_plus' => 'bool',
				'google_remarketing' => 'bool',
				'instagram' => 'bool',
				'linkedin' => 'bool',
				'paypal' => 'bool',
				'pinterest' => 'bool',
				'piwik' => 'bool',
				'tumblr' => 'bool',
				'twitter' => 'bool',
				'youtube' => 'bool',
			)
		);

	public function GET()
	{
		$this->viewList();
	}

	public function POST()
	{
		$K = \Poodle::getKernel();
		$CFG = $K->CFG;

		if ($_POST->bool('remove_og_image')) {
			$CFG->set('output', 'og_image', '');
		} else if (isset($_FILES['upload_og_image'])) {
			$file = $_FILES->getAsFileObject('upload_og_image');
			if (!$file->error)
			try {
				$img = \Poodle\Image::open($file->tmp_name);
				// Facebook recommends images 1200 x 630, minimum 600 x 315, 1.91:1 aspect ratio
//				if (200 <= $img->getImageWidth() && 200 <= $img->getImageHeight()) {
				if (10 <= $img->getImageWidth() && 10 <= $img->getImageHeight()) {
					unset($img);
					$item = \Poodle\Media\Item::createFromUpload($file);
					$CFG->set('output', 'og_image', '/media/'.$item->file);
				}
			} catch (\Throwable $e) {}
		}

		foreach (self::$cfg as $section => $options) {
			foreach ($options as $key => $type) {
				$CFG->set($section, $key, $_POST->$type('config', $section, $key));
			}
		}

		$CFG->set('debug', 'poodle_level', array_sum($_POST['config']['debug']['poodle_level']));

		$this->closeRequest(null, $_SERVER['REQUEST_URI']);
	}

	protected function viewList()
	{
		$OUT = \Poodle::getKernel()->OUT;
		$OUT->head->addCSS('poodle_config');

		$CFG = \Poodle::getKernel()->CFG;
		$lvl = (int) $CFG->debug->poodle_level;
		$OUT->debug_options = array(
			array('label' => 'All', 'value' => \Poodle::DBG_ALL, 'active' => $lvl & \Poodle::DBG_ALL),
/*			array('label' => 'Memory',              'value' => \Poodle::DBG_MEMORY,              'active' => $lvl & \Poodle::DBG_MEMORY),
			array('label' => 'Parse time',          'value' => \Poodle::DBG_PARSE_TIME,          'active' => $lvl & \Poodle::DBG_PARSE_TIME),
			array('label' => 'TPL time',            'value' => \Poodle::DBG_TPL_TIME,            'active' => $lvl & \Poodle::DBG_TPL_TIME),
			array('label' => 'SQL',                 'value' => \Poodle::DBG_SQL,                 'active' => $lvl & \Poodle::DBG_SQL),
			array('label' => 'SQL queries',         'value' => \Poodle::DBG_SQL_QUERIES,         'active' => $lvl & \Poodle::DBG_SQL_QUERIES),
			array('label' => 'JavaScript',          'value' => \Poodle::DBG_JAVASCRIPT,          'active' => $lvl & \Poodle::DBG_JAVASCRIPT),
			array('label' => 'PHP',                 'value' => \Poodle::DBG_PHP,                 'active' => $lvl & \Poodle::DBG_PHP),
			array('label' => 'Exec time',           'value' => \Poodle::DBG_EXEC_TIME,           'active' => $lvl & \Poodle::DBG_EXEC_TIME),
			array('label' => 'Included files',      'value' => \Poodle::DBG_TPL_INCLUDED_FILES,  'active' => $lvl & \Poodle::DBG_TPL_INCLUDED_FILES),
			array('label' => 'Included files',      'value' => \Poodle::DBG_INCLUDED_FILES,      'active' => $lvl & \Poodle::DBG_INCLUDED_FILES),
			array('label' => 'Declared classes',    'value' => \Poodle::DBG_DECLARED_CLASSES,    'active' => $lvl & \Poodle::DBG_DECLARED_CLASSES),
			array('label' => 'Declared interfaces', 'value' => \Poodle::DBG_DECLARED_INTERFACES, 'active' => $lvl & \Poodle::DBG_DECLARED_INTERFACES),

			PHP errors:
			    1      E_ERROR
			    2      E_WARNING
			    4      E_PARSE
			    8      E_NOTICE
			   16      E_CORE_ERROR
			   32      E_CORE_WARNING
			   64      E_COMPILE_ERROR
			  128      E_COMPILE_WARNING
			  256      E_USER_ERROR
			  512      E_USER_WARNING
			 1024      E_USER_NOTICE
			 2048      E_STRICT
			 4096      E_RECOVERABLE_ERROR
			 8192      E_DEPRECATED
			16384      E_USER_DEPRECATED
*/		);

		$OUT->output_templates = array(
			array('label' => 'default', 'value' => 'default', 'selected' => false),
		);
		foreach (glob('tpl/*', GLOB_ONLYDIR) as $dir) {
			$dir = basename($dir);
			if ('default' !== $dir) {
				$OUT->output_templates[] = array('label' => $dir, 'value' => $dir, 'selected' => ($dir == $CFG->output->template));
			}
		}

		$OUT->privacy_has = array(
			'comments'           => array('label' => 'Comments',            'checked' => $CFG->privacy->comments),
			'newsletters'        => array('label' => 'Newsletters',         'checked' => $CFG->privacy->newsletters),
			'newsletters_track'  => array('label' => 'Newsletter-Tracking', 'checked' => $CFG->privacy->newsletters_track),
			'registration'       => array('label' => 'Registration',        'checked' => $CFG->privacy->registration),
		);
		$OUT->privacy_applications = array(
			'addthis'            => array('label' => 'AddThis',            'checked' => $CFG->privacy->addthis),
			'doubleclick'        => array('label' => 'DoubleClick',        'checked' => $CFG->privacy->doubleclick),
			'facebook'           => array('label' => 'Facebook',           'checked' => $CFG->privacy->facebook),
			'google_adsense'     => array('label' => 'Google AdSense',     'checked' => $CFG->privacy->google_adsense),
			'google_remarketing' => array('label' => 'Google Remarketing', 'checked' => $CFG->privacy->google_remarketing),
			'google_plus'        => array('label' => 'Google+',            'checked' => $CFG->privacy->google_plus),
			'google_adwords'     => array('label' => 'Google-AdWords',     'checked' => $CFG->privacy->google_adwords),
			'instagram'          => array('label' => 'Instagram',          'checked' => $CFG->privacy->instagram),
			'linkedin'           => array('label' => 'LinkedIn',           'checked' => $CFG->privacy->linkedin),
			'paypal'             => array('label' => 'PayPal',             'checked' => $CFG->privacy->paypal),
			'pinterest'          => array('label' => 'Pinterest',          'checked' => $CFG->privacy->pinterest),
			'piwik'              => array('label' => 'PIWIK',              'checked' => $CFG->privacy->piwik),
			'tumblr'             => array('label' => 'Tumblr',             'checked' => $CFG->privacy->tumblr),
			'twitter'            => array('label' => 'Twitter',            'checked' => $CFG->privacy->twitter),
			'youtube'            => array('label' => 'YouTube',            'checked' => $CFG->privacy->youtube),
		);

		$OUT->display('poodle/config/index');
	}

}
