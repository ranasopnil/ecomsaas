<?php

namespace Tests\Unit;

use App\Support\GeoPoint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GeoPointTest extends TestCase
{
    public function test_it_measures_a_known_distance(): void
    {
        $dhaka = new GeoPoint(23.8103, 90.4125);
        $chittagong = new GeoPoint(22.3569, 91.7832);

        // Roughly 216 km apart in a straight line.
        $this->assertEqualsWithDelta(216, $dhaka->distanceTo($chittagong), 3);
    }

    public function test_a_short_hop_across_a_city_is_a_couple_of_kilometres(): void
    {
        $dhaka = new GeoPoint(23.8103, 90.4125);
        $gulshan = new GeoPoint(23.7925, 90.4078);

        $this->assertEqualsWithDelta(2.0, $dhaka->distanceTo($gulshan), 0.5);
    }

    public function test_distance_reads_the_same_in_both_directions(): void
    {
        $a = new GeoPoint(23.8103, 90.4125);
        $b = new GeoPoint(22.3569, 91.7832);

        $this->assertEqualsWithDelta($a->distanceTo($b), $b->distanceTo($a), 0.0001);
    }

    public function test_a_point_is_no_distance_from_itself(): void
    {
        $point = new GeoPoint(23.8103, 90.4125);

        $this->assertSame(0.0, $point->distanceTo($point));
    }

    public function test_it_answers_whether_a_point_is_inside_a_radius(): void
    {
        $shop = new GeoPoint(23.8103, 90.4125);

        $this->assertTrue((new GeoPoint(23.7925, 90.4078))->isWithin(5, $shop));
        $this->assertFalse((new GeoPoint(22.3569, 91.7832))->isWithin(5, $shop));
    }

    public function test_it_refuses_a_position_that_is_not_on_the_earth(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeoPoint(120.0, 90.0);
    }

    public function test_half_a_position_is_no_position(): void
    {
        $this->assertNull(GeoPoint::tryFrom(23.8103, null));
        $this->assertNull(GeoPoint::tryFrom(null, 90.4125));
        $this->assertNull(GeoPoint::tryFrom('not a number', 90.4125));
        $this->assertNotNull(GeoPoint::tryFrom('23.8103', '90.4125'));
    }
}
