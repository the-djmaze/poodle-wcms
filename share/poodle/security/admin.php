<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Security;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Security',
		$allowed_methods = array('GET','POST');

	public function POST()
	{
		$tbl = \Poodle::getKernel()->SQL->TBL->security_domains;
		foreach ($_POST['security_domains'] as $domain) {
			$tbl->update(array(
				'ban_url'    => !empty($domain['url']),
				'ban_email'  => !empty($domain['email']),
				'ban_dns_mx' => !empty($domain['dns_mx'])
			), array(
				'ban_domain' => $domain['name']
			));
		}
		if (isset($_POST['add'])) {
			$tbl->insert(array(
				'ban_domain' => $_POST->text('add_security_domain', 'name'),
				'ban_url'    => $_POST->bool('add_security_domain', 'url'),
				'ban_email'  => $_POST->bool('add_security_domain', 'email'),
				'ban_dns_mx' => $_POST->bool('add_security_domain', 'dns_mx')
			));
		}
		$this->closeRequest(null, $_SERVER['REQUEST_URI']);
	}

	public function GET()
	{
		$K = \Poodle::getKernel();
		$K->OUT->security_domains = $K->SQL->query("SELECT
				ban_domain name,
				ban_url    url,
				ban_email  email,
				ban_dns_mx dns_mx
			FROM {$K->SQL->TBL->security_domains}
			ORDER BY 1 ASC");
		$K->OUT->display('poodle/security/admin');
	}

}
