<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Measure;

class Watt extends Unit
{
	const BASE = 'W';

	public function toAmpere(Volt $V, $ac_pf = 1)
	{
		$V = clone $V;
		$A = new Ampere($this);
		return $A->div($V->mul($ac_pf));
	}

	public function toVolt(Ampere $A, $ac_pf = 1)
	{
		$A = clone $A;
		$W = new Watt($this);
		return $W->div($A->mul($ac_pf));
	}

}
