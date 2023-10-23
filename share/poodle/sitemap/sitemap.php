<?php

namespace Poodle;

class Sitemap extends \Poodle\Resource\Basic
{

	public
		$allowed_methods = array('GET','HEAD');
/*
	function __construct($data=array())
	{
		parent::__construct($data);

		$K = \Poodle::getKernel();
		if ('xml' === $K->mlf) {
			$mtime = $K->SQL->uFetchRow("SELECT
				MAX(rd.resource_mtime)
			FROM {$K->SQL->TBL->view_public_resources} AS r
			LEFT JOIN {$K->SQL->TBL->resources_data} AS rd ON (rd.resource_id = r.id AND rd.resource_status = 2)
			LEFT JOIN {$K->SQL->TBL->resources_metadata} m ON (m.resource_id = r.id AND m.l10n_id = 0 AND resource_meta_name = 'meta-robots')
			WHERE NOT type_flags & 1
			  AND (resource_meta_value IS NULL OR resource_meta_value = 'all' OR resource_meta_value = 'index, nofollow')");
			$this->mtime = $mtime[0] ?: time();
		}
	}
*/
	public function GET()
	{
		$K = \Poodle::getKernel();

		// Are we viewing the XML version?
		if ('xml' === $K->mlf) {
			$OUT = $K->OUT;

			// Set the sitemap_entries variable, so we can use it in the template
			$OUT->sitemap_entries = $K->SQL->query("SELECT
				'".\Poodle\URI::abs_base()."' || r.uri AS loc,
				NULL /*DATE_FORMAT(FROM_UNIXTIME(MAX(rd.resource_mtime)),'%Y-%m-%dT%H:%i:%sZ')*/ AS lastmod,
				NULL AS changefreq,
				NULL AS priority
			FROM {$K->SQL->TBL->view_public_resources} AS r
			LEFT JOIN {$K->SQL->TBL->resources_data} AS rd ON (rd.resource_id = r.id AND rd.resource_status = 2)
			LEFT JOIN {$K->SQL->TBL->resources_metadata} m ON (m.resource_id = r.id AND m.l10n_id = 0 AND resource_meta_name = 'meta-robots')
			WHERE NOT type_flags & 1
			  AND (resource_meta_value IS NULL OR resource_meta_value = 'all' OR resource_meta_value = 'index, nofollow')
			GROUP BY 1
			ORDER BY 1");

			// Viewable by browsing to [page URI].xml
			$OUT->display('poodle/output/sitemap');
		} else {
			// Display the default resource content supplied by the user in the Admin
			parent::get();
		}
	}

}
