<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\XLSX;

class StyleSheet
{
	protected
		$owner,
		$file,

		$borders,
		$fills,
		$fonts,
		$numFmts,
		$cellXfs;

	function __construct(Document $owner)
	{
		$this->owner = $owner;
		$this->borders = new Borders();
		$this->fills   = new Fills();
		$this->fonts   = new Fonts();
		$this->numFmts = new NumberFormats();
		$this->cellXfs = new CellXfs();
		$this->addCellStyle('GENERAL', null);
	}

	public function __destruct()
	{
		if ($this->file) {
			$this->file->close();
		}
	}

	public function addCellStyle($number_format, $cell_style_string)
	{
		$xf = new CellXf();

		$xf->num_fmt_idx = $this->numFmts->append($number_format);

		if ($cell_style_string) {
			$style = json_decode($cell_style_string, true);

			if (isset($style['border']) && is_string($style['border'])) {
				$xf->border_idx = $this->borders->append($style['border']);
			}

			if (isset($style['fill']) && is_string($style['fill'])) {
				$xf->fill_idx = $this->fills->append($style['fill']);
			}

			if (isset($style['halign'])) {
				$xf->alignment['horizontal'] = $style['halign'];
			}

			if (isset($style['valign'])) {
				$xf->alignment['vertical'] = $style['valign'];
			}

			$font = clone $this->fonts[0];
			if (isset($style['font-size'])) {
				$font->size = floatval($style['font-size']);
			}
			if (isset($style['font']) && is_string($style['font'])) {
				$font->name = $style['font'];
			}
			if (isset($style['font-style']) && is_string($style['font-style'])) {
				$font->bold      = false !== strpos($style['font-style'], 'bold');
				$font->italic    = false !== strpos($style['font-style'], 'italic');
				$font->strike    = false !== strpos($style['font-style'], 'strike');
				$font->underline = false !== strpos($style['font-style'], 'underline');
			}
			if (isset($style['color']) && is_string($style['color'])) {
				$font->color = $style['color'];
			}
			$xf->font_idx = $this->fonts->append($font);
		}

		return $this->cellXfs->append($xf);
	}

	public function writeXML()
	{
		$file = new \Poodle\Stream\File($this->owner->newTempFile(), 'w');
		$file->setWriteBuffer(8192);

		$file->write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n");
		$file->write('<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');

		$file->write($this->numFmts->__toString());
		$file->write($this->fonts->__toString());
		$file->write($this->fills->__toString());
		$file->write($this->borders->__toString());

		$file->write('<cellStyleXfs count="20">');
		$file->write('<xf applyAlignment="true" applyBorder="true" applyFont="true" applyProtection="true" borderId="0" fillId="0" fontId="0" numFmtId="164">');
		$file->write('<alignment horizontal="general" indent="0" shrinkToFit="false" textRotation="0" vertical="bottom" wrapText="false"/>');
		$file->write('<protection hidden="false" locked="true"/>');
		$file->write('</xf>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="2" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="2" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="0" numFmtId="0"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="43"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="41"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="44"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="42"/>');
		$file->write('<xf applyAlignment="false" applyBorder="false" applyFont="true" applyProtection="false" borderId="0" fillId="0" fontId="1" numFmtId="9"/>');
		$file->write('</cellStyleXfs>');

		$file->write($this->cellXfs->__toString());

		$file->write('<cellStyles count="6">');
		$file->write('<cellStyle builtinId="0" customBuiltin="false" name="Normal" xfId="0"/>');
		$file->write('<cellStyle builtinId="3" customBuiltin="false" name="Comma" xfId="15"/>');
		$file->write('<cellStyle builtinId="6" customBuiltin="false" name="Comma [0]" xfId="16"/>');
		$file->write('<cellStyle builtinId="4" customBuiltin="false" name="Currency" xfId="17"/>');
		$file->write('<cellStyle builtinId="7" customBuiltin="false" name="Currency [0]" xfId="18"/>');
		$file->write('<cellStyle builtinId="5" customBuiltin="false" name="Percent" xfId="19"/>');
		$file->write('</cellStyles>');
		$file->write('</styleSheet>');
		$file->close();
		$this->file = $file;
		return $this->file->filename;
	}
}

class CellXfs
{
	protected
		$data = array();

	public function append($value)
	{
		if (!($value instanceof CellXf)) {
			throw new \InvalidArgumentException('Value not of type CellXf');
		}

		foreach ($this->data as $i => $xf) {
			if ($value == $xf) {
				return $i;
			}
		}

		$this->data[] = $value;
		return count($this->data) - 1;
	}

	function __toString()
	{
		$xml = '<cellXfs count="'.count($this->data).'">';
		foreach ($this->data as $i => $xf) {
			$xml .= $xf->__toString();
		}
		return $xml . '</cellXfs>';
	}
}

class CellXf
{
	public
		$alignment = array(
			'horizontal' => '',
			'vertical' => '',
			'textRotation' => 0,
			'wrapText' => false,
			'indent' => 0,
			'shrinkToFit' => false
		),
		$border_idx  = 0,
		$fill_idx    = 0,
		$font_idx    = 0,
		$num_fmt_idx = 0;

	protected static
		$halign_values = array('general','left','right','justify','center','centerContinuous','distributed','fill'),
		$valign_values = array('bottom','center','distributed','justify','top');

	function __toString()
	{
		if ($this->alignment['horizontal'] && !in_array($this->alignment['horizontal'], static::$halign_values, true)) {
			$this->alignment['horizontal'] = '';
		}
		if ($this->alignment['vertical'] && !in_array($this->alignment['vertical'], static::$valign_values, true)) {
			$this->alignment['vertical'] = '';
		}
		$applyAlignment = $this->alignment['horizontal']
			|| $this->alignment['vertical']
			|| $this->alignment['textRotation']
			|| $this->alignment['wrapText']
			|| $this->alignment['indent']
			|| $this->alignment['shrinkToFit'];
		$xml = '<xf applyAlignment="'.($applyAlignment ? 'true' : 'false')
			. '" applyBorder="'.($this->border_idx ? 'true' : 'false')
			. '" applyFont="'.($this->font_idx ? 'true' : 'false')
			. '" applyProtection="false" borderId="'.$this->border_idx
			. '" fillId="'.$this->fill_idx
			. '" fontId="'.$this->font_idx
			. '" numFmtId="'.(164 + $this->num_fmt_idx)
			. '" xfId="0">';
		if ($applyAlignment) {
			$xml .= '<alignment horizontal="'.($this->alignment['horizontal'] ?: 'general')
				. '" vertical="'.($this->alignment['vertical'] ?: 'bottom')
				. '" textRotation="'.$this->alignment['textRotation']
				. '" wrapText="'.($this->alignment['wrapText']?'true':'false')
				. '" indent="'.$this->alignment['indent']
				. '" shrinkToFit="'.($this->alignment['shrinkToFit']?'true':'false').'"/>';
		}
		return $xml . '<protection locked="true" hidden="false"/></xf>';
	}
}

class Borders
{
	protected
		$data = array();

	protected static
		$border_values = array('left','right','top','bottom');

	function __construct()
	{
		$this->data[] = new Border();
	}

	public function append($value)
	{
		if (is_string($value)) {
			$sides = array_intersect(explode(',', $value), static::$border_values);
			if (!$sides) {
				return 0;
			}
			$value = new Border();
			foreach ($sides as $side) {
				if ($side) {
					$value->$side = 'hair';
				}
			}
		}

		if (!($value instanceof Border)) {
			throw new \InvalidArgumentException('Value not of type Poodle\\XLSX\\Border');
		}

		foreach ($this->data as $i => $border) {
			if ($value == $border) {
				return $i;
			}
		}

		$this->data[] = $value;
		return count($this->data) - 1;
	}

	function __toString()
	{
		$xml = '<borders count="'.count($this->data).'">';
		foreach ($this->data as $border) {
			$xml .= $border->__toString();
		}
		return $xml . '</borders>';
	}
}

class Border
{
	public
		$diagonalDown = false,
		$diagonalUp = false,
		$left = '',
		$right = '',
		$top = '',
		$bottom = '',
		$diagonal = '';

	function __toString()
	{
		return '<border diagonalDown="false" diagonalUp="false">'
			. '<left'.($this->left ? ' style="'.$this->left.'"' : '').'/>'
			. '<right'.($this->right ? ' style="'.$this->right.'"' : '').'/>'
			. '<top'.($this->top ? ' style="'.$this->top.'"' : '').'/>'
			. '<bottom'.($this->bottom ? ' style="'.$this->bottom.'"' : '').'/>'
			. '<diagonal/>'
			. '</border>';
	}
}

class Color
{
	protected
		$value = '';

	public function __construct($v)
	{
		$v = strtoupper($v);
		if ('#' === $v[0]) {
			$v = substr($v, 1, 6);
		}
		switch (strlen($v))
		{
		case 3:
			// expand cf0 => ccff00
			$v = $v[0].$v[0].$v[1].$v[1].$v[2].$v[2];
		case 6:
			$v = 'FF'.$v;
			break;
		}
		if (!preg_match('/^[A-F0-9]{8}$/D', $v)) {
			throw new \InvalidArgumentException('Invalid color value');
		}
		$this->value = $v;
	}

	function __toString()
	{
		return $this->value;
	}
}

class Fills
{
	protected
		$data = array();

	function __construct()
	{
		$this->data[] = new patternFill();
		$this->data[0]->type = 'none';
		$this->data[] = new patternFill();
		$this->data[1]->type = 'gray125';
	}

	public function append($value)
	{
		if (is_string($value)) {
			$color = $value;
			$value = new patternFill();
			$value->fgColor = $color;
		}

		if (!($value instanceof patternFill)) {
			throw new \InvalidArgumentException('Value not of type Poodle\\XLSX\\Fill');
		}

		foreach ($this->data as $i => $fill) {
			if ($value == $fill) {
				return $i;
			}
		}

		$this->data[] = $value;
		return count($this->data) - 1;
	}

	function __toString()
	{
		$xml = '<fills count="'.count($this->data).'">';
		foreach ($this->data as $fill) {
			$xml .= $fill->__toString();
		}
		return $xml . '</fills>';
	}
}

class patternFill
{
	public
		$type = 'solid';

	protected
		$fgColor,
		$bgColor;

	function __set($k, $v)
	{
		if ('fgColor' === $k || 'bgColor' === $k) {
			$this->$k = strlen($v) ? new Color($v) : '';
		}
	}

	function __toString()
	{
		$xml = '<fill><patternFill patternType="'.$this->type.'"';
		if ($this->fgColor) {
			$xml .= '><fgColor rgb="'.$this->fgColor.'"/><bgColor indexed="64"/></patternFill>';
		} else {
			$xml .= '/>';
		}
		return $xml . '</fill>';
	}
}

class NumberFormats
{
	const
		FIRST_ID = 164;

	protected
		$data = array();

	public function append($value)
	{
		if (!is_string($value)) {
			throw new \InvalidArgumentException('Value not of type string');
		}

		foreach ($this->data as $i => $numFmt) {
			if ($value == $numFmt) {
				return $i;
			}
		}

		$this->data[] = $value;
		return static::FIRST_ID + count($this->data) - 1;
	}

	function __toString()
	{
		$xml = '<numFmts count="'.count($this->data).'">';
		foreach ($this->data as $i => $v) {
			$xml .= '<numFmt numFmtId="'.(static::FIRST_ID + $i)
				.'" formatCode="'.Writer::xmlspecialchars($v).'" />';
		}
		return $xml . '</numFmts>';
	}
}

class Fonts extends \ArrayIterator
{
	function __construct()
	{
		// 4 default placeholders
		parent::__construct(array(
			new Font(),
		));
		$this[0]->name    = 'Calibri';
		$this[0]->charset = 1; // DEFAULT_CHARSET
	}

	function __toString()
	{
		$str = '<fonts count="'.$this->count().'">';
		foreach ($this as $font) {
			$str .= $font->__toString();
		}
		return $str . '</fonts>';
	}

	public function append($value)
	{
		if (!($value instanceof Font)) {
			throw new \InvalidArgumentException('value not of type Poodle\\XLSX\\Font');
		}
		foreach ($this as $i => $font) {
			if ($value == $font) {
				return $i;
			}
		}
		$i = $this->count();
		parent::offsetSet($i, $value);
		return $i;
	}

	public function offsetSet($i, $v) {}
	public function offsetUnset($i) {}
	public function asort() {}
	public function ksort() {}
	public function natcasesort() {}
	public function natsort() {}
	public function uasort($f) {}
	public function uksort($f) {}
}

class Font
{
	const
		FAMILY_NONE   = 0, // auto, No Font Family, Not applicable.
		FAMILY_ROMAN  = 1, // Proportional Font With Serifs
		FAMILY_SWISS  = 2, // Proportional Font Without Serifs
		FAMILY_MONO   = 3, // Modern Monospace Font
		FAMILY_SCRIPT = 4,
		FAMILY_DECO   = 5, // Decorative Novelty Font

		ANSI_CHARSET    = 0,
		DEFAULT_CHARSET = 1;

	public
		$name    = 'Arial',
		$size    = 10,
		$family  = 0,
		$charset = 0,
//		$condense  = false, // <condense>
		$bold      = false,
		$italic    = false,
		$strike    = false,
//		$extend    = false, // <extend>
//		$outline   = false, // <outline>
//		$shadow    = false, // <shadow>
		$underline = false; // 'single'
/*
		VerticalTextAlignment <x:vertAlign>
		$scheme    = 'none'; // major, minor
*/
	protected
		$color;

	protected static $families = array('', 'Times New Roman', 'Calibri', 'Courier New', 'Comic Sans MS');

	function __set($k, $v)
	{
		if ('color' === $k) {
			$this->$k = strlen($v) ? new Color($v) : '';
		}
	}

	function __toString()
	{
		$f = array_search($this->name, static::$families, true);
		if (0 < $f) {
			$this->family = $f;
		}
		return '<font><sz val="'.$this->size.'"/>'
		. '<name val="'.$this->name.'"/>'
		. ($this->charset   ? '<charset val="'.$this->charset.'"/>' : '')
		. '<family val="'.$this->family.'"/>'
		. ($this->color     ? '<color rgb="'.$this->color.'"/>' : '')
		. ($this->bold      ? '<b val="true"/>' : '')
		. ($this->italic    ? '<i val="true"/>' : '')
		. ($this->underline ? '<u val="single"/>' : '')
		. ($this->strike    ? '<strike val="true"/>' : '')
		. '</font>';
	}

}
