<?php
/*
	Suitable for:
		Rabobank     iDEAL Professional
		ING BANK     iDEAL Advanced
		ABN AMRO     iDEAL Zelfbouw
		Friesland    iDEAL Zakelijk Pro
		Deutschebank iDEAL Pro
*/

namespace Poodle\Payment\iDEAL;

abstract class Request
{
	protected
		$iDEAL,
		$result,
		$timestamp,
		$token,
		$tokenCode;

	function __construct(Acquirer $iDEAL)
	{
		$this->iDEAL     = $iDEAL;
		$this->timestamp = gmdate('Y-m-d\\TH:i:s\\Z');
	}

	function __get($k)
	{
		if (property_exists($this,$k)) { return $this->$k; }
		if ($this->result && false !== ($v = $this->getResultProp($k))) {
			return $v;
		}
		if ('errorCode' === $k) { return null; }
		trigger_error("Undefined property {$k}");
	}

	public function getResultProp($k)
	{
		if ($this->result) {
			return self::getXmlNodeValue($this->result->body, $k);
		}
	}

	protected static function getXmlNodeValue($xml, $nodeName)
	{
		if ($xml && preg_match("#<{$nodeName}(\s[^>]*)?>(.*?)</{$nodeName}>#s", $xml, $m)) {
			return html_entity_decode($m[2], ENT_XML1);
		}
		return false;
	}

	// Validate configuration
	protected function validateProperties(array $props = array())
	{
		$props = array_merge($props, array('privateKey', 'privateKeyPass', 'privateCertificate'));

		foreach ($props as $k) {
			if (empty($this->$k) && empty($this->iDEAL->$k)) {
				trigger_error('Invalid iDEAL.' . $k);
				return false;
			}
		}

		if (empty($this->iDEAL->urlAcquirer)) {
			throw new \UnexpectedValueException('Invalid iDEAL.urlAcquirer');
		}

		if (!preg_match('/^[0-9]{9}$/', $this->iDEAL->merchantId)) {
			throw new \UnexpectedValueException('Invalid iDEAL.merchantId');
		}

		if (999999 < $this->iDEAL->merchantSubId || 0 > $this->iDEAL->merchantSubId) {
			throw new \UnexpectedValueException('Invalid iDEAL.merchantSubId');
		}
	}

	// Send GET/POST data through sockets
	protected function postRequest($type, $xml)
	{
		$headers = array(
			'Accept: text/html',
			'Accept: charset=ISO-8859-1',
			'Content-Type: text/html; charset=ISO-8859-1',
		);

		// Sign the XML body
		$xml = $this->signXML('<'.$type.' xmlns="http://www.idealdesk.com/ideal/messages/mer-acq/3.3.1" version="3.3.1">'
			.'<createDateTimestamp>'.$this->timestamp.'</createDateTimestamp>'
			.$xml
			."</{$type}>");

		// Prepend XML declaration
		$xml = '<?xml version="1.0" encoding="utf-8"?>'."\n".$xml;

		$request = \Poodle\HTTP\Request::factory();
		$request->timeout = 10;
		$this->result = $request->post($this->iDEAL->urlAcquirer, $xml, $headers);
		if ($this->result) {
			if ($this->__get('errorCode')) {
				throw new \Exception('Error '.$this->__get('errorCode') . ': ' . $this->__get('errorMessage').' - '.$this->__get('errorDetail'));
			}
		} else {
			throw new \Exception('Error while connecting to ' . $this->iDEAL->urlAcquirer);
		}

		$this->verifySignature();
	}

	protected static function getCertificateFingerprint($file)
	{
		$data = is_file($file) ? file_get_contents($file) : $file;
		if ($data) {
			$data = openssl_x509_read($data);
			if (!openssl_x509_export($data, $data)) {
				throw new \Exception('Error in certificate ' . $file);
			}
			$data = str_replace('-----BEGIN CERTIFICATE-----', '', $data);
			$data = str_replace('-----END CERTIFICATE-----', '', $data);
			return strtoupper(sha1(base64_decode($data)));
		} else {
			throw new \Exception('Cannot open certificate ' . $file);
		}
	}

	// Calculate signature of the given message
	protected function getSignature($message)
	{
		$file = $this->iDEAL->privateKey;
		$data = is_file($file) ? file_get_contents($file) : $file;
		if ($data) {
			if ($privateKey = openssl_get_privatekey($data, $this->iDEAL->privateKeyPass)) {
				$signature = '';
				if (openssl_sign($message, $signature, $privateKey)) {
					openssl_pkey_free($privateKey);
					return base64_encode($signature);
				} else {
					throw new \Exception('Error while signing message');
				}
			} else {
				throw new \Exception('Invalid passphrase for private key ' . $this->iDEAL->privateKey);
			}
		} else {
			throw new \Exception('Cannot open private key ' . $this->iDEAL->privateKey);
		}
	}

	protected function signXML($xml)
	{
		// Generate DigestValue
		$DigestValue = base64_encode(\Poodle\Hash::string('sha1', $xml, true));

		// Create SignedInfo
		$sig = '<SignedInfo xmlns="http://www.w3.org/2000/09/xmldsig#">'
			.'<CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></CanonicalizationMethod>'
			.'<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod>'
			.'<Reference URI="">'
			.'<Transforms>'
			.'<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"></Transform>'
			.'</Transforms>'
			.'<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod>'
			.'<DigestValue>'.$DigestValue.'</DigestValue>'
			.'</Reference>'
			.'</SignedInfo>';

		// Return Signed XML
		$p = strrpos($xml,'</');
		return substr($xml,0,$p)
			.'<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">'
			.$sig
			.'<SignatureValue>'.$this->getSignature($sig).'</SignatureValue>'
			.'<KeyInfo><KeyName>'.self::getCertificateFingerprint($this->iDEAL->privateCertificate).'</KeyName></KeyInfo>'
			.'</Signature>'
			.substr($xml,$p);
	}

	// Verify signature of the response message
	protected function verifySignature()
	{
		$signature = base64_decode($this->__get('SignatureValue'));
		if (!$signature) {
			throw new \UnexpectedValueException('Invalid iDEAL.Response.SignatureValue');
		}

		if (!preg_match('#<SignedInfo>.*</SignedInfo>#', $this->result->body, $signedinfo)) {
			throw new \UnexpectedValueException('Invalid iDEAL.Response.SignedInfo');
		}
		$signedinfo = $signedinfo[1];

		$fingerprint = strtoupper($this->__get('KeyName'));
		if (!$fingerprint) {
			throw new \UnexpectedValueException('Invalid iDEAL.Response.KeyName');
		}

		$certificate = null;
		$files = glob(__DIR__ . '/certificates/*.cer');
		foreach ($files as $file) {
			if (self::getCertificateFingerprint($file) === $fingerprint) {
				$certificate = file_get_contents($file);
				break;
			}
		}
		if (!$certificate) {
			throw new \UnexpectedValueException('No Certificate matching iDEAL.Response.KeyName');
		}

		if ($publicKey = openssl_pkey_get_public($certificate)) {
			if (openssl_verify($signedinfo, $signature, $publicKey)); {
				openssl_pkey_free($publicKey);
				return true;
			}

			openssl_pkey_free($publicKey);
			throw new \UnexpectedValueException('Invalid iDEAL.Response.Signature');
		}

		throw new \UnexpectedValueException('Failed to read iDEAL Certificate public key');
	}

}
