<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Measure;

class Volt extends Unit
{
	const BASE = 'V';

	public function toAmpere(Watt $W)
	{
		$A = new Ampere($W);
		return $A->div($this);
	}

	public function toWatt(Ampere $A, $ac_pf = 1)
	{
		$W = new Watt($A);
		return $W->mul($this)->mul($ac_pf);
	}

}
