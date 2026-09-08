<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use App\Models\PaymentRefund;

/**
 * What every online gateway must be able to do.
 *
 * Written as a contract so Nagad, SSLCommerz and the rest slot in behind the
 * same four steps, and the checkout never has to know which one it is talking
 * to. Every method throws App\Exceptions\GatewayFailed rather than returning
 * an error, so a failure can never be mistaken for a success.
 */
interface OnlineGateway
{
    /**
     * Prove the shop's own credentials work, changing nothing.
     */
    public function testConnection(): void;

    /**
     * Open a payment and return the address to send the customer to.
     */
    public function start(Payment $payment, string $callbackUrl): string;

    /**
     * Take the money, once the customer has approved it.
     *
     * @return array<string, mixed> what the gateway said
     */
    public function complete(Payment $payment): array;

    /**
     * Ask the gateway what it thinks the state of a payment is.
     *
     * @return array<string, mixed>
     */
    public function status(Payment $payment): array;

    /**
     * Give money back.
     *
     * @return array<string, mixed>
     */
    public function refund(PaymentRefund $refund): array;

    /**
     * Does the gateway's own answer say the money actually moved?
     *
     * Every gateway words this differently, and only the gateway itself can
     * settle it. Nothing else in the system is allowed to decide.
     *
     * @param  array<string, mixed>  $body
     */
    public function saysCompleted(array $body): bool;

    /**
     * What to write down about a payment the gateway has answered for.
     *
     * @param  array<string, mixed>  $body
     * @return array{transaction_id: ?string, payer_account: ?string, failure_reason: ?string}
     */
    public function outcomeOf(array $body): array;

    /**
     * The gateway's own id for a refund it just made.
     *
     * @param  array<string, mixed>  $body
     */
    public function refundIdOf(array $body): ?string;

    /**
     * Is this account pointed at the gateway's test system rather than at
     * real money? Shown to the shopkeeper so a test shop is never mistaken
     * for a live one.
     */
    public function isTestMode(): bool;
}
