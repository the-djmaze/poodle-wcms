<?php

namespace Poodle\Payment\iDEAL\Request;

class Issuer extends \Poodle\Payment\iDEAL\Request
{
	// Execute request (Lookup issuer list)
	public function doRequest()
	{
		$this->validateProperties();

		$CFG = \Poodle::getKernel()->CFG;
		$cache_key = 'ideal-issuers-'.md5($this->iDEAL->urlAcquirer);
		$cache_ttl = 0;
		if (isset($CFG->ideal, $CFG->ideal->cache_ttl))
		{
			$cache_ttl = max(0,(int)$CFG->ideal->cache_ttl);
			if ($cache_ttl) {
				$issuers = \Poodle::getKernel()->CACHE->get($cache_key);
				if ($issuers) { return $issuers; }
			}
		}

		$this->postRequest('DirectoryReq',
			'<Merchant>'
			.'<merchantID>'.$this->iDEAL->merchantId.'</merchantID>'
			.'<subID>'.$this->iDEAL->merchantSubId.'</subID>'
			.'</Merchant>');

		/**
		 * Process result
		 */

		$xml = $this->result->body;
		if ($xml)
		{
			$issuers = array();
			while (strpos($xml, '<issuerID>'))
			{
				$issuers[] = array(
					'id'   => self::getXmlNodeValue($xml, 'issuerID'),
					'name' => self::getXmlNodeValue($xml, 'issuerName')
				);
				$xml = substr($xml, strpos($xml, '</Issuer>') + 10);
			}

			// Save data in cache?
			if ($cache_ttl)
			{
				\Poodle::getKernel()->CACHE->get($cache_key, $aIssuerList, $cache_ttl);
			}

			return $issuers;
		}

		return false;
	}
}
