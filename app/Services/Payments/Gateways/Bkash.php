<?php

namespace App\Services\Payments\Gateways;

use App\Exceptions\GatewayFailed;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentRefund;
use App\Services\Payments\Contracts\OnlineGateway;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * bKash tokenised checkout, using one shop's own merchant account.
 *
 * The shop enters its own app key, app secret, username and password. Those
 * four are the shop's property: they are encrypted, never shown back, and
 * never written to a log or an error message.
 *
 * The flow bKash expects:
 *   1. swap the four credentials for a short-lived token
 *   2. create a payment, get back an address to send the customer to
 *   3. the customer approves it on bKash's own page
 *   4. bKash sends the customer back to us, and we execute the payment
 *
 * Step 4 is the only step that moves money, and it is the one bKash may tell
 * us about more than once. Nothing here acts on it twice.
 */
class Bkash implements OnlineGateway
{
    public const KEY = 'bkash';

    protected const SANDBOX_BASE = 'https://tokenized.sandbox.bka.sh/v1.2.0-beta/tokenized/checkout';

    protected const LIVE_BASE = 'https://tokenized.pay.bka.sh/v1.2.0-beta/tokenized/checkout';

    /** bKash says an hour; we drop it early so a request never races the expiry. */
    protected const TOKEN_TTL = 3000;

    public function __construct(protected PaymentMethod $method) {}

    public function testConnection(): void
    {
        $this->forgetToken();

        $this->token();
    }

    public function start(Payment $payment, string $callbackUrl): string
    {
        $this->assertCurrency($payment->amount);

        $body = $this->post('/create', [
            'mode' => '0011',
            'payerReference' => $payment->payer_reference ?: $payment->reference,
            'callbackURL' => $callbackUrl,
            'amount' => $payment->amount->toDecimal(),
            'currency' => $payment->amount->currency,
            'intent' => 'sale',
            'merchantInvoiceNumber' => $payment->reference,
        ]);

        $url = $body['bkashURL'] ?? null;
        $gatewayPaymentId = $body['paymentID'] ?? null;

        if (! is_string($url) || $url === '' || ! is_string($gatewayPaymentId) || $gatewayPaymentId === '') {
            throw GatewayFailed::refused(self::KEY, 'bKash opened a payment but did not say where to send the customer.');
        }

        $payment->forceFill([
            'gateway_payment_id' => $gatewayPaymentId,
            'status' => Payment::STATUS_INITIATED,
            'is_sandbox' => $this->isSandbox(),
            'initiated_at' => now(),
        ])->save();

        return $url;
    }

    public function complete(Payment $payment): array
    {
        $this->assertHasGatewayPayment($payment);

        return $this->post('/execute', ['paymentID' => $payment->gateway_payment_id]);
    }

    public function status(Payment $payment): array
    {
        $this->assertHasGatewayPayment($payment);

        return $this->post('/payment/status', ['paymentID' => $payment->gateway_payment_id]);
    }

    public function refund(PaymentRefund $refund): array
    {
        $payment = $refund->payment;

        $this->assertCurrency($refund->amount);
        $this->assertHasGatewayPayment($payment);

        if ($payment->gateway_transaction_id === null) {
            throw GatewayFailed::refused(self::KEY, 'That payment has no bKash transaction to refund against.');
        }

        return $this->post('/payment/refund', [
            'paymentID' => $payment->gateway_payment_id,
            'trxID' => $payment->gateway_transaction_id,
            'amount' => $refund->amount->toDecimal(),
            'sku' => $payment->reference,
            'reason' => $refund->reason ?: 'Refund',
        ]);
    }

    /**
     * bKash calls a payment successful with statusCode 0000, and a completed
     * transaction "Completed". Both have to agree before we treat it as paid.
     */
    public static function saysCompleted(array $body): bool
    {
        return ($body['statusCode'] ?? null) === '0000'
            && in_array($body['transactionStatus'] ?? null, ['Completed', 'Authorized'], true);
    }

    /*
     * ---------------------------------------------------------------
     * Talking to bKash
     * ---------------------------------------------------------------
     */

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(string $path, array $body): array
    {
        $response = $this->send(
            $this->client()->withHeaders([
                'Authorization' => $this->token(),
                'X-APP-Key' => $this->credential('app_key'),
            ]),
            $path,
            $body,
        );

        return $this->unwrap($response);
    }

    /**
     * The four credentials in exchange for a token, kept only in the cache and
     * only for this one shop.
     */
    protected function token(): string
    {
        return Cache::remember($this->tokenCacheKey(), self::TOKEN_TTL, function (): string {
            $response = $this->send(
                $this->client()->withHeaders([
                    'username' => $this->credential('username'),
                    'password' => $this->credential('password'),
                ]),
                '/token/grant',
                [
                    'app_key' => $this->credential('app_key'),
                    'app_secret' => $this->credential('app_secret'),
                ],
            );

            $body = $this->unwrap($response);
            $token = $body['id_token'] ?? null;

            if (! is_string($token) || $token === '') {
                throw GatewayFailed::refused(self::KEY, 'bKash accepted the details but sent no token back.');
            }

            return $token;
        });
    }

    protected function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /**
     * Tied to the shop and to the app key in use, so rotating a credential
     * cannot leave the old token in play.
     */
    protected function tokenCacheKey(): string
    {
        return 'bkash:token:'.$this->method->tenant_id.':'
            .substr(hash('sha256', $this->credential('app_key').'|'.($this->isSandbox() ? 'sandbox' : 'live')), 0, 32);
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->isSandbox() ? self::SANDBOX_BASE : self::LIVE_BASE)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    protected function send(PendingRequest $request, string $path, array $body): Response
    {
        try {
            return $request->post($path, $body);
        } catch (ConnectionException) {
            // Deliberately swallowing the original: its message can carry the
            // request, and the request carries the shop's credentials.
            throw GatewayFailed::unreachable(self::KEY);
        }
    }

    /**
     * bKash answers 200 even when it is saying no, so the body decides.
     *
     * @return array<string, mixed>
     */
    protected function unwrap(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw GatewayFailed::refused(self::KEY, 'bKash sent back something we could not read.');
        }

        $code = $body['statusCode'] ?? $body['errorCode'] ?? null;
        $message = $body['statusMessage'] ?? $body['errorMessage'] ?? '';

        if ($response->failed() || ($code !== null && $code !== '0000')) {
            throw GatewayFailed::refused(
                self::KEY,
                $this->explain((string) $code, (string) $message),
                $code === null ? null : (string) $code,
            );
        }

        return $body;
    }

    /**
     * bKash's own wording is for developers. These are the ones a shopkeeper
     * will actually hit, said plainly. Anything else is passed through.
     */
    protected function explain(string $code, string $message): string
    {
        return match ($code) {
            '2001' => 'bKash did not accept those details. Check the app key and app secret.',
            '2002' => 'bKash did not accept the username or password.',
            '2003' => 'bKash says this merchant account is not active yet.',
            '2006' => 'That payment has already been completed.',
            '2007' => 'That payment has already been used.',
            '2023' => 'That payment has expired. Ask the customer to start again.',
            '2029' => 'bKash is busy. Please try again in a moment.',
            '2062' => 'The customer does not have enough balance.',
            '401', '403' => 'bKash rejected the shop\'s credentials.',
            default => $message !== '' ? $message : 'bKash refused the request.',
        };
    }

    /*
     * ---------------------------------------------------------------
     * Guards
     * ---------------------------------------------------------------
     */

    protected function assertCurrency(Money $amount): void
    {
        if ($amount->currency !== 'BDT') {
            throw GatewayFailed::refused(self::KEY, 'bKash only takes Bangladeshi Taka.');
        }
    }

    protected function assertHasGatewayPayment(Payment $payment): void
    {
        if ($payment->gateway_payment_id === null) {
            throw GatewayFailed::refused(self::KEY, 'That payment was never opened with bKash.');
        }
    }

    protected function isSandbox(): bool
    {
        return (bool) ($this->method->settings['sandbox'] ?? false);
    }

    protected function credential(string $field): string
    {
        // 'username' is the only one the shop does not consider secret, so it
        // lives with the settings rather than the encrypted blob.
        $value = $field === 'username'
            ? ($this->method->settings['username'] ?? null)
            : ($this->method->credentials[$field] ?? null);

        if (! is_string($value) || $value === '') {
            throw GatewayFailed::notConfigured(self::KEY);
        }

        return $value;
    }
}
