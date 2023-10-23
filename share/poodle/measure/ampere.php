<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Measure;

class Ampere extends Unit
{
	const BASE = 'A';

	public function toVolt(Watt $W)
	{
		$V = new Volt($W);
		return $W->div($this);
	}

	public function toWatt(Volt $V, $ac_pf = 1)
	{
		$W = new Watt($this);
		return $W->mul($V)->mul($ac_pf);
	}

}
