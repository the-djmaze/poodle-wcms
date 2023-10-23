<?php

namespace Poodle\Payment\iDEAL\Acquirers;

class Rabobank extends \Poodle\Payment\iDEAL\Acquirer
{
	/*
	 * iDEAL Lite
	 */

	public function getForm()
	{
		if ($this->testMode) {
			$this->urlAcquirer = 'https://idealtest.rabobank.nl/ideal/mpiPayInitRabo.do';
		} else {
			$this->urlAcquirer = 'https://ideal.rabobank.nl/ideal/mpiPayInitRabo.do';
		}
		return parent::getForm();
	}

	/*
	 * iDEAL Professional
	 */

	private function setAcquirerUrl()
	{
		if ($this->testMode) {
			$this->urlAcquirer = 'https://idealtest.rabobank.nl/ideal/iDEALv3';
		} else {
			$this->urlAcquirer = 'https://ideal.rabobank.nl/ideal/iDEALv3';
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
