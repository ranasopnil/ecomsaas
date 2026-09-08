<?php

namespace App\Services\Couriers\Drivers;

use App\Exceptions\CourierFailed;
use App\Models\Order;
use App\Services\Couriers\Contracts\CourierDriver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Steadfast Courier, using one shop's own merchant account.
 *
 * The shop enters its own API key and secret key. Both are the shop's
 * property: encrypted, never shown back, never logged.
 *
 * Steadfast takes an address as words, so there is nothing for the shopkeeper
 * to choose — the parcel is booked straight from the order. The order's own
 * reference goes across as the invoice number, which Steadfast keeps unique,
 * so the same order can never quietly become two parcels.
 */
class Steadfast extends Driver implements CourierDriver
{
    public const KEY = 'steadfast';

    protected const BASE = 'https://portal.steadfast.com.bd/api/v1';

    public function name(): string
    {
        return 'Steadfast';
    }

    /**
     * Ask Steadfast what the account's balance is. It reads one number,
     * books nothing and changes nothing, but the key and secret still have
     * to be accepted before there is an answer.
     */
    public function testConnection(): void
    {
        $this->get('/get_balance');
    }

    public function book(Order $order, array $with = []): array
    {
        $body = $this->post('/create_order', [
            'invoice' => $this->merchantReference($order),
            'recipient_name' => mb_substr($order->customer_name, 0, 100),
            'recipient_phone' => $this->phone($order->customer_phone),
            'recipient_address' => mb_substr($order->customer_address, 0, 250),
            'cod_amount' => $this->cashToCollect($order),
            'note' => mb_substr((string) $order->customer_note, 0, 250),
        ]);

        $consignment = $body['consignment']['consignment_id'] ?? null;
        $tracking = $body['consignment']['tracking_code'] ?? null;

        if ($consignment === null) {
            throw CourierFailed::refused(self::KEY, 'Steadfast took the parcel but did not give it a consignment number.');
        }

        return [
            'consignment_id' => (string) $consignment,
            // The tracking code is what a customer follows; the consignment
            // number is what the shop quotes to Steadfast.
            'tracking_code' => is_string($tracking) && $tracking !== '' ? $tracking : (string) $consignment,
            'raw' => $this->safe($body['consignment'] ?? []),
        ];
    }

    public function status(Order $order): array
    {
        if ($order->tracking_code === null) {
            throw CourierFailed::refused(self::KEY, 'That order was never booked with Steadfast.');
        }

        // Asked by the tracking code Steadfast itself gave us, so a parcel
        // sent out a second time is not confused with the first.
        return $this->get('/status_by_trackingcode/'.rawurlencode($order->tracking_code));
    }

    public function statusFrom(array $body): string
    {
        $said = $body['delivery_status'] ?? null;

        if (! is_string($said) || $said === '') {
            return 'Steadfast said nothing about it.';
        }

        // Steadfast's own words are written for a computer. These are the
        // ones a shopkeeper will actually see, said plainly.
        return match ($said) {
            'pending' => 'Waiting to be collected',
            'delivered_approval_pending', 'delivered' => 'Delivered',
            'partial_delivered_approval_pending', 'partial_delivered' => 'Partly delivered',
            'cancelled_approval_pending', 'cancelled' => 'Cancelled',
            'unknown_approval_pending', 'unknown' => 'Steadfast is not sure yet',
            'hold' => 'On hold',
            'in_review' => 'Being looked at',
            default => ucfirst(str_replace('_', ' ', $said)),
        };
    }

    /*
     * ---------------------------------------------------------------
     * Talking to Steadfast
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
            ->withHeaders([
                'Api-Key' => $this->detail('api_key'),
                'Secret-Key' => $this->detail('secret_key'),
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    /**
     * @return array<string, mixed>
     */
    protected function unwrap(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw CourierFailed::refused(self::KEY, 'Steadfast sent back something we could not read.');
        }

        // Steadfast puts its own status in the body as well as in the
        // response, and the body is the one that means anything.
        $said = (int) ($body['status'] ?? $response->status());

        if ($response->failed() || ($said !== 200 && $said !== 0)) {
            throw CourierFailed::refused(self::KEY, $this->explain($response->status(), $said, $body));
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function explain(int $httpStatus, int $said, array $body): string
    {
        if ($httpStatus === 401 || $said === 401) {
            return 'Steadfast did not accept that API key or secret key.';
        }

        $message = $body['message'] ?? '';

        if (is_string($message) && str_contains(strtolower($message), 'invoice')) {
            return 'Steadfast already has a parcel with this order\'s number.';
        }

        return is_string($message) && $message !== ''
            ? 'Steadfast would not take the parcel: '.$message
            : 'Steadfast would not take the parcel.';
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
