<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\GatewayFailed;
use App\Facades\Tenancy;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Services\Payments\Contracts\OnlineGateway;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * AmarPay, using one shop's own store account.
 *
 * The shop enters its own store ID and signature key. The signature key is the
 * shop's property: encrypted, never shown back, never written to a log or an
 * error message.
 *
 * The flow AmarPay expects:
 *   1. post the order and get an address to send the customer to
 *   2. the customer pays there, by card, mobile money or bank
 *   3. AmarPay posts them back to us
 *   4. we ask AmarPay ourselves what happened to our own transaction id
 *
 * Step 4 is the only step that decides anything. AmarPay does not sign what it
 * posts to us in step 3, so that post is treated as nothing more than a nudge
 * to go and look — anybody can post to a web address.
 *
 * AmarPay has no message it sends on its own, so a customer who pays and then
 * closes the tab is caught by the payments:reconcile sweep rather than by
 * anything arriving here.
 */
class AmarPay implements OnlineGateway
{
    public const KEY = 'amarpay';

    protected const SANDBOX_BASE = 'https://sandbox.aamarpay.com';

    protected const LIVE_BASE = 'https://secure.aamarpay.com';

    /** Opening a payment. */
    protected const SESSION_PATH = '/jsonpost.php';

    /** Asking what happened to one of our own transactions. */
    protected const CHECK_PATH = '/api/v1/trxcheck/request.php';

    /** What AmarPay calls a payment that really was made. */
    protected const PAID = ['SUCCESSFUL', 'SUCCESS'];

    public function __construct(protected PaymentMethod $method) {}

    /**
     * Ask AmarPay about a transaction that cannot exist.
     *
     * There is no "are these details right?" call, so this stands in for one.
     * It opens nothing and charges nothing; AmarPay only has to say whether it
     * knows the store and its key.
     */
    public function testConnection(): void
    {
        $body = $this->check('CREDENTIALCHECK'.now()->format('YmdHis'));

        // AmarPay answers an unknown transaction with an empty state, and bad
        // details by naming what it did not like. Only the second is a
        // failure: not finding a transaction we invented is the right answer.
        $said = strtolower(json_encode($body) ?: '');

        if (str_contains($said, 'store') || str_contains($said, 'signature')) {
            throw GatewayFailed::refused(self::KEY, 'AmarPay did not accept that store ID or signature key.');
        }
    }

    public function start(Payment $payment, string $callbackUrl): string
    {
        $this->assertCurrency($payment->amount);

        $order = $payment->order_id === null ? null : Order::find($payment->order_id);

        $response = $this->send(fn () => $this->client()->asJson()->post(self::SESSION_PATH, [
            'store_id' => $this->storeId(),
            'signature_key' => $this->signatureKey(),

            'tran_id' => $payment->reference,
            'amount' => $payment->amount->toDecimal(),
            'currency' => $payment->amount->currency,
            'desc' => $this->basketLabel($payment),

            // All three come back to the same place, saying which they are.
            'success_url' => $this->withOutcome($callbackUrl, 'success'),
            'fail_url' => $this->withOutcome($callbackUrl, 'failure'),
            'cancel_url' => $this->withOutcome($callbackUrl, 'cancel'),

            // Who is paying. AmarPay insists on these, and sends its own
            // receipt to the address given, so the shop's own is used rather
            // than inventing one for a customer who never gave us theirs.
            'cus_name' => $order?->customer_name ?: 'Customer',
            'cus_email' => Tenancy::current()?->email ?: 'orders@example.com',
            'cus_phone' => $order?->customer_phone ?: '00000000000',
            'cus_add1' => $order?->customer_address ?: 'Not given',
            'cus_city' => $order?->delivery_area_name ?: 'Dhaka',
            'cus_country' => 'Bangladesh',
        ]));

        $url = $this->paymentUrlFrom($response);

        $payment->forceFill([
            // AmarPay names the payment in the address it sends us to. That
            // name is what its own screens show, so it is worth keeping.
            'gateway_payment_id' => $this->trackFrom($url) ?? $payment->reference,
            'status' => Payment::STATUS_INITIATED,
            'is_sandbox' => $this->isTestMode(),
            'initiated_at' => now(),
        ])->save();

        return $url;
    }

    /**
     * AmarPay takes the money on its own page, so there is nothing to execute
     * here — only its own account of our transaction, which is the only thing
     * allowed to decide whether the shop was paid.
     */
    public function complete(Payment $payment): array
    {
        return $this->status($payment);
    }

    public function status(Payment $payment): array
    {
        $body = $this->check($payment->reference);

        $said = (string) ($body['mer_txnid'] ?? '');

        if ($said === '') {
            // Not an error: a customer who never got as far as paying leaves
            // nothing behind at AmarPay.
            return ['pay_status' => 'NOT_FOUND', 'mer_txnid' => $payment->reference];
        }

        if ($said !== $payment->reference) {
            throw GatewayFailed::refused(self::KEY, 'AmarPay answered about a different payment.');
        }

        $this->assertMatches($body, $payment);

        return $body;
    }

    /**
     * AmarPay does not open its refund API to every store, and we will not
     * guess at money. A shop refunds from its own AmarPay panel, and the
     * refund is recorded there.
     */
    public function refund(PaymentRefund $refund): array
    {
        throw GatewayFailed::refused(
            self::KEY,
            'AmarPay refunds are made in your own AmarPay merchant panel, not from here.',
        );
    }

    public function saysCompleted(array $body): bool
    {
        return in_array(strtoupper((string) ($body['pay_status'] ?? '')), self::PAID, true);
    }

    public function outcomeOf(array $body): array
    {
        return [
            'transaction_id' => $this->text($body, 'pg_txnid'),
            // What they paid with — a wallet or card name — rather than an
            // account number, which AmarPay does not hand over.
            'payer_account' => $this->text($body, 'payment_processor') ?? $this->text($body, 'card_type'),
            'failure_reason' => $this->explainOutcome($body),
        ];
    }

    public function refundIdOf(array $body): ?string
    {
        return null;
    }

    public function isTestMode(): bool
    {
        return (bool) ($this->method->settings['sandbox'] ?? false);
    }

    /*
     * ---------------------------------------------------------------
     * Talking to AmarPay
     * ---------------------------------------------------------------
     */

    /**
     * What AmarPay says about one of our own transactions.
     *
     * @return array<string, mixed>
     */
    protected function check(string $reference): array
    {
        $response = $this->send(fn () => $this->client()->get(self::CHECK_PATH, [
            'request_id' => $reference,
            'store_id' => $this->storeId(),
            'signature_key' => $this->signatureKey(),
            'type' => 'json',
        ]));

        $body = $response->json();

        if ($response->failed()) {
            throw GatewayFailed::refused(self::KEY, 'AmarPay would not answer about that payment.');
        }

        return is_array($body) ? $body : [];
    }

    /**
     * The address to send the customer to, out of whatever shape AmarPay
     * answered in.
     *
     * AmarPay answers a good request with the address, and a bad one by naming
     * the fields it did not like. Both arrive as an ordinary body, so the
     * shape of the answer is what tells them apart.
     */
    protected function paymentUrlFrom(Response $response): string
    {
        $body = $response->json();

        if (is_array($body)) {
            $url = $body['payment_url'] ?? null;

            if (is_string($url) && $url !== '') {
                return $this->absolute($url);
            }

            throw GatewayFailed::refused(self::KEY, $this->explainRefusal($body));
        }

        // Some AmarPay stores answer with the address on its own.
        $plain = trim($response->body());

        if ($plain !== '' && (str_starts_with($plain, 'http') || str_starts_with($plain, '/'))) {
            return $this->absolute($plain);
        }

        throw GatewayFailed::refused(self::KEY, 'AmarPay would not open the payment.');
    }

    /**
     * AmarPay names what it did not like, field by field. Said plainly.
     *
     * @param  array<string, mixed>  $body
     */
    protected function explainRefusal(array $body): string
    {
        $said = collect($body)
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->map(fn ($value, $key) => strtolower($key.' '.$value))
            ->join(' ');

        return match (true) {
            str_contains($said, 'store') || str_contains($said, 'signature') => 'AmarPay did not accept that store ID or signature key.',
            str_contains($said, 'amount') => 'AmarPay will not take an amount like that. Its smallest payment is 10 Taka.',
            str_contains($said, 'tran_id') || str_contains($said, 'duplicate') => 'AmarPay already has a payment with that reference.',
            (string) ($body['error'] ?? '') !== '' => (string) $body['error'],
            default => 'AmarPay would not open the payment.',
        };
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->isTestMode() ? self::SANDBOX_BASE : self::LIVE_BASE)
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
            // request, and the request carries the signature key.
            throw GatewayFailed::unreachable(self::KEY);
        }
    }

    /**
     * The payment AmarPay reports has to be for the money we asked for.
     *
     * @param  array<string, mixed>  $body
     */
    protected function assertMatches(array $body, Payment $payment): void
    {
        if (! $this->saysCompleted($body)) {
            return;
        }

        $currency = strtoupper($this->text($body, 'currency') ?? '');
        $amount = $this->text($body, 'amount');

        if ($currency !== $payment->amount->currency || $amount === null) {
            throw GatewayFailed::refused(self::KEY, 'AmarPay reported this payment in a different currency.');
        }

        try {
            $paid = Money::fromDecimal(
                str_replace([',', ' '], '', $amount),
                $payment->amount->currency,
                $payment->amount->exponent,
            );
        } catch (InvalidArgumentException) {
            throw GatewayFailed::refused(self::KEY, 'AmarPay reported an amount we could not read.');
        }

        // Never take somebody's word for the amount. A payment for less than
        // the order is not that order paid.
        if ($paid->minor !== $payment->amount->minor) {
            throw GatewayFailed::refused(self::KEY, 'The amount AmarPay reported does not match this order.');
        }
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function explainOutcome(array $body): string
    {
        return match (strtoupper((string) ($body['pay_status'] ?? ''))) {
            'NOT_FOUND', '' => 'AmarPay has no record of that payment being made.',
            'FAILED' => 'AmarPay says the payment failed.',
            'CANCELED', 'CANCELLED' => 'The customer cancelled it.',
            'PROCESSING', 'PENDING' => 'AmarPay is still processing that payment.',
            default => 'AmarPay did not complete the payment.',
        };
    }

    /*
     * ---------------------------------------------------------------
     * Small things
     * ---------------------------------------------------------------
     */

    protected function absolute(string $url): string
    {
        return str_starts_with($url, 'http')
            ? $url
            : ($this->isTestMode() ? self::SANDBOX_BASE : self::LIVE_BASE).'/'.ltrim($url, '/');
    }

    /**
     * AmarPay's own name for the payment, out of the address it gave us.
     */
    protected function trackFrom(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $track = $query['track'] ?? null;

        return is_string($track) && $track !== '' ? $track : null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function text(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected function withOutcome(string $url, string $outcome): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'outcome='.$outcome;
    }

    protected function basketLabel(Payment $payment): string
    {
        $shop = Tenancy::current()?->name;

        return $shop ? mb_substr($shop, 0, 60).' order '.$payment->reference : 'Order '.$payment->reference;
    }

    protected function assertCurrency(Money $amount): void
    {
        if ($amount->currency !== 'BDT') {
            throw GatewayFailed::refused(self::KEY, 'AmarPay only takes Bangladeshi Taka.');
        }
    }

    protected function storeId(): string
    {
        $value = $this->method->settings['store_id'] ?? null;

        if (! is_string($value) || $value === '') {
            throw GatewayFailed::notConfigured(self::KEY);
        }

        return $value;
    }

    protected function signatureKey(): string
    {
        $value = $this->method->credentials['signature_key'] ?? null;

        if (! is_string($value) || $value === '') {
            throw GatewayFailed::notConfigured(self::KEY);
        }

        return $value;
    }
}
