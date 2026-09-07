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
}
