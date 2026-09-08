<?php

namespace App\Services\Couriers\Drivers;

use App\Exceptions\CourierFailed;
use App\Models\Courier;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * The plumbing every courier module shares.
 *
 * A courier's own account details are read here and nowhere else, and a
 * connection that fails is turned into a plain sentence before anything can
 * put the request — and the details inside it — into a log.
 */
abstract class Driver
{
    public function __construct(protected Courier $courier) {}

    abstract public function name(): string;

    /**
     * Nothing to choose, for the couriers that ask for nothing.
     *
     * @return array<int, array{key: string, label: string, depends_on: ?string}>
     */
    public function asks(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $chosen
     * @return array<string|int, string>
     */
    public function optionsFor(string $key, array $chosen = []): array
    {
        return [];
    }

    protected function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException) {
            // Deliberately swallowing the original: its message can carry the
            // request, and the request carries the shop's own details.
            throw CourierFailed::unreachable($this->name());
        }
    }

    /**
     * What the courier is to collect at the door, in whole units of the
     * shop's currency, which is what every one of these APIs expects.
     */
    protected function cashToCollect(Order $order): int
    {
        if ($order->payment_status !== Order::PAYMENT_ON_DELIVERY) {
            return 0;
        }

        $owed = max(0, $order->total_minor - $order->cod_received_minor);

        return (int) round($owed / (10 ** $order->currency_exponent));
    }

    /**
     * What this parcel is called on the courier's side.
     *
     * The order's own reference, so both systems name the same parcel the
     * same way — except for a parcel that came back and is going out again,
     * which needs a name of its own: couriers keep these unique and would
     * refuse the second one otherwise.
     */
    protected function merchantReference(Order $order): string
    {
        $before = $order->events()
            ->where('to_status', Order::STATUS_HANDED_OVER)
            ->count();

        return $before === 0 ? $order->reference : $order->reference.'-'.($before + 1);
    }

    protected function money(int $minor, Order $order): Money
    {
        return new Money($minor, $order->currency, $order->currency_exponent);
    }

    /**
     * A detail the shop entered. Secrets live in the encrypted blob, the rest
     * beside them.
     */
    protected function detail(string $field): string
    {
        $definition = $this->courier->definition()['fields'][$field] ?? [];

        $value = ($definition['secret'] ?? false)
            ? ($this->courier->credentials[$field] ?? null)
            : ($this->courier->settings[$field] ?? null);

        if (! is_string($value) || $value === '') {
            throw CourierFailed::notConfigured($this->name());
        }

        return $value;
    }

    protected function optional(string $field): ?string
    {
        $definition = $this->courier->definition()['fields'][$field] ?? [];

        $value = ($definition['secret'] ?? false)
            ? ($this->courier->credentials[$field] ?? null)
            : ($this->courier->settings[$field] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function isSandbox(): bool
    {
        return (bool) ($this->courier->settings['sandbox'] ?? false);
    }

    /**
     * Everything the shop should keep about a courier's answer, minus
     * anything that looks like a credential.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function safe(array $body): array
    {
        return collect($body)
            ->except(['access_token', 'refresh_token', 'client_secret', 'password', 'api_key', 'secret_key', 'token'])
            ->all();
    }
}
