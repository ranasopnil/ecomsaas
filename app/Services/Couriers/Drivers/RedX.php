<?php

namespace App\Services\Couriers\Drivers;

use App\Exceptions\CourierFailed;
use App\Models\Order;
use App\Services\Couriers\Contracts\CourierDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * RedX, using one shop's own merchant account.
 *
 * The shop enters the token RedX issued it. It is the shop's property:
 * encrypted, never shown back, never logged.
 *
 * RedX delivers to areas of its own naming, so the shopkeeper picks the area
 * from RedX's own list as the parcel is handed over.
 */
class RedX extends Driver implements CourierDriver
{
    public const KEY = 'redx';

    protected const BASE = 'https://openapi.redx.com.bd/v1.0.0-beta';

    /** RedX counts a parcel's weight in grammes. */
    protected const DEFAULT_WEIGHT = 500;

    public function name(): string
    {
        return 'RedX';
    }

    /**
     * Ask RedX for its own area list. It books nothing and changes nothing,
     * but the token still has to be accepted before there is an answer.
     */
    public function testConnection(): void
    {
        $this->get('/areas');
    }

    public function book(Order $order, array $with = []): array
    {
        $areaId = (string) ($with['area'] ?? '');

        if ($areaId === '') {
            throw CourierFailed::needsChoosing('Delivery area');
        }

        $body = $this->post('/parcel', [
            'customer_name' => mb_substr($order->customer_name, 0, 100),
            'customer_phone' => $this->phone($order->customer_phone),
            'customer_address' => mb_substr($order->customer_address, 0, 250),
            'delivery_area' => $this->optionsFor('area')[$areaId] ?? 'Unknown',
            'delivery_area_id' => (int) $areaId,

            // Our own reference goes with it, so both systems name the same
            // parcel the same way.
            'merchant_invoice_id' => $this->merchantReference($order),

            'cash_collection_amount' => (string) $this->cashToCollect($order),
            'parcel_weight' => self::DEFAULT_WEIGHT,
            'value' => (int) round($order->total_minor / (10 ** $order->currency_exponent)),
            'instruction' => mb_substr((string) $order->customer_note, 0, 200),
            'pickup_store_id' => (int) $this->detail('pickup_store_id'),
            'parcel_details_json' => $order->lines()->get()->map(fn ($line) => [
                'name' => mb_substr($line->title(), 0, 100),
                'category' => 'general',
                'value' => (int) round($line->line_total_minor / (10 ** $order->currency_exponent)),
            ])->values()->all(),
        ]);

        $tracking = $body['tracking_id'] ?? null;

        if (! is_string($tracking) || $tracking === '') {
            throw CourierFailed::refused(self::KEY, 'RedX took the parcel but did not give it a tracking number.');
        }

        return [
            'consignment_id' => $tracking,
            'tracking_code' => $tracking,
            'raw' => $this->safe($body),
        ];
    }

    public function status(Order $order): array
    {
        if ($order->tracking_code === null) {
            throw CourierFailed::refused(self::KEY, 'That order was never booked with RedX.');
        }

        return $this->get('/parcel/track/'.rawurlencode($order->tracking_code));
    }

    public function statusFrom(array $body): string
    {
        $steps = $body['tracking'] ?? [];

        if (! is_array($steps) || $steps === []) {
            return 'RedX said nothing about it.';
        }

        // The last thing RedX wrote down about the parcel.
        $latest = collect($steps)->last();
        $said = is_array($latest) ? ($latest['message_en'] ?? $latest['status'] ?? null) : null;

        return is_string($said) && $said !== '' ? $said : 'RedX said nothing about it.';
    }

    public function asks(): array
    {
        return [
            ['key' => 'area', 'label' => 'Delivery area', 'depends_on' => null],
        ];
    }

    public function optionsFor(string $key, array $chosen = []): array
    {
        if ($key !== 'area') {
            return [];
        }

        // RedX's list is the same for every shop and does not change from one
        // parcel to the next, so it is kept for an hour.
        return Cache::remember('redx:areas', now()->addHour(), function () {
            $areas = $this->get('/areas')['areas'] ?? [];

            return collect(is_array($areas) ? $areas : [])
                ->filter(fn ($area) => is_array($area) && isset($area['id'], $area['name']))
                ->mapWithKeys(fn (array $area) => [
                    (string) $area['id'] => trim((string) $area['name']
                        .(isset($area['district_name']) ? ', '.$area['district_name'] : '')),
                ])
                ->all();
        });
    }

    /*
     * ---------------------------------------------------------------
     * Talking to RedX
     * ---------------------------------------------------------------
     */

    /**
     * @return array<string, mixed>
     */
    protected function get(string $path): array
    {
        return $this->unwrap($this->send(fn () => $this->client()->get($path)));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(string $path, array $body): array
    {
        return $this->unwrap($this->send(fn () => $this->client()->post($path, $body)));
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE)
            // RedX wants the word "Bearer" inside its own header, so a
            // shopkeeper who pasted it in as well does not send it twice.
            ->withHeaders(['API-ACCESS-TOKEN' => 'Bearer '.$this->bareToken()])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    protected function bareToken(): string
    {
        return trim(preg_replace('/^bearer\s+/i', '', $this->detail('access_token')) ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    protected function unwrap(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw CourierFailed::refused(self::KEY, 'RedX sent back something we could not read.');
        }

        if ($response->failed()) {
            throw CourierFailed::refused(self::KEY, $this->explain($response->status(), $body));
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function explain(int $status, array $body): string
    {
        if ($status === 401 || $status === 403) {
            return 'RedX did not accept that access token.';
        }

        $message = $body['message'] ?? ($body['error'] ?? '');

        return is_string($message) && $message !== ''
            ? 'RedX would not take the parcel: '.$message
            : 'RedX would not take the parcel.';
    }

    protected function phone(string $typed): string
    {
        $digits = preg_replace('/\D+/', '', $typed) ?? '';

        if (str_starts_with($digits, '880')) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }
}
