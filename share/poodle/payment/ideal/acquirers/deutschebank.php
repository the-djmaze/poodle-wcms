<?php

namespace Poodle\Payment\iDEAL\Acquirers;

class Deutschebank extends \Poodle\Payment\iDEAL\Acquirer
{
	/*
	 * iDEAL Easy
	 */
/*
	public function getForm()
	{
		if ($this->testMode) {
			$this->urlAcquirer = 'https://idealtest.db.com/ideal/mpiPayInit???.do';
		} else {
			$this->urlAcquirer = 'https://ideal.db.com/ideal/mpiPayInit???.do';
		}
		return parent::getForm();
	}
*/
	/*
	 * iDEAL Pro
	 */

	private function setAcquirerUrl()
	{
		if ($this->testMode) {
			$this->urlAcquirer = 'https://idealtest.db.com/ideal/iDEALv3';
		} else {
			$this->urlAcquirer = 'https://ideal.db.com/ideal/iDEALv3';
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
