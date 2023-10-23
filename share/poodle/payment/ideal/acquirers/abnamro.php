<?php

namespace Poodle\Payment\iDEAL\Acquirers;

class ABNAMRO extends \Poodle\Payment\iDEAL\Acquirer
{
	/*
	 * iDEAL Easy
	 */

	public function getForm()
	{
		$this->urlAcquirer = 'https://internetkassa.abnamro.nl/ncol/prod/orderstandard.asp';
		if ($this->testMode) {
			$this->merchantId = 'TESTiDEALEASY';
		}
		return parent::getForm();
	}


	/*
	 * iDEAL Zelfbouw
	 */

	private function setAcquirerUrl()
	{
		if ($this->testMode) {
			$this->urlAcquirer = 'https://abnamro-test.ideal-payment.de:443/ideal/iDEALv3';
		} else {
			$this->urlAcquirer = 'https://abnamro.ideal-payment.de:443/ideal/iDEALv3';
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
