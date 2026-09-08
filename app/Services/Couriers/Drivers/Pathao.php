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
 * Pathao Courier, using one shop's own merchant account.
 *
 * The shop enters its own client id, client secret, username and password.
 * Those are the shop's property: encrypted, never shown back, and never
 * written to a log or an error message.
 *
 * Pathao does not take an address as words. It wants a city, then a zone
 * inside it, then an area inside that, each chosen from its own list — so
 * the shopkeeper picks all three as the parcel is handed over, and the
 * lists come from Pathao itself.
 */
class Pathao extends Driver implements CourierDriver
{
    public const KEY = 'pathao';

    protected const SANDBOX_BASE = 'https://courier-api-sandbox.pathao.com';

    protected const LIVE_BASE = 'https://api-hermes.pathao.com';

    /** Pathao's token lasts longer; we drop it early so a request never races the expiry. */
    protected const TOKEN_TTL = 3000;

    /** Normal delivery, as against Pathao's on-demand service. */
    protected const NORMAL_DELIVERY = 48;

    /** A parcel, as against a document. */
    protected const PARCEL = 2;

    /** Pathao's smallest accepted weight, in kilogrammes. */
    protected const LIGHTEST = 0.5;

    public function name(): string
    {
        return 'Pathao';
    }

    public function testConnection(): void
    {
        $this->forgetToken();

        // Asking for the city list proves the token works and reads nothing
        // of anybody's: it books nothing and changes nothing.
        $this->token();
        $this->get('/aladdin/api/v1/countries/1/city-list');
    }

    public function book(Order $order, array $with = []): array
    {
        foreach ($this->asks() as $ask) {
            if (($with[$ask['key']] ?? '') === '') {
                throw CourierFailed::needsChoosing($ask['label']);
            }
        }

        $body = $this->post('/aladdin/api/v1/orders', [
            'store_id' => (int) $this->detail('store_id'),

            // Our own reference goes with it, so Pathao — and anybody
            // reading either system later — can line the two up.
            'merchant_order_id' => $this->merchantReference($order),

            'recipient_name' => mb_substr($order->customer_name, 0, 100),
            'recipient_phone' => $this->phone($order->customer_phone),
            'recipient_address' => mb_substr($order->customer_address, 0, 220),
            'recipient_city' => (int) $with['city'],
            'recipient_zone' => (int) $with['zone'],
            'recipient_area' => (int) $with['area'],

            'delivery_type' => self::NORMAL_DELIVERY,
            'item_type' => self::PARCEL,
            'item_quantity' => max(1, $order->itemCount()),
            'item_weight' => self::LIGHTEST,
            'amount_to_collect' => $this->cashToCollect($order),
            'item_description' => mb_substr('Order '.$order->reference, 0, 200),
            'special_instruction' => mb_substr((string) $order->customer_note, 0, 200) ?: null,
        ]);

        $consignment = $body['data']['consignment_id'] ?? null;

        if (! is_string($consignment) || $consignment === '') {
            throw CourierFailed::refused(self::KEY, 'Pathao took the parcel but did not give it a consignment number.');
        }

        return [
            'consignment_id' => $consignment,
            'tracking_code' => $consignment,
            'raw' => $this->safe($body['data'] ?? []),
        ];
    }

    public function status(Order $order): array
    {
        if ($order->tracking_code === null) {
            throw CourierFailed::refused(self::KEY, 'That order was never booked with Pathao.');
        }

        return $this->get('/aladdin/api/v1/orders/'.rawurlencode($order->tracking_code).'/info');
    }

    public function statusFrom(array $body): string
    {
        $said = $body['data']['order_status'] ?? $body['order_status'] ?? null;

        return is_string($said) && $said !== '' ? $said : 'Pathao said nothing about it.';
    }

    public function asks(): array
    {
        return [
            ['key' => 'city', 'label' => 'City', 'depends_on' => null],
            ['key' => 'zone', 'label' => 'Zone', 'depends_on' => 'city'],
            ['key' => 'area', 'label' => 'Area', 'depends_on' => 'zone'],
        ];
    }

    public function optionsFor(string $key, array $chosen = []): array
    {
        // Pathao's own lists, kept for an hour: they are the same for every
        // shop and they do not change from one parcel to the next.
        return match ($key) {
            'city' => $this->list(
                'cities',
                '/aladdin/api/v1/countries/1/city-list',
                'city_id',
                'city_name',
            ),
            'zone' => ($chosen['city'] ?? '') === '' ? [] : $this->list(
                'zones:'.$chosen['city'],
                '/aladdin/api/v1/cities/'.(int) $chosen['city'].'/zone-list',
                'zone_id',
                'zone_name',
            ),
            'area' => ($chosen['zone'] ?? '') === '' ? [] : $this->list(
                'areas:'.$chosen['zone'],
                '/aladdin/api/v1/zones/'.(int) $chosen['zone'].'/area-list',
                'area_id',
                'area_name',
            ),
            default => [],
        };
    }

    /*
     * ---------------------------------------------------------------
     * Talking to Pathao
     * ---------------------------------------------------------------
     */

    /**
     * @return array<string|int, string>
     */
    protected function list(string $cacheKey, string $path, string $idField, string $nameField): array
    {
        return Cache::remember(
            'pathao:'.($this->isSandbox() ? 'sandbox' : 'live').':'.$cacheKey,
            now()->addHour(),
            function () use ($path, $idField, $nameField) {
                $rows = $this->get($path)['data']['data'] ?? [];

                return collect(is_array($rows) ? $rows : [])
                    ->filter(fn ($row) => is_array($row) && isset($row[$idField], $row[$nameField]))
                    ->mapWithKeys(fn (array $row) => [(string) $row[$idField] => (string) $row[$nameField]])
                    ->all();
            },
        );
    }

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
        return Http::baseUrl($this->isSandbox() ? self::SANDBOX_BASE : self::LIVE_BASE)
            ->withToken($this->token())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    /**
     * The four details in exchange for a token, kept only in the cache and
     * only for this one shop.
     */
    protected function token(): string
    {
        return Cache::remember($this->tokenCacheKey(), self::TOKEN_TTL, function (): string {
            $response = $this->send(fn () => Http::baseUrl($this->isSandbox() ? self::SANDBOX_BASE : self::LIVE_BASE)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(30)
                ->post('/aladdin/api/v1/issue-token', [
                    'client_id' => $this->detail('client_id'),
                    'client_secret' => $this->detail('client_secret'),
                    'grant_type' => 'password',
                    'username' => $this->detail('username'),
                    'password' => $this->detail('password'),
                ]));

            $body = $this->unwrap($response);
            $token = $body['access_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw CourierFailed::refused(self::KEY, 'Pathao accepted the details but sent no token back.');
            }

            return $token;
        });
    }

    protected function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * Tied to the shop and to the client id in use, so changing a detail
     * cannot leave the old token in play.
     */
    protected function tokenCacheKey(): string
    {
        return 'pathao:token:'.$this->courier->tenant_id.':'.substr(hash(
            'sha256',
            ($this->optional('client_id') ?? '').'|'.($this->isSandbox() ? 'sandbox' : 'live'),
        ), 0, 32);
    }

    /**
     * @return array<string, mixed>
     */
    protected function unwrap(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw CourierFailed::refused(self::KEY, 'Pathao sent back something we could not read.');
        }

        if ($response->failed()) {
            throw CourierFailed::refused(self::KEY, $this->explain($response->status(), $body));
        }

        return $body;
    }

    /**
     * Pathao writes for developers, and answers a bad parcel with a list of
     * what was wrong with it. This says it in one sentence.
     *
     * @param  array<string, mixed>  $body
     */
    protected function explain(int $status, array $body): string
    {
        if ($status === 401 || $status === 403) {
            return 'Pathao did not accept this shop\'s details. Check the client id, secret, username and password.';
        }

        $errors = $body['errors'] ?? [];

        if (is_array($errors) && $errors !== []) {
            $first = collect($errors)->flatten()->filter(fn ($line) => is_string($line))->first();

            if (is_string($first) && $first !== '') {
                return 'Pathao would not take the parcel: '.$first;
            }
        }

        $message = $body['message'] ?? '';

        return is_string($message) && $message !== ''
            ? 'Pathao would not take the parcel: '.$message
            : 'Pathao would not take the parcel.';
    }

    /**
     * Pathao wants a Bangladeshi mobile number as eleven digits.
     */
    protected function phone(string $typed): string
    {
        $digits = preg_replace('/\D+/', '', $typed) ?? '';

        // +8801712345678 and 008801712345678 both mean 01712345678.
        if (str_starts_with($digits, '880')) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }
}
