<?php
//---------------------------------------------------------------
// QRCode
//
// Copyright (c) 2009 Kazuhiko Arase
//
// URL: http://www.d-project.com/
//
// Licensed under the MIT license:
//   http://www.opensource.org/licenses/mit-license.php
//
// The word "QR Code" is registered trademark of
// DENSO WAVE INCORPORATED
//   http://www.denso-wave.com/qrcode/faqpatent-e.html
//
// Modified by MH X Solutions for speed, PHP 7 and new features:
//	- QRCode->createHTML()
//	- QRCode->createUnicode()
//	- QRCode->__toString()
//---------------------------------------------------------------------

namespace Poodle;

class QRCode
{

	const
		ERROR_CORRECT_LEVEL_L = 1, // 7%
		ERROR_CORRECT_LEVEL_M = 0, // 15%
		ERROR_CORRECT_LEVEL_Q = 3, // 25%
		ERROR_CORRECT_LEVEL_H = 2; // 30%

	protected
		$typeNumber,
		$modules,
		$moduleCount,
		$errorCorrectLevel,
		$qrDataList;

	function __construct()
	{
		$this->typeNumber = 1;
		$this->errorCorrectLevel = static::ERROR_CORRECT_LEVEL_H;
		$this->qrDataList = array();
	}

	function __toString()
	{
		return $this->createUnicode();
	}

	function __get($k)
	{
		if (property_exists($this, $k)) {
			return $this->$k;
		}
	}

	function __set($k, $v)
	{
		switch ($k)
		{
		case 'typeNumber':
			$this->setTypeNumber($v);
			break;
		case 'errorCorrectLevel':
			$this->setErrorCorrectLevel($v);
			break;
		}
	}

	public function setTypeNumber($typeNumber)
	{
		if (1 > $typeNumber || 40 < $typeNumber) {
			throw new \OutOfBoundsException("Invalid typeNumber: {$typeNumber}");
		}
		$this->typeNumber = $typeNumber;
		return $this;
	}

	public function setErrorCorrectLevel($errorCorrectLevel)
	{
		if (0 > $errorCorrectLevel || 3 < $errorCorrectLevel) {
			throw new \OutOfBoundsException("Invalid errorCorrectLevel: {$errorCorrectLevel}");
		}
		$this->errorCorrectLevel = $errorCorrectLevel;
		return $this;
	}

	public function addData($data, $mode = 0)
	{
		$this->qrDataList[] = new QRData($data, $mode);
	}

	function clearData()
	{
		$this->qrDataList = array();
	}

	function getDataCount()
	{
		return count($this->qrDataList);
	}

	function getData($index)
	{
		return $this->qrDataList[$index];
	}

	// used for converting fg/bg colors (e.g. #0000ff = 0x0000FF)
	// added 2015.07.27 ~ DoktorJ
	protected function hex2rgb($hex = 0x0)
	{
		return array(
			'r' => floor($hex / 65536),
			'g' => floor($hex / 256) % 256,
			'b' => $hex % 256
		);
	}

	protected function makeImpl($test, $maskPattern)
	{
		$this->moduleCount = $this->typeNumber * 4 + 17;

		$this->modules = array_fill(0, $this->moduleCount, array_fill(0, $this->moduleCount, null));

		$this->setupPositionProbePattern(0, 0);
		$this->setupPositionProbePattern($this->moduleCount - 7, 0);
		$this->setupPositionProbePattern(0, $this->moduleCount - 7);

		$this->setupPositionAdjustPattern();
		$this->setupTimingPattern();

		$this->setupTypeInfo($test, $maskPattern);

		if ($this->typeNumber >= 7) {
			$this->setupTypeNumber($test);
		}

		$dataArray = $this->qrDataList;

		$data = self::createData($this->typeNumber, $this->errorCorrectLevel, $dataArray);

		$this->mapData($data, $maskPattern);
	}

	protected function setupPositionProbePattern($row, $col)
	{
		for ($r = -1; $r <= 7; ++$r) {
			for ($c = -1; $c <= 7; ++$c) {
				if ($row + $r <= -1 || $this->moduleCount <= $row + $r
						|| $col + $c <= -1 || $this->moduleCount <= $col + $c) {
					continue;
				}
				$this->modules[$row + $r][$col + $c] = (
					(0 <= $r && $r <= 6 && ($c == 0 || $c == 6))
					|| (0 <= $c && $c <= 6 && ($r == 0 || $r == 6))
					|| (2 <= $r && $r <= 4 && 2 <= $c && $c <= 4)
				);
			}
		}
	}

	protected function getBestMaskPattern()
	{
		$minLostPoint = 0;
		$pattern = 0;
		for ($i = 0; $i < 8; ++$i) {
			$this->makeImpl(true, $i);
			$lostPoint = QRUtil::getLostPoint($this);
			if ($i == 0 || $minLostPoint > $lostPoint) {
				$minLostPoint = $lostPoint;
				$pattern = $i;
			}
		}
		return $pattern;
	}

	protected function setupTimingPattern()
	{
		for ($r = 8; $r < $this->moduleCount - 8; ++$r) {
			if ($this->modules[$r][6] !== null) {
				continue;
			}
			$this->modules[$r][6] = ($r % 2 == 0);
		}

		for ($c = 8; $c < $this->moduleCount - 8; ++$c) {
			if ($this->modules[6][$c] !== null) {
				continue;
			}
			$this->modules[6][$c] = ($c % 2 == 0);
		}
	}

	protected function setupPositionAdjustPattern()
	{
		$pos = QRUtil::getPatternPosition($this->typeNumber);
		$cnt = count($pos);
		for ($i = 0; $i < $cnt; ++$i) {
			for ($j = 0; $j < $cnt; ++$j) {
				$row = $pos[$i];
				$col = $pos[$j];
				if ($this->modules[$row][$col] !== null) {
					continue;
				}
				for ($r = -2; $r <= 2; ++$r) {
					for ($c = -2; $c <= 2; ++$c) {
						$this->modules[$row + $r][$col + $c] = ($r == -2 || $r == 2 || $c == -2 || $c == 2 || ($r == 0 && $c == 0));
					}
				}
			}
		}
	}

	protected function setupTypeNumber($test)
	{
		$bits = QRUtil::getBCHTypeNumber($this->typeNumber);
		for ($i = 0; $i < 18; ++$i) {
			$mod = (!$test && (($bits >> $i) & 1) == 1);
			$this->modules[(int)floor($i / 3)][$i % 3 + $this->moduleCount - 8 - 3] = $mod;
		}
		for ($i = 0; $i < 18; ++$i) {
			$mod = (!$test && (($bits >> $i) & 1) == 1);
			$this->modules[$i % 3 + $this->moduleCount - 8 - 3][floor($i / 3)] = $mod;
		}
	}

	protected function setupTypeInfo($test, $maskPattern)
	{
		$bits = QRUtil::getBCHTypeInfo(($this->errorCorrectLevel << 3) | $maskPattern);

		// vertical
		for ($i = 0; $i < 15; ++$i) {
			$mod = (!$test && (($bits >> $i) & 1) == 1);
			if ($i < 6) {
				$this->modules[$i][8] = $mod;
			} else if ($i < 8) {
				$this->modules[$i + 1][8] = $mod;
			} else {
				$this->modules[$this->moduleCount - 15 + $i][8] = $mod;
			}
		}

		// horizontal
		for ($i = 0; $i < 15; ++$i) {
			$mod = (!$test && (($bits >> $i) & 1) == 1);
			if ($i < 8) {
				$this->modules[8][$this->moduleCount - $i - 1] = $mod;
			} else if ($i < 9) {
				$this->modules[8][15 - $i - 1 + 1] = $mod;
			} else {
				$this->modules[8][15 - $i - 1] = $mod;
			}
		}

		// fixed module
		$this->modules[$this->moduleCount - 8][8] = !$test;
	}

	protected function mapData(&$data, $maskPattern)
	{
		$inc = -1;
		$row = $this->moduleCount - 1;
		$bitIndex = 7;
		$byteIndex = 0;
		$length = count($data);
		for ($col = $this->moduleCount - 1; $col > 0; $col -= 2) {
			if ($col == 6) --$col;
			while (true) {
				for ($c = 0; $c < 2; ++$c) {
					if ($this->modules[$row][$col - $c] === null) {
						$dark = false;
						if ($byteIndex < $length) {
							$dark = ((($data[$byteIndex] >> $bitIndex) & 1) == 1);
						}
						$mask = QRUtil::getMask($maskPattern, $row, $col - $c);
						if ($mask) {
							$dark = !$dark;
						}
						$this->modules[$row][$col - $c] = $dark;
						--$bitIndex;
						if ($bitIndex == -1) {
							++$byteIndex;
							$bitIndex = 7;
						}
					}
				}
				$row += $inc;
				if ($row < 0 || $this->moduleCount <= $row) {
					$row -= $inc;
					$inc = -$inc;
					break;
				}
			}
		}
	}

	protected static function createBytes(&$buffer, &$rsBlocks)
	{
		$offset = 0;
		$maxDcCount = 0;
		$maxEcCount = 0;
		$dcdata = array();
		$ecdata = array();
		$cntBlocks = count($rsBlocks);

		for ($r = 0; $r < $cntBlocks; ++$r) {

			$dcCount = $rsBlocks[$r]->getDataCount();
			$ecCount = $rsBlocks[$r]->getTotalCount() - $dcCount;

			$maxDcCount = max($maxDcCount, $dcCount);
			$maxEcCount = max($maxEcCount, $ecCount);

			$dcdata[$r] = array();
			for ($i = 0; $i < $dcCount; ++$i) {
				$bdata = $buffer->getBuffer();
				$dcdata[$r][$i] = 0xff & $bdata[$i + $offset];
			}
			$offset += $dcCount;

			$rsPoly = QRUtil::getErrorCorrectPolynomial($ecCount);
			$rawPoly = new QRPolynomial($dcdata[$r], $rsPoly->getLength() - 1);

			$modPoly = $rawPoly->mod($rsPoly);
			$rsCount = $rsPoly->getLength() - 1;
			$ecdata[$r] = array();
			for ($i = 0; $i < $rsCount; ++$i) {
				$modIndex = $i + $modPoly->getLength() - $rsCount;
				$ecdata[$r][$i] = ($modIndex >= 0)? $modPoly->get($modIndex) : 0;
			}
		}

		$totalCodeCount = 0;
		for ($i = 0; $i < $cntBlocks; ++$i) {
			$totalCodeCount += $rsBlocks[$i]->getTotalCount();
		}

		$data = array_fill(0, $totalCodeCount, null);

		$index = 0;

		for ($i = 0; $i < $maxDcCount; ++$i) {
			for ($r = 0; $r < $cntBlocks; ++$r) {
				if ($i < count($dcdata[$r])) {
					$data[$index++] = $dcdata[$r][$i];
				}
			}
		}

		for ($i = 0; $i < $maxEcCount; ++$i) {
			for ($r = 0; $r < $cntBlocks; ++$r) {
				if ($i < count($ecdata[$r])) {
					$data[$index++] = $ecdata[$r][$i];
				}
			}
		}

		return $data;
	}

	protected static function createData($typeNumber, $errorCorrectLevel, $dataArray)
	{
		$rsBlocks = QRRSBlock::getRSBlocks($typeNumber, $errorCorrectLevel);
		$buffer = new QRBitBuffer();
		$cnt = count($dataArray);
		for ($i = 0; $i < $cnt; ++$i) {
			$data = $dataArray[$i];
			$buffer->put($data->getMode(), 4);
			$buffer->put($data->getLength(), $data->getLengthInBits($typeNumber));
			$data->write($buffer);
		}

		$totalDataCount = 0;
		$cnt = count($rsBlocks);
		for ($i = 0; $i < $cnt; ++$i) {
			$totalDataCount += $rsBlocks[$i]->getDataCount();
		}

		if ($buffer->getLengthInBits() > $totalDataCount * 8) {
			throw new \OutOfBoundsException("code length overflow. ("
				. $buffer->getLengthInBits()
				. ">"
				.  $totalDataCount * 8
				. ")");
		}

		// end code.
		if ($buffer->getLengthInBits() + 4 <= $totalDataCount * 8) {
			$buffer->put(0, 4);
		}

		// padding
		while ($buffer->getLengthInBits() % 8 != 0) {
			$buffer->putBit(false);
		}

		// padding
		while (true) {

			if ($buffer->getLengthInBits() >= $totalDataCount * 8) {
				break;
			}
			$buffer->put(0xEC, 8); // QR_PAD0

			if ($buffer->getLengthInBits() >= $totalDataCount * 8) {
				break;
			}
			$buffer->put(0x11, 8); // QR_PAD1
		}

		return self::createBytes($buffer, $rsBlocks);
	}

	public function isDark($row, $col)
	{
		return ($this->modules[$row][$col] !== null) && $this->modules[$row][$col];
	}

	public function getModuleCount()
	{
		return $this->moduleCount;
	}

	function make()
	{
		$this->makeImpl(false, $this->getBestMaskPattern());
	}

	public static function getMinimumQRCode($data, $errorCorrectLevel)
	{
		$qr = new QRCode();
		$qr->setErrorCorrectLevel($errorCorrectLevel);
		$qr->addData($data);

		$qrData = $qr->getData(0);
		$length = $qrData->getLength();
		$mode   = $qrData->getMode();

		for ($typeNumber = 1; $typeNumber <= 10; ++$typeNumber) {
			if ($length <= QRUtil::getMaxLength($typeNumber, $mode, $errorCorrectLevel)) {
				$qr->setTypeNumber($typeNumber);
				break;
			}
		}

		$qr->make();

		return $qr;
	}

	public function createUnicode()
	{
		$qrUnicode = '';
		$moduleCount = $this->getModuleCount();
		for ($r = 0; $r < $moduleCount; $r += 2) {
			for ($c = 0; $c < $moduleCount; ++$c) {
				$w2 = ($r+1 >= $moduleCount || !$this->isDark($r+1, $c));
				if ($this->isDark($r, $c)) {
					$qrUnicode .= ($w2 ? '▀' : '█'); // upper half block : full block
				} else {
					$qrUnicode .= ($w2 ? ' ' : '▄'); // nobreak space : lower half block
				}
			}
			$qrUnicode .= "\n";
		}
		return $qrUnicode;
	}

	// added $fg (foreground), $bg (background), and $bgtrans (use transparent bg) parameters
	// also added some simple error checking on parameters
	// updated 2015.07.27 ~ DoktorJ
	public function createImage($size = 2, $margin = 2, $fg = 0x000000, $bg = 0xFFFFFF, $bgtrans = false)
	{
		// size/margin EC
		if (!is_numeric($size)) $size = 2;
		if (!is_numeric($margin)) $margin = 2;
		if ($size < 1) $size = 1;
		if ($margin < 0) $margin = 0;
		$moduleCount = $this->getModuleCount();
		$image_size = $moduleCount * $size + $margin * 2;

		$image = imagecreatetruecolor($image_size, $image_size);

		// fg/bg EC
		if ($fg < 0 || $fg > 0xFFFFFF) $fg = 0x0;
		if ($bg < 0 || $bg > 0xFFFFFF) $bg = 0xFFFFFF;

		// convert hexadecimal RGB to arrays for imagecolorallocate
		$fgrgb = $this->hex2rgb($fg);
		$bgrgb = $this->hex2rgb($bg);

		// replace $black and $white with $fgc and $bgc
		$fgc = imagecolorallocate($image, $fgrgb['r'], $fgrgb['g'], $fgrgb['b']);
		$bgc = imagecolorallocate($image, $bgrgb['r'], $bgrgb['g'], $bgrgb['b']);
		if ($bgtrans) imagecolortransparent($image, $bgc);

		// update $white to $bgc
		imagefilledrectangle($image, 0, 0, $image_size, $image_size, $bgc);

		for ($r = 0; $r < $moduleCount; ++$r) {
			for ($c = 0; $c < $moduleCount; ++$c) {
				if ($this->isDark($r, $c)) {
					// update $black to $fgc
					imagefilledrectangle($image,
						$margin + $c * $size,
						$margin + $r * $size,
						$margin + ($c + 1) * $size - 1,
						$margin + ($r + 1) * $size - 1,
						$fgc);
				}
			}
		}

		return $image;
	}

	public function createImageBlob($size = 2, $margin = 2, $fg = 0x000000, $bg = 0xFFFFFF, $bgtrans = false)
	{
		$img = $this->createImage($size, $margin, $fg, $bg, $bgtrans);
		ob_start();
		imagesavealpha($img, true);
		if (!imagepng($img, null, 9)) {
			ob_end_clean();
			throw new \Exception('Failed to generate image blob');
		}
		return ob_get_clean();
	}

	public function createHTML($size = "2px")
	{
		$moduleCount = $this->getModuleCount();
		$style = "border-style:none;border-collapse:collapse;margin:0px;padding:0px;";
		$html = "<table style='$style'>";
		for ($r = 0; $r < $moduleCount; ++$r) {
			$html .= "<tr style='$style'>";
			for ($c = 0; $c < $moduleCount; ++$c) {
				$color = $this->isDark($r, $c)? "#000000" : "#ffffff";
				$html .= "<td style='$style;width:$size;height:$size;background-color:$color'></td>";
			}
			$html .= "</tr>";
		}
		return $html . "</table>";
	}
}

//---------------------------------------------------------------
// QRUtil
//---------------------------------------------------------------

abstract class QRUtil {

	const
		MASK_PATTERN000 = 0,
		MASK_PATTERN001 = 1,
		MASK_PATTERN010 = 2,
		MASK_PATTERN011 = 3,
		MASK_PATTERN100 = 4,
		MASK_PATTERN101 = 5,
		MASK_PATTERN110 = 6,
		MASK_PATTERN111 = 7,

		G15 = 1335,
		G18 = 7973,
		G15_MASK = 21522;

	static $QR_MAX_LENGTH = array(
		array(array(41,  25,  17,  10),  array(34,  20,  14,  8),   array(27,  16,  11,  7),  array(17,  10,  7,   4)),
		array(array(77,  47,  32,  20),  array(63,  38,  26,  16),  array(48,  29,  20,  12), array(34,  20,  14,  8)),
		array(array(127, 77,  53,  32),  array(101, 61,  42,  26),  array(77,  47,  32,  20), array(58,  35,  24,  15)),
		array(array(187, 114, 78,  48),  array(149, 90,  62,  38),  array(111, 67,  46,  28), array(82,  50,  34,  21)),
		array(array(255, 154, 106, 65),  array(202, 122, 84,  52),  array(144, 87,  60,  37), array(106, 64,  44,  27)),
		array(array(322, 195, 134, 82),  array(255, 154, 106, 65),  array(178, 108, 74,  45), array(139, 84,  58,  36)),
		array(array(370, 224, 154, 95),  array(293, 178, 122, 75),  array(207, 125, 86,  53), array(154, 93,  64,  39)),
		array(array(461, 279, 192, 118), array(365, 221, 152, 93),  array(259, 157, 108, 66), array(202, 122, 84,  52)),
		array(array(552, 335, 230, 141), array(432, 262, 180, 111), array(312, 189, 130, 80), array(235, 143, 98,  60)),
		array(array(652, 395, 271, 167), array(513, 311, 213, 131), array(364, 221, 151, 93), array(288, 174, 119, 74))
	);

	static $QR_PATTERN_POSITION_TABLE = array(
		array(),
		array(6, 18),
		array(6, 22),
		array(6, 26),
		array(6, 30),
		array(6, 34),
		array(6, 22, 38),
		array(6, 24, 42),
		array(6, 26, 46),
		array(6, 28, 50),
		array(6, 30, 54),
		array(6, 32, 58),
		array(6, 34, 62),
		array(6, 26, 46, 66),
		array(6, 26, 48, 70),
		array(6, 26, 50, 74),
		array(6, 30, 54, 78),
		array(6, 30, 56, 82),
		array(6, 30, 58, 86),
		array(6, 34, 62, 90),
		array(6, 28, 50, 72, 94),
		array(6, 26, 50, 74, 98),
		array(6, 30, 54, 78, 102),
		array(6, 28, 54, 80, 106),
		array(6, 32, 58, 84, 110),
		array(6, 30, 58, 86, 114),
		array(6, 34, 62, 90, 118),
		array(6, 26, 50, 74, 98, 122),
		array(6, 30, 54, 78, 102, 126),
		array(6, 26, 52, 78, 104, 130),
		array(6, 30, 56, 82, 108, 134),
		array(6, 34, 60, 86, 112, 138),
		array(6, 30, 58, 86, 114, 142),
		array(6, 34, 62, 90, 118, 146),
		array(6, 30, 54, 78, 102, 126, 150),
		array(6, 24, 50, 76, 102, 128, 154),
		array(6, 28, 54, 80, 106, 132, 158),
		array(6, 32, 58, 84, 110, 136, 162),
		array(6, 26, 54, 82, 110, 138, 166),
		array(6, 30, 58, 86, 114, 142, 170)
	);

	static function getPatternPosition($typeNumber)
	{
		return self::$QR_PATTERN_POSITION_TABLE[$typeNumber - 1];
	}

	static function getMaxLength($typeNumber, $mode, $errorCorrectLevel)
	{
		$t = $typeNumber - 1;
		$e = 0;
		$m = 0;

		switch ($errorCorrectLevel)
		{
		case QRCode::ERROR_CORRECT_LEVEL_L : $e = 0; break;
		case QRCode::ERROR_CORRECT_LEVEL_M : $e = 1; break;
		case QRCode::ERROR_CORRECT_LEVEL_Q : $e = 2; break;
		case QRCode::ERROR_CORRECT_LEVEL_H : $e = 3; break;
		default :
			throw new \OutOfBoundsException("e:{$errorCorrectLevel}");
		}

		switch ($mode)
		{
		case QRData::MODE_NUMBER    : $m = 0; break;
		case QRData::MODE_ALPHA_NUM : $m = 1; break;
		case QRData::MODE_8BIT_BYTE : $m = 2; break;
		case QRData::MODE_KANJI     : $m = 3; break;
		default :
			throw new \OutOfBoundsException("m:{$mode}");
		}

		return self::$QR_MAX_LENGTH[$t][$e][$m];
	}

	static function getErrorCorrectPolynomial($errorCorrectLength)
	{
		$a = new QRPolynomial(array(1));
		for ($i = 0; $i < $errorCorrectLength; ++$i) {
			$a = $a->multiply(new QRPolynomial(array(1, QRMath::gexp($i))));
		}
		return $a;
	}

	static function getMask($maskPattern, $i, $j)
	{
		switch ($maskPattern)
		{
		case self::MASK_PATTERN000 : return ($i + $j) % 2 == 0;
		case self::MASK_PATTERN001 : return $i % 2 == 0;
		case self::MASK_PATTERN010 : return $j % 3 == 0;
		case self::MASK_PATTERN011 : return ($i + $j) % 3 == 0;
		case self::MASK_PATTERN100 : return (floor($i / 2) + floor($j / 3)) % 2 == 0;
		case self::MASK_PATTERN101 : return ($i * $j) % 2 + ($i * $j) % 3 == 0;
		case self::MASK_PATTERN110 : return (($i * $j) % 2 + ($i * $j) % 3) % 2 == 0;
		case self::MASK_PATTERN111 : return (($i * $j) % 3 + ($i + $j) % 2) % 2 == 0;
		default :
			throw new \OutOfBoundsException("mask:{$maskPattern}");
		}
	}

	static function getLostPoint($qrCode)
	{
		$moduleCount = $qrCode->getModuleCount();
		$lostPoint = 0;

		// LEVEL1
		for ($row = 0; $row < $moduleCount; ++$row) {
			for ($col = 0; $col < $moduleCount; ++$col) {
				$sameCount = 0;
				$dark = $qrCode->isDark($row, $col);
				for ($r = -1; $r <= 1; ++$r) {
					if ($row + $r < 0 || $moduleCount <= $row + $r) {
						continue;
					}
					for ($c = -1; $c <= 1; ++$c) {
						if ($col + $c < 0 || $moduleCount <= $col + $c || ($r == 0 && $c == 0)) {
							continue;
						}
						if ($dark == $qrCode->isDark($row + $r, $col + $c)) {
							++$sameCount;
						}
					}
				}
				if ($sameCount > 5) {
					$lostPoint += (3 + $sameCount - 5);
				}
			}
		}

		// LEVEL2
		for ($row = 0; $row < $moduleCount - 1; ++$row) {
			for ($col = 0; $col < $moduleCount - 1; ++$col) {
				$count = 0;
				if ($qrCode->isDark($row,     $col    )) ++$count;
				if ($qrCode->isDark($row + 1, $col    )) ++$count;
				if ($qrCode->isDark($row,     $col + 1)) ++$count;
				if ($qrCode->isDark($row + 1, $col + 1)) ++$count;
				if ($count == 0 || $count == 4) {
					$lostPoint += 3;
				}
			}
		}

		// LEVEL3
		for ($row = 0; $row < $moduleCount; ++$row) {
			for ($col = 0; $col < $moduleCount - 6; ++$col) {
				if ($qrCode->isDark($row, $col)
						&& !$qrCode->isDark($row, $col + 1)
						&&  $qrCode->isDark($row, $col + 2)
						&&  $qrCode->isDark($row, $col + 3)
						&&  $qrCode->isDark($row, $col + 4)
						&& !$qrCode->isDark($row, $col + 5)
						&&  $qrCode->isDark($row, $col + 6)) {
					$lostPoint += 40;
				}
			}
		}
		for ($col = 0; $col < $moduleCount; ++$col) {
			for ($row = 0; $row < $moduleCount - 6; ++$row) {
				if ($qrCode->isDark($row, $col)
						&& !$qrCode->isDark($row + 1, $col)
						&&  $qrCode->isDark($row + 2, $col)
						&&  $qrCode->isDark($row + 3, $col)
						&&  $qrCode->isDark($row + 4, $col)
						&& !$qrCode->isDark($row + 5, $col)
						&&  $qrCode->isDark($row + 6, $col)) {
					$lostPoint += 40;
				}
			}
		}

		// LEVEL4
		$darkCount = 0;
		for ($col = 0; $col < $moduleCount; ++$col) {
			for ($row = 0; $row < $moduleCount; ++$row) {
				if ($qrCode->isDark($row, $col)) {
					++$darkCount;
				}
			}
		}
		$ratio = abs(100 * $darkCount / $moduleCount / $moduleCount - 50) / 5;
		$lostPoint += $ratio * 10;

		return $lostPoint;
	}

	static function getBCHTypeInfo($data)
	{
		$d = $data << 10;
		$g15 = static::getBCHDigit(self::G15);
		while (true) {
			$v = static::getBCHDigit($d) - $g15;
			if ($v < 0) {
				break;
			}
			$d ^= (self::G15 << $v);
		}
		return (($data << 10) | $d) ^ self::G15_MASK;
	}

	static function getBCHTypeNumber($data)
	{
		$d = $data << 12;
		$g18 = static::getBCHDigit(self::G18);
		while (true) {
			$v = static::getBCHDigit($d) - $g18;
			if ($v < 0) {
				break;
			}
			$d ^= (self::G18 << $v);
		}
		return ($data << 12) | $d;
	}

	protected static function getBCHDigit($data)
	{
		$digit = 0;
		while ($data != 0) {
			++$digit;
			$data >>= 1;
		}
		return $digit;
	}
}

//---------------------------------------------------------------
// QRRSBlock
//---------------------------------------------------------------

class QRRSBlock {

	protected $totalCount;
	protected $dataCount;

	static $QR_RS_BLOCK_TABLE = array(

		// L
		// M
		// Q
		// H

		// 1
		array(1, 26, 19),
		array(1, 26, 16),
		array(1, 26, 13),
		array(1, 26, 9),

		// 2
		array(1, 44, 34),
		array(1, 44, 28),
		array(1, 44, 22),
		array(1, 44, 16),

		// 3
		array(1, 70, 55),
		array(1, 70, 44),
		array(2, 35, 17),
		array(2, 35, 13),

		// 4
		array(1, 100, 80),
		array(2, 50, 32),
		array(2, 50, 24),
		array(4, 25, 9),

		// 5
		array(1, 134, 108),
		array(2, 67, 43),
		array(2, 33, 15, 2, 34, 16),
		array(2, 33, 11, 2, 34, 12),

		// 6
		array(2, 86, 68),
		array(4, 43, 27),
		array(4, 43, 19),
		array(4, 43, 15),

		// 7
		array(2, 98, 78),
		array(4, 49, 31),
		array(2, 32, 14, 4, 33, 15),
		array(4, 39, 13, 1, 40, 14),

		// 8
		array(2, 121, 97),
		array(2, 60, 38, 2, 61, 39),
		array(4, 40, 18, 2, 41, 19),
		array(4, 40, 14, 2, 41, 15),

		// 9
		array(2, 146, 116),
		array(3, 58, 36, 2, 59, 37),
		array(4, 36, 16, 4, 37, 17),
		array(4, 36, 12, 4, 37, 13),

		// 10
		array(2, 86, 68, 2, 87, 69),
		array(4, 69, 43, 1, 70, 44),
		array(6, 43, 19, 2, 44, 20),
		array(6, 43, 15, 2, 44, 16),

		// 11
		array(4, 101, 81),
		array(1, 80, 50, 4, 81, 51),
		array(4, 50, 22, 4, 51, 23),
		array(3, 36, 12, 8, 37, 13),

		// 12
		array(2, 116, 92, 2, 117, 93),
		array(6, 58, 36, 2, 59, 37),
		array(4, 46, 20, 6, 47, 21),
		array(7, 42, 14, 4, 43, 15),

		// 13
		array(4, 133, 107),
		array(8, 59, 37, 1, 60, 38),
		array(8, 44, 20, 4, 45, 21),
		array(12, 33, 11, 4, 34, 12),

		// 14
		array(3, 145, 115, 1, 146, 116),
		array(4, 64, 40, 5, 65, 41),
		array(11, 36, 16, 5, 37, 17),
		array(11, 36, 12, 5, 37, 13),

		// 15
		array(5, 109, 87, 1, 110, 88),
		array(5, 65, 41, 5, 66, 42),
		array(5, 54, 24, 7, 55, 25),
		array(11, 36, 12, 7, 37, 13),

		// 16
		array(5, 122, 98, 1, 123, 99),
		array(7, 73, 45, 3, 74, 46),
		array(15, 43, 19, 2, 44, 20),
		array(3, 45, 15, 13, 46, 16),

		// 17
		array(1, 135, 107, 5, 136, 108),
		array(10, 74, 46, 1, 75, 47),
		array(1, 50, 22, 15, 51, 23),
		array(2, 42, 14, 17, 43, 15),

		// 18
		array(5, 150, 120, 1, 151, 121),
		array(9, 69, 43, 4, 70, 44),
		array(17, 50, 22, 1, 51, 23),
		array(2, 42, 14, 19, 43, 15),

		// 19
		array(3, 141, 113, 4, 142, 114),
		array(3, 70, 44, 11, 71, 45),
		array(17, 47, 21, 4, 48, 22),
		array(9, 39, 13, 16, 40, 14),

		// 20
		array(3, 135, 107, 5, 136, 108),
		array(3, 67, 41, 13, 68, 42),
		array(15, 54, 24, 5, 55, 25),
		array(15, 43, 15, 10, 44, 16),

		// 21
		array(4, 144, 116, 4, 145, 117),
		array(17, 68, 42),
		array(17, 50, 22, 6, 51, 23),
		array(19, 46, 16, 6, 47, 17),

		// 22
		array(2, 139, 111, 7, 140, 112),
		array(17, 74, 46),
		array(7, 54, 24, 16, 55, 25),
		array(34, 37, 13),

		// 23
		array(4, 151, 121, 5, 152, 122),
		array(4, 75, 47, 14, 76, 48),
		array(11, 54, 24, 14, 55, 25),
		array(16, 45, 15, 14, 46, 16),

		// 24
		array(6, 147, 117, 4, 148, 118),
		array(6, 73, 45, 14, 74, 46),
		array(11, 54, 24, 16, 55, 25),
		array(30, 46, 16, 2, 47, 17),

		// 25
		array(8, 132, 106, 4, 133, 107),
		array(8, 75, 47, 13, 76, 48),
		array(7, 54, 24, 22, 55, 25),
		array(22, 45, 15, 13, 46, 16),

		// 26
		array(10, 142, 114, 2, 143, 115),
		array(19, 74, 46, 4, 75, 47),
		array(28, 50, 22, 6, 51, 23),
		array(33, 46, 16, 4, 47, 17),

		// 27
		array(8, 152, 122, 4, 153, 123),
		array(22, 73, 45, 3, 74, 46),
		array(8, 53, 23, 26, 54, 24),
		array(12, 45, 15, 28, 46, 16),

		// 28
		array(3, 147, 117, 10, 148, 118),
		array(3, 73, 45, 23, 74, 46),
		array(4, 54, 24, 31, 55, 25),
		array(11, 45, 15, 31, 46, 16),

		// 29
		array(7, 146, 116, 7, 147, 117),
		array(21, 73, 45, 7, 74, 46),
		array(1, 53, 23, 37, 54, 24),
		array(19, 45, 15, 26, 46, 16),

		// 30
		array(5, 145, 115, 10, 146, 116),
		array(19, 75, 47, 10, 76, 48),
		array(15, 54, 24, 25, 55, 25),
		array(23, 45, 15, 25, 46, 16),

		// 31
		array(13, 145, 115, 3, 146, 116),
		array(2, 74, 46, 29, 75, 47),
		array(42, 54, 24, 1, 55, 25),
		array(23, 45, 15, 28, 46, 16),

		// 32
		array(17, 145, 115),
		array(10, 74, 46, 23, 75, 47),
		array(10, 54, 24, 35, 55, 25),
		array(19, 45, 15, 35, 46, 16),

		// 33
		array(17, 145, 115, 1, 146, 116),
		array(14, 74, 46, 21, 75, 47),
		array(29, 54, 24, 19, 55, 25),
		array(11, 45, 15, 46, 46, 16),

		// 34
		array(13, 145, 115, 6, 146, 116),
		array(14, 74, 46, 23, 75, 47),
		array(44, 54, 24, 7, 55, 25),
		array(59, 46, 16, 1, 47, 17),

		// 35
		array(12, 151, 121, 7, 152, 122),
		array(12, 75, 47, 26, 76, 48),
		array(39, 54, 24, 14, 55, 25),
		array(22, 45, 15, 41, 46, 16),

		// 36
		array(6, 151, 121, 14, 152, 122),
		array(6, 75, 47, 34, 76, 48),
		array(46, 54, 24, 10, 55, 25),
		array(2, 45, 15, 64, 46, 16),

		// 37
		array(17, 152, 122, 4, 153, 123),
		array(29, 74, 46, 14, 75, 47),
		array(49, 54, 24, 10, 55, 25),
		array(24, 45, 15, 46, 46, 16),

		// 38
		array(4, 152, 122, 18, 153, 123),
		array(13, 74, 46, 32, 75, 47),
		array(48, 54, 24, 14, 55, 25),
		array(42, 45, 15, 32, 46, 16),

		// 39
		array(20, 147, 117, 4, 148, 118),
		array(40, 75, 47, 7, 76, 48),
		array(43, 54, 24, 22, 55, 25),
		array(10, 45, 15, 67, 46, 16),

		// 40
		array(19, 148, 118, 6, 149, 119),
		array(18, 75, 47, 31, 76, 48),
		array(34, 54, 24, 34, 55, 25),
		array(20, 45, 15, 61, 46, 16)
	);

	function __construct($totalCount, $dataCount)
	{
		$this->totalCount = $totalCount;
		$this->dataCount  = $dataCount;
	}

	function getDataCount()
	{
		return $this->dataCount;
	}

	function getTotalCount()
	{
		return $this->totalCount;
	}

	static function getRSBlocks($typeNumber, $errorCorrectLevel)
	{
		$rsBlock = static::getRsBlockTable($typeNumber, $errorCorrectLevel);
		$length = count($rsBlock) / 3;

		$list = array();

		for ($i = 0; $i < $length; ++$i) {

			$count = $rsBlock[$i * 3 + 0];
			$totalCount = $rsBlock[$i * 3 + 1];
			$dataCount  = $rsBlock[$i * 3 + 2];

			for ($j = 0; $j < $count; ++$j) {
				$list[] = new static($totalCount, $dataCount);
			}
		}

		return $list;
	}

	static function getRsBlockTable($typeNumber, $errorCorrectLevel)
	{
		switch ($errorCorrectLevel)
		{
		case QRCode::ERROR_CORRECT_LEVEL_L : return self::$QR_RS_BLOCK_TABLE[($typeNumber - 1) * 4 + 0];
		case QRCode::ERROR_CORRECT_LEVEL_M : return self::$QR_RS_BLOCK_TABLE[($typeNumber - 1) * 4 + 1];
		case QRCode::ERROR_CORRECT_LEVEL_Q : return self::$QR_RS_BLOCK_TABLE[($typeNumber - 1) * 4 + 2];
		case QRCode::ERROR_CORRECT_LEVEL_H : return self::$QR_RS_BLOCK_TABLE[($typeNumber - 1) * 4 + 3];
		default :
			throw new \OutOfBoundsException("tn:{$typeNumber}/ecl:{$errorCorrectLevel}");
		}
	}
}

//---------------------------------------------------------------
// QRData
//---------------------------------------------------------------

class QRData
{
	const
		MODE_NUMBER    = 1,
		MODE_ALPHA_NUM = 2,
		MODE_8BIT_BYTE = 4,
		MODE_KANJI     = 8;

	protected
		$data,
		$mode;

	function __construct($data, $mode = 0)
	{
		$this->data = $data;
		$this->mode = $mode ?: static::detectMode($data);
	}

	function getData()
	{
		return $this->data;
	}

	function getLength()
	{
		return (self::MODE_KANJI == $this->mode)
			? floor(strlen($this->data) / 2)
			: strlen($this->data);
	}

	function getMode()
	{
		return $this->mode;
	}

	function getLengthInBits($type)
	{
		if (1 > $type || 40 < $type) {
			throw new \OutOfBoundsException("Invalid type: {$type}");
		}
		switch ($this->mode)
		{
		case self::MODE_NUMBER:
			if ($type < 10) {
				return 10;
			}
			if ($type < 27) {
				return 12;
			}
			return 14;

		case self::MODE_ALPHA_NUM:
			if ($type < 10) {
				return 9;
			}
			if ($type < 27) {
				return 11;
			}
			return 13;

		case self::MODE_8BIT_BYTE:
			if ($type < 10) {
				return 8;
			}
			if ($type < 27) {
				return 16;
			}
			return 16;

		case self::MODE_KANJI:
			if ($type < 10) {
				return 8;
			}
			if ($type < 27) {
				return 10;
			}
			return 12;
		}
	}

	function write(&$buffer)
	{
		$i = 0;
		$data = $this->data;
		$length = strlen($data);
		switch ($this->mode)
		{
		case self::MODE_NUMBER:
			while ($i + 2 < $length) {
				$num = static::parseInt(substr($data, $i, 3));
				$buffer->put($num, 10);
				$i += 3;
			}
			if ($i < $length) {
				if ($length - $i == 1) {
					$num = static::parseInt(substr($data, $i, $i + 1));
					$buffer->put($num, 4);
				} else if ($length - $i == 2) {
					$num = static::parseInt(substr($data, $i, $i + 2));
					$buffer->put($num, 7);
				}
			}
			break;

		case self::MODE_ALPHA_NUM:
			while ($i + 1 < $length) {
				$buffer->put(static::getCode(ord($data[$i])) * 45 + static::getCode(ord($data[$i + 1])), 11);
				$i += 2;
			}
			if ($i < $length) {
				$buffer->put(static::getCode(ord($data[$i])), 6);
			}
			break;

		case self::MODE_8BIT_BYTE:
			for (; $i < $length; ++$i) {
				$buffer->put(ord($data[$i]), 8);
			}
			break;

		case self::MODE_KANJI:
			while ($i + 1 < $length) {
				$c = ((0xff & ord($data[$i])) << 8) | (0xff & ord($data[$i + 1]));
				if (0x8140 <= $c && $c <= 0x9FFC) {
					$c -= 0x8140;
				} else if (0xE040 <= $c && $c <= 0xEBBF) {
					$c -= 0xC140;
				} else {
					throw new \OutOfBoundsException("illegal char at " . ($i + 1) . "/{$c}");
				}
				$c = (($c >> 8) & 0xff) * 0xC0 + ($c & 0xff);
				$buffer->put($c, 13);
				$i += 2;
			}
			if ($i < $length) {
				throw new \OutOfBoundsException("illegal char at " . ($i + 1));
			}
			break;
		}
	}

	protected static function parseInt($s)
	{
		if (!ctype_digit((string)$s)) {
			throw new \OutOfBoundsException("Not numeric: {$s}");
		}
		return (int) $s;
	}

	protected static function getCode($c)
	{
		// 0-9
		if (0x30 <= $c && $c <= 0x39) {
			return $c - 0x30;
		}
		// A-Z
		if (0x41 <= $c && $c <= 0x5A) {
			return $c - 0x41 + 10;
		}
		switch ($c)
		{
			case 0x20: return 36; // ' '
			case 0x24: return 37; // $
			case 0x25: return 38; // %
			case 0x2A: return 39; // *
			case 0x2B: return 40; // +
			case 0x2D: return 41; // -
			case 0x2E: return 42; // .
			case 0x2F: return 43; // /
			case 0x3A: return 44; // :
			default :
				throw new \OutOfBoundsException("illegal char: {$c}");
		}
	}

	protected static function detectMode($s)
	{
		if (ctype_digit((string)$s)) {
			return self::MODE_NUMBER;
		}
		if (preg_match('#^[0-9A-Z \\$\\%\\*\\+\\-\\.\\/\\:]*$#D', $s)) {
			return self::MODE_ALPHA_NUM;
		}
		return static::isKanji($s) ? self::MODE_KANJI : self::MODE_8BIT_BYTE;
	}

	protected static function isKanji($s)
	{
		$data = $s;
		$length = strlen($data);
		$i = 0;
		while ($i + 1 < $length) {
			$c = ((0xff & ord($data[$i])) << 8) | (0xff & ord($data[$i + 1]));
			if (!(0x8140 <= $c && $c <= 0x9FFC) && !(0xE040 <= $c && $c <= 0xEBBF)) {
				return false;
			}
			$i += 2;
		}
		return !($i < $length);
	}
}

//---------------------------------------------------------------
// QRMath
//---------------------------------------------------------------

abstract class QRMath
{
	protected static
		$EXP_TABLE = array(1,2,4,8,16,32,64,128,29,58,116,232,205,135,19,38,76,152,45,90,180,117,234,201,143,3,6,12,24,48,96,192,157,39,78,156,37,74,148,53,106,212,181,119,238,193,159,35,70,140,5,10,20,40,80,160,93,186,105,210,185,111,222,161,95,190,97,194,153,47,94,188,101,202,137,15,30,60,120,240,253,231,211,187,107,214,177,127,254,225,223,163,91,182,113,226,217,175,67,134,17,34,68,136,13,26,52,104,208,189,103,206,129,31,62,124,248,237,199,147,59,118,236,197,151,51,102,204,133,23,46,92,184,109,218,169,79,158,33,66,132,21,42,84,168,77,154,41,82,164,85,170,73,146,57,114,228,213,183,115,230,209,191,99,198,145,63,126,252,229,215,179,123,246,241,255,227,219,171,75,150,49,98,196,149,55,110,220,165,87,174,65,130,25,50,100,200,141,7,14,28,56,112,224,221,167,83,166,81,162,89,178,121,242,249,239,195,155,43,86,172,69,138,9,18,36,72,144,61,122,244,245,247,243,251,235,203,139,11,22,44,88,176,125,250,233,207,131,27,54,108,216,173,71,142,1),
		$LOG_TABLE = array(0,0,1,25,2,50,26,198,3,223,51,238,27,104,199,75,4,100,224,14,52,141,239,129,28,193,105,248,200,8,76,113,5,138,101,47,225,36,15,33,53,147,142,218,240,18,130,69,29,181,194,125,106,39,249,185,201,154,9,120,77,228,114,166,6,191,139,98,102,221,48,253,226,152,37,179,16,145,34,136,54,208,148,206,143,150,219,189,241,210,19,92,131,56,70,64,30,66,182,163,195,72,126,110,107,58,40,84,250,133,186,61,202,94,155,159,10,21,121,43,78,212,229,172,115,243,167,87,7,112,192,247,140,128,99,13,103,74,222,237,49,197,254,24,227,165,153,119,38,184,180,124,17,68,146,217,35,32,137,46,55,63,209,91,149,188,207,205,144,135,151,178,220,252,190,97,242,86,211,171,20,42,93,158,132,60,57,83,71,109,65,162,31,45,67,216,183,123,164,118,196,23,73,236,127,12,111,246,108,161,59,82,41,157,85,170,251,96,134,177,187,204,62,90,203,89,95,176,156,169,160,81,11,245,22,235,122,117,44,215,79,174,213,233,230,231,173,232,116,214,244,234,168,80,88,175);

	static function glog($n)
	{
		if ($n < 1) {
			throw new \OutOfBoundsException("log({$n})");
		}
		return self::$LOG_TABLE[$n];
	}

	static function gexp($n)
	{
		if ($n < 0) {
			$n = $n % 255 + 255;
		}
		if ($n > 255) {
			$n = $n % 255;
		}
		return self::$EXP_TABLE[$n];
	}
}

//---------------------------------------------------------------
// QRPolynomial
//---------------------------------------------------------------

class QRPolynomial {

	protected $num;

	function __construct($num, $shift = 0)
	{
		$offset = 0;
		$limit = count($num);
		while ($offset < $limit && $num[$offset] == 0) {
			++$offset;
		}
		$this->num = array_fill(0, $limit - $offset + $shift, 0);
		for ($i = 0; $i < $limit - $offset; ++$i) {
			$this->num[$i] = $num[$i + $offset];
		}
	}

	function get($index)
	{
		return $this->num[$index];
	}

	function getLength()
	{
		return count($this->num);
	}

	function multiply($e)
	{
		$tl = $this->getLength();
		$el = $e->getLength();
		$num = array_fill(0, $tl + $el - 1, 0);
		for ($i = 0; $i < $tl; ++$i) {
			$vi = QRMath::glog($this->get($i));
			for ($j = 0; $j < $el; ++$j) {
				$num[$i + $j] ^= QRMath::gexp($vi + QRMath::glog($e->get($j)));
			}
		}
		return new static($num);
	}

	function mod($e)
	{
		$tl = $this->getLength();
		$el = $e->getLength();
		if ($tl < $el) {
			return $this;
		}
		$ratio = QRMath::glog($this->get(0)) - QRMath::glog($e->get(0));
		$num = array();
		for ($i = 0; $i < $tl; ++$i) {
			$num[$i] = $this->num[$i];
			if ($i < $el) {
				$num[$i] ^= QRMath::gexp(QRMath::glog($e->get($i)) + $ratio);
			}
		}
		$newPolynomial = new static($num);
		return $newPolynomial->mod($e);
	}
}

//---------------------------------------------------------------
// QRBitBuffer
//---------------------------------------------------------------

class QRBitBuffer {

	protected $buffer;
	protected $length;

	function __construct()
	{
		$this->buffer = array();
		$this->length = 0;
	}

	function getBuffer()
	{
		return $this->buffer;
	}

	function getLengthInBits()
	{
		return $this->length;
	}

	function get($index)
	{
		$bufIndex = (int)floor($index / 8);
		return (($this->buffer[$bufIndex] >> (7 - $index % 8)) & 1) == 1;
	}

	function put($num, $length)
	{
		for ($i = 0; $i < $length; ++$i) {
			$this->putBit((($num >> ($length - $i - 1)) & 1) == 1);
		}
	}

	function putBit($bit)
	{
		$bufIndex = (int)floor($this->length / 8);
		if (count($this->buffer) <= $bufIndex) {
			$this->buffer[] = 0;
		}
		if ($bit) {
			$this->buffer[$bufIndex] |= (0x80 >> ($this->length % 8));
		}
		++$this->length;
	}
}
