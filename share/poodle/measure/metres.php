<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Measure;

class Metres extends Unit
{
	const BASE = 'm';

	public function getInches()
	{
		return $this->asClone()->div('0.0254');
	}

	public function getFeet()
	{
		return $this->asClone()->div('0.3048'); // 12 inches
	}

	public function getYards()
	{
		return $this->asClone()->div('0.9144'); // 36 inches
	}

	public function getMiles()
	{
		return $this->asClone()->div('1609.344'); // 63360 inches
	}

	public function getNauticalMiles()
	{
		return $this->asClone()->div('1852');
	}

	public function getFathoms()
	{
		return $this->asClone()->div('1.8288');
	}

}
