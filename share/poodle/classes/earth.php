<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Unicode symbol: ⴲ
	Alternative symbol: ♁
*/

namespace Poodle;

abstract class Earth
{
	const
		RADIUS = 6371; // earth mean radius in km, 3959 is miles

	public static function square($lat, $lon, $radius /*km*/)
	{
		// first-cut bounding box (in degrees)
		$maxLat = $lat + rad2deg($radius / self::RADIUS);
		$minLat = $lat - rad2deg($radius / self::RADIUS);

		// compensate for degrees longitude getting smaller with increasing latitude
		$maxLon = $lon + rad2deg($radius / self::RADIUS / cos(deg2rad($lat)));
		$minLon = $lon - rad2deg($radius / self::RADIUS / cos(deg2rad($lat)));

		return array(
			'N' => $maxLat, // max 90
			'E' => $maxLon, // max 180
			'S' => $minLat, // max -90
			'W' => $minLon  // max -180
		);
	}

	public static function distanceBetween($lat1, $lon1, $lat2, $lon2)
	{
		return acos(
		    sin(deg2rad($lat1)) * sin(deg2rad($lat2))
		  + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2 - $lon1))
		) * self::RADIUS;
	}

}
/*
$lat = 53.2277;
$lon = 6.57;
$q = \Poodle\Earth::square($lat, $lon, 50);
$lat_rad = deg2rad($lat);
$lon_rad = deg2rad($lon);
echo "SELECT
	code,
	latitude,
	longitude,
	accuracy,
	acos(sin($lat_rad)*sin(radians(latitude)) + cos($lat_rad)*cos(radians(latitude))*cos(radians(longitude)-$lon_rad))*6371 AS distance
FROM latlon_postcodes_nl
WHERE latitude>{$q['S']} AND latitude<{$q['N']}
  AND longitude>{$q['W']} AND longitude<{$q['E']}
HAVING distance<25
ORDER BY distance";
*/
