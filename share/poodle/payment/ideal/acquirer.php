<?php

namespace Poodle\Payment\iDEAL;

abstract class Acquirer
{
	protected
		// Account settings
		$merchantId    = '',
		$merchantSubId = 0,
		$testMode      = false,

		// URL's
		$urlAcquirer = '',
		$urlReturn   = '', // used by transaction

		// Order settings
		$orderId          = '', // Min 1, Max 16 characters, should be a unique reference to your order
		$orderAmount      = 0,  // Amount must be in EURO's
		$orderCurrency    = 'EUR',
		$orderDescription = '', // Min 1, Max 32 characters

		// Request settings
		$privateKey,
		$privateKeyPass,
		$privateCertificate,

		// getForm() settings
		$hashKey     = '', // Your secret hash key to secure form data (should match your Ideal Dashboard)
		$urlCancel   = '',
		$urlError    = '',
		$urlSuccess  = '',
		$buttonLabel = 'Betalen met iDEAL';

	function __construct()
	{
		$CFG = \Poodle::getKernel()->CFG->ideal;
		if ($CFG)
		{
			if (isset($CFG->hashKey))            { $this->hashKey        = $CFG->hashKey; }
			if (isset($CFG->merchantId))         { $this->merchantId     = $CFG->merchantId; }
			if (isset($CFG->merchantSubId))      { $this->merchantSubId  = $CFG->merchantSubId; }
			if (isset($CFG->testMode))           { $this->testMode       = $CFG->testMode; }
			if (isset($CFG->privateKey))         { $this->privateKey     = $CFG->privateKey; }
			if (isset($CFG->privateKeyPass))     { $this->privateKeyPass = $CFG->privateKeyPass; }
			if (isset($CFG->privateCertificate)) { $this->privateCertificate = $CFG->privateCertificate; }
		}
	}

	function __get($k)
	{
		if (property_exists($this,$k)) { return $this->$k; }
		trigger_error("Undefined property {$k}");
	}

	function __isset($k)
	{
		return (property_exists($this,$k) && isset($this->$k));
	}

	function __set($k, $v)
	{
		switch ($k)
		{
		case 'orderAmount':
			// must be in cents
			$this->orderAmount = round($v * 100);
			break;

		case 'orderDescription':
			$this->orderDescription = substr($v, 0, 32);
			break;

		case 'orderId':
			$this->orderId = substr($v, 0, 16);
			break;

		case 'merchantSubId':
			$this->merchantSubId = min(99999, max(0, $v));
			break;

		case 'hashKey':
		case 'merchantId':
		case 'privateKey':
		case 'privateKeyPass':
		case 'privateCertificate':
			$this->$k = (string)$v;
			break;

		case 'testMode':
			$this->testMode = !empty($v);
			break;

		case 'urlReturn':   // getNewTransaction option
		case 'urlCancel':   // getForm option
		case 'urlError':    // getForm option
		case 'urlSuccess':  // getForm option
			$this->$k = \Poodle\URI::resolve($v);
			break;

		case 'buttonLabel': // getForm option
			$this->buttonLabel = $v;
			break;

		default:
			trigger_error("Undefined property {$k}");
		}
	}

	public function getIssuers()
	{
		$request = new \Poodle\Payment\iDEAL\Request\Issuer($this);
		return $request->doRequest();
	}

	public function getNewTransaction($issuerId)
	{
		$request = new \Poodle\Payment\iDEAL\Request\Transaction($this, $issuerId);
		return $request->doRequest();
	}

	public function getTransactionStatus($transactionId)
	{
		$request = new \Poodle\Payment\iDEAL\Request\Status($this, $transactionId);
		return $request->doRequest();
	}

	// Generate iDEAL Basic/Lite/Easy form
	public function getForm()
	{
		$mode = 'lite';
		$FORM = new \Poodle\Output\HTML();
		$FORM->iDEAL = $this;
		// ABN Amro
		if ('Poodle\\Payment\\iDEAL\\Acquirers\\ABNAMRO' === get_class($this))
		{
			$mode = 'easy';
			$FORM->languageCode = 'NL_NL';
			$FORM->paymentType  = 'iDEAL';
			$FORM->HTTP_REFERER = \Poodle\URI::abs('/');
		}
		// Rabobank, ING, etc.
		else
		{
			$FORM->languageCode = 'nl';  // NL
			$FORM->paymentType  = 'ideal';
			$FORM->validUntil   = date('Y-m-d\\TH:i:s\\Z', strtotime('+1 hour'));

			// Setup hash string
			$shastring = $this->hashKey . $this->merchantId . $this->merchantSubId
			. $this->orderAmount . $this->orderId . $FORM->paymentType . $FORM->validUntil
			. '1' . $this->orderDescription . '1' . $this->orderAmount;

			// Replacement of ‘forbidden characters’
			$shastring = htmlspecialchars_decode($shastring);

			// Remove space characters: "\t", "\n", "\r" and " "
			$shastring = preg_replace('/[ \t\r\n]/', '', $shastring);

			// Generate hash
			$FORM->hash = sha1($shastring);
		}
		return $FORM->toString('poodle/payment/ideal/'.$mode);
	}

	// ING iDEAL Basic Notification XML
	public function processNotificationXML()
	{
/*
		ING:
		$_SERVER['REQUEST_METHOD'] = POST
		<?xml version="1.0" encoding="UTF-8"?>
		<Notification xmlns="http://www.idealdesk.com/Message" version="1.1.0">
		<createDateTimeStamp>20130308153109</createDateTimeStamp>
		  <transactionID>0050001229189448</transactionID>
		  <purchaseID>2013000000</purchaseID>
		  <status>Cancelled</status>
		</Notification>
*/
		libxml_disable_entity_loader(true);
		$notification = simplexml_load_string(\Poodle\Input\POST::raw_data());
		$notification->createDateTimeStamp;
		$notification->status;
		$notification->transactionID;
		$notification->purchaseID;
	}
}
