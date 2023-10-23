<?php

namespace Poodle\Payment\iDEAL\Acquirers;

class Frieslandbank extends \Poodle\Payment\iDEAL\Acquirer
{
	/*
	 * iDEAL Basic
	 */

	public function getForm()
	{
		if ($this->testMode) {
			$this->urlAcquirer = 'https://testidealkassa.frieslandbank.nl/ideal/mpiPayInitFriesland.do';
		} else {
			$this->urlAcquirer = 'https://idealkassa.frieslandbank.nl/ideal/mpiPayInitFriesland.do';
		}
		return parent::getForm();
	}

	/*
	 * iDEAL Advanced
	 */

	private function setAcquirerUrl()
	{
		if ($this->testMode) {
			$this->urlAcquirer = 'https://testidealkassa.frieslandbank.nl/ideal/iDEALv3';
		} else {
			$this->urlAcquirer = 'https://idealkassa.frieslandbank.nl/ideal/iDEALv3';
		}
	}

	public function getIssuers()
	{
		self::setAcquirerUrl();
		return parent::getIssuers();
	}

	public function getNewTransaction($issuerId)
	{
		self::setAcquirerUrl();
		return parent::getNewTransaction($issuerId);
	}

	public function getTransactionStatus($transactionId)
	{
		self::setAcquirerUrl();
		return parent::getTransactionStatus($transactionId);
	}

}
