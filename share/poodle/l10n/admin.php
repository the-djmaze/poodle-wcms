<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.
*/

namespace Poodle\L10N;

class Admin extends \Poodle\Resource\Admin
{
	public
		$title = 'Languages',
		$allowed_methods = array('GET','HEAD','POST');

	public function GET()
	{
		if ('translate' === \Poodle::$PATH[1]) {
			$this->viewTranslator();
		} else {
			$this->viewList();
		}
	}

	public function POST()
	{
		$K = \Poodle::getKernel();
		$SQL = $K->SQL;

		if ('translate' === \Poodle::$PATH[1]) {
			$lng = $_POST->text('lng');
			if (!preg_match(\Poodle\L10N::REGEX,$lng)) {
				throw new \Exception('Invalid language');
			}
			$col = 'v_'.strtr($lng,'-','_');
			$tbl = $SQL->TBL->l10n_translate;
			if (isset($_POST['add'])) {
				if (trim($_POST['translate_add']['msg_id'])) {
					$data = array(
						'msg_id' => $_POST['translate_add']['msg_id'],
						'v_en' => $_POST['translate_add']['value']
					);
					$data[$col] = $data['v_en'];
					$tbl->insert($data);
				}
			} else {
				foreach ($_POST['translate'] as $msg_id => $value) {
					if ($msg_id) {
						$tbl->update(array($col => $value), array('msg_id' => $msg_id));
					}
				}
			}
		}
		else
		{
			if (isset($_POST['l10n']['active'])) {
				$ids = implode(',',$_POST['l10n']['active']);
				if (preg_match('/^[0-9,]+$/D',$ids)) {
					$SQL->exec("UPDATE {$SQL->TBL->l10n} SET l10n_active=0 WHERE l10n_id NOT IN ({$ids})");
					$SQL->exec("UPDATE {$SQL->TBL->l10n} SET l10n_active=1 WHERE l10n_id IN ({$ids})");
				}
			}
			if (isset($_POST['l10n']['default'])) {
				$K->CFG->set('poodle', 'l10n_default', $_POST['l10n']['default']);
				$SQL->exec("UPDATE {$SQL->TBL->l10n} SET l10n_active=1 WHERE l10n_bcp47=".$SQL->quote($K->CFG->poodle->l10n_default));
			}
			$K->CACHE->delete('l10n_active');
		}
		\Poodle\URI::redirect($_SERVER['REQUEST_URI']);
	}

	protected function viewList()
	{
		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		$OUT->languages = array();
		foreach (self::getFullList() as $v) {
			$lbl = empty($v['v-'.$K->L10N->lng]) ? $v['v-en'] : $v['v-'.$K->L10N->lng];
			$OUT->languages[$lbl] = array(
				'id'      => $v['id'],
				'bcp47'   => $v['bcp47'],
				'active'  => $v['active'],
				'label'   => $lbl,
				'default' => ($v['bcp47'] === $K->CFG->poodle->l10n_default),
			);
		}
		ksort($OUT->languages);
		$OUT->head->addCSS('poodle_l10n_admin');
		$OUT->display('poodle/l10n/admin/overview');
	}

	protected function viewTranslator()
	{
		$K = \Poodle::getKernel();
		$OUT = $K->OUT;
		$SQL = $K->SQL;
		$OUT->crumbs->append($OUT->L10N['Translate'], '/admin/poodle_l10n/translate/');
		$OUT->head->addCSS('poodle_l10n_admin')->addScript('poodle_l10n_admin');

		$lng = $_GET->text('l10n_id') ?: $OUT->L10N->lng;
		if (!preg_match(\Poodle\L10N::REGEX,$lng)) {
			throw new \Exception('Invalid language');
		}
		$col = 'v_'.strtr($lng,'-','_');

		$OUT->trans_lng = $lng;
		$OUT->translations = $SQL->query("SELECT
			msg_id,
			v_en   en,
			{$col} value
		FROM {$SQL->TBL->l10n_translate}
		ORDER BY 1");

		$OUT->display('poodle/l10n/admin/translate');
	}

	protected static function getFullList()
	{
		$all = array();
		$SQL = \Poodle::getKernel()->SQL;
		if ($SQL && isset($SQL->TBL->l10n, $SQL->TBL->l10n_translate)) {
			$qr = $SQL->query("SELECT
				l10n_id       id,
				l10n_bcp47    bcp47,
				l10n_active   active,
				l10n_iso639_1 iso639_1,
				l10n_iso639_2 iso639_2,
				lt.*
			FROM {$SQL->TBL->l10n}
			LEFT JOIN {$SQL->TBL->l10n_translate} lt ON (msg_id = 'l10n_'||l10n_bcp47)");
			while ($r = $qr->fetch_assoc()) {
				if (\Poodle\L10N::getIniFile($r['bcp47'])) {
					unset($r['msg_id']);
					$all[$r['id']] = array();
					foreach ($r as $k => $v) {
						$all[$r['id']][strtr($k,'_','-')] = $v;
					}
				}
			}
		}
		return $all;
	}

}
