<?php

namespace Poodle\Payment\iDEAL\Request;

class Status extends \Poodle\Payment\iDEAL\Request
{
	protected
		// Transaction info
		$transactionId,

		// Status info
		$consumerAccountNumber,
		$consumerName,
		$consumerCity,
		$transactionStatus;

	public function __construct(\Poodle\Payment\iDEAL\Acquirer $iDEAL, $transactionId)
	{
		parent::__construct($iDEAL);
		$this->transactionId = $transactionId;
	}

	// Execute request
	public function doRequest()
	{
		$this->validateProperties();

		if (in_array('transactionId',$props) && !preg_match('/^[0-9]{16}$/', $this->transactionId)) {
			throw new \UnexpectedValueException('Invalid iDEAL.transactionID');
		}

		$this->postRequest('AcquirerStatusReq',
			'<Merchant>'
			.'<merchantID>'.$this->iDEAL->merchantId.'</merchantID>'
			.'<subID>'.$this->iDEAL->merchantSubId.'</subID>'
			.'</Merchant>'
			.'<Transaction>'
			.'<transactionID>'.$this->transactionId.'</transactionID>'
			.'</Transaction>');

		/**
		 * Process result
		 */

		$timestamp = $this->__get('createDateTimeStamp');
		$transactionId = $this->__get('transactionID');
		$transactionStatus = $this->__get('status');

		$accountNumber = (string) $this->__get('consumerAccountNumber');
/*
		AcquirerId: the ID of the acquirer.
		TransactionId: the ID of the transaction.
		Status: the status of the transaction.
			SUCCESS:   Positive result; the payment is guaranteed.
			CANCELLED: Negative result due to cancellation by Consumer; no payment has been made.
			EXPIRED:   Negative result due to expiration of the transaction; no payment has been made.
			FAILURE:   Negative result due to other reasons; no payment has been made.
			OPEN:      Final result not yet known.
		StatusTimestamp: the timestamp of current status.

		If the transaction has been successful (status=Success), the consumer’s details
		will also be provided, i.e. his or her ConsumerName, ConsumerIBAN,
		ConsumerBIC, Amount and Currency.
*/
		$this->transactionStatus = strtoupper($transactionStatus);

		$this->consumerCity  = $this->__get('consumerCity');
		$this->consumerName  = $this->__get('consumerName');
		$this->consumerAccountNumber = $consumerAccountNumber;

		return $this->transactionStatus;
	}

}
