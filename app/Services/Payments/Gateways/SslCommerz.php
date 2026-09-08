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
 * SSLCommerz, using one shop's own store account.
 *
 * The shop enters its own store ID and store password. The password is the
 * shop's property: encrypted, never shown back, never written to a log or an
 * error message.
 *
 * The flow SSLCommerz expects:
 *   1. open a session and get an address to send the customer to
 *   2. the customer pays there, by card, mobile money or bank
 *   3. SSLCommerz posts them back to us, and posts us a notice separately
 *   4. we ask SSLCommerz ourselves what happened to our own transaction id
 *
 * Step 4 is the only step that decides anything. What is posted to us in
 * steps 3 is a nudge to go and look, never proof: anybody can post to a web
 * address, so nothing that arrives that way is believed on its own.
 */
class SslCommerz implements OnlineGateway
{
    public const KEY = 'sslcommerz';

    protected const SANDBOX_BASE = 'https://sandbox.sslcommerz.com';

    protected const LIVE_BASE = 'https://securepay.sslcommerz.com';

    /** Opening a payment session. */
    protected const SESSION_PATH = '/gwprocess/v4/api.php';

    /** Asking about, and refunding, a transaction of our own. */
    protected const QUERY_PATH = '/validator/api/merchantTransIDvalidationAPI.php';

    /** What SSLCommerz calls a transaction that really was paid. */
    protected const PAID = ['VALID', 'VALIDATED'];

    public function __construct(protected PaymentMethod $method) {}

    /**
     * Ask SSLCommerz about a transaction that cannot exist.
     *
     * There is no "are these details right?" call, so this stands in for one:
     * it opens nothing, charges nothing and changes nothing, and SSLCommerz
     * still has to accept the store ID and password before it will answer.
     */
    public function testConnection(): void
    {
        $body = $this->query(['tran_id' => 'CREDENTIALCHECK'.now()->format('YmdHis')]);

        $connect = strtoupper((string) ($body['APIConnect'] ?? ''));

        if ($connect === 'DONE') {
            return;
        }

        throw GatewayFailed::refused(self::KEY, match ($connect) {
            'INVALID_REQUEST', 'FAILED_AUTH' => 'SSLCommerz did not accept that store ID or password.',
            'INACTIVE' => 'SSLCommerz says this store is not active yet.',
            default => 'SSLCommerz answered in a way we did not expect. Check the store ID and password.',
        });
    }

    public function start(Payment $payment, string $callbackUrl): string
    {
        $this->assertCurrency($payment->amount);

        $order = $payment->order_id === null ? null : Order::find($payment->order_id);

        $body = $this->post(self::SESSION_PATH, [
            'store_id' => $this->storeId(),
            'store_passwd' => $this->storePassword(),

            'total_amount' => $payment->amount->toDecimal(),
            'currency' => $payment->amount->currency,
            'tran_id' => $payment->reference,

            // All three come back to the same place, saying which they are.
            'success_url' => $this->withOutcome($callbackUrl, 'success'),
            'fail_url' => $this->withOutcome($callbackUrl, 'failure'),
            'cancel_url' => $this->withOutcome($callbackUrl, 'cancel'),
            'ipn_url' => route('payments.sslcommerz.ipn'),

            // Who is paying. SSLCommerz insists on these, and sends its own
            // receipt to the address given, so the shop's own is used rather
            // than inventing one for a customer who never gave us theirs.
            'cus_name' => $order?->customer_name ?: 'Customer',
            'cus_email' => Tenancy::current()?->email ?: 'orders@example.com',
            'cus_phone' => $order?->customer_phone ?: '00000000000',
            'cus_add1' => $order?->customer_address ?: 'Not given',
            'cus_city' => $order?->delivery_area_name ?: 'Dhaka',
            'cus_country' => 'Bangladesh',

            // What is being bought, in the shape SSLCommerz asks for.
            'shipping_method' => 'NO',
            'num_of_item' => 1,
            'product_name' => $this->basketLabel($payment),
            'product_category' => 'general',
            'product_profile' => 'general',
        ]);

        if (strtoupper((string) ($body['status'] ?? '')) !== 'SUCCESS') {
            throw GatewayFailed::refused(
                self::KEY,
                $this->explainSession((string) ($body['failedreason'] ?? '')),
            );
        }

        $url = $body['GatewayPageURL'] ?? null;
        $sessionKey = $body['sessionkey'] ?? null;

        if (! is_string($url) || $url === '') {
            throw GatewayFailed::refused(self::KEY, 'SSLCommerz opened a payment but did not say where to send the customer.');
        }

        $payment->forceFill([
            'gateway_payment_id' => is_string($sessionKey) && $sessionKey !== '' ? $sessionKey : $payment->reference,
            'status' => Payment::STATUS_INITIATED,
            'is_sandbox' => $this->isTestMode(),
            'initiated_at' => now(),
        ])->save();

        return $url;
    }

    /**
     * SSLCommerz takes the money on its own page, so there is nothing to
     * execute here — only its own account of our transaction, which is the
     * only thing allowed to decide whether the shop was paid.
     */
    public function complete(Payment $payment): array
    {
        return $this->status($payment);
    }

    public function status(Payment $payment): array
    {
        $body = $this->query(['tran_id' => $payment->reference]);

        if (strtoupper((string) ($body['APIConnect'] ?? '')) !== 'DONE') {
            throw GatewayFailed::refused(self::KEY, 'SSLCommerz would not answer about that payment.');
        }

        $transaction = $this->pick($body, $payment);

        if ($transaction === null) {
            // Not an error: a customer who never got as far as paying leaves
            // nothing behind at SSLCommerz.
            return ['status' => 'NOT_FOUND', 'tran_id' => $payment->reference];
        }

        $this->assertMatches($transaction, $payment);

        return $transaction;
    }

    public function refund(PaymentRefund $refund): array
    {
        $payment = $refund->payment;

        $this->assertCurrency($refund->amount);

        if ($payment->gateway_transaction_id === null) {
            throw GatewayFailed::refused(self::KEY, 'That payment has no SSLCommerz bank transaction to refund against.');
        }

        $body = $this->query([
            'bank_tran_id' => $payment->gateway_transaction_id,
            'refund_amount' => $refund->amount->toDecimal(),
            'refund_remarks' => mb_substr($refund->reason ?: 'Refund', 0, 255),
        ]);

        if (strtolower((string) ($body['status'] ?? '')) !== 'success') {
            throw GatewayFailed::refused(
                self::KEY,
                (string) ($body['errorReason'] ?? '') ?: 'SSLCommerz would not give that money back.',
            );
        }

        return $body;
    }

    public function saysCompleted(array $body): bool
    {
        return in_array(strtoupper((string) ($body['status'] ?? '')), self::PAID, true);
    }

    public function outcomeOf(array $body): array
    {
        return [
            'transaction_id' => $this->text($body, 'bank_tran_id'),
            // What they paid with — a card type or a wallet name — rather than
            // an account number, which SSLCommerz does not hand over.
            'payer_account' => $this->text($body, 'card_type'),
            'failure_reason' => $this->explainOutcome($body),
        ];
    }

    public function refundIdOf(array $body): ?string
    {
        return $this->text($body, 'refund_ref_id');
    }

    public function isTestMode(): bool
    {
        return (bool) ($this->method->settings['sandbox'] ?? false);
    }

    /**
     * Is this notice signed by this shop's own store password?
     *
     * Kept here so the password itself never has to be handed out to whatever
     * is checking.
     *
     * @param  array<string, mixed>  $posted
     */
    public function noticeIsSigned(array $posted): bool
    {
        return self::signatureIsValid($posted, $this->storePassword());
    }

    /**
     * Is a notice really from SSLCommerz?
     *
     * SSLCommerz signs what it posts with a hash of the fields it names, the
     * store password among them. Only somebody who knows the password can
     * produce it.
     *
     * @param  array<string, mixed>  $posted
     */
    public static function signatureIsValid(array $posted, string $storePassword): bool
    {
        $signature = (string) ($posted['verify_sign'] ?? '');
        $keys = (string) ($posted['verify_key'] ?? '');

        if ($signature === '' || $keys === '' || $storePassword === '') {
            return false;
        }

        $fields = [];

        foreach (explode(',', $keys) as $key) {
            $key = trim($key);

            if ($key !== '') {
                $fields[$key] = (string) ($posted[$key] ?? '');
            }
        }

        $fields['store_passwd'] = md5($storePassword);
        ksort($fields);

        $pairs = [];

        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        // Compared in constant time: a plain === leaks how much of a forged
        // signature was right.
        return hash_equals(md5(implode('&', $pairs)), $signature);
    }

    /*
     * ---------------------------------------------------------------
     * Talking to SSLCommerz
     * ---------------------------------------------------------------
     */

    /**
     * The query and refund endpoint. The store details go on every call.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function query(array $parameters): array
    {
        return $this->unwrap($this->send(fn () => $this->client()->get(self::QUERY_PATH, [
            ...$parameters,
            'store_id' => $this->storeId(),
            'store_passwd' => $this->storePassword(),
            'format' => 'json',
        ])));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(string $path, array $body): array
    {
        return $this->unwrap($this->send(fn () => $this->client()->asForm()->post($path, $body)));
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
            // request, and the request carries the store password.
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
            throw GatewayFailed::refused(self::KEY, 'SSLCommerz sent back something we could not read.');
        }

        if ($response->failed()) {
            throw GatewayFailed::refused(self::KEY, 'SSLCommerz refused the request.');
        }

        return $body;
    }

    /**
     * Our own transaction out of everything SSLCommerz sends back.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>|null
     */
    protected function pick(array $body, Payment $payment): ?array
    {
        $elements = $body['element'] ?? [];

        if (! is_array($elements) || $elements === []) {
            return null;
        }

        // A single transaction sometimes comes back on its own rather than in
        // a list of one.
        if (isset($elements['tran_id'])) {
            $elements = [$elements];
        }

        $ours = collect($elements)
            ->filter(fn ($element) => is_array($element) && ($element['tran_id'] ?? null) === $payment->reference);

        // The one that was paid, if any of them was.
        return $ours->first(fn (array $element) => $this->saysCompleted($element))
            ?? $ours->first();
    }

    /**
     * The transaction SSLCommerz describes has to be the one we opened, for
     * the money we asked for.
     *
     * @param  array<string, mixed>  $transaction
     */
    protected function assertMatches(array $transaction, Payment $payment): void
    {
        if (! $this->saysCompleted($transaction)) {
            return;
        }

        $currency = strtoupper($this->text($transaction, 'currency') ?? '');
        $amount = $this->text($transaction, 'currency_amount') ?? $this->text($transaction, 'amount');

        if ($currency !== $payment->amount->currency || $amount === null) {
            throw GatewayFailed::refused(self::KEY, 'SSLCommerz reported this payment in a different currency.');
        }

        try {
            // SSLCommerz writes amounts as plain decimals, but a stray comma
            // or space must not become a five hundred error.
            $paid = Money::fromDecimal(
                str_replace([',', ' '], '', $amount),
                $payment->amount->currency,
                $payment->amount->exponent,
            );
        } catch (InvalidArgumentException) {
            throw GatewayFailed::refused(self::KEY, 'SSLCommerz reported an amount we could not read.');
        }

        // Never take somebody's word for the amount. A payment for less than
        // the order is not that order paid.
        if ($paid->minor !== $payment->amount->minor) {
            throw GatewayFailed::refused(self::KEY, 'The amount SSLCommerz reported does not match this order.');
        }
    }

    /**
     * SSLCommerz's own wording is for developers. These are the ones a
     * shopkeeper will actually hit, said plainly.
     */
    protected function explainSession(string $reason): string
    {
        return match (true) {
            str_contains(strtolower($reason), 'store credential') => 'SSLCommerz did not accept that store ID or password.',
            str_contains(strtolower($reason), 'inactive') => 'SSLCommerz says this store is not active yet.',
            str_contains(strtolower($reason), 'amount') => 'SSLCommerz will not take an amount like that. Its smallest payment is 10 Taka.',
            $reason !== '' => $reason,
            default => 'SSLCommerz would not open the payment.',
        };
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function explainOutcome(array $body): string
    {
        return match (strtoupper((string) ($body['status'] ?? ''))) {
            'NOT_FOUND' => 'SSLCommerz has no record of that payment being made.',
            'FAILED' => 'SSLCommerz says the payment failed.',
            'CANCELLED' => 'The customer cancelled it.',
            'EXPIRED' => 'The payment page expired before it was paid.',
            'UNATTEMPTED' => 'The customer never started paying.',
            default => 'SSLCommerz did not complete the payment.',
        };
    }

    /*
     * ---------------------------------------------------------------
     * Small things
     * ---------------------------------------------------------------
     */

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
            throw GatewayFailed::refused(self::KEY, 'SSLCommerz only takes Bangladeshi Taka.');
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

    protected function storePassword(): string
    {
        $value = $this->method->credentials['store_password'] ?? null;

        if (! is_string($value) || $value === '') {
            throw GatewayFailed::notConfigured(self::KEY);
        }

        return $value;
    }
}
