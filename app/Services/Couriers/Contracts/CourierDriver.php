<?php

namespace App\Services\Couriers\Contracts;

use App\Models\Order;

/**
 * What every courier we can talk to must be able to do.
 *
 * Written as a contract so Pathao, Steadfast and RedX slot in behind the same
 * few steps, and the order screen never has to know which one it is talking
 * to. Every method throws App\Exceptions\CourierFailed rather than returning
 * an error, so a failure can never be mistaken for a parcel that was booked.
 */
interface CourierDriver
{
    /**
     * Prove the shop's own details work, booking nothing.
     */
    public function testConnection(): void;

    /**
     * Book this order as a parcel.
     *
     * The order's own reference goes with it, so a courier asked twice for
     * the same parcel can recognise it as the same one.
     *
     * @param  array<string, mixed>  $with  what the shopkeeper chose — see asks()
     * @return array{consignment_id: string, tracking_code: ?string, raw: array<string, mixed>}
     */
    public function book(Order $order, array $with = []): array;

    /**
     * Ask the courier what has happened to a parcel.
     *
     * @return array<string, mixed>
     */
    public function status(Order $order): array;

    /**
     * What the courier says about it, in its own words, for the shopkeeper
     * to read. Nothing is decided by this: it is what the courier said.
     *
     * @param  array<string, mixed>  $body
     */
    public function statusFrom(array $body): string;

    /**
     * What the shopkeeper has to choose before a parcel can be booked —
     * Pathao wants a city, then a zone, then an area.
     *
     * Each one may depend on the one before it.
     *
     * @return array<int, array{key: string, label: string, depends_on: ?string}>
     */
    public function asks(): array;

    /**
     * The choices for one of those, given what has been chosen so far.
     *
     * @param  array<string, mixed>  $chosen
     * @return array<string|int, string>
     */
    public function optionsFor(string $key, array $chosen = []): array;
}
