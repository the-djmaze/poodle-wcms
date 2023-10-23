<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Unicode symbol: ⴲ
	Alternative symbol: ♁
*/

namespace Poodle\Measure;

abstract class Earth
{
	const
		RADIUS = 6371; // earth mean radius in km, 3959 is miles

	public static function square($lat, $lon, $radius /*km*/)
	{
		if ($radius instanceof Metres) {
			$radius = $radius->asFloat() * 1000;
		}
		$radius /= self::RADIUS;

		// first-cut bounding box (in degrees)
		$lat_deg = rad2deg($radius);

		// compensate for degrees longitude getting smaller with increasing latitude
		$lon_deg = rad2deg($radius / cos(deg2rad($lat)));

		return array(
			'N' => $lat + $lat_deg, // max 90
			'E' => $lon + $lon_deg, // max 180
			'S' => $lat - $lat_deg, // max -90
			'W' => $lon - $lon_deg  // max -180
		);
	}

	public static function distance($lat1, $lon1, $lat2, $lon2)
	{
		return acos(
		    sin(deg2rad($lat1)) * sin(deg2rad($lat2))
		  + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2 - $lon1))
		) * self::RADIUS;
	}

}
