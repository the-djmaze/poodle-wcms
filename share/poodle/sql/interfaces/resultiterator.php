<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.
*/

namespace Poodle\SQL\Interfaces;

interface ResultIterator extends Result, \Iterator
{
	# Iterator: rewind(), valid(), current(), key(), next()
}
