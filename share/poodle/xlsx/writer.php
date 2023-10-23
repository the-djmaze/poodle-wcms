<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\XLSX;

class Writer extends Document
{

	public function writeToStdOut($filename)
	{
		$temp_file = $this->newTempFile();
		self::writeToFile($temp_file);
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Pragma: no-cache');
		header('Content-Transfer-Encoding: binary');
		\Poodle\HTTP\Headers::setContentDisposition('attachment', array('filename'=>$filename));
		\Poodle\HTTP\Headers::setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', array('name'=>$filename));
		readfile($temp_file);
		exit;
	}

	public function writeToString()
	{
		$temp_file = $this->newTempFile();
		self::writeToFile($temp_file);
		return file_get_contents($temp_file);
	}

	public function writeToFile($filename)
	{
		if (file_exists($filename)) {
			if (!is_writable($filename)) {
				throw new \Exception('File is not writeable.');
			}
			unlink($filename);
		}

		if (empty($this->sheets)) {
			throw new \Exception('No worksheets defined.');
		}

		if (class_exists('ZipArchive')) {
			$zip = new \ZipArchive();
			if (!$zip->open($filename, \ZipArchive::CREATE)) {
				throw new \Exception("Unable to create zip.");
			}
		} else {
			throw new \Exception('ZipArchive not installed and PharData not working in Microsoft Excel');
			$zip = new \PharData($filename, \Phar::CURRENT_AS_FILEINFO | \Phar::KEY_AS_FILENAME, null, \Phar::ZIP);
		}

		$zip->addEmptyDir("docProps/");
		$zip->addFromString("docProps/app.xml" , self::buildAppXML() );
		$zip->addFromString("docProps/core.xml", self::buildCoreXML());

		$zip->addEmptyDir("_rels/");
		$zip->addFromString("_rels/.rels", self::buildRelationshipsXML());

		$zip->addEmptyDir("xl/worksheets/");
		foreach ($this->sheets as $sheet) {
			$sheet->finalize();
			$zip->addFile($sheet->filename, "xl/worksheets/".$sheet->xmlname);
		}
		$zip->addFromString("xl/workbook.xml", self::buildWorkbookXML());
		$zip->addFile($this->stylesheet->writeXML(), "xl/styles.xml" );  //$zip->addFromString("xl/styles.xml"           , self::buildStylesXML() );
		$zip->addFromString("[Content_Types].xml", self::buildContentTypesXML() );

		$zip->addEmptyDir("xl/_rels/");
		$zip->addFromString("xl/_rels/workbook.xml.rels", self::buildWorkbookRelsXML() );
		if ($zip instanceof \ZipArchive) {
			$zip->close();
		} else {
			$zip->compressFiles(\Phar::GZ);
		}
	}

	protected function initializeSheet($sheet_name, $cell_widths = 15)
	{
		if (!isset($this->sheets[$sheet_name])) {
			$this->sheets[$sheet_name] = new Writer_Sheet($this, $sheet_name, $cell_widths);
		}
	}

	public function getSheet($sheet_name)
	{
		return empty($this->sheets[$sheet_name])
			? null
			: $this->sheets[$sheet_name];
	}

	public function writeSheetHeader($sheet_name, array $header_types, $suppress_row = false, $style = null, $cell_widths = 15)
	{
		if ($sheet_name && $header_types) {
			self::initializeSheet($sheet_name, $cell_widths);
			$this->sheets[$sheet_name]->writeHeader($header_types, $suppress_row, $style);
		}
	}

	public function writeSheetRow($sheet_name, array $row, array $style = array())
	{
		if ($sheet_name && $row) {
			self::initializeSheet($sheet_name);
			$this->sheets[$sheet_name]->writeRow($row, $style);
		}
	}

	public function markMergedCell($sheet_name, $start_cell_row, $start_cell_column, $end_cell_row, $end_cell_column)
	{
		$sheet = $this->getSheet($sheet_name);
		if ($sheet) {
			$sheet->markMergedCell($start_cell_row, $start_cell_column, $end_cell_row, $end_cell_column);
		}
	}

	public function writeSheet(array $data, $sheet_name='', array $header_types=array())
	{
		$sheet_name = $sheet_name ?: 'Sheet1';
		$data = $data ?: array(array(''));
		if ($header_types) {
			$this->writeSheetHeader($sheet_name, $header_types);
		}
		foreach ($data as $row) {
			$this->writeSheetRow($sheet_name, $row);
		}
		if ($this->sheets[$sheet_name]) {
			$this->sheets[$sheet_name]->finalize();
		}
	}

	protected function buildAppXML()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
			. '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
/*
			. '<Application>Microsoft Excel</Application>'
			. '<DocSecurity>0</DocSecurity>'
			. '<ScaleCrop>false</ScaleCrop>'
			. '<HeadingPairs>'
				. '<vt:vector size="2" baseType="variant">'
					. '<vt:variant>'
						. '<vt:lpstr>Werkbladen</vt:lpstr>'
					. '</vt:variant>'
					. '<vt:variant>'
						. '<vt:i4>1</vt:i4>'
					. '</vt:variant>'
				. '</vt:vector>'
			. '</HeadingPairs>'
			. '<TitlesOfParts>'
				. '<vt:vector size="1" baseType="lpstr">'
					. '<vt:lpstr>Blad1</vt:lpstr>'
				. '</vt:vector>'
			. '</TitlesOfParts>'
			. '<Company></Company>'
			. '<LinksUpToDate>false</LinksUpToDate>'
			. '<SharedDoc>false</SharedDoc>'
			. '<HyperlinksChanged>false</HyperlinksChanged>'
			. '<AppVersion>16.0300</AppVersion>'
			. '<TotalTime>0</TotalTime>'
*/
			. '</Properties>';
	}

	protected function buildCoreXML()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
			. '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
			. '<dc:creator>'.self::xmlspecialchars($this->author).'</dc:creator>'
			. '<dcterms:created xsi:type="dcterms:W3CDTF">'.date("Y-m-d\TH:i:s.00\Z").'</dcterms:created>'
//			. '<cp:lastModifiedBy>'.self::xmlspecialchars($this->author).'</cp:lastModifiedBy>'
//			. '<dcterms:modified xsi:type="dcterms:W3CDTF">'.date("Y-m-d\TH:i:s.00\Z").'</dcterms:modified>'
//			. '<cp:revision>0</cp:revision>'
			. '</cp:coreProperties>';
	}

	protected function buildRelationshipsXML()
	{
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
			. '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
			. '</Relationships>';
	}

	protected function buildWorkbookXML()
	{
		$i = 0;
		$xml = '';
		foreach ($this->sheets as $sheet) {
			$xml .= '<sheet name="'.self::xmlspecialchars(mb_substr($sheet->name,0,31)).'" sheetId="'.(++$i).'" state="visible" r:id="rId'.($i+1).'"/>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<fileVersion appName="Calc"/><workbookPr backupFile="false" showObjects="all" date1904="false"/>'
//			. '<bookViews><workbookView activeTab="0" firstSheet="0" showHorizontalScroll="true" showSheetTabs="true" showVerticalScroll="true" tabRatio="212" windowHeight="8192" windowWidth="16384" xWindow="0" yWindow="0"/></bookViews>'
			. '<bookViews><workbookView xWindow="0" yWindow="0" windowWidth="27870" windowHeight="14595"/></bookViews>'
			. '<sheets>'
			. $xml
			. '</sheets>'
			. '<calcPr iterateCount="100" refMode="A1" iterate="false" iterateDelta="0.001"/></workbook>';
	}

	protected function buildWorkbookRelsXML()
	{
		$i = 1;
		$xml = '';
		foreach ($this->sheets as $sheet) {
			$xml .= '<Relationship Id="rId'.(++$i).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/'.$sheet->xmlname.'"/>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
			. $xml
			. '</Relationships>';
	}

	protected function buildContentTypesXML()
	{
		$xml = '';
		foreach ($this->sheets as $sheet) {
			$xml .= '<Override PartName="/xl/worksheets/'.$sheet->xmlname.'" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. $xml
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
			. '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
			. '</Types>';
	}


	public static function log($string)
	{
		file_put_contents("php://stderr", date("Y-m-d H:i:s:").rtrim(is_array($string) ? json_encode($string) : $string)."\n");
	}

	public static function xmlspecialchars($val)
	{
		// note: badchars includes \t\n\r \x09\x0a\x0d
		static $badchars = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x7f";
		static $goodchars = "                                 ";
		return strtr(htmlspecialchars($val, ENT_QUOTES | ENT_XML1), $badchars, $goodchars);
	}
}

class Writer_Sheet
{
	public
		$name               = '';

	protected
		$owner              = null, // Writer
		$stream             = null, // \Poodle\Stream\File
		$xmlname,
		$max_cell_tag_start = 0,
		$max_cell_tag_end   = 0,
		$row_count          = 0,
		$cell_widths,
		$columns            = array(),
		$merge_cells        = array();

	function __construct(Writer $owner, $name, $cell_widths = 15)
	{
		$this->owner       = $owner;
		$this->name        = $name;
		$this->cell_widths = $cell_widths;
		$this->stream      = new \Poodle\Stream\File($owner->newTempFile(), 'w');
		$this->stream->setWriteBuffer(8192);
	}

	public function __destruct()
	{
		if ($this->stream) {
			$this->stream->close();
		}
	}

	function __get($k)
	{
		if ('filename' === $k) {
			return $this->stream->$k;
		}
		if ('xmlname' === $k) {
			if (!$this->xmlname) {
				$i = array_search($this, $this->owner->sheets, true);
				$i = array_search($i, array_keys($this->owner->sheets), true);
				$this->xmlname = 'sheet' . ($i + 1).".xml";
			}
			return $this->xmlname;
		}
	}

	protected function initialize()
	{
		if ($this->stream->tell() || !$this->stream->stream) {
			return;
		}
		$tabselected = count($this->owner->sheets) == 1 ? '1' : '0'; // only first sheet is selected
		$max_cell = static::xlsCell(Document::MAX_ROWS - 1, Document::MAX_COLS - 1);
		$this->stream->write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
		$this->stream->write('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">');
		$this->max_cell_tag_start = $this->stream->tell();
		$this->stream->write('<dimension ref="A1:' . $max_cell . '"/>');
		$this->max_cell_tag_end = $this->stream->tell();
		$this->stream->write('<sheetViews>');
		$this->stream->write('<sheetView tabSelected="' . $tabselected . '" workbookViewId="0">');
		$this->stream->write('<selection activeCell="A1" sqref="A1"/>');
		$this->stream->write('</sheetView>');
		$this->stream->write('</sheetViews>');
		$this->stream->write('<sheetFormatPr defaultRowHeight="15"/>');
		$this->stream->write('<cols>');
		if ($this->cell_widths && is_array($this->cell_widths)) {
			$r = 0;
			foreach ($this->cell_widths as $i => $width) {
				if (0 < $i && !$r) {
					$this->stream->write('<col collapsed="false" hidden="false" max="'.$i.'" min="1" style="0" width="15"/>');
				}
				if ($r) {
					$this->stream->write(' max="'.$i.'"/>');
				}
				++$i;
				$this->stream->write('<col collapsed="false" hidden="false" min="'.$i.'" style="0" width="'.max(10, $width).'"');
				$r = $i;
			}
			$this->stream->write(' max="1025"/>');
		} else {
			$this->stream->write('<col collapsed="false" hidden="false" max="1025" min="1" style="0" width="'.max(10, $this->cell_widths).'"/>');
		}
		$this->stream->write('</cols>');
		$this->stream->write('<sheetData>');
	}

	protected function initializeColumnTypes($header_types)
	{
		$column_types = array();
		foreach ($header_types as $v) {
			$number_format = static::numberFormatStandardized($v);
			$number_format_type = static::determineNumberFormatType($number_format);
			$cell_style_idx = $this->owner->stylesheet->addCellStyle($number_format, null);
			$column_types[] = array('number_format' => $number_format,//contains excel format like 'YYYY-MM-DD HH:MM:SS'
									'number_format_type' => $number_format_type, //contains friendly format like 'datetime'
									'default_cell_style' => $cell_style_idx,
									);
		}
		return $column_types;
	}

	public function writeHeader(array $header_types, $suppress_row = false, $style = null)
	{
		if (empty($header_types) || $this->row_count)
			return;

		$this->columns = $this->initializeColumnTypes($header_types);
		if (!$suppress_row && $this->stream->stream) {
			$header_row = array_keys($header_types);
			$this->initialize();
			$this->stream->write('<row r="1">');
			foreach ($header_row as $c => $v) {
				$cell_style_idx = $this->owner->stylesheet->addCellStyle('GENERAL', json_encode($style));
				$this->writeCell(0, $c, $v, 'n_string', $cell_style_idx);
			}
			$this->stream->write('</row>');
			++$this->row_count;
		}
	}

	public function writeRow(array $row, array $style = array())
	{
		if (!$this->stream->stream) {
			return;
		}

		if (empty($this->columns)) {
			$this->columns = $this->initializeColumnTypes(array_fill($from, count($row), 'GENERAL') ); // will map to n_auto
		}

		$this->initialize();
		$this->stream->write('<row r="' . ($this->row_count + 1) . '">');
		$c = 0;
		foreach ($row as $v) {
			if (!is_array($v)) {
				if (is_null($v) || 0 == strlen($v)) {
					$v = array('value' => $v, 'format' => 'string');
				} else {
					$v = array('value' => $v);
				}
			}
			$cellStyle = $style ? json_encode(isset($style[0]) ? $style[$c] : $style) : null;
			if (isset($v['format'])) {
				$number_format = static::numberFormatStandardized($v['format']);
				$number_format_type = static::determineNumberFormatType($number_format);
				$cell_style_idx = $this->owner->stylesheet->addCellStyle($number_format, $cellStyle);
			} else {
				$number_format = $this->columns[$c]['number_format'];
				$number_format_type = $this->columns[$c]['number_format_type'];
				$cell_style_idx = $cellStyle ? $this->owner->stylesheet->addCellStyle($number_format, $cellStyle) : $this->columns[$c]['default_cell_style'];
			}
			$this->writeCell($this->row_count, $c, $v['value'], $number_format_type, $cell_style_idx);
			++$c;
		}
		$this->stream->write('</row>');
		++$this->row_count;
	}

	protected function writeCell($row_number, $column_number, $value, $format_type, $cell_style_idx)
	{
		$c = '<c r="'.static::xlsCell($row_number, $column_number).'" s="'.$cell_style_idx.'"';

		if (is_object($value) && method_exists($value, '__toString')) {
			$value = $value->__toString();
		}

		if (!is_scalar($value) || '' === $value)
		{
			// objects, array, empty
			return $this->stream->write($c.'/>');
		}

		if ('n_date' === $format_type) {
			$value = intval(static::convert_date_time($value));
		} else if ('n_datetime' === $format_type) {
			$value = static::convert_date_time($value);
		}

		if (is_string($value) && '=' === $value[0])
		{
			$this->stream->write($c.' t="s"><f>'.Writer::xmlspecialchars(str_replace('=SOM(','SUM(',$value)).'</f></c>');
		}
		// n_auto / auto-detect unknown column types
		else if ('n_string' === $format_type || !(is_numeric($value) && preg_match('/^(\\-?[1-9][0-9]*|0)(\\.[0-9]+)?$/', $value)))
		{
			$this->stream->write($c.' t="inlineStr"><is><t>'.Writer::xmlspecialchars($value).'</t></is></c>');
		}
		else // n_numeric
		{
			// int, float, currency
			$this->stream->write($c.' t="n"><v>'.Writer::xmlspecialchars($value).'</v></c>');
		}
	}

	public function markMergedCell($start_cell_row, $start_cell_column, $end_cell_row, $end_cell_column)
	{
		if (!$this->stream->stream) {
			return;
		}

		$startCell = static::xlsCell($start_cell_row, $start_cell_column);
		$endCell = static::xlsCell($end_cell_row, $end_cell_column);
		$this->merge_cells[] = $startCell . ":" . $endCell;
	}

	public function finalize()
	{
		if (!$this->stream->stream) {
			return;
		}

		$this->stream->write('</sheetData>');

		if (!empty($this->merge_cells)) {
			$this->stream->write('<mergeCells>');
			foreach ($this->merge_cells as $range) {
				$this->stream->write('<mergeCell ref="' . $range . '"/>');
			}
			$this->stream->write('</mergeCells>');
		}

		$this->stream->write('<printOptions headings="false" gridLines="false" gridLinesSet="true" horizontalCentered="false" verticalCentered="false"/>');
		$this->stream->write('<pageMargins left="0.5" right="0.5" top="1.0" bottom="1.0" header="0.5" footer="0.5"/>');
		$this->stream->write('<pageSetup blackAndWhite="false" cellComments="none" copies="1" draft="false" firstPageNumber="1" fitToHeight="1" fitToWidth="1" horizontalDpi="300" orientation="portrait" pageOrder="downThenOver" paperSize="1" scale="100" useFirstPageNumber="true" usePrinterDefaults="false" verticalDpi="300"/>');
		$this->stream->write('<headerFooter differentFirst="false" differentOddEven="false">');
		$this->stream->write('<oddHeader>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12&amp;A</oddHeader>');
		$this->stream->write('<oddFooter>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12Page &amp;P</oddFooter>');
		$this->stream->write('</headerFooter>');
		$this->stream->write('</worksheet>');

		$max_cell = static::xlsCell($this->row_count - 1, count($this->columns) - 1);
		$max_cell_tag = '<dimension ref="A1:' . $max_cell . '"';
		$padding_length = $this->max_cell_tag_end - $this->max_cell_tag_start - strlen($max_cell_tag);
		$this->stream->seek($this->max_cell_tag_start);
		$this->stream->write($max_cell_tag.str_repeat(" ", $padding_length-2).'/>');
		$this->stream->close();
	}

	/**
	 * @param $row int, zero based
	 * @param $column int, zero based
	 * @return Cell label/coordinates, maximum is AMJ1048577
	 */
	protected static function xlsCell($row, $column)
	{
		$r = '';
		for ($n = $column; $n >= 0; $n = intval($n / 26) - 1) {
			$r = chr($n % 26 + 0x41) . $r;
		}
		return $r . ($row + 1);
	}

	protected static function convert_date_time($date_input)
	{
		$dt = new \Poodle\DateTime($date_input);

		$days = (new \DateTime('1900-01-01 00:00:00'))->diff($dt)->days
			// Seconds as fraction
			+ ((($dt->format('H') * 60 * 60)
				+ ($dt->format('i') * 60)
				+ $dt->format('s')
				) / 86400);

		// using 1900 as epoch, not 1904, ignoring 1904 special case
		++$days;

		// Adjust for Excel erroneously treating 1900 as a leap year.
		if ($days > 59) {
			++$days;
		}

		return $days;
	}

	protected static function determineNumberFormatType($format)
	{
		$format = preg_replace('/\[(Black|Blue|Cyan|Green|Magenta|Red|White|Yellow)\]/i', '', $format);
		if ('GENERAL' === $format) return 'n_auto';
		if ('@' === $format) return 'n_string';
		if ('0' === $format) return 'n_numeric';
		if (preg_match('/[H]{1,2}:[M]{1,2}|[M]{1,2}:[S]{1,2}/', $format)) {
			return 'n_datetime';
		}
		if (preg_match('/[Y]{2,4}|[D]{1,2}|[M]{1,2}/', $format)) {
			return 'n_date';
		}
		if (preg_match('/$|€|%|[0-9]/', $format)) {
			return 'n_numeric';
		}
		return 'n_auto';
	}

	protected static function numberFormatStandardized($format)
	{
		switch ($format)
		{
		case 'string':
			$format = '@';
			break;
		case 'number':
		case 'integer':
			$format = '0';
			break;
		case 'date':
			$format = 'YYYY-MM-DD';
			break;
		case 'datetime':
			$format = 'YYYY-MM-DD HH:MM:SS';
			break;
		case 'price':
			$format = '#,##0.00';
			break;
		case 'money':
		case 'dollar':
			$format = '[$$-1009]#,##0.00;[RED]-[$$-1009]#,##0.00';
			break;
		case 'euro':
			$format = '[$€-413] #,##0.00;[RED][$€-413] #,##0.00-';
			break;
		}

		$result = '';
		for ($i=0, $l = strlen($format); $i < $l; ++$i) {
			$c = $format[$i];
			if ('[' === $c || '"' === $c) {
				$p = strpos($format, ('[' === $c) ? ']' : $c, $i + 1);
				if ($p) {
					$i = $p;
					continue;
				}
			}
			if (false !== strpos(' -()', $c) && (0 === $i || '_' !== $format[$i-1])) {
				$result .= '\\';
			}
			$result .= $c;
		}
		return $result;
	}
}
