<?php

namespace Poodle\Payment\iDEAL\Request;

class Transaction extends \Poodle\Payment\iDEAL\Request
{
	protected
		$issuerId,
		$entranceCode;

	public function __construct(\Poodle\Payment\iDEAL\Acquirer $iDEAL, $issuerId)
	{
		parent::__construct($iDEAL);
		$this->issuerId = $issuerId;
		$this->entranceCode = sha1(\Poodle\UUID::generate());
	}

	// Execute request (Setup transaction)
	public function doRequest()
	{
		$this->validateProperties();

		if (!preg_match('/^[A-Z]{6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3})?$/', $this->issuerId)) {
			throw new \UnexpectedValueException('Invalid iDEAL.Transaction.issuerId');
		}

		if (!preg_match('/^[a-zA-Z0-9]{1,40}$/', $this->entranceCode)) {
			throw new \UnexpectedValueException('Invalid iDEAL.entranceCode');
		}

		if (512 < strlen($this->iDEAL->urlReturn)) {
			throw new \UnexpectedValueException('Invalid iDEAL.urlReturn');
		}

		if (!preg_match('/^[a-zA-Z0-9]+$/', $this->iDEAL->orderId)) {
			throw new \UnexpectedValueException("Invalid iDEAL.orderId");
		}

		if (0 > $this->iDEAL->orderAmount || 1000000000000 <= $this->iDEAL->orderAmount) {
			throw new \UnexpectedValueException('Invalid iDEAL.orderAmount');
		}

		if (1 > strlen($this->iDEAL->orderDescription) || 35 < strlen($this->iDEAL->orderDescription)) {
			throw new \UnexpectedValueException('Invalid iDEAL.orderDescription');
		}
/*
		if ($this->currency !== 'EUR') {
			throw new \UnexpectedValueException("Invalid iDEAL.currency");
		}

		if ($this->expirationPeriod < 1 || $this->expirationPeriod > 60) {
			throw new \UnexpectedValueException("Invalid iDEAL.expirationPeriod");
		}

		if (!preg_match('/^[a-z]{2}$/', $this->getLanguage()) {
			throw new \UnexpectedValueException("Invalid iDEAL.language");
		}
*/
		$this->postRequest('AcquirerTrxReq',
			'<Issuer>'
			.'<issuerID>'.$this->issuerId.'</issuerID>'
			.'</Issuer>'
			.'<Merchant>'
			.'<merchantID>'.$this->iDEAL->merchantId.'</merchantID>'
			.'<subID>'.$this->iDEAL->merchantSubId.'</subID>'
			.'<merchantReturnURL>'.$this->iDEAL->urlReturn.'</merchantReturnURL>'
			.'</Merchant>'
			.'<Transaction>'
			.'<purchaseID>'.$this->iDEAL->orderId.'</purchaseID>'
			.'<amount>'.$this->iDEAL->orderAmount.'</amount>'
			.'<currency>EUR</currency>'
			.'<expirationPeriod>PT1H</expirationPeriod>'
			.'<language>nl</language>'
			.'<description>'.htmlspecialchars($this->iDEAL->orderDescription, ENT_NOQUOTES).'</description>'
			.'<entranceCode>'.$this->entranceCode.'</entranceCode>'
			.'</Transaction>');

		/**
		 * Process result
		 */

		// $this->__get('purchaseID')

		return array(
			'id'  => $this->__get('transactionID'),
			'ec'  => $this->entranceCode,
			'url' => $this->__get('issuerAuthenticationURL')
		);
	}
}
