<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * A point on the earth, and how far it is from another one.
 *
 * Distances are straight-line kilometres, not road distance. That is the right
 * measure for "do you deliver here?": it is stable, needs nothing external, and
 * never gets a shop into trouble by being optimistic about traffic.
 */
final readonly class GeoPoint implements Stringable
{
    /** Mean radius of the earth in kilometres. */
    private const EARTH_RADIUS_KM = 6371.0088;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException("[{$latitude}] is not a latitude.");
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException("[{$longitude}] is not a longitude.");
        }
    }

    /**
     * Build from anything a form or a database row might hand over, or null if
     * either half is missing. A point needs both numbers to mean anything.
     */
    public static function tryFrom(mixed $latitude, mixed $longitude): ?self
    {
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        try {
            return new self((float) $latitude, (float) $longitude);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Kilometres between two points, over the curve of the earth.
     */
    public function distanceTo(self $other): float
    {
        $latitude = deg2rad($other->latitude - $this->latitude);
        $longitude = deg2rad($other->longitude - $this->longitude);

        $a = sin($latitude / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) * sin($longitude / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }

    public function isWithin(float $kilometres, self $of): bool
    {
        return $this->distanceTo($of) <= $kilometres;
    }

    public function __toString(): string
    {
        return round($this->latitude, 6).','.round($this->longitude, 6);
    }
}
