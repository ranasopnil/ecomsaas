<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\GatewayFailed;
use App\Facades\Tenancy;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Services\Payments\Contracts\OnlineGateway;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout, using one shop's own Stripe account.
 *
 * The shop enters its own secret key and webhook signing secret. Both are the
 * shop's property: encrypted, never shown back, never written to a log or an
 * error message.
 *
 * The flow:
 *   1. we open a Checkout Session and get an address for the customer
 *   2. the customer pays on Stripe's own page
 *   3. Stripe sends them back to us, and we ask Stripe what really happened
 *   4. Stripe also tells our webhook, in case the customer never comes back
 *
 * Nothing here talks to a Stripe library. It is an ordinary web API and this
 * is an ordinary HTTP call, so nothing is downloaded or loaded at runtime.
 *
 * Whether money moved is decided by Stripe's own answer and nothing else: a
 * customer landing back on the success address proves only that they clicked.
 */
class Stripe implements OnlineGateway
{
    public const KEY = 'stripe';

    protected const BASE = 'https://api.stripe.com';

    /**
     * Pinned on purpose. Stripe changes its answers between versions, and a
     * shop's payments must not start reading differently because Stripe
     * shipped something new.
     */
    protected const API_VERSION = '2024-06-20';

    /** Currencies Stripe counts in whole units, with no decimals at all. */
    protected const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /** Currencies Stripe counts to three decimal places. */
    protected const THREE_DECIMAL = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    public function __construct(protected PaymentMethod $method) {}

    /**
     * Ask Stripe who the key belongs to. It reads one thing and changes
     * nothing, so a shopkeeper can press it as often as they like.
     */
    public function testConnection(): void
    {
        $this->get('/v1/balance');
    }

    public function start(Payment $payment, string $callbackUrl): string
    {
        $body = $this->post('/v1/checkout/sessions', [
            'mode' => 'payment',
            'client_reference_id' => $payment->reference,
            // Stripe fills the session id in for us on the way back.
            'success_url' => $this->withQuery($callbackUrl, [
                'outcome' => 'success',
                'session_id' => '{CHECKOUT_SESSION_ID}',
            ]),
            'cancel_url' => $this->withQuery($callbackUrl, [
                'outcome' => 'cancel',
                'reference' => $payment->reference,
            ]),
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => strtolower($payment->amount->currency),
                    'unit_amount' => $this->stripeAmount($payment->amount),
                    'product_data' => ['name' => $this->basketLabel($payment)],
                ],
            ]],
            'metadata' => ['payment_reference' => $payment->reference],
            'payment_intent_data' => [
                'metadata' => ['payment_reference' => $payment->reference],
            ],
        ], $payment->reference.':start');

        $url = $body['url'] ?? null;
        $sessionId = $body['id'] ?? null;

        if (! is_string($url) || $url === '' || ! is_string($sessionId) || $sessionId === '') {
            throw GatewayFailed::refused(self::KEY, 'Stripe opened a payment but did not say where to send the customer.');
        }

        $payment->forceFill([
            'gateway_payment_id' => $sessionId,
            'status' => Payment::STATUS_INITIATED,
            'is_sandbox' => $this->isTestMode(),
            'initiated_at' => now(),
        ])->save();

        return $url;
    }

    /**
     * Stripe takes the money on its own page, so there is nothing to execute
     * here — only Stripe's own account of what happened, which is the only
     * thing allowed to decide whether the shop was paid.
     */
    public function complete(Payment $payment): array
    {
        return $this->status($payment);
    }

    public function status(Payment $payment): array
    {
        $this->assertHasSession($payment);

        return $this->get('/v1/checkout/sessions/'.$payment->gateway_payment_id);
    }

    public function refund(PaymentRefund $refund): array
    {
        $payment = $refund->payment;

        if ($payment->gateway_transaction_id === null) {
            throw GatewayFailed::refused(self::KEY, 'That payment has no Stripe charge to refund against.');
        }

        return $this->post('/v1/refunds', [
            'payment_intent' => $payment->gateway_transaction_id,
            'amount' => $this->stripeAmount($refund->amount),
            // Stripe only accepts three words here, so the shopkeeper's own
            // wording is kept beside it rather than squeezed into it.
            'reason' => 'requested_by_customer',
            'metadata' => [
                'payment_reference' => $payment->reference,
                'note' => mb_substr((string) $refund->reason, 0, 480),
            ],
        ], 'refund:'.$refund->id);
    }

    public function saysCompleted(array $body): bool
    {
        return ($body['payment_status'] ?? null) === 'paid';
    }

    public function outcomeOf(array $body): array
    {
        $intent = $body['payment_intent'] ?? null;

        return [
            'transaction_id' => is_array($intent) ? ($intent['id'] ?? null) : (is_string($intent) ? $intent : null),
            'payer_account' => $body['customer_details']['email'] ?? null,
            'failure_reason' => $this->explainOutcome($body),
        ];
    }

    public function refundIdOf(array $body): ?string
    {
        $id = $body['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * Stripe says which world a key belongs to in the key itself, so a shop
     * can never be shown "live" while it is really only testing.
     */
    public function isTestMode(): bool
    {
        $key = (string) ($this->method->credentials['secret_key'] ?? '');

        return str_starts_with($key, 'sk_test_') || str_starts_with($key, 'rk_test_');
    }

    /**
     * Is a message really from Stripe?
     *
     * Stripe signs every webhook with the shop's own signing secret. Anyone
     * can post to the address; only Stripe can sign for it. The timestamp is
     * checked too, so an old message cannot be replayed later.
     */
    public static function signatureIsValid(string $payload, string $header, string $secret, int $toleranceSeconds = 300): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$name, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($name === 't') {
                $timestamp = $value;
            } elseif ($name === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || ! ctype_digit($timestamp) || $signatures === []) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            // Compared in constant time: a plain === leaks how much of a
            // forged signature was right.
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /*
     * ---------------------------------------------------------------
     * Talking to Stripe
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
    protected function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        $request = $this->client()->asForm();

        if ($idempotencyKey !== null) {
            // Sending the same thing twice must never take the money twice.
            // Stripe replays its first answer instead.
            $request = $request->withHeaders(['Idempotency-Key' => $this->method->tenant_id.':'.$idempotencyKey]);
        }

        return $this->unwrap($this->send(fn () => $request->post($path, $body)));
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(self::BASE)
            ->withToken($this->credential('secret_key'))
            ->withHeaders(['Stripe-Version' => self::API_VERSION])
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    protected function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException) {
            // Deliberately swallowing the original: its message can carry the
            // request, and the request carries the shop's secret key.
            throw GatewayFailed::unreachable(self::KEY);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function unwrap(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw GatewayFailed::refused(self::KEY, 'Stripe sent back something we could not read.');
        }

        if ($response->failed() || isset($body['error'])) {
            $error = is_array($body['error'] ?? null) ? $body['error'] : [];

            throw GatewayFailed::refused(
                self::KEY,
                $this->explain($response->status(), (string) ($error['code'] ?? ''), (string) ($error['message'] ?? '')),
                ($error['code'] ?? null) === null ? null : (string) $error['code'],
            );
        }

        return $body;
    }

    /**
     * Stripe writes for developers. These are the ones a shopkeeper will
     * actually hit, said plainly. Anything else is passed through.
     */
    protected function explain(int $status, string $code, string $message): string
    {
        return match (true) {
            $status === 401 => 'Stripe did not accept that secret key. Copy it again from your Stripe dashboard.',
            $status === 403 => 'That Stripe key is not allowed to do this. Use a standard secret key.',
            $status === 429 => 'Stripe is busy. Please try again in a moment.',
            $code === 'amount_too_small' => 'Stripe will not take an amount this small.',
            $code === 'amount_too_large' => 'That amount is more than Stripe will take in one payment.',
            $code === 'card_declined' => 'The card was declined.',
            $code === 'currency_not_supported' => 'Your Stripe account cannot take money in this currency.',
            $code === 'resource_missing' => 'Stripe does not have that payment on file any more.',
            $status >= 500 => 'Stripe is having trouble. Please try again in a moment.',
            $message !== '' => $message,
            default => 'Stripe refused the request.',
        };
    }

    /**
     * Why a payment that is not paid is not paid, in a sentence.
     *
     * @param  array<string, mixed>  $body
     */
    protected function explainOutcome(array $body): string
    {
        return match ($body['payment_status'] ?? null) {
            'unpaid' => ($body['status'] ?? null) === 'expired'
                ? 'The payment page expired before it was paid.'
                : 'The customer did not finish paying.',
            'no_payment_required' => 'Stripe says no payment was required.',
            default => 'Stripe did not complete the payment.',
        };
    }

    /*
     * ---------------------------------------------------------------
     * Amounts and guards
     * ---------------------------------------------------------------
     */

    /**
     * The amount as Stripe counts it.
     *
     * Stripe has its own idea of how many decimals a currency has, and it is
     * not always ours. Converting through the count of decimal places rather
     * than assuming they match is what keeps a Rupiah price from being sent
     * a hundred times too large.
     */
    protected function stripeAmount(Money $amount): int
    {
        $difference = $this->stripeExponent($amount->currency) - $amount->exponent;

        if ($difference === 0) {
            return $amount->minor;
        }

        if ($difference > 0) {
            return $amount->minor * (10 ** $difference);
        }

        $divisor = 10 ** abs($difference);

        // Refusing rather than rounding: rounding here would quietly charge
        // the customer something other than the price they were shown.
        if ($amount->minor % $divisor !== 0) {
            throw GatewayFailed::refused(
                self::KEY,
                'Stripe cannot take '.$amount->currency.' to that many decimal places.',
            );
        }

        return intdiv($amount->minor, $divisor);
    }

    protected function stripeExponent(string $currency): int
    {
        $currency = strtoupper($currency);

        return match (true) {
            in_array($currency, self::ZERO_DECIMAL, true) => 0,
            in_array($currency, self::THREE_DECIMAL, true) => 3,
            default => 2,
        };
    }

    /**
     * What the customer sees they are paying for on Stripe's page.
     */
    protected function basketLabel(Payment $payment): string
    {
        $shop = Tenancy::current()?->name;

        return $shop ? mb_substr($shop, 0, 60).' order '.$payment->reference : 'Order '.$payment->reference;
    }

    /**
     * @param  array<string, string>  $query
     */
    protected function withQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        // Built by hand rather than with http_build_query: Stripe's
        // {CHECKOUT_SESSION_ID} placeholder has to survive as it is written.
        $pairs = [];

        foreach ($query as $key => $value) {
            $pairs[] = $key.'='.($value === '{CHECKOUT_SESSION_ID}' ? $value : rawurlencode($value));
        }

        return $url.$separator.implode('&', $pairs);
    }

    protected function assertHasSession(Payment $payment): void
    {
        if ($payment->gateway_payment_id === null) {
            throw GatewayFailed::refused(self::KEY, 'That payment was never opened with Stripe.');
        }
    }

    protected function credential(string $field): string
    {
        $value = $this->method->credentials[$field] ?? null;

        if (! is_string($value) || $value === '') {
            throw GatewayFailed::notConfigured(self::KEY);
        }

        return $value;
    }
}
